package db

import (
	"API/models"
	"database/sql"
	"fmt"
	"math"

	"github.com/google/uuid"
)

const (
	UpcycleCommissionRate = 0.08
	StripeFeeRate         = 0.029
	StripeFixedFee        = 0.30
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

func CreateOrderInDB(order models.Order) error {

	newID := uuid.New()
	eventID := uuidPointerToValue(order.EventID)
	productID := uuidPointerToValue(order.ProductID)

	tx, err := Db.Begin()
	if err != nil {
		return fmt.Errorf("createOrder package db : %s", err.Error())
	}

	defer func() {
		if err != nil {
			_ = tx.Rollback()
		}
	}()

	if order.EventID != nil && *order.EventID != uuid.Nil {
		err = checkAndIncrementParticipants(tx, *order.EventID)
		if err != nil {
			return err
		}
	}

	_, err = tx.Exec("INSERT INTO orders (id, user_id, event_id, product_id, transaction_id, amount, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())", newID, order.UserID, eventID, productID, order.TransactionID, order.Amount, order.Status)
	if err != nil {
		return fmt.Errorf("createOrder package db : %s", err.Error())
	}

	if order.ProductID != nil && *order.ProductID != uuid.Nil && order.Amount > 0 {
		var ownerIDStr string
		err = tx.QueryRow("SELECT user_id FROM annonces WHERE id = ? FOR UPDATE", order.ProductID.String()).Scan(&ownerIDStr)
		if err != nil {
			if err == sql.ErrNoRows {
				return fmt.Errorf("annonce not found")
			}
			return fmt.Errorf("createOrder package db annonce: %s", err.Error())
		}

		ownerID, parseErr := uuid.Parse(ownerIDStr)
		if parseErr != nil {
			return fmt.Errorf("createOrder package db annonce owner uuid: %s", parseErr.Error())
		}

		if ownerID != order.UserID {

			var annoncePriceHT float64
			_ = tx.QueryRow("SELECT price FROM annonces WHERE id = ?", order.ProductID.String()).Scan(&annoncePriceHT)
			credit := math.Round(annoncePriceHT*100) / 100
			_, err = tx.Exec("UPDATE users SET balance = balance + ? WHERE id = ?", credit, ownerID.String())
			if err != nil {
				return fmt.Errorf("createOrder package db credit owner: %s", err.Error())
			}
		}
	}

	if err = tx.Commit(); err != nil {
		return fmt.Errorf("createOrder package db : %s", err.Error())
	}

	return nil

}

func uuidPointerToValue(id *uuid.UUID) interface{} {
	if id == nil || *id == uuid.Nil {
		return nil
	}
	return *id
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
