package db

import (
	"API/models"
	"database/sql"
	"fmt"

	"github.com/google/uuid"
)

func GetPlanningFromDB(userID string) ([]models.Planning, error) {
	planning := []models.Planning{}

	rows, err := Db.Query("SELECT id, title, description, start_time, end_time, date, user_id, created_at, updated_at FROM planning WHERE user_id = ? AND start_time >= NOW() AND start_time < DATE_ADD(NOW(), INTERVAL 7 DAY) ORDER BY start_time ASC", userID)
	if err != nil {
		return nil, fmt.Errorf("getPlanningFromDB: %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {
		var entry models.Planning
		var idStr, userIDStr string
		var createdAt, updatedAt sql.NullString
		var description sql.NullString
		var startTime sql.NullString
		var endTime sql.NullString
		var dateVal sql.NullString

		err := rows.Scan(&idStr, &entry.Title, &description, &startTime, &endTime, &dateVal, &userIDStr, &createdAt, &updatedAt)
		if err != nil {
			return nil, fmt.Errorf("getPlanningFromDB scan: %s", err.Error())
		}

		entry.ID, err = uuid.Parse(idStr)
		if err != nil {
			return nil, fmt.Errorf("getPlanningFromDB uuid parse id: %s", err.Error())
		}

		entry.UserID, err = uuid.Parse(userIDStr)
		if err != nil {
			return nil, fmt.Errorf("getPlanningFromDB uuid parse user_id: %s", err.Error())
		}

		if createdAt.Valid {
			entry.CreatedAt = createdAt.String
		}
		if updatedAt.Valid {
			entry.UpdatedAt = updatedAt.String
		}
		if description.Valid {
			entry.Description = description.String
		}
		if startTime.Valid {
			entry.StartTime = startTime.String
		}
		if endTime.Valid {
			entry.EndTime = endTime.String
		}
		if dateVal.Valid {
			entry.Date = dateVal.String
		}

		planning = append(planning, entry)
	}

	if err = rows.Err(); err != nil {
		return nil, fmt.Errorf("getPlanningFromDB rows: %s", err.Error())
	}

	return planning, nil
}

func GetPlanningFromDBWithRange(userID string, limit int, offset int, start string, end string) ([]models.Planning, error) {
	planning := []models.Planning{}

	var rows *sql.Rows
	var err error

	if start != "" && end != "" {
		rows, err = Db.Query("SELECT id, title, description, start_time, end_time, date, user_id, created_at, updated_at FROM planning WHERE user_id = ? AND start_time >= ? AND start_time < ? ORDER BY start_time ASC LIMIT ? OFFSET ?", userID, start, end, limit, offset)
	} else if start != "" {
		rows, err = Db.Query("SELECT id, title, description, start_time, end_time, date, user_id, created_at, updated_at FROM planning WHERE user_id = ? AND start_time >= ? ORDER BY start_time ASC LIMIT ? OFFSET ?", userID, start, limit, offset)
	} else if end != "" {
		rows, err = Db.Query("SELECT id, title, description, start_time, end_time, date, user_id, created_at, updated_at FROM planning WHERE user_id = ? AND start_time < ? ORDER BY start_time ASC LIMIT ? OFFSET ?", userID, end, limit, offset)
	} else {
		rows, err = Db.Query("SELECT id, title, description, start_time, end_time, date, user_id, created_at, updated_at FROM planning WHERE user_id = ? AND start_time >= NOW() AND start_time < DATE_ADD(NOW(), INTERVAL 7 DAY) ORDER BY start_time ASC LIMIT ? OFFSET ?", userID, limit, offset)
	}

	if err != nil {
		return nil, fmt.Errorf("getPlanningFromDBWithRange: %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {
		var entry models.Planning
		var idStr, userIDStr string
		var createdAt, updatedAt sql.NullString
		var description sql.NullString
		var startTime sql.NullString
		var endTime sql.NullString
		var dateVal sql.NullString

		err := rows.Scan(&idStr, &entry.Title, &description, &startTime, &endTime, &dateVal, &userIDStr, &createdAt, &updatedAt)
		if err != nil {
			return nil, fmt.Errorf("getPlanningFromDBWithRange scan: %s", err.Error())
		}

		entry.ID, err = uuid.Parse(idStr)
		if err != nil {
			return nil, fmt.Errorf("getPlanningFromDBWithRange uuid parse id: %s", err.Error())
		}

		entry.UserID, err = uuid.Parse(userIDStr)
		if err != nil {
			return nil, fmt.Errorf("getPlanningFromDBWithRange uuid parse user_id: %s", err.Error())
		}

		if createdAt.Valid {
			entry.CreatedAt = createdAt.String
		}
		if updatedAt.Valid {
			entry.UpdatedAt = updatedAt.String
		}
		if description.Valid {
			entry.Description = description.String
		}
		if startTime.Valid {
			entry.StartTime = startTime.String
		}
		if endTime.Valid {
			entry.EndTime = endTime.String
		}
		if dateVal.Valid {
			entry.Date = dateVal.String
		}

		planning = append(planning, entry)
	}

	if err = rows.Err(); err != nil {
		return nil, fmt.Errorf("getPlanningFromDBWithRange rows: %s", err.Error())
	}

	return planning, nil
}

func CountPlanningFromDB(userID string, start string, end string) (int, error) {
	var count int

	var err error
	if start != "" && end != "" {
		err = Db.QueryRow("SELECT COUNT(*) FROM planning WHERE user_id = ? AND start_time >= ? AND start_time < ?", userID, start, end).Scan(&count)
	} else if start != "" {
		err = Db.QueryRow("SELECT COUNT(*) FROM planning WHERE user_id = ? AND start_time >= ?", userID, start).Scan(&count)
	} else if end != "" {
		err = Db.QueryRow("SELECT COUNT(*) FROM planning WHERE user_id = ? AND start_time < ?", userID, end).Scan(&count)
	} else {
		err = Db.QueryRow("SELECT COUNT(*) FROM planning WHERE user_id = ? AND start_time >= NOW() AND start_time < DATE_ADD(NOW(), INTERVAL 7 DAY)", userID).Scan(&count)
	}

	if err != nil {
		return 0, fmt.Errorf("countPlanningFromDB: %s", err.Error())
	}

	return count, nil
}

func CreatePlanningInDB(entry models.Planning) error {

	currentTime := getCurrentTime()
	entry.CreatedAt = currentTime
	entry.UpdatedAt = currentTime

	_, err := Db.Exec("INSERT INTO planning (id, title, description, start_time, end_time, date, user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
		entry.ID.String(), entry.Title, entry.Description, entry.StartTime, entry.EndTime, entry.Date, entry.UserID.String(), entry.CreatedAt, entry.UpdatedAt)

	if err != nil {
		return fmt.Errorf("createPlanningInDB: %s", err.Error())
	}

	return nil
}

func GetAllPlanningFromDB() ([]models.Planning, error) {
	planning := []models.Planning{}

	rows, err := Db.Query("SELECT id, title, description, start_time, end_time, date, user_id, created_at, updated_at FROM planning ORDER BY start_time ASC")
	if err != nil {
		return nil, fmt.Errorf("getAllPlanningFromDB: %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {
		var entry models.Planning
		var idStr, userIDStr string
		var createdAt, updatedAt sql.NullString
		var description sql.NullString
		var startTime sql.NullString
		var endTime sql.NullString
		var dateVal sql.NullString

		err := rows.Scan(&idStr, &entry.Title, &description, &startTime, &endTime, &dateVal, &userIDStr, &createdAt, &updatedAt)
		if err != nil {
			return nil, fmt.Errorf("getAllPlanningFromDB scan: %s", err.Error())
		}

		entry.ID, err = uuid.Parse(idStr)
		if err != nil {
			return nil, fmt.Errorf("getAllPlanningFromDB uuid parse id: %s", err.Error())
		}

		entry.UserID, err = uuid.Parse(userIDStr)
		if err != nil {
			return nil, fmt.Errorf("getAllPlanningFromDB uuid parse user_id: %s", err.Error())
		}

		if createdAt.Valid {
			entry.CreatedAt = createdAt.String
		}

		if updatedAt.Valid {
			entry.UpdatedAt = updatedAt.String
		}

		if description.Valid {
			entry.Description = description.String
		}

		if startTime.Valid {
			entry.StartTime = startTime.String
		}

		if endTime.Valid {
			entry.EndTime = endTime.String
		}

		if dateVal.Valid {
			entry.Date = dateVal.String
		}

		planning = append(planning, entry)
	}

	if err = rows.Err(); err != nil {
		return nil, fmt.Errorf("getAllPlanningFromDB rows: %s", err.Error())
	}

	return planning, nil
}

func DeletePlanningFromDB(planningID string, userUUID uuid.UUID) error {

	result, err := Db.Exec("DELETE FROM planning WHERE id = ? AND user_id = ?", planningID, userUUID.String())
	if err != nil {
		return fmt.Errorf("deletePlanningFromDB: %s", err.Error())
	}

	rowsAffected, err := result.RowsAffected()
	if err != nil {
		return fmt.Errorf("deletePlanningFromDB rows affected: %s", err.Error())
	}

	if rowsAffected == 0 {
		return fmt.Errorf("deletePlanningFromDB: no planning entry found with id %s for user %s", planningID, userUUID.String())
	}

	return nil
}

func GetPlanningEntryByID(planningID string) (models.Planning, error) {
	var entry models.Planning
	var idStr, userIDStr string

	row := Db.QueryRow("SELECT id, title, description, start_time, end_time, date, user_id, created_at, updated_at FROM planning WHERE id = ?", planningID)

	var createdAt, updatedAt sql.NullString
	var description sql.NullString
	var startTime sql.NullString
	var endTime sql.NullString
	var dateVal sql.NullString

	err := row.Scan(&idStr, &entry.Title, &description, &startTime, &endTime, &dateVal, &userIDStr, &createdAt, &updatedAt)

	if err != nil {
		if err == sql.ErrNoRows {
			return entry, fmt.Errorf("getPlanningEntryByID: no planning entry found with id %s", planningID)
		}

		return entry, fmt.Errorf("getPlanningEntryByID scan: %s", err.Error())
	}

	entry.ID, err = uuid.Parse(idStr)
	if err != nil {
		return entry, fmt.Errorf("getPlanningEntryByID uuid parse id: %s", err.Error())
	}

	entry.UserID, err = uuid.Parse(userIDStr)
	if err != nil {
		return entry, fmt.Errorf("getPlanningEntryByID uuid parse user_id: %s", err.Error())
	}

	if createdAt.Valid {
		entry.CreatedAt = createdAt.String
	}

	if updatedAt.Valid {
		entry.UpdatedAt = updatedAt.String
	}

	if description.Valid {
		entry.Description = description.String
	}

	if startTime.Valid {
		entry.StartTime = startTime.String
	}

	if endTime.Valid {
		entry.EndTime = endTime.String
	}

	if dateVal.Valid {
		entry.Date = dateVal.String
	}

	return entry, nil
}

func UpdatePlanningInDB(entry models.Planning) error {

	currentTime := getCurrentTime()
	entry.UpdatedAt = currentTime

	result, err := Db.Exec("UPDATE planning SET title = ?, description = ?, start_time = ?, end_time = ?, date = ?, updated_at = ? WHERE id = ?",
		entry.Title, entry.Description, entry.StartTime, entry.EndTime, entry.Date, entry.UpdatedAt, entry.ID.String())

	if err != nil {
		return fmt.Errorf("updatePlanningInDB: %s", err.Error())
	}

	rowsAffected, err := result.RowsAffected()
	if err != nil {
		return fmt.Errorf("updatePlanningInDB rows affected: %s", err.Error())
	}

	if rowsAffected == 0 {
		return fmt.Errorf("updatePlanningInDB: no planning entry found with id %s", entry.ID.String())
	}

	return nil
}
