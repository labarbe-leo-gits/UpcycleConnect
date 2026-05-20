package db

import (
	"API/models"
	"database/sql"
	"fmt"
	"math"

	"github.com/google/uuid"
)

func uuidPointerToValue(id *uuid.UUID) interface{} {
	if id == nil || *id == uuid.Nil {
		return nil
	}
	return *id
}

func grantBadge(tx *sql.Tx, userID uuid.UUID, badgeName string) error {
	var badgeID string
	if err := tx.QueryRow("SELECT id FROM badges WHERE name = ?", badgeName).Scan(&badgeID); err != nil {
		return err
	}
	_, err := tx.Exec("INSERT IGNORE INTO user_badges (id, user_id, badge_id) VALUES (UUID(), ?, ?)", userID.String(), badgeID)
	return err
}

const (
	UpcycleCommissionRate = 0.08
	StripeFeeRate         = 0.029
	StripeFixedFee        = 0.30

	BadgeExpertXPThreshold = 5000 // XP required to earn "expert" badge
)

func CalcTTC(ht float64) float64 {
	return math.Round(((ht*(1+UpcycleCommissionRate))+StripeFixedFee)/(1-StripeFeeRate)*100) / 100
}

func GetOrdersFromDB() ([]models.Order, error) {

	orders := []models.Order{}
	rows, err := Db.Query("SELECT id, user_id, event_id, product_id, transaction_id, amount, status, created_at, updated_at FROM orders")

	if err != nil {
		return nil, fmt.Errorf("getOrders package db : %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {
		var order models.Order
		var idStr, userIDStr, eventIDStr, productIDStr sql.NullString
		err := rows.Scan(&idStr, &userIDStr, &eventIDStr, &productIDStr, &order.TransactionID, &order.Amount, &order.Status, &order.CreatedAt, &order.UpdatedAt)
		if err != nil {
			return nil, fmt.Errorf("getOrders package db scan : %s", err.Error())
		}
		if idStr.Valid {
			order.ID, err = uuid.Parse(idStr.String)
			if err != nil {
				return nil, fmt.Errorf("getOrders package db uuid parse id : %s", err.Error())
			}
		}
		if userIDStr.Valid {
			order.UserID, err = uuid.Parse(userIDStr.String)
			if err != nil {
				return nil, fmt.Errorf("getOrders package db uuid parse user_id : %s", err.Error())
			}
		}
		if eventIDStr.Valid {
			parsedID, parseErr := uuid.Parse(eventIDStr.String)
			if parseErr != nil {
				return nil, fmt.Errorf("getOrders package db uuid parse event_id : %s", parseErr.Error())
			}
			order.EventID = &parsedID
		}
		if productIDStr.Valid {
			parsedID, parseErr := uuid.Parse(productIDStr.String)
			if parseErr != nil {
				return nil, fmt.Errorf("getOrders package db uuid parse product_id : %s", parseErr.Error())
			}
			order.ProductID = &parsedID
		}
		orders = append(orders, order)
	}

	err = rows.Err()
	if err != nil {
		return nil, fmt.Errorf("getOrders package db rows : %s", err.Error())
	}

	return orders, nil

}

func CreateOrderInDB(order models.Order) (uuid.UUID, error) {

	newID := uuid.New()

	eventID := uuidPointerToValue(order.EventID)
	productID := uuidPointerToValue(order.ProductID)
	availabilityID := uuidPointerToValue(order.OrderAvailabilityID)

	if eventID == nil && productID == nil {
		return uuid.Nil, fmt.Errorf("createOrder package db: missing event_id and product_id")
	}

	if eventID != nil && availabilityID == nil {
		return uuid.Nil, fmt.Errorf("createOrder package db: schedule slot is required")
	}

	tx, err := Db.Begin()
	if err != nil {
		return uuid.Nil, fmt.Errorf("createOrder package db : %s", err.Error())
	}

	defer func() {
		if err != nil {
			_ = tx.Rollback()
		}
	}()

	if order.EventID != nil && *order.EventID != uuid.Nil {
		if order.OrderAvailabilityID == nil || *order.OrderAvailabilityID == uuid.Nil {
			return uuid.Nil, fmt.Errorf("event_availability_id is required for service orders")
		}
		err = checkSlotAvailability(tx, *order.EventID, *order.OrderAvailabilityID)
		if err != nil {
			return uuid.Nil, err
		}
		err = checkAndIncrementParticipants(tx, *order.EventID)
		if err != nil {
			return uuid.Nil, err
		}
	}

	_, err = tx.Exec("INSERT INTO orders (id, user_id, event_id, product_id, event_availability_id, transaction_id, amount, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())", newID, order.UserID, eventID, productID, availabilityID, order.TransactionID, order.Amount, order.Status)
	if err != nil {

		fmt.Printf("[ERROR] CreateOrderInDB insert: %s, userID=%s, eventID=%#v, productID=%#v, transactionID=%s, amount=%.2f, status=%d\n",
			err.Error(), order.UserID.String(), eventID, productID, order.TransactionID, order.Amount, order.Status)

		return uuid.Nil, fmt.Errorf("createOrder package db : %s", err.Error())
	}

	var ownerID uuid.UUID
	if order.ProductID != nil && *order.ProductID != uuid.Nil && order.Amount > 0 {
		var ownerIDStr string
		err = tx.QueryRow("SELECT user_id FROM annonces WHERE id = ? FOR UPDATE", order.ProductID.String()).Scan(&ownerIDStr)
		if err != nil {
			if err == sql.ErrNoRows {
				return uuid.Nil, fmt.Errorf("annonce not found")
			}
			return uuid.Nil, fmt.Errorf("createOrder package db annonce: %s", err.Error())
		}

		ownerID, parseErr := uuid.Parse(ownerIDStr)
		if parseErr != nil {
			return uuid.Nil, fmt.Errorf("createOrder package db annonce owner uuid: %s", parseErr.Error())
		}

		if ownerID != order.UserID {

			var annoncePriceHT float64
			_ = tx.QueryRow("SELECT price FROM annonces WHERE id = ?", order.ProductID.String()).Scan(&annoncePriceHT)
			credit := math.Round(annoncePriceHT*100) / 100
			_, err = tx.Exec("UPDATE users SET balance = balance + ? WHERE id = ?", credit, ownerID.String())
			if err != nil {
				return uuid.Nil, fmt.Errorf("createOrder package db credit owner: %s", err.Error())
			}

			commissionAmount := math.Round(annoncePriceHT*UpcycleCommissionRate*100) / 100
			amountAfterCommission := math.Round((annoncePriceHT-commissionAmount)*100) / 100
			commissionTransaction := models.CommissionTransaction{
				ID:               uuid.New(),
				OrderID:          newID,
				SellerID:         ownerID,
				AmountBeforeComm: annoncePriceHT,
				CommissionRate:   UpcycleCommissionRate * 100,
				CommissionAmount: commissionAmount,
				AmountAfterComm:  amountAfterCommission,
				Status:           0,
			}
			if err := CreateCommissionTransactionWithTx(tx, commissionTransaction); err != nil {
				return uuid.Nil, fmt.Errorf("createOrder package db commission transaction: %s", err.Error())
			}

			var sellerScore float64
			var oldSellerXP int
			_ = tx.QueryRow("SELECT user_xp FROM users WHERE id = ? FOR UPDATE", ownerID.String()).Scan(&oldSellerXP)
			err = tx.QueryRow("SELECT upcycling_score FROM users WHERE id = ?", ownerID.String()).Scan(&sellerScore)
			if err == nil && order.Amount > 0 {
				xpSeller := int(math.Round(order.Amount * sellerScore * 0.1 * 4 / 2))
				if xpSeller > 0 {
					_, _ = tx.Exec(`UPDATE users SET user_xp = user_xp + ?, user_level = FLOOR((user_xp + ?) / 1200) WHERE id = ?`, xpSeller, xpSeller, ownerID.String())
					newSellerXP := oldSellerXP + xpSeller
					if oldSellerXP == 0 {
						_ = grantBadge(tx, ownerID, "pionnier")
					}
					if newSellerXP >= BadgeExpertXPThreshold {
						_ = grantBadge(tx, ownerID, "expert")
					}
				}
			}
		}
	}

	var upcyclingScore float64
	var oldXP int
	_ = tx.QueryRow("SELECT user_xp FROM users WHERE id = ? FOR UPDATE", order.UserID.String()).Scan(&oldXP)
	err = tx.QueryRow("SELECT upcycling_score FROM users WHERE id = ?", order.UserID.String()).Scan(&upcyclingScore)
	if err == nil && order.Amount > 0 {
		xpAward := int(math.Round(order.Amount * upcyclingScore * 0.1 * 4))
		if xpAward > 0 {
			_, _ = tx.Exec(`UPDATE users SET user_xp = user_xp + ?, user_level = FLOOR((user_xp + ?) / 1200) WHERE id = ?`, xpAward, xpAward, order.UserID.String())
			newXP := oldXP + xpAward
			if oldXP == 0 {
				_ = grantBadge(tx, order.UserID, "pionnier")
			}
			if newXP >= BadgeExpertXPThreshold {
				_ = grantBadge(tx, order.UserID, "expert")
			}
		}
	}

	if ownerID != order.UserID {
		var sellerScore float64
		err = tx.QueryRow("SELECT upcycling_score FROM users WHERE id = ?", ownerID.String()).Scan(&sellerScore)
		if err == nil && order.Amount > 0 {
			xpSeller := int(math.Round(order.Amount * sellerScore * 0.1 * 4 / 2))
			if xpSeller > 0 {
				_, _ = tx.Exec(`UPDATE users SET user_xp = user_xp + ?, user_level = FLOOR((user_xp + ?) / 1200) WHERE id = ?`, xpSeller, xpSeller, ownerID.String())
			}
		}
	}

	if err = tx.Commit(); err != nil {
		return uuid.Nil, fmt.Errorf("createOrder package db : %s", err.Error())
	}

	return newID, nil
}

func checkAndIncrementParticipants(tx *sql.Tx, eventID uuid.UUID) error {
	var maxParticipants, currentParticipants sql.NullInt64
	err := tx.QueryRow("SELECT maximum_participants, current_participants FROM evenements WHERE id = ? FOR UPDATE", eventID).Scan(&maxParticipants, &currentParticipants)
	if err != nil {
		if err == sql.ErrNoRows {
			return fmt.Errorf("event not found")
		}
		return fmt.Errorf("checkParticipants package db : %s", err.Error())
	}

	currentValue := 0
	if currentParticipants.Valid {
		currentValue = int(currentParticipants.Int64)
	}

	if maxParticipants.Valid {
		maxValue := int(maxParticipants.Int64)
		if currentValue >= maxValue {
			return fmt.Errorf("event_full")
		}
	}

	_, err = tx.Exec("UPDATE evenements SET current_participants = ? WHERE id = ?", currentValue+1, eventID)
	if err != nil {
		return fmt.Errorf("updateParticipants package db : %s", err.Error())
	}

	return nil
}

func checkSlotAvailability(tx *sql.Tx, eventID uuid.UUID, availabilityID uuid.UUID) error {
	var dbEventID string
	var isAvailable bool

	err := tx.QueryRow("SELECT event_id, is_available FROM event_availability WHERE id = ? FOR UPDATE", availabilityID.String()).Scan(&dbEventID, &isAvailable)
	if err != nil {
		if err == sql.ErrNoRows {
			return fmt.Errorf("event_availability_not_found")
		}
		return fmt.Errorf("checkAndReserveSlot package db : %s", err.Error())
	}

	if dbEventID != eventID.String() {
		return fmt.Errorf("event_availability_mismatch")
	}

	if !isAvailable {
		return fmt.Errorf("event_full")
	}

	return nil
}

func GetOrdersByUserIDFromDB(userID uuid.UUID) ([]models.Order, error) {

	orders := []models.Order{}
	rows, err := Db.Query("SELECT id, user_id, event_id, product_id, transaction_id, amount, status FROM orders WHERE user_id = ?", userID)

	if err != nil {
		return nil, fmt.Errorf("getOrdersByUserID package db : %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {
		var order models.Order
		var idStr, userIDStr, eventIDStr, productIDStr sql.NullString
		err := rows.Scan(&idStr, &userIDStr, &eventIDStr, &productIDStr, &order.TransactionID, &order.Amount, &order.Status)
		if err != nil {
			return nil, fmt.Errorf("getOrdersByUserID package db scan : %s", err.Error())
		}
		if idStr.Valid {
			order.ID, err = uuid.Parse(idStr.String)
			if err != nil {
				return nil, fmt.Errorf("getOrdersByUserID package db uuid parse id : %s", err.Error())
			}
		}
		if userIDStr.Valid {
			order.UserID, err = uuid.Parse(userIDStr.String)
			if err != nil {
				return nil, fmt.Errorf("getOrdersByUserID package db uuid parse user_id : %s", err.Error())
			}
		}

		if eventIDStr.Valid {
			parsedID, parseErr := uuid.Parse(eventIDStr.String)
			if parseErr != nil {
				return nil, fmt.Errorf("getOrdersByUserID package db uuid parse event_id : %s", parseErr.Error())
			}
			order.EventID = &parsedID
		}

		if productIDStr.Valid {
			parsedID, parseErr := uuid.Parse(productIDStr.String)
			if parseErr != nil {
				return nil, fmt.Errorf("getOrdersByUserID package db uuid parse product_id : %s", parseErr.Error())
			}

			order.ProductID = &parsedID
		}

		orders = append(orders, order)
	}

	err = rows.Err()
	if err != nil {
		return nil, fmt.Errorf("getOrdersByUserID package db rows : %s", err.Error())
	}

	return orders, nil
}

func GetOrderByIDFromDB(orderID uuid.UUID) (*models.Order, error) {

	var order models.Order
	var idStr, userIDStr, eventIDStr, productIDStr sql.NullString
	err := Db.QueryRow("SELECT id, user_id, event_id, product_id, transaction_id, amount, status FROM orders WHERE id = ?", orderID).Scan(&idStr, &userIDStr, &eventIDStr, &productIDStr, &order.TransactionID, &order.Amount, &order.Status)

	if err != nil {
		if err == sql.ErrNoRows {
			return nil, fmt.Errorf("order not found")
		}

		return nil, fmt.Errorf("getOrderByID package db : %s", err.Error())
	}

	if idStr.Valid {
		order.ID, err = uuid.Parse(idStr.String)

		if err != nil {
			return nil, fmt.Errorf("getOrderByID package db uuid parse id : %s", err.Error())
		}

	}

	if userIDStr.Valid {
		order.UserID, err = uuid.Parse(userIDStr.String)

		if err != nil {
			return nil, fmt.Errorf("getOrderByID package db uuid parse user_id : %s", err.Error())
		}

	}

	if eventIDStr.Valid {
		parsedID, parseErr := uuid.Parse(eventIDStr.String)
		if parseErr != nil {
			return nil, fmt.Errorf("getOrderByID package db uuid parse event_id : %s", parseErr.Error())
		}
		order.EventID = &parsedID
	}

	if productIDStr.Valid {
		parsedID, parseErr := uuid.Parse(productIDStr.String)
		if parseErr != nil {
			return nil, fmt.Errorf("getOrderByID package db uuid parse product_id : %s", parseErr.Error())
		}

		order.ProductID = &parsedID
	}

	return &order, nil
}

func GetRefundRequestsByOrderIDFromDB(orderID uuid.UUID) ([]models.RefundRequest, error) {

	refundRequests := []models.RefundRequest{}
	rows, err := Db.Query("SELECT id, order_id, user_id, reason, status, created_at, updated_at, approved_by FROM refundsRequests WHERE order_id = ?", orderID)

	if err != nil {
		return nil, fmt.Errorf("getRefundRequestsByOrderID package db : %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {
		var refundRequest models.RefundRequest
		var idStr, orderIDStr, userIDStr, approverIDStr sql.NullString
		err := rows.Scan(&idStr, &orderIDStr, &userIDStr, &refundRequest.Reason, &refundRequest.Status, &refundRequest.CreatedAt, &refundRequest.UpdatedAt, &approverIDStr)

		if err != nil {
			return nil, fmt.Errorf("getRefundRequestsByOrderID package db scan : %s", err.Error())
		}

		if idStr.Valid {
			refundRequest.ID, err = uuid.Parse(idStr.String)

			if err != nil {
				return nil, fmt.Errorf("getRefundRequestsByOrderID package db uuid parse id : %s", err.Error())
			}

		}

		if orderIDStr.Valid {
			refundRequest.OrderID, err = uuid.Parse(orderIDStr.String)

			if err != nil {
				return nil, fmt.Errorf("getRefundRequestsByOrderID package db uuid parse order_id : %s", err.Error())
			}

		}

		if userIDStr.Valid {
			refundRequest.UserID, err = uuid.Parse(userIDStr.String)

			if err != nil {
				return nil, fmt.Errorf("getRefundRequestsByOrderID package db uuid parse user_id : %s", err.Error())
			}

		}

		if approverIDStr.Valid {
			parsedID, parseErr := uuid.Parse(approverIDStr.String)

			if parseErr != nil {
				return nil, fmt.Errorf("getRefundRequestsByOrderID package db uuid parse approver_id : %s", parseErr.Error())
			}

			refundRequest.ApproverID = parsedID
		}

		refundRequests = append(refundRequests, refundRequest)
	}

	err = rows.Err()
	if err != nil {
		return nil, fmt.Errorf("getRefundRequestsByOrderID package db rows : %s", err.Error())
	}

	return refundRequests, nil
}
