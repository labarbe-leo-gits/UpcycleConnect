package models

import "github.com/google/uuid"

type Invoice struct {
	ID                    uuid.UUID  `json:"id"`
	UserID                uuid.UUID  `json:"user_id"`
	ContractID            *uuid.UUID `json:"contract_id,omitempty"`
	StripeInvoiceID       string     `json:"stripe_invoice_id"`
	StripePaymentIntentID string     `json:"stripe_payment_intent_id,omitempty"`
	AmountDue             float64    `json:"amount_due"`
	AmountPaid            float64    `json:"amount_paid"`
	Currency              string     `json:"currency"`
	Status                string     `json:"status"`
	DueDate               string     `json:"due_date,omitempty"`
	PeriodStart           string     `json:"period_start,omitempty"`
	PeriodEnd             string     `json:"period_end,omitempty"`
	InvoiceURL            string     `json:"invoice_url,omitempty"`
	ReceiptURL            string     `json:"receipt_url,omitempty"`
	CreatedAt             string     `json:"created_at,omitempty"`
	UpdatedAt             string     `json:"updated_at,omitempty"`
}
