// User model for the API

package models

import "github.com/google/uuid"

type User struct {
	ID               uuid.UUID `json:"id" gorm:"type:uuid;primaryKey"`
	FirstName        string    `json:"first_name"`
	LastName         string    `json:"last_name"`
	Balance          float64   `json:"balance"`
	UpcyclingScore   float64   `json:"upcycling_score"`
	CompanyName      string    `json:"company_name,omitempty"`
	UserType         int       `json:"user_type"`
	Username         string    `json:"username" gorm:"unique;not null"`
	Email            string    `json:"email" gorm:"unique;not null"`
	Password         string    `json:"password,omitempty" gorm:"not null"`
	CreatedAt        string    `json:"created_at,omitempty"`
	LastLogin        string    `json:"last_login,omitempty"`
	OAuthProvider    string    `json:"oauth_provider,omitempty"`
	OAuthID          string    `json:"oauth_id,omitempty"`
	ProfilePicture   string    `json:"profile_picture,omitempty"`
	UserSecret       string    `json:"user_secret,omitempty"`
	TwoFASecret      string    `json:"-"`
	TwoFAEnabled     bool      `json:"twofa_enabled,omitempty"`
	TwoFABackupCodes []string  `json:"-"`
}
