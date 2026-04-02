package models

import "github.com/google/uuid"

type GroupDiscussion struct {
	ID        uuid.UUID `json:"id"`
	Title     string    `json:"title"`
	ImageUrl  string    `json:"image_url"`
	CreatedBy uuid.UUID `json:"created_by"`
	CreatedAt string    `json:"created_at"`
}
