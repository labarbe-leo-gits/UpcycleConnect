package models

import "github.com/google/uuid"

type Project struct {
	ID          uuid.UUID  `json:"id"`
	UserID      uuid.UUID  `json:"user_id"`
	AnnonceID   *uuid.UUID `json:"annonce_id,omitempty"`
	Title       string     `json:"title"`
	Description string     `json:"description"`
	Status      int        `json:"status"`
	AIGenerated int        `json:"ai_generated"`
	CreatedAt   string     `json:"created_at"`
	UpdatedAt   string     `json:"updated_at"`
}
