package models

import "github.com/google/uuid"

type Annonce struct {
	ID 			uuid.UUID 	`json:"id" gorm:"type:uuid;primaryKey"`
	UserID 		uuid.UUID 	`json:"user_id" gorm:"type:uuid;not null"`
	Title 		string 		`json:"title" gorm:"not null"`
	Status 		int 		`json:"status" gorm:"not null;default:0"`
	ViewCount 	int 		`json:"view_count" gorm:"not null;default:0"`
	Description string 		`json:"description,omitempty"`
	Price 		float64 	`json:"price,omitempty"`
	CreatedAt 	string 		`json:"created_at,omitempty"`
	UpdatedAt 	string 		`json:"updated_at,omitempty"`
}
