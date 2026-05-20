package db

import (
	"API/models"
	"errors"

	"github.com/google/uuid"
)

func GetTrainingSessionsFromDB() ([]models.TrainingSession, error) {
	query := `
		SELECT id, event_id, creator_id, title, description, session_type,
		       price_per_person, currency, max_participants, current_participants,
		       is_online, online_link, status, created_at, updated_at
		FROM training_sessions
		ORDER BY created_at DESC
	`

	rows, err := Db.Query(query)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var sessions []models.TrainingSession
	for rows.Next() {
		var session models.TrainingSession
		if err := rows.Scan(
			&session.ID, &session.EventID, &session.CreatorID, &session.Title,
			&session.Description, &session.SessionType, &session.PricePerPerson,
			&session.Currency, &session.MaxParticipants, &session.CurrentParticipants,
			&session.IsOnline, &session.OnlineLink, &session.Status,
			&session.CreatedAt, &session.UpdatedAt,
		); err != nil {
			return nil, err
		}
		sessions = append(sessions, session)
	}

	return sessions, rows.Err()
}

func GetTrainingSessionByIDFromDB(sessionID uuid.UUID) (*models.TrainingSession, error) {
	query := `
		SELECT id, event_id, creator_id, title, description, session_type,
		       price_per_person, currency, max_participants, current_participants,
		       is_online, online_link, status, created_at, updated_at
		FROM training_sessions
		WHERE id = ?
	`

	var session models.TrainingSession
	err := Db.QueryRow(query, sessionID.String()).Scan(
		&session.ID, &session.EventID, &session.CreatorID, &session.Title,
		&session.Description, &session.SessionType, &session.PricePerPerson,
		&session.Currency, &session.MaxParticipants, &session.CurrentParticipants,
		&session.IsOnline, &session.OnlineLink, &session.Status,
		&session.CreatedAt, &session.UpdatedAt,
	)

	if err != nil {
		return nil, err
	}

	return &session, nil
}

func CreateTrainingSessionInDB(session models.TrainingSession) error {
	query := `
		INSERT INTO training_sessions
		(id, event_id, creator_id, title, description, session_type,
		 price_per_person, currency, max_participants, current_participants,
		 is_online, online_link, status, created_at, updated_at)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
	`

	_, err := Db.Exec(query,
		session.ID.String(), session.EventID.String(), session.CreatorID.String(),
		session.Title, session.Description, session.SessionType,
		session.PricePerPerson, session.Currency, session.MaxParticipants,
		session.CurrentParticipants, session.IsOnline, session.OnlineLink, session.Status,
	)

	return err
}

func UpdateTrainingSessionInDB(sessionID uuid.UUID, updates map[string]interface{}) error {
	query := `
		UPDATE training_sessions
		SET title = COALESCE(?, title),
		    description = COALESCE(?, description),
		    price_per_person = COALESCE(?, price_per_person),
		    currency = COALESCE(?, currency),
		    max_participants = COALESCE(?, max_participants),
		    is_online = ?,
		    online_link = COALESCE(?, online_link),
		    status = ?,
		    updated_at = NOW()
		WHERE id = ?
	`

	_, err := Db.Exec(query,
		updates["title"], updates["description"], updates["price_per_person"],
		updates["currency"], updates["max_participants"], updates["is_online"],
		updates["online_link"], updates["status"], sessionID.String(),
	)

	return err
}

func DeleteTrainingSessionInDB(sessionID uuid.UUID) error {
	query := "DELETE FROM training_sessions WHERE id = ?"
	_, err := Db.Exec(query, sessionID.String())
	return err
}

func CreateTrainingSessionRegistrationInDB(registration models.TrainingSessionRegistration) error {
	sessionQuery := `
		SELECT max_participants, current_participants
		FROM training_sessions
		WHERE id = ?
	`

	var maxParticipants *int
	var currentParticipants int
	err := Db.QueryRow(sessionQuery, registration.SessionID.String()).Scan(&maxParticipants, &currentParticipants)
	if err != nil {
		return err
	}

	if maxParticipants != nil && currentParticipants >= *maxParticipants {
		return errors.New("session_full")
	}

	tx, err := Db.Begin()
	if err != nil {
		return err
	}
	defer tx.Rollback()

	query := `
		INSERT INTO training_session_registrations
		(id, session_id, user_id, order_id, amount_paid, status, registered_at, created_at)
		VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
	`

	orderIDStr := ""
	if registration.OrderID != nil {
		orderIDStr = registration.OrderID.String()
	}

	_, err = tx.Exec(query,
		registration.ID.String(), registration.SessionID.String(), registration.UserID.String(),
		orderIDStr, registration.AmountPaid, registration.Status,
	)

	if err != nil {
		return err
	}

	updateQuery := `
		UPDATE training_sessions
		SET current_participants = current_participants + 1
		WHERE id = ?
	`

	_, err = tx.Exec(updateQuery, registration.SessionID.String())
	if err != nil {
		return err
	}

	return tx.Commit()
}

func GetTrainingSessionRegistrationsBySessionFromDB(sessionID uuid.UUID) ([]models.TrainingSessionRegistration, error) {
	query := `
		SELECT id, session_id, user_id, order_id, amount_paid, status,
		       registered_at, attended_at, created_at
		FROM training_session_registrations
		WHERE session_id = ?
		ORDER BY registered_at DESC
	`

	rows, err := Db.Query(query, sessionID.String())
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var registrations []models.TrainingSessionRegistration
	for rows.Next() {
		var reg models.TrainingSessionRegistration
		if err := rows.Scan(
			&reg.ID, &reg.SessionID, &reg.UserID, &reg.OrderID, &reg.AmountPaid,
			&reg.Status, &reg.RegisteredAt, &reg.AttendedAt, &reg.CreatedAt,
		); err != nil {
			return nil, err
		}
		registrations = append(registrations, reg)
	}

	return registrations, rows.Err()
}

func GetTrainingSessionRegistrationsByUserFromDB(userID uuid.UUID) ([]models.TrainingSessionRegistration, error) {
	query := `
		SELECT id, session_id, user_id, order_id, amount_paid, status,
		       registered_at, attended_at, created_at
		FROM training_session_registrations
		WHERE user_id = ?
		ORDER BY registered_at DESC
	`

	rows, err := Db.Query(query, userID.String())
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var registrations []models.TrainingSessionRegistration
	for rows.Next() {
		var reg models.TrainingSessionRegistration
		if err := rows.Scan(
			&reg.ID, &reg.SessionID, &reg.UserID, &reg.OrderID, &reg.AmountPaid,
			&reg.Status, &reg.RegisteredAt, &reg.AttendedAt, &reg.CreatedAt,
		); err != nil {
			return nil, err
		}
		registrations = append(registrations, reg)
	}

	return registrations, rows.Err()
}

func GetAllTrainingSessionRegistrationsFromDB() ([]models.TrainingSessionRegistration, error) {
	query := `
		SELECT id, session_id, user_id, order_id, amount_paid, status,
		       registered_at, attended_at, created_at
		FROM training_session_registrations
		ORDER BY registered_at DESC
	`

	rows, err := Db.Query(query)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var registrations []models.TrainingSessionRegistration
	for rows.Next() {
		var reg models.TrainingSessionRegistration
		if err := rows.Scan(
			&reg.ID, &reg.SessionID, &reg.UserID, &reg.OrderID, &reg.AmountPaid,
			&reg.Status, &reg.RegisteredAt, &reg.AttendedAt, &reg.CreatedAt,
		); err != nil {
			return nil, err
		}
		registrations = append(registrations, reg)
	}

	return registrations, rows.Err()
}

func UpdateTrainingSessionRegistrationStatusInDB(regID uuid.UUID, status int) error {
	query := `
		UPDATE training_session_registrations
		SET status = ?
		WHERE id = ?
	`

	_, err := Db.Exec(query, status, regID.String())
	return err
}

func MarkTrainingSessionAsAttendedInDB(regID uuid.UUID) error {
	query := `
		UPDATE training_session_registrations
		SET attended_at = NOW()
		WHERE id = ?
	`

	_, err := Db.Exec(query, regID.String())
	return err
}
