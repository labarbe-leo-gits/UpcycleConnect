package models

import "github.com/google/uuid"

type Favorite struct {
	ID 	  uuid.UUID `json:"id" gorm:"type:char(36);primaryKey;default:uuid_generate_v4()"`
	UserID uuid.UUID `json:"user_id" gorm:"type:char(36);not null"`
	AnnonceID uuid.UUID `json:"annonce_id" gorm:"type:char(36);not null"`
	CreatedAt string    `json:"created_at" gorm:"type:timestamp;default:current_timestamp"`
}