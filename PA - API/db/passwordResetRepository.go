package db

import (
	"database/sql"
	"fmt"
	"time"

	"github.com/google/uuid"
)

type PasswordReset struct {
	ID        string
	UserID    string
	Email     string
	Code      string
	ExpiresAt time.Time
	UsedAt    sql.NullTime
}

func CreateOrUpdatePasswordReset(email string, userID uuid.UUID, code string, expiresAt time.Time) error {
	_, err := Db.Exec("DELETE FROM password_resets WHERE email = ?", email)
	if err != nil {
		return fmt.Errorf("failed to clear existing password reset: %v", err)
	}

	_, err = Db.Exec(
		"INSERT INTO password_resets (id, user_id, email, code, expires_at, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
		uuid.New().String(), userID.String(), email, code, expiresAt.Format("2006-01-02 15:04:05"),
	)
	if err != nil {
		return fmt.Errorf("failed to create password reset record: %v", err)
	}

	return nil
}

func GetPasswordResetByEmailAndCode(email, code string) (*PasswordReset, error) {
	row := Db.QueryRow(
		"SELECT id, user_id, email, code, expires_at, used_at FROM password_resets WHERE email = ? AND code = ? LIMIT 1",
		email, code,
	)

	var reset PasswordReset
	var expiresAt string
	err := row.Scan(&reset.ID, &reset.UserID, &reset.Email, &reset.Code, &expiresAt, &reset.UsedAt)
	if err != nil {
		if err == sql.ErrNoRows {
			return nil, fmt.Errorf("password reset not found")
		}
		return nil, fmt.Errorf("failed to query password reset: %v", err)
	}

	reset.ExpiresAt, err = time.Parse("2006-01-02 15:04:05", expiresAt)
	if err != nil {
		return nil, fmt.Errorf("failed to parse expires_at: %v", err)
	}

	return &reset, nil
}

func MarkPasswordResetUsed(id string) error {
	_, err := Db.Exec("UPDATE password_resets SET used_at = NOW() WHERE id = ?", id)
	if err != nil {
		return fmt.Errorf("failed to mark password reset used: %v", err)
	}
	return nil
}
