package models

import "github.com/google/uuid"

type TipComment struct {
	ID        uuid.UUID  `json:"id"`
	TipID     uuid.UUID  `json:"tip_id"`
	UserID    uuid.UUID  `json:"user_id"`
	Username  string     `json:"username,omitempty"`
	ParentID  *uuid.UUID `json:"parent_id,omitempty"`
	Content   string     `json:"content"`
	CreatedAt string     `json:"created_at"`
	UpdatedAt string     `json:"updated_at"`
}
