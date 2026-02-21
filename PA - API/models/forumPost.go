package models

import (
	"github.com/google/uuid"
)

type ForumPost struct {
	ID        uuid.UUID `json:"id"`
	ForumID   uuid.UUID `json:"forum_id"`
	AuthorID  uuid.UUID `json:"author_id"`
	ParentID  uuid.UUID `json:"parent_id,omitempty"`
	Content   string    `json:"content"`
	CreatedAt string    `json:"created_at"`
	UpdatedAt string    `json:"updated_at"`
}
