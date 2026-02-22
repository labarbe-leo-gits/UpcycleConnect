package models

import "github.com/google/uuid"

type Discussion struct {
	ID 		uuid.UUID `json:"id"`
	User1ID 	uuid.UUID `json:"user1_id"`
	User2ID 	uuid.UUID `json:"user2_id"`
	CreatedAt 	string    `json:"created_at"`
}