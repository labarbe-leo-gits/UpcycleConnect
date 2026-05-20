package models

import "github.com/google/uuid"

type PartnershipCampaign struct {
	ID                    uuid.UUID                 `json:"id"`
	PartnerName           string                    `json:"partner_name"`
	PartnerLogo           *string                   `json:"partner_logo,omitempty"`
	Description           *string                   `json:"description,omitempty"`
	WebsiteURL            *string                   `json:"website_url,omitempty"`
	Status                int                       `json:"status"`
	MonthlyPrice          float64                   `json:"monthly_price"`
	Currency              string                    `json:"currency"`
	ContractID            *uuid.UUID                `json:"contract_id,omitempty"`
	StartDate             string                    `json:"start_date"`
	EndDate               string                    `json:"end_date"`
	StripePaymentIntentID *string                   `json:"stripe_payment_intent_id,omitempty"`
	CreatedAt             string                    `json:"created_at,omitempty"`
	UpdatedAt             string                    `json:"updated_at,omitempty"`
	Items                 []PartnershipCampaignItem `json:"items,omitempty"`
}

type PartnershipCampaignItem struct {
	ID               uuid.UUID  `json:"id"`
	CampaignID       uuid.UUID  `json:"campaign_id"`
	AnnonceID        *uuid.UUID `json:"annonce_id,omitempty"`
	PositionType     string     `json:"position_type"`
	PositionPriority int        `json:"position_priority"`
	CreatedAt        string     `json:"created_at,omitempty"`
}
