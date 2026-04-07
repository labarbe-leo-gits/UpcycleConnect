package models

type PendingRegistration struct {
	ID           string `json:"id"`
	FirstName    string `json:"first_name"`
	LastName     string `json:"last_name"`
	CompanyName  string `json:"company_name,omitempty"`
	Siret        string `json:"siret,omitempty"`
	UserType     int    `json:"user_type"`
	Username     string `json:"username"`
	Email        string `json:"email"`
	PasswordHash string `json:"password_hash"`
	LLMQuota     int    `json:"llm_quota"`
	Token        string `json:"token"`
	ExpiresAt    string `json:"expires_at"`
	CreatedAt    string `json:"created_at"`
}
