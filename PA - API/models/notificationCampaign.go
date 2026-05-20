package models

import "github.com/google/uuid"

type NotificationCampaign struct {
	ID              uuid.UUID `json:"id"`
	Title           string    `json:"title"`
	Message         string    `json:"message"`
	TargetUserType  int       `json:"target_user_type"`
	Status          int       `json:"status"`
	ScheduledAt     *string   `json:"scheduled_at,omitempty"`
	CreatedByUserID string    `json:"created_by_user_id"`
	CreatedAt       string    `json:"created_at"`
	UpdatedAt       string    `json:"updated_at"`
	RecipientCount  int       `json:"recipient_count"`
	SentCount       int       `json:"sent_count"`
	FailedCount     int       `json:"failed_count"`
	ReadCount       int       `json:"read_count"`
}
