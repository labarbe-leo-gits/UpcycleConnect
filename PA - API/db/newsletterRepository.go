package db

import (
	"API/models"
	"database/sql"
	"fmt"
	"time"

	"github.com/google/uuid"
)

func GetNewslettersFromDB(page, limit int, search string, status *int, senderType *int) ([]models.Newsletter, int, error) {
	var newsletters []models.Newsletter

	query := "SELECT id, title, status, COALESCE(created_by_user_type, 0) AS created_by_user_type, created_at, updated_at FROM newsletter WHERE 1=1"
	var args []interface{}

	if search != "" {
		query += " AND title LIKE ?"
		args = append(args, "%"+search+"%")
	}

	if status != nil {
		query += " AND status = ?"
		args = append(args, *status)
	}

	if senderType != nil {
		query += " AND created_by_user_type = ?"
		args = append(args, *senderType)
	}

	// Get total count - use same query pattern
	countQuery := "SELECT COUNT(*) FROM newsletter WHERE 1=1"
	countArgs := []interface{}{}

	if search != "" {
		countQuery += " AND title LIKE ?"
		countArgs = append(countArgs, "%"+search+"%")
	}

	if status != nil {
		countQuery += " AND status = ?"
		countArgs = append(countArgs, *status)
	}

	if senderType != nil {
		countQuery += " AND created_by_user_type = ?"
		countArgs = append(countArgs, *senderType)
	}

	var totalCount int
	countErr := Db.QueryRow(countQuery, countArgs...).Scan(&totalCount)
	if countErr != nil && countErr != sql.ErrNoRows {
		return nil, 0, fmt.Errorf("failed to count newsletters: %v", countErr)
	}

	// Get paginated results
	offset := (page - 1) * limit
	query += " ORDER BY created_at DESC LIMIT ? OFFSET ?"
	args = append(args, limit, offset)

	rows, err := Db.Query(query, args...)
	if err != nil {
		return nil, 0, fmt.Errorf("failed to query newsletters: %v", err)
	}
	defer rows.Close()

	for rows.Next() {
		var newsletter models.Newsletter
		err := rows.Scan(&newsletter.ID, &newsletter.Title, &newsletter.Status, &newsletter.CreatedByUserType, &newsletter.CreatedAt, &newsletter.UpdatedAt)
		if err != nil {
			return nil, 0, fmt.Errorf("failed to scan newsletter: %v", err)
		}
		newsletters = append(newsletters, newsletter)
	}

	if err = rows.Err(); err != nil {
		return nil, 0, fmt.Errorf("error iterating newsletter rows: %v", err)
	}

	return newsletters, totalCount, nil
}

func GetNewsletterByIDFromDB(id string) (models.Newsletter, error) {
	var newsletter models.Newsletter

	row := Db.QueryRow("SELECT id, title, content, status, COALESCE(created_by_user_type, 0) AS created_by_user_type, created_at, updated_at FROM newsletter WHERE id = ?", id)
	err := row.Scan(&newsletter.ID, &newsletter.Title, &newsletter.Content, &newsletter.Status, &newsletter.CreatedByUserType, &newsletter.CreatedAt, &newsletter.UpdatedAt)

	if err != nil {
		if err == sql.ErrNoRows {
			return newsletter, fmt.Errorf("newsletter not found")
		}
		return newsletter, fmt.Errorf("failed to query newsletter: %v", err)
	}

	return newsletter, nil
}

func CreateNewsletterInDB(title, content string, createdByUserType int) (uuid.UUID, error) {
	newID := uuid.New()
	currentTime := time.Now().UTC().Format("2006-01-02 15:04:05")

	_, err := Db.Exec(
		"INSERT INTO newsletter (id, title, content, status, created_by_user_type, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)",
		newID.String(), title, content, 0, createdByUserType, currentTime, currentTime,
	)

	if err != nil {
		return uuid.Nil, fmt.Errorf("failed to create newsletter: %v", err)
	}

	return newID, nil
}

func UpdateNewsletterInDB(id, title, content string) error {
	currentTime := time.Now().UTC().Format("2006-01-02 15:04:05")

	result, err := Db.Exec(
		"UPDATE newsletter SET title = ?, content = ?, updated_at = ? WHERE id = ?",
		title, content, currentTime, id,
	)

	if err != nil {
		return fmt.Errorf("failed to update newsletter: %v", err)
	}

	rowsAffected, err := result.RowsAffected()
	if err != nil {
		return fmt.Errorf("failed to get rows affected: %v", err)
	}

	if rowsAffected == 0 {
		return fmt.Errorf("newsletter not found")
	}

	return nil
}

func DeleteNewsletterFromDB(id string) error {
	result, err := Db.Exec("DELETE FROM newsletter WHERE id = ?", id)

	if err != nil {
		return fmt.Errorf("failed to delete newsletter: %v", err)
	}

	rowsAffected, err := result.RowsAffected()
	if err != nil {
		return fmt.Errorf("failed to get rows affected: %v", err)
	}

	if rowsAffected == 0 {
		return fmt.Errorf("newsletter not found")
	}

	return nil
}

func UpdateNewsletterStatusFromDB(id string, status int) error {
	currentTime := time.Now().UTC().Format("2006-01-02 15:04:05")

	result, err := Db.Exec(
		"UPDATE newsletter SET status = ?, updated_at = ? WHERE id = ?",
		status, currentTime, id,
	)

	if err != nil {
		return fmt.Errorf("failed to update newsletter status: %v", err)
	}

	rowsAffected, err := result.RowsAffected()
	if err != nil {
		return fmt.Errorf("failed to get rows affected: %v", err)
	}

	if rowsAffected == 0 {
		return fmt.Errorf("newsletter not found")
	}

	return nil
}

func GetNewsletterRecipientsCountFromDB(id string) (int, error) {
	var count int

	row := Db.QueryRow("SELECT COUNT(*) FROM newsletter_recipients WHERE newsletter_id = ?", id)
	err := row.Scan(&count)

	if err != nil {
		return 0, fmt.Errorf("failed to count recipients: %v", err)
	}

	return count, nil
}

func LogNewsletterRecipientFromDB(newsletterID, userID string) error {
	newID := uuid.New()

	_, err := Db.Exec(
		"INSERT INTO newsletter_recipients (id, newsletter_id, user_id, sent_at) VALUES (?, ?, ?, NOW())",
		newID.String(), newsletterID, userID,
	)

	if err != nil {
		// Ignore duplicate key errors (recipient already logged)
		return nil
	}

	return nil
}

func GetSubscribedUsersFromDB() ([]models.User, error) {
	var users []models.User

	rows, err := Db.Query(
		"SELECT id, first_name, email FROM users WHERE is_active = 1 AND newsletter_subscribed = 1 ORDER BY id",
	)
	if err != nil {
		return nil, fmt.Errorf("failed to query subscribed users: %v", err)
	}
	defer rows.Close()

	for rows.Next() {
		var user models.User
		err := rows.Scan(&user.ID, &user.FirstName, &user.Email)
		if err != nil {
			return nil, fmt.Errorf("failed to scan user: %v", err)
		}
		users = append(users, user)
	}

	if err = rows.Err(); err != nil {
		return nil, fmt.Errorf("error iterating user rows: %v", err)
	}

	return users, nil
}
