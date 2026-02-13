package models

import "github.com/google/uuid"

type Notification struct {
	ID        uuid.UUID `json:"id"`
	AnnonceID uuid.UUID `json:"annonce_id,omitempty"`
	UserID    uuid.UUID `json:"user_id"`
	Message   string    `json:"message"`
	Read      bool      `json:"read"`
	CreatedAt string	`json:"created_at"`
}