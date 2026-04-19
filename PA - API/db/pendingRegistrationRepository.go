package db

import (
	"API/models"
	"database/sql"
	"fmt"
	"strings"

	"github.com/google/uuid"
)

func GetPendingRegistrationByIdentifier(identifier string) (*models.PendingRegistration, error) {
	row := Db.QueryRow(
		"SELECT id, first_name, last_name, company_name, siret, user_type, username, email, password_hash, llm_quota, token, expires_at, created_at FROM pending_registrations WHERE username = ? OR email = ? LIMIT 1",
		identifier,
		identifier,
	)

	var registration models.PendingRegistration
	var companyName sql.NullString
	var siret sql.NullString
	err := row.Scan(
		&registration.ID,
		&registration.FirstName,
		&registration.LastName,
		&companyName,
		&siret,
		&registration.UserType,
		&registration.Username,
		&registration.Email,
		&registration.PasswordHash,
		&registration.LLMQuota,
		&registration.Token,
		&registration.ExpiresAt,
		&registration.CreatedAt,
	)
	if err != nil {
		if err == sql.ErrNoRows {
			return nil, nil
		}
		return nil, fmt.Errorf("getPendingRegistrationByIdentifier: %s", err.Error())
	}

	if companyName.Valid {
		registration.CompanyName = companyName.String
	}
	if siret.Valid {
		registration.Siret = siret.String
	}

	return &registration, nil
}

func GetPendingRegistrationByID(id string) (*models.PendingRegistration, error) {
	row := Db.QueryRow(
		"SELECT id, first_name, last_name, company_name, siret, user_type, username, email, password_hash, llm_quota, token, expires_at, created_at FROM pending_registrations WHERE id = ? LIMIT 1",
		id,
	)

	var registration models.PendingRegistration
	var companyName sql.NullString
	var siret sql.NullString
	err := row.Scan(
		&registration.ID,
		&registration.FirstName,
		&registration.LastName,
		&companyName,
		&siret,
		&registration.UserType,
		&registration.Username,
		&registration.Email,
		&registration.PasswordHash,
		&registration.LLMQuota,
		&registration.Token,
		&registration.ExpiresAt,
		&registration.CreatedAt,
	)
	if err != nil {
		if err == sql.ErrNoRows {
			return nil, nil
		}
		return nil, fmt.Errorf("getPendingRegistrationByID: %s", err.Error())
	}

	if companyName.Valid {
		registration.CompanyName = companyName.String
	}
	if siret.Valid {
		registration.Siret = siret.String
	}

	return &registration, nil
}

func CreatePendingRegistration(p models.PendingRegistration) (string, error) {
	if p.ID == "" {
		p.ID = uuid.New().String()
	}

	_, err := Db.Exec(
		"INSERT INTO pending_registrations (id, first_name, last_name, company_name, siret, user_type, username, email, password_hash, llm_quota, token, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
		p.ID,
		p.FirstName,
		p.LastName,
		sql.NullString{String: strings.TrimSpace(p.CompanyName), Valid: strings.TrimSpace(p.CompanyName) != ""},
		sql.NullString{String: strings.TrimSpace(p.Siret), Valid: strings.TrimSpace(p.Siret) != ""},
		p.UserType,
		p.Username,
		p.Email,
		p.PasswordHash,
		p.LLMQuota,
		p.Token,
		p.ExpiresAt,
	)
	if err != nil {
		return "", fmt.Errorf("createPendingRegistration: %s", err.Error())
	}

	return p.ID, nil
}

func UpdatePendingRegistrationToken(id string, token string, expiresAt string) error {
	_, err := Db.Exec("UPDATE pending_registrations SET token = ?, expires_at = ? WHERE id = ?", token, expiresAt, id)
	if err != nil {
		return fmt.Errorf("updatePendingRegistrationToken: %s", err.Error())
	}
	return nil
}

func DeletePendingRegistration(id string) error {
	_, err := Db.Exec("DELETE FROM pending_registrations WHERE id = ?", id)
	if err != nil {
		return fmt.Errorf("deletePendingRegistration: %s", err.Error())
	}
	return nil
}

func CreateUserFromPendingRegistration(p models.PendingRegistration) (string, error) {
	userID := uuid.New().String()
	companyName := sql.NullString{String: strings.TrimSpace(p.CompanyName), Valid: strings.TrimSpace(p.CompanyName) != ""}
	siret := sql.NullString{String: strings.TrimSpace(p.Siret), Valid: strings.TrimSpace(p.Siret) != ""}

	_, err := Db.Exec(
		"INSERT INTO users (id, first_name, last_name, company_name, siret, user_type, username, email, password_hash, LLM_quota) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
		userID,
		p.FirstName,
		p.LastName,
		companyName,
		siret,
		p.UserType,
		p.Username,
		p.Email,
		p.PasswordHash,
		p.LLMQuota,
	)
	if err != nil {
		return "", fmt.Errorf("createUserFromPendingRegistration: %s", err.Error())
	}

	return userID, nil
}
