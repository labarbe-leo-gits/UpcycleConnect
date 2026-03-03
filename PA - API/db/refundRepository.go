package db

import (
	"API/models"
	"fmt"

	"github.com/google/uuid"
)

func CreateRefundRequestInDB(refundRequest models.RefundRequest) (err error){

	newID := uuid.New()
	currentTime := getCurrentTime()

	_, err = Db.Exec(
		"INSERT INTO refundsRequests (id, order_id, user_id, reason, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)",
		newID.String(), refundRequest.OrderID.String(), refundRequest.UserID.String(), refundRequest.Reason, "pending", currentTime, currentTime,
	)

	if err != nil {
		return fmt.Errorf("createRefundRequestInDB: %s", err.Error())
	}

	return nil

}