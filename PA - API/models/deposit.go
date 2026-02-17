package models

import "github.com/google/uuid"

type Deposit struct {
	ID		uuid.UUID `json:"id" db:"id"`
	UserID		uuid.UUID `json:"user_id" db:"user_id"`
	ConteneurID	uuid.UUID `json:"conteneur_id" db:"conteneur_id"`
	ObjectName string   `json:"object_name" db:"object_name"`
	ObjectDescription string   `json:"object_description" db:"object_description"`
	Status int 	 `json:"status" db:"status"`
	CreatedAt   string    `json:"created_at" db:"created_at"`
	UpdatedAt   string    `json:"updated_at" db:"updated_at"`
}