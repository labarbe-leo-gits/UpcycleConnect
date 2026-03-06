package models

import "github.com/google/uuid"

type ProjectStep struct {
	ID              uuid.UUID `json:"id"`
	ProjectID       uuid.UUID `json:"project_id"`
	StepOrder       int       `json:"step_order"`
	Title           string    `json:"title"`
	Description     string    `json:"description"`
	DurationMinutes *int      `json:"duration_minutes,omitempty"`
	CreatedAt       string    `json:"created_at"`
}
