package models

import "github.com/google/uuid"

type Tip struct {
	ID		uuid.UUID `json:"id" gorm:"type:uuid;primaryKey"`
}