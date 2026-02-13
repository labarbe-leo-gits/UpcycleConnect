package models

import (
	"github.com/google/uuid"
)

type Conteneur struct {
	ID          uuid.UUID `json:"id" db:"id"`
	Name        string    `json:"name" db:"name"`
	City 	  string    `json:"city" db:"city"`
	Road 	  string    `json:"road" db:"road"`
	PostalCode  int    `json:"postal_code" db:"postal_code"`
	Number	  int	  `json:"number" db:"number"`
	CreatedAt   string    `json:"created_at" db:"created_at"`
	UpdatedAt   string    `json:"updated_at" db:"updated_at"`
}