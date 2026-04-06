package models

import "github.com/google/uuid"

type Contact struct {

	ID         uuid.UUID `json:"id" gorm:"type:char(36);primaryKey;default:uuid_generate_v4()"`
	Name 	 string    `json:"name" gorm:"type:varchar(255);not null"`
	Email    string    `json:"email" gorm:"type:varchar(255);not null"`
	Message string    `json:"message" gorm:"type:text;not null"`
	CreatedAt string    `json:"created_at" gorm:"type:timestamp;default:current_timestamp"`

}

type ContactResponse struct {

	ID 	  uuid.UUID `json:"id" gorm:"type:char(36);primaryKey;default:uuid_generate_v4()"`
	ContactID uuid.UUID `json:"contact_id" gorm:"type:char(36);not null"`
	ResponderID uuid.UUID `json:"responder_id" gorm:"type:char(36);not null"`
	Message string    `json:"message" gorm:"type:text;not null"`
	CreatedAt string    `json:"created_at" gorm:"type:timestamp;default:current_timestamp"`

}