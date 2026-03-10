package models

import "github.com/google/uuid"

type Tag struct {
	ID  uuid.UUID `json:"id"`
	Name string `json:"name"`
	BackgroundColor string `json:"background_color"`
	TextColor string `json:"text_color"`
	CreatedAt string `json:"created_at"`
}

type ItemTag struct {
	ID uuid.UUID `json:"id"`
	TagID uuid.UUID `json:"tag_id"`
	AnnonceID uuid.UUID `json:"annonce_id,omitempty"`
	EventID uuid.UUID `json:"event_id,omitempty"`
	ProjectID uuid.UUID `json:"project_id,omitempty"`
}
