package models

import "github.com/google/uuid"

type Poll struct {
	ID       uuid.UUID `json:"id"`
	Question string    `json:"question"`
	CreatedBy uuid.UUID `json:"created_by"`
	CreatedAt string     `json:"created_at"`
}

type PollOption struct {
	ID     uuid.UUID `json:"id"`
	PollID uuid.UUID `json:"poll_id"`
	Text   string    `json:"text"`
}

type PollVote struct {
	ID       uuid.UUID `json:"id"`
	PollID   uuid.UUID `json:"poll_id"`
	OptionID uuid.UUID `json:"option_id"`
	UserID   uuid.UUID `json:"user_id"`
	CreatedAt string    `json:"created_at"`
}
