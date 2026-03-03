package models

import "github.com/google/uuid"

type Refund struct {
	ID        uuid.UUID `json:"id" gorm:"type:uuid;primaryKey"`
	RefundRequestID uuid.UUID `json:"refund_request_id" gorm:"type:uuid;not null"`
	Amount   float64   `json:"amount" gorm:"type:decimal(10,2);not null"`
	Status  int       `json:"status" gorm:"type:int;default:1"`
	ProcessedBy uuid.UUID `json:"processed_by" gorm:"type:uuid;default:null"`
	CreatedAt int64     `json:"created_at" gorm:"autoCreateTime"`
	UpdatedAt int64     `json:"updated_at" gorm:"autoUpdateTime"`
}