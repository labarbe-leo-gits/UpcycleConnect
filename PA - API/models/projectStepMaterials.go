package models

import "github.com/google/uuid"

type ProjectStepMaterial struct {
	StepID    uuid.UUID `json:"step_id"`
	FacteurID uuid.UUID `json:"facteur_id"`
	Quantity  *float64  `json:"quantity,omitempty"`
	Nom       string    `json:"nom,omitempty"`
}
