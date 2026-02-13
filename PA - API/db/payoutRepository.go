package db

import (
	"API/models"
	"fmt"

	"github.com/google/uuid"
)

func GetPayoutsFromDB() ([]models.Payout, error) {

	var payouts []models.Payout
	rows, err := Db.Query("SELECT id, user_id, amount, status, payment_request_id, done_by, created_at, updated_at FROM payouts")
	if err != nil {
		return nil, fmt.Errorf("getPayoutsFromDB: %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {
		var payout models.Payout
		var idStr, userIDStr, paymentRequestIDStr, doneByStr string
		var createdAt, updatedAt string
		err := rows.Scan(&idStr, &userIDStr, &payout.Amount, &payout.Status, &paymentRequestIDStr, &doneByStr, &createdAt, &updatedAt)
		if err != nil {
			return nil, fmt.Errorf("getPayoutsFromDB scan: %s", err.Error())
		}

		payout.ID, err = uuid.Parse(idStr)
		if err != nil {
			return nil, fmt.Errorf("getPayoutsFromDB uuid parse id: %s", err.Error())
		}

		payout.UserID, err = uuid.Parse(userIDStr)
		if err != nil {
			return nil, fmt.Errorf("getPayoutsFromDB uuid parse user_id: %s", err.Error())
		}

		payout.PaymentRequestID, err = uuid.Parse(paymentRequestIDStr)
		if err != nil {
			return nil, fmt.Errorf("getPayoutsFromDB uuid parse payment_request_id: %s", err.Error())
		}

		payout.DoneBy, err = uuid.Parse(doneByStr)
		if err != nil {
			return nil, fmt.Errorf("getPayoutsFromDB uuid parse done_by: %s", err.Error())
		}

		payout.CreatedAt = createdAt
		payout.UpdatedAt = updatedAt

		payouts = append(payouts, payout)
	}

	return payouts, nil
}

func CreatePayoutInDB(payout models.Payout) error {

	_, err := Db.Exec("INSERT INTO payouts (id, user_id, amount, status, payment_request_id, done_by) VALUES ($1, $2, $3, $4, $5, $6)",
		payout.ID, payout.UserID, payout.Amount, payout.Status, payout.PaymentRequestID, payout.DoneBy)

	if err != nil {
		return fmt.Errorf("createPayoutInDB: %s", err.Error())
	}

	return nil
}

func GetPayoutsByUserIDFromDB(userID uuid.UUID) ([]models.Payout, error) {

	var payouts []models.Payout
	rows, err := Db.Query("SELECT id, user_id, amount, status, payment_request_id, done_by, created_at, updated_at FROM payouts WHERE user_id = $1", userID)

	if err != nil {
		return nil, fmt.Errorf("getPayoutsByUserIDFromDB: %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {
		var payout models.Payout
		var idStr, userIDStr, paymentRequestIDStr, doneByStr string
		var createdAt, updatedAt string
		err := rows.Scan(&idStr, &userIDStr, &payout.Amount, &payout.Status, &paymentRequestIDStr, &doneByStr, &createdAt, &updatedAt)
		if err != nil {
			return nil, fmt.Errorf("getPayoutsByUserIDFromDB scan: %s", err.Error())
		}

		payout.ID, err = uuid.Parse(idStr)
		if err != nil {
			return nil, fmt.Errorf("getPayoutsByUserIDFromDB uuid parse id: %s", err.Error())
		}

		payout.UserID, err = uuid.Parse(userIDStr)
		if err != nil {
			return nil, fmt.Errorf("getPayoutsByUserIDFromDB uuid parse user_id: %s", err.Error())
		}

		payout.PaymentRequestID, err = uuid.Parse(paymentRequestIDStr)
		if err != nil {
			return nil, fmt.Errorf("getPayoutsByUserIDFromDB uuid parse payment_request_id: %s", err.Error())
		}

		payout.DoneBy, err = uuid.Parse(doneByStr)
		if err != nil {
			return nil, fmt.Errorf("getPayoutsByUserIDFromDB uuid parse done_by: %s", err.Error())
		}

		payout.CreatedAt = createdAt
		payout.UpdatedAt = updatedAt

		payouts = append(payouts, payout)
	}

	return payouts, nil
}
