package models

import "github.com/google/uuid"

type Image struct {
	ID       uuid.UUID `json:"id" gorm:"type:uuid;primaryKey"`
	EventID  string `json:"event_id,omitempty"`
	ProductID string `json:"product_id,omitempty"`
	FileName string `json:"file_name"`
	CreatedAt string `json:"created_at,omitempty"`
	UploadedBy uuid.UUID `json:"uploaded_by" gorm:"type:uuid;not null"`
}