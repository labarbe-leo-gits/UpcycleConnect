package models

import "github.com/google/uuid"

type Message struct {
	ID                uuid.UUID    `json:"id"`
	DiscussionID      uuid.UUID    `json:"discussion_id,omitempty"`
	GroupDiscussionID uuid.UUID    `json:"group_discussion_id,omitempty"`
	SenderID          uuid.UUID    `json:"sender_id"`
	Content           string       `json:"content"`
	CreatedAt         string       `json:"created_at"`
	Attachments       []Attachment `json:"attachments,omitempty"`
}
