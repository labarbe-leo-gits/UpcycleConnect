package models

import "github.com/google/uuid"

type GroupDiscussion struct {
	ID 		uuid.UUID `json:"id"`
	Title 	string    `json:"title"`
	CreatedBy uuid.UUID `json:"created_by"`
	CreatedAt string    `json:"created_at"`
}