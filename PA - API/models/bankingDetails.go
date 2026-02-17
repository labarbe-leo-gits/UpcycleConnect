package models

import "github.com/google/uuid"

type BankingDetails struct {
	ID         uuid.UUID `json:"id" gorm:"type:uuid;primaryKey"`
	UserID     uuid.UUID `json:"user_id" gorm:"type:uuid;not null"`
	RIB        string    `json:"rib" gorm:"not null"`
	IBAN       string    `json:"iban" gorm:"not null"`
	BIC        string    `json:"bic" gorm:"not null"`
	HolderName string    `json:"holder_name" gorm:"not null"`
	IsSaved   bool   `json:"is_saved" gorm:"default:true"`
	CreatedAt string `json:"created_at,omitempty"`
	UpdatedAt string `json:"updated_at,omitempty"`
}
