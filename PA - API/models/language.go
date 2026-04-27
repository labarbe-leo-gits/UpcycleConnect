package models

import (
	"time"

	"github.com/google/uuid"
)

type Language struct {
	ID        uuid.UUID `json:"id" gorm:"type:char(36);primaryKey;default:uuid_generate_v4()"`
	Code      string    `json:"code" gorm:"type:varchar(10);not null;unique"`
	Name      string    `json:"name" gorm:"type:varchar(255);not null"`
	CreatedAt time.Time `json:"created_at" gorm:"type:timestamp;default:current_timestamp"`
}

type LanguageTranslation struct {
	ID         uuid.UUID `json:"id" gorm:"type:char(36);primaryKey;default:uuid_generate_v4()"`
	LanguageID uuid.UUID `json:"language_id" gorm:"type:char(36);not null;index"`
	KeyName    string    `json:"key_name" gorm:"type:varchar(255);not null;index"`
	Section    string    `json:"section" gorm:"type:varchar(255)"`
	Value      string    `json:"value" gorm:"type:longtext;not null"`
	CreatedAt  time.Time `json:"created_at" gorm:"type:timestamp;default:current_timestamp"`
}
