package models

import "github.com/google/uuid"

type User struct {
	ID       uuid.UUID `json:"id" gorm:"type:uuid;primaryKey"`
	Username string    `json:"username" gorm:"unique;not null"`
	Password string    `json:"password" gorm:"not null"`
	LastLogin string   `json:"last_login" gorm:"not null"`
}