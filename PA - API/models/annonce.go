package models

import "github.com/google/uuid"

type Annonce struct {
	ID 			uuid.UUID 	`json:"id" gorm:"type:uuid;primaryKey"`
	UserID 		uuid.UUID 	`json:"user_id" gorm:"type:uuid;not null"`
	Title 		string 		`json:"title" gorm:"not null"`
	Description string 		`json:"description,omitempty"`
	Price 		float64 	`json:"price,omitempty"`
	CreatedAt 	string 		`json:"created_at,omitempty"`
	UpdatedAt 	string 		`json:"updated_at,omitempty"`
}
