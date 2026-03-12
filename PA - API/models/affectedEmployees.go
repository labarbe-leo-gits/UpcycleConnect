package models

import "github.com/google/uuid"

type AffectedEmployee struct {
	ID      uuid.UUID `json:"id"`
	UserID  uuid.UUID `json:"user_id"`
	EventID uuid.UUID `json:"event_id"`
}