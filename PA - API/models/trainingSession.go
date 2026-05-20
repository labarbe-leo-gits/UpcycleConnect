package models

import "github.com/google/uuid"

type TrainingSession struct {
	ID                  uuid.UUID `json:"id"`
	EventID             uuid.UUID `json:"event_id"`
	CreatorID           uuid.UUID `json:"creator_id"`
	Title               string    `json:"title"`
	Description         *string   `json:"description,omitempty"`
	SessionType         string    `json:"session_type"`
	PricePerPerson      float64   `json:"price_per_person"`
	Currency            string    `json:"currency"`
	MaxParticipants     *int      `json:"max_participants,omitempty"`
	CurrentParticipants int       `json:"current_participants"`
	IsOnline            bool      `json:"is_online"`
	OnlineLink          *string   `json:"online_link,omitempty"`
	Status              int       `json:"status"`
	CreatedAt           string    `json:"created_at,omitempty"`
	UpdatedAt           string    `json:"updated_at,omitempty"`
}

type TrainingSessionRegistration struct {
	ID           uuid.UUID        `json:"id"`
	SessionID    uuid.UUID        `json:"session_id"`
	UserID       uuid.UUID        `json:"user_id"`
	OrderID      *uuid.UUID       `json:"order_id,omitempty"`
	AmountPaid   float64          `json:"amount_paid"`
	Status       int              `json:"status"`
	RegisteredAt string           `json:"registered_at,omitempty"`
	AttendedAt   *string          `json:"attended_at,omitempty"`
	CreatedAt    string           `json:"created_at,omitempty"`
	Session      *TrainingSession `json:"session,omitempty"`
}
