package models

import "github.com/google/uuid"

type Review struct {

	ID        uuid.UUID `json:"id" gorm:"type:uuid;primaryKey"`
	ReviewerID uuid.UUID `json:"reviewer_id" gorm:"type:uuid;not null"`
	ReviewedUserID uuid.UUID `json:"reviewed_user_id" gorm:"type:uuid;not null"`
	Rating    int       `json:"rating" gorm:"not null"`
	Comment  string    `json:"comment" gorm:"type:text"`
	CreatedAt string   `json:"created_at" gorm:"autoCreateTime"`
	UpdatedAt string   `json:"updated_at" gorm:"autoUpdateTime"`

}