package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"errors"
	"fmt"
	"net/http"

	"github.com/google/uuid"
)

func GetPaymentRequests(w http.ResponseWriter, r *http.Request) {

	paymentRequests, err := db.GetPaymentRequestsFromDB()

	if err != nil {
		fmt.Println("[ERROR] GetPaymentRequests:", err)
		sendError(w, "Unable to fetch payment requests", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	jsonResponse, err := json.Marshal(paymentRequests)

	if err != nil {
		fmt.Println("[ERROR] GetPaymentRequests marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)
}

func ValidatePaymentRequestDto(paymentRequestDto models.PaymentRequest) []string {

	var validationErrors []string

	if paymentRequestDto.UserID == uuid.Nil {
		validationErrors = append(validationErrors, "UserID is required and must be a valid UUID")
	}

	if paymentRequestDto.Amount <= 0 {
		validationErrors = append(validationErrors, "Amount must be greater than 0")
	}

	if paymentRequestDto.BankingDetailsID == uuid.Nil {
		validationErrors = append(validationErrors, "BankingDetailsID is required and must be a valid UUID")
	}

	if paymentRequestDto.Status < 0 || paymentRequestDto.Status > 2 {
		validationErrors = append(validationErrors, "Status must be 0 (pending), 1 (approved), or 2 (rejected)")
	}

	return validationErrors
}

func CreatePaymentRequest(w http.ResponseWriter, r *http.Request) {

	var paymentRequestDto models.PaymentRequest
	err := json.NewDecoder(r.Body).Decode(&paymentRequestDto)
	if err != nil {
		fmt.Println("[ERROR] CreatePaymentRequest decode:", err)
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	validationErrors := ValidatePaymentRequestDto(paymentRequestDto)

	if len(validationErrors) > 0 {
		sendError(w, "Validation errors: "+fmt.Sprintf("%v", validationErrors), http.StatusBadRequest)
		return
	}

	err = db.CreatePaymentRequestInDB(paymentRequestDto)

	if err != nil {
		if errors.Is(err, db.ErrInsufficientBalance) {
			sendError(w, "Requested amount exceeds available balance", http.StatusBadRequest)
			return
		}
		fmt.Println("[ERROR] CreatePaymentRequest:", err)
		sendError(w, "Unable to create payment request", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(paymentRequestDto)

}
