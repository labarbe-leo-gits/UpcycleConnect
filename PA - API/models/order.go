package models

import "github.com/google/uuid"

type Order struct {
	ID        uuid.UUID `json:"id" gorm:"type:uuid;primaryKey"`
	UserID	uuid.UUID `json:"user_id" gorm:"type:uuid;not null"`
	EventID	uuid.UUID `json:"event_id" gorm:"type:uuid;omitempty"`
	ProductID uuid.UUID `json:"product_id" gorm:"type:uuid;omitempty"`
	TransactionID string    `json:"transaction_id,omitempty"`
	Amount    float64   `json:"amount,omitempty"`
	Status int	   `json:"status,omitempty"`
	CreatedAt string    `json:"created_at,omitempty"`
	UpdatedAt string    `json:"updated_at,omitempty"`
}
