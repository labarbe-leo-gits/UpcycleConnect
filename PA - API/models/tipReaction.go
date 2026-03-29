package models

import "github.com/google/uuid"

type TipReaction struct {
	ID           uuid.UUID `json:"id"`
	TipID        uuid.UUID `json:"tip_id"`
	UserID       uuid.UUID `json:"user_id"`
	ReactionType int       `json:"reaction_type"`
	CreatedAt    string    `json:"created_at"`
	UpdatedAt    string    `json:"updated_at"`
}

type TipReactionSummary struct {
	TipID         string `json:"tip_id"`
	Likes         int    `json:"likes"`
	Dislikes      int    `json:"dislikes"`
	CurrentUser   string `json:"current_user_reaction"`
}
