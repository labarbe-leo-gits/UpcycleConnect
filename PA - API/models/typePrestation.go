package models

import "github.com/google/uuid"

type TypePrestation struct {
	ID          uuid.UUID `json:"id" gorm:"type:uuid;primaryKey"`
	Name 	  string    `json:"name" gorm:"unique;not null"`
	CreatedAt   string    `json:"created_at"`
}