package models

import "github.com/google/uuid"

type Tip struct {
	ID          uuid.UUID  `json:"id"`
	Title       string     `json:"title"`
	Description string     `json:"description"`
	PollID      *uuid.UUID `json:"poll_id,omitempty"`
	CreatedBy   uuid.UUID  `json:"created_by"`
	CreatedAt   string     `json:"created_at"`
	UpdatedBy   uuid.UUID  `json:"updated_by"`
	UpdatedAt   string     `json:"updated_at"`
}
