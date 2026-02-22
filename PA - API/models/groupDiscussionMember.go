package models

import "github.com/google/uuid"

type GroupDiscussionMember struct {
	ID 			  uuid.UUID `json:"id"`
	GroupDiscussionID uuid.UUID `json:"group_discussion_id"`
	UserID 		  uuid.UUID `json:"user_id"`
	JoinedAt 	  string    `json:"joined_at"`
}