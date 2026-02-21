package models

import "github.com/google/uuid"

type FacteurMateriaux struct {
	ID             uuid.UUID `json:"id" gorm:"type:uuid;primaryKey"`
	Nom            string    `json:"nom"`
	FacteurCO2     float64   `json:"facteur_co2"`
	FacteurEnergie float64   `json:"facteur_energie"`
}
