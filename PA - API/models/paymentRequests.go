package models

import "github.com/google/uuid"

type PaymentRequest struct {
	ID          uuid.UUID `json:"id" gorm:"type:uuid;primaryKey"`
	UserID      uuid.UUID `json:"user_id" gorm:"type:uuid;not null"`
	Amount      float64   `json:"amount" gorm:"not null"`
	Status 		int		  `json:"status" gorm:"not null;default:0"`
	BankingDetailsID uuid.UUID `json:"banking_details_id" gorm:"type:uuid;not null"`
	CreatedAt   string    `json:"created_at,omitempty"`
	UpdatedAt   string    `json:"updated_at,omitempty"`
}