package models

import "github.com/google/uuid"

type Ban struct {
	ID           uuid.UUID `json:"id"`
	UserID       uuid.UUID `json:"user_id"`
	Reason 	 string    `json:"reason"`
	BannedAt     string    `json:"banned_at"`
	BannedBy     uuid.UUID `json:"banned_by"`
	DurationDays int       `json:"duration_days"`
}