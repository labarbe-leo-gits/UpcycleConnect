package models

import "github.com/google/uuid"

type ProjectLikes struct {
	ID 	  uuid.UUID `json:"id" gorm:"type:uuid;primaryKey"`
	ProjectID uuid.UUID `json:"project_id" gorm:"type:uuid;not null"`
	UserID    uuid.UUID `json:"user_id" gorm:"type:uuid;not null"`
	CreatedAt string    `json:"created_at" gorm:"autoCreateTime"`
}