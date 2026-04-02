// Service model for the API based on evenement structure in DB

package models

import "github.com/google/uuid"

type ServiceSchedule struct {
	ID          uuid.UUID `json:"id,omitempty"`
	Hour        int       `json:"hour"`
	IsAvailable bool      `json:"is_available"`
}

type Service struct {
	ID                  uuid.UUID         `json:"id" gorm:"type:uuid;primaryKey"`
	Name                string            `json:"name" gorm:"not null"`
	Description         string            `json:"description,omitempty"`
	Price               float64           `json:"price,omitempty"`
	Type                uuid.UUID         `json:"type_id" gorm:"type:uuid;not null"`
	ServiceDate         string            `json:"service_date,omitempty"`
	ServiceRoad         string            `json:"service_road,omitempty"`
	ServiceCity         string            `json:"service_city,omitempty"`
	ServiceZip          string            `json:"service_zip,omitempty"`
	MaximumParticipants *int              `json:"maximum_participants,omitempty"`
	CurrentParticipants int               `json:"current_participants,omitempty"`
	Schedules           []ServiceSchedule `json:"schedules,omitempty"`
	MeetingType         string            `json:"meeting_type,omitempty"`
	OnlineMeetingLink   string            `json:"online_meeting_link,omitempty"`
	CreatedBy           uuid.UUID         `json:"created_by" gorm:"type:uuid;not null"`
	CreatedAt           string            `json:"created_at,omitempty"`
	UpdatedAt           string            `json:"updated_at,omitempty"`
}
