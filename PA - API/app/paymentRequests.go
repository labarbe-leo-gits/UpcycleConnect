package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"errors"
	"fmt"
	"net/http"
	"strings"

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

func UpdatePaymentRequestStatus(w http.ResponseWriter, r *http.Request) {
	id := strings.TrimSuffix(strings.TrimPrefix(r.URL.Path, "/payment-requests/"), "/status")
	if id == "" {
		id = r.URL.Query().Get("id")
	}

	if id == "" {
		http.Error(w, "Payment request ID is required", http.StatusBadRequest)
		return
	}

	requestID, err := uuid.Parse(id)
	if err != nil {
		http.Error(w, "Invalid payment request ID", http.StatusBadRequest)
		return
	}

	var body struct {
		Status       *int   `json:"status"`
		ApproverID   string `json:"approver_id"`
		AdminComment string `json:"admin_comment"`
	}

	if err := json.NewDecoder(r.Body).Decode(&body); err != nil {
		http.Error(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	if body.Status == nil {
		http.Error(w, "Status value is required", http.StatusBadRequest)
		return
	}

	if *body.Status < 0 || *body.Status > 2 {
		http.Error(w, "Invalid status value", http.StatusBadRequest)
		return
	}

	paymentRequest, err := db.GetPaymentRequestByIDFromDB(requestID)
	if err != nil {
		fmt.Println("[ERROR] UpdatePaymentRequestStatus fetching request:", err)
		http.Error(w, "Unable to fetch payment request", http.StatusInternalServerError)
		return
	}

	if paymentRequest.Status != 0 {
		http.Error(w, "Only pending payment requests can be updated", http.StatusBadRequest)
		return
	}

	approver := uuid.Nil
	if body.ApproverID != "" {
		approver, err = uuid.Parse(body.ApproverID)
		if err != nil {
			http.Error(w, "Invalid approver_id", http.StatusBadRequest)
			return
		}
	}

	if approver == uuid.Nil {
		if rawUserID := r.Context().Value("user_id"); rawUserID != nil {
			if userIDStr, ok := rawUserID.(string); ok {
				if parsedUserID, err := uuid.Parse(userIDStr); err == nil {
					approver = parsedUserID
				}
			}
		}
	}

	if approver == uuid.Nil {
		http.Error(w, "Unable to determine approver", http.StatusUnauthorized)
		return
	}

	if err := db.UpdatePaymentRequestStatusInDB(paymentRequest, *body.Status, approver); err != nil {
		fmt.Println("[ERROR] UpdatePaymentRequestStatus:", err)
		http.Error(w, "Failed to update payment request status", http.StatusInternalServerError)
		return
	}

	updatedRequest, err := db.GetPaymentRequestByIDFromDB(requestID)
	if err != nil {
		fmt.Println("[ERROR] UpdatePaymentRequestStatus readback:", err)
		http.Error(w, "Payment request status updated but failed to retrieve updated record", http.StatusInternalServerError)
		return
	}

	var statusLabel string
	if updatedRequest.Status == 1 {
		statusLabel = "approved"
	} else if updatedRequest.Status == 2 {
		statusLabel = "rejected"
	} else {
		statusLabel = "updated"
	}

	approverName := "Admin"
	if u, err := db.GetUserByIDFromDB(approver); err == nil && u.Username != "" {
		approverName = u.Username
	}

	message := fmt.Sprintf("Your payout request of %.2f € has been %s by %s.", updatedRequest.Amount, statusLabel, approverName)
	if body.AdminComment != "" {
		message = fmt.Sprintf("%s Comment: %s", message, body.AdminComment)
	}

	notif := models.Notification{
		UserID:  paymentRequest.UserID,
		Message: message,
	}
	if err := db.CreateNotificationInDB(notif); err != nil {
		fmt.Println("[ERROR] UpdatePaymentRequestStatus CreateNotificationInDB:", err)
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(updatedRequest)
}
