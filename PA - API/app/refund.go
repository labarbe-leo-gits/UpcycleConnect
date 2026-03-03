package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"

	"github.com/google/uuid"
)

func ValidateRefundRequestDto(refundRequestDto models.RefundRequest) []string {

	var validationErrors []string
	if refundRequestDto.OrderID == uuid.Nil {
		validationErrors = append(validationErrors, "OrderID is required and must be a valid UUID")
	}

	if refundRequestDto.Reason == "" {
		validationErrors = append(validationErrors, "Reason is required")
	}

	if refundRequestDto.UserID == uuid.Nil {
		validationErrors = append(validationErrors, "UserID is required and must be a valid UUID")
	}

	return validationErrors

}

func CreateRefundRequest(w http.ResponseWriter, r *http.Request) {

	var refundRequestDto models.RefundRequest

	err := json.NewDecoder(r.Body).Decode(&refundRequestDto)

	if err != nil {
		fmt.Println("[ERROR] CreateRefundRequest decode:", err)
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	validationErrors := ValidateRefundRequestDto(refundRequestDto)

	if len(validationErrors) > 0 {
		fmt.Println("[ERROR] CreateRefundRequest validation:", validationErrors)
		sendError(w, "Validation errors: "+fmt.Sprint(validationErrors), http.StatusBadRequest)
		return
	}

	existingRequest, err := db.GetRefundRequestsByOrderIDFromDB(refundRequestDto.OrderID)
	if err != nil {
		fmt.Println("[ERROR] CreateRefundRequest DB check:", err)
		sendError(w, "Failed to check existing refund requests: "+err.Error(), http.StatusInternalServerError)
		return
	}

	if len(existingRequest) > 0{
		fmt.Println("[ERROR] CreateRefundRequest already exists for this order and user")
		sendError(w, "A refund request for this order already exists for this user", http.StatusConflict)
		return
	}

	err = db.CreateRefundRequestInDB(refundRequestDto)
	if err != nil {
		fmt.Println("[ERROR] CreateRefundRequest DB insert:", err)
		sendError(w, "Failed to create refund request: "+err.Error(), http.StatusInternalServerError)
		return
	}

	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]string{"message": "Refund request created successfully"})
}


