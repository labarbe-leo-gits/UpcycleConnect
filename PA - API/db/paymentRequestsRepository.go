package db

import (
	"API/models"
	"database/sql"
	"errors"
	"fmt"
	"time"

	"github.com/google/uuid"
)

var ErrInsufficientBalance = errors.New("insufficient balance")

func GetPaymentRequestsFromDB() ([]models.PaymentRequest, error) {

	var paymentRequests []models.PaymentRequest

	rows, err := Db.Query("SELECT id, user_id, amount, status, banking_details_id, created_at, updated_at FROM paymentsRequests")
	if err != nil {
		return nil, fmt.Errorf("getPaymentRequestsFromDB: %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {
		var paymentRequest models.PaymentRequest
		var idStr, userIDStr, bankingDetailsIDStr string
		var createdAt, updatedAt string
		err := rows.Scan(&idStr, &userIDStr, &paymentRequest.Amount, &paymentRequest.Status, &bankingDetailsIDStr, &createdAt, &updatedAt)
		if err != nil {
			return nil, fmt.Errorf("getPaymentRequestsFromDB scan: %s", err.Error())
		}

		paymentRequest.ID, err = uuid.Parse(idStr)
		if err != nil {
			return nil, fmt.Errorf("getPaymentRequestsFromDB uuid parse id: %s", err.Error())
		}
		paymentRequest.UserID, err = uuid.Parse(userIDStr)
		if err != nil {
			return nil, fmt.Errorf("getPaymentRequestsFromDB uuid parse user_id: %s", err.Error())
		}

		paymentRequest.BankingDetailsID, err = uuid.Parse(bankingDetailsIDStr)
		if err != nil {
			return nil, fmt.Errorf("getPaymentRequestsFromDB uuid parse banking_details_id: %s", err.Error())
		}

		paymentRequest.CreatedAt = createdAt
		paymentRequest.UpdatedAt = updatedAt
		paymentRequests = append(paymentRequests, paymentRequest)
	}

	err = rows.Err()
	if err != nil {
		return nil, fmt.Errorf("getPaymentRequestsFromDB rows: %s", err.Error())
	}

	return paymentRequests, nil
}

func CreatePaymentRequestInDB(paymentRequest models.PaymentRequest) (err error) {

	newID := uuid.New()
	currentTime := time.Now().UTC().Format("2006-01-02 15:04:05")

	tx, err := Db.Begin()
	if err != nil {
		return fmt.Errorf("createPaymentRequestInDB begin: %s", err.Error())
	}

	defer func() {
		if err != nil {
			_ = tx.Rollback()
		}
	}()

	var currentBalance float64
	err = tx.QueryRow("SELECT balance FROM users WHERE id = ? FOR UPDATE", paymentRequest.UserID.String()).Scan(&currentBalance)
	if err != nil {
		if err == sql.ErrNoRows {
			return fmt.Errorf("createPaymentRequestInDB: user not found")
		}
		return fmt.Errorf("createPaymentRequestInDB select balance: %s", err.Error())
	}

	if currentBalance < paymentRequest.Amount {
		return ErrInsufficientBalance
	}

	_, err = tx.Exec("UPDATE users SET balance = balance - ? WHERE id = ?", paymentRequest.Amount, paymentRequest.UserID.String())
	if err != nil {
		return fmt.Errorf("createPaymentRequestInDB update balance: %s", err.Error())
	}

	_, err = tx.Exec("INSERT INTO paymentsRequests (id, user_id, amount, status, banking_details_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)",
		newID.String(), paymentRequest.UserID.String(), paymentRequest.Amount, paymentRequest.Status, paymentRequest.BankingDetailsID.String(), currentTime, currentTime)
	if err != nil {
		return fmt.Errorf("createPaymentRequestInDB insert: %s", err.Error())
	}

	err = tx.Commit()
	if err != nil {
		return fmt.Errorf("createPaymentRequestInDB commit: %s", err.Error())
	}

	return nil
}

func GetPaymentRequestByIDFromDB(paymentRequestID uuid.UUID) (models.PaymentRequest, error) {
	var paymentRequest models.PaymentRequest
	var idStr, userIDStr, bankingDetailsIDStr string
	var createdAt, updatedAt string
	err := Db.QueryRow("SELECT id, user_id, amount, status, banking_details_id, created_at, updated_at FROM paymentsRequests WHERE id = ?", paymentRequestID.String()).
		Scan(&idStr, &userIDStr, &paymentRequest.Amount, &paymentRequest.Status, &bankingDetailsIDStr, &createdAt, &updatedAt)
	if err != nil {
		if err == sql.ErrNoRows {
			return paymentRequest, fmt.Errorf("payment request not found")
		}
		return paymentRequest, fmt.Errorf("getPaymentRequestByIDFromDB: %s", err.Error())
	}

	paymentRequest.ID, err = uuid.Parse(idStr)
	if err != nil {
		return paymentRequest, fmt.Errorf("getPaymentRequestByIDFromDB uuid parse id: %s", err.Error())
	}

	paymentRequest.UserID, err = uuid.Parse(userIDStr)
	if err != nil {
		return paymentRequest, fmt.Errorf("getPaymentRequestByIDFromDB uuid parse user_id: %s", err.Error())
	}

	paymentRequest.BankingDetailsID, err = uuid.Parse(bankingDetailsIDStr)
	if err != nil {
		return paymentRequest, fmt.Errorf("getPaymentRequestByIDFromDB uuid parse banking_details_id: %s", err.Error())
	}

	paymentRequest.CreatedAt = createdAt
	paymentRequest.UpdatedAt = updatedAt

	return paymentRequest, nil
}

func UpdatePaymentRequestStatusInDB(paymentRequest models.PaymentRequest, newStatus int, approverID uuid.UUID) error {
	tx, err := Db.Begin()
	if err != nil {
		return fmt.Errorf("updatePaymentRequestStatusInDB begin: %s", err.Error())
	}

	currentTime := time.Now().UTC().Format("2006-01-02 15:04:05")

	_, err = tx.Exec("UPDATE paymentsRequests SET status = ?, updated_at = ? WHERE id = ?", newStatus, currentTime, paymentRequest.ID.String())
	if err != nil {
		_ = tx.Rollback()
		return fmt.Errorf("updatePaymentRequestStatusInDB update request: %s", err.Error())
	}

	if newStatus == 1 {
		payoutID := uuid.New()
		_, err = tx.Exec("INSERT INTO payouts (id, user_id, amount, status, payment_request_id, done_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
			payoutID.String(), paymentRequest.UserID.String(), paymentRequest.Amount, 1, paymentRequest.ID.String(), approverID.String(), currentTime, currentTime)
		if err != nil {
			_ = tx.Rollback()
			return fmt.Errorf("updatePaymentRequestStatusInDB create payout: %s", err.Error())
		}
	} else if newStatus == 2 {
		_, err = tx.Exec("UPDATE users SET balance = balance + ? WHERE id = ?", paymentRequest.Amount, paymentRequest.UserID.String())
		if err != nil {
			_ = tx.Rollback()
			return fmt.Errorf("updatePaymentRequestStatusInDB refund balance: %s", err.Error())
		}
	}

	err = tx.Commit()
	if err != nil {
		return fmt.Errorf("updatePaymentRequestStatusInDB commit: %s", err.Error())
	}

	return nil
}
