package models

import "github.com/google/uuid"

type Newsletter struct {
	ID        uuid.UUID `json:"id"`
	Title     string    `json:"title"`
	Content   string    `json:"content"`
	Status    int       `json:"status"` // 0: draft, 1: scheduled, 2: sent
	CreatedAt string    `json:"created_at"`
	UpdatedAt string    `json:"updated_at"`
}

type NewsletterRecipient struct {
	ID           uuid.UUID `json:"id"`
	NewsletterID uuid.UUID `json:"newsletter_id"`
	UserID       uuid.UUID `json:"user_id"`
	SentAt       *string   `json:"sent_at,omitempty"`
}
