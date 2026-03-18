package models

import "github.com/google/uuid"

type AdCampaign struct {
	ID                    uuid.UUID  `json:"id"`
	UserID                uuid.UUID  `json:"user_id"`
	ContractID            *uuid.UUID `json:"contract_id,omitempty"`
	Name                  string     `json:"name"`
	Description           string     `json:"description,omitempty"`
	Status                int        `json:"status"`
	StartDate             string     `json:"start_date"`
	EndDate               string     `json:"end_date"`
	Budget                float64    `json:"budget"`
	Currency              string     `json:"currency"`
	StripePaymentIntentID string     `json:"stripe_payment_intent_id,omitempty"`
	CreatedAt             string     `json:"created_at,omitempty"`
	UpdatedAt             string     `json:"updated_at,omitempty"`
}
