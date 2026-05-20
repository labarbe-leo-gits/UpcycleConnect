package models

import "github.com/google/uuid"

type CommissionSettings struct {
	ID            uuid.UUID `json:"id"`
	CommissionMin float64   `json:"commission_rate_min"`
	CommissionMax float64   `json:"commission_rate_max"`
	IsGlobal      bool      `json:"is_global"`
	EffectiveFrom string    `json:"effective_from"`
	EffectiveTo   *string   `json:"effective_to,omitempty"`
	CreatedAt     string    `json:"created_at,omitempty"`
	UpdatedAt     string    `json:"updated_at,omitempty"`
}

type CommissionTransaction struct {
	ID               uuid.UUID `json:"id"`
	OrderID          uuid.UUID `json:"order_id"`
	SellerID         uuid.UUID `json:"seller_id"`
	AmountBeforeComm float64   `json:"amount_before_commission"`
	CommissionRate   float64   `json:"commission_rate"`
	CommissionAmount float64   `json:"commission_amount"`
	AmountAfterComm  float64   `json:"amount_after_commission"`
	Status           int       `json:"status"`
	Notes            *string   `json:"notes,omitempty"`
	CreatedAt        string    `json:"created_at,omitempty"`
	UpdatedAt        string    `json:"updated_at,omitempty"`
}
