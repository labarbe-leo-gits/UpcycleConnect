package models

import "github.com/google/uuid"

type Category struct {
	ID        uuid.UUID `json:"id" gorm:"type:char(36);primaryKey;default:uuid_generate_v4()"`
	Name      string    `json:"name" gorm:"type:varchar(255);not null;unique"`
	CreatedAt string    `json:"created_at" gorm:"type:timestamp;default:current_timestamp"`
}