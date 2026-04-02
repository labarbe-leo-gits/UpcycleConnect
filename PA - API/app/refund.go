package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"
	"strconv"
	"strings"

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

	if len(existingRequest) > 0 {
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

func GetRefundRequests(w http.ResponseWriter, r *http.Request) {
	statusQuery := r.URL.Query().Get("status")
	userIDQuery := r.URL.Query().Get("user_id")
	orderIDQuery := r.URL.Query().Get("order_id")
	searchQuery := r.URL.Query().Get("search")

	var statusFilter *int
	var userFilter *uuid.UUID
	var orderFilter *uuid.UUID

	if statusQuery != "" {
		parsedStatus, err := strconv.Atoi(statusQuery)
		if err == nil {
			statusFilter = &parsedStatus
		}
	}

	if userIDQuery != "" {
		uid, err := uuid.Parse(userIDQuery)
		if err == nil {
			userFilter = &uid
		}
	}

	if orderIDQuery != "" {
		oid, err := uuid.Parse(orderIDQuery)
		if err == nil {
			orderFilter = &oid
		}
	}

	refundRequests, err := db.SearchRefundRequestsInDB(statusFilter, userFilter, orderFilter, searchQuery)
	if err != nil {
		fmt.Println("[ERROR] GetRefundRequests:", err)
		http.Error(w, "Failed to fetch refund requests", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(refundRequests)
}

func GetRefundRequestByID(w http.ResponseWriter, r *http.Request) {
	id := ""
	parts := strings.Split(strings.Trim(r.URL.Path, "/"), "/")
	if len(parts) > 1 && parts[0] == "refund-requests" {
		id = parts[1]
	}

	if id == "" {
		id = r.URL.Query().Get("id")
	}

	if id == "" {
		http.Error(w, "Refund request ID is required", http.StatusBadRequest)
		return
	}

	refID, err := uuid.Parse(id)
	if err != nil {
		http.Error(w, "Invalid refund request ID", http.StatusBadRequest)
		return
	}

	refundRequest, err := db.GetRefundRequestByIDFromDB(refID)
	if err != nil {
		fmt.Println("[ERROR] GetRefundRequestByID:", err)
		http.Error(w, "Refund request not found", http.StatusNotFound)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(refundRequest)
}

func UpdateRefundRequestStatus(w http.ResponseWriter, r *http.Request) {
	id := ""
	parts := strings.Split(strings.Trim(r.URL.Path, "/"), "/")
	if len(parts) >= 3 && parts[len(parts)-1] == "status" {
		id = parts[len(parts)-2]
	}

	if id == "" {
		id = r.URL.Query().Get("id")
	}

	if id == "" {
		http.Error(w, "Refund request ID is required", http.StatusBadRequest)
		return
	}

	refID, err := uuid.Parse(id)
	if err != nil {
		http.Error(w, "Invalid refund request ID", http.StatusBadRequest)
		return
	}

	var hit struct {
		Status       *int   `json:"status"`
		ApproverID   string `json:"approver_id"`
		AdminComment string `json:"admin_comment"`
	}

	if err := json.NewDecoder(r.Body).Decode(&hit); err != nil {
		http.Error(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	if hit.Status == nil {
		http.Error(w, "Status value is required", http.StatusBadRequest)
		return
	}

	if *hit.Status < 0 || *hit.Status > 2 {
		http.Error(w, "Invalid status value", http.StatusBadRequest)
		return
	}

	approver := uuid.Nil
	if hit.ApproverID != "" {
		approver, err = uuid.Parse(hit.ApproverID)
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

	originalRefund, err := db.GetRefundRequestByIDFromDB(refID)
	if err != nil {
		fmt.Println("[ERROR] UpdateRefundRequestStatus fetching original:", err)
		http.Error(w, "Unable to fetch current refund request", http.StatusInternalServerError)
		return
	}

	if err := db.UpdateRefundRequestStatusInDB(refID, *hit.Status, approver, hit.AdminComment); err != nil {
		fmt.Println("[ERROR] UpdateRefundRequestStatus:", err)
		http.Error(w, "Failed to update refund request status", http.StatusInternalServerError)
		return
	}

	updatedRefund, err := db.GetRefundRequestByIDFromDB(refID)
	if err != nil {
		fmt.Println("[ERROR] UpdateRefundRequestStatus readback:", err)
		http.Error(w, "Refund status updated but failed to retrieve updated record", http.StatusInternalServerError)
		return
	}

	if originalRefund.Status != updatedRefund.Status {
		var statusLabel string
		if updatedRefund.Status == 1 {
			statusLabel = "approved"
		} else if updatedRefund.Status == 2 {
			statusLabel = "rejected"
		} else {
			statusLabel = "updated"
		}

		approverName := "Admin"
		if approver != uuid.Nil {
			if u, err := db.GetUserByIDFromDB(approver); err == nil && u.Username != "" {
				approverName = u.Username
			}
		}

		paymentID := "unknown"
		order, err := db.GetOrderByIDFromDB(updatedRefund.OrderID)
		if err == nil && order.TransactionID != "" {
			paymentID = order.TransactionID
		}

		message := fmt.Sprintf("Your refund request for payment %s has been %s by %s.", paymentID, statusLabel, approverName)
		if hit.AdminComment != "" {
			message = fmt.Sprintf("%s Comment: %s", message, hit.AdminComment)
		}

		notif := models.Notification{
			UserID:  updatedRefund.UserID,
			Message: message,
		}
		if err := db.CreateNotificationInDB(notif); err != nil {
			fmt.Println("[ERROR] UpdateRefundRequestStatus CreateNotificationInDB:", err)
		}
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(updatedRefund)
}
