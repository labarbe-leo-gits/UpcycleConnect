package models

import "github.com/google/uuid"

type Deposit struct {
	ID                uuid.UUID `json:"id" db:"id"`
	UserID            uuid.UUID `json:"user_id" db:"user_id"`
	ConteneurID       uuid.UUID `json:"conteneur_id" db:"conteneur_id"`
	ObjectName        string    `json:"object_name" db:"object_name"`
	ObjectDescription string    `json:"object_description" db:"object_description"`
	ObjectState       int       `json:"object_state,omitempty" db:"object_state"`
	Status            int       `json:"status" db:"status"`
	Barcode           string    `json:"barcode,omitempty" db:"barcode"`
	RetrievalCode     string    `json:"retrieval_code,omitempty" db:"retrieval_code"`
	CreatedAt         string    `json:"created_at" db:"created_at"`
	UpdatedAt         string    `json:"updated_at" db:"updated_at"`
}

type UpdateDepositStatusDto struct {
	Status int    `json:"status"`
	ID     string `json:"id,omitempty"`
}

type DepositFile struct {
	ID           uuid.UUID `json:"id" db:"id"`
	DepositID    uuid.UUID `json:"deposit_id" db:"deposit_id"`
	Filename     string    `json:"filename" db:"filename"`
	OriginalName string    `json:"original_name" db:"original_name"`
	CreatedAt    string    `json:"created_at" db:"created_at"`
}

type DepositFileInput struct {
	Filename     string `json:"filename"`
	OriginalName string `json:"original_name"`
}

type ConteneurItem struct {
	Deposit
	Files []DepositFile `json:"files"`
}
