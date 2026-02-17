package models

import (
	"github.com/google/uuid"
)

type Forum struct {
	ID          uuid.UUID `json:"id"`
	Title       string    `json:"title"`
	Description string    `json:"description"`
	CreatedBy   uuid.UUID `json:"created_by"`
	CreatedAt   string    `json:"created_at"`
	UpdatedAt   string    `json:"updated_at"`
	PostCount   int       `json:"post_count,omitempty"`
	LatestPost  string    `json:"latest_post,omitempty"`
}
