package models

import "github.com/google/uuid"

type Report struct {
	ID		  uuid.UUID `json:"id"`
	ReporterID  uuid.UUID `json:"reporter_id"`
	ReportedUserID uuid.UUID `json:"reported_user_id,omitempty"`
	ReportedAnnonceID uuid.UUID `json:"reported_annonce_id,omitempty"`
	ReportedForumPostID uuid.UUID `json:"reported_forum_post_id,omitempty"`
	ReportedForumID uuid.UUID `json:"reported_forum_id,omitempty"`
	Reason	  string    `json:"reason"`
	CreatedAt   string    `json:"created_at"`
}