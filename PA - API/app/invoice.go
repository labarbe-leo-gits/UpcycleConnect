package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"

	"github.com/google/uuid"
)

func CreateInvoice(w http.ResponseWriter, r *http.Request) {
	var payload struct {
		UserID              string  `json:"user_id"`
		StripeCustomerID    string  `json:"stripe_customer_id"`
		SubscriptionID      string  `json:"subscription_id"`
		StripeInvoiceID     string  `json:"stripe_invoice_id"`
		StripePaymentIntent string  `json:"stripe_payment_intent_id"`
		AmountDue           float64 `json:"amount_due"`
		AmountPaid          float64 `json:"amount_paid"`
		Currency            string  `json:"currency"`
		Status              string  `json:"status"`
		DueDate             string  `json:"due_date"`
		PeriodStart         string  `json:"period_start"`
		PeriodEnd           string  `json:"period_end"`
		InvoiceURL          string  `json:"invoice_url"`
		ReceiptURL          string  `json:"receipt_url"`
	}

	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		sendError(w, "Invalid request body", http.StatusBadRequest)
		return
	}

	var userID uuid.UUID
	var err error
	if payload.UserID != "" {
		userID, err = uuid.Parse(payload.UserID)
		if err != nil {
			sendError(w, "Invalid user ID", http.StatusBadRequest)
			return
		}
	} else if payload.StripeCustomerID != "" {
		userID, err = db.GetUserIDByStripeCustomerID(payload.StripeCustomerID)
		if err != nil {
			sendError(w, "Unable to resolve user from stripe customer", http.StatusBadRequest)
			return
		}
	} else {
		sendError(w, "Missing user_id or stripe_customer_id", http.StatusBadRequest)
		return
	}

	var contractID *uuid.UUID
	if payload.SubscriptionID != "" {
		cid, err := db.GetContractIDBySubscription(payload.SubscriptionID)
		if err != nil {
			fmt.Println("[ERROR] CreateInvoice get contract:", err)
		} else {
			contractID = cid
		}
	}

	inv := models.Invoice{
		UserID:                userID,
		ContractID:            contractID,
		StripeInvoiceID:       payload.StripeInvoiceID,
		StripePaymentIntentID: payload.StripePaymentIntent,
		AmountDue:             payload.AmountDue,
		AmountPaid:            payload.AmountPaid,
		Currency:              payload.Currency,
		Status:                payload.Status,
		DueDate:               payload.DueDate,
		PeriodStart:           payload.PeriodStart,
		PeriodEnd:             payload.PeriodEnd,
		InvoiceURL:            payload.InvoiceURL,
		ReceiptURL:            payload.ReceiptURL,
	}

	if err := db.UpsertInvoice(inv); err != nil {
		fmt.Println("[ERROR] CreateInvoice:", err)
		sendError(w, "Failed to create invoice", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"status": "ok"})
}
