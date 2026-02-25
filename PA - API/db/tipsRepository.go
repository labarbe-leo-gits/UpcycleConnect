package db

import (
	"API/models"
	"fmt"

	"github.com/google/uuid"
)

func GetTipsFromDB() ([]models.Tip, error) {

	rows, err := Db.Query("SELECT id, title, description, created_by, updated_by, created_at, updated_at FROM conseils")
	if err != nil {
		return nil, fmt.Errorf("failed to query tips: %v", err)
	}

	defer rows.Close()

	var tips []models.Tip

	for rows.Next() {
		var tip models.Tip
		var createdByStr, updatedByStr string
		err := rows.Scan(&tip.ID, &tip.Title, &tip.Description, &createdByStr, &updatedByStr, &tip.CreatedAt, &tip.UpdatedAt)
		if err != nil {
			return nil, fmt.Errorf("failed to scan tip: %v", err)
		}
		if tip.CreatedBy, err = uuid.Parse(createdByStr); err != nil {
			return nil, fmt.Errorf("failed to parse created_by: %v", err)
		}
		if updatedByStr != "" {
			if tip.UpdatedBy, err = uuid.Parse(updatedByStr); err != nil {
				return nil, fmt.Errorf("failed to parse updated_by: %v", err)
			}
		}

		tips = append(tips, tip)
	}

	if err = rows.Err(); err != nil {
		return nil, fmt.Errorf("error iterating over tip rows: %v", err)
	}

	return tips, nil
}

func CreateTipInDB(tip models.Tip) (uuid.UUID, error) {

	newID := uuid.New()
	currentTIme := getCurrentTime()

	updatedBy := tip.UpdatedBy
	if updatedBy == uuid.Nil {
		updatedBy = tip.CreatedBy
	}
	_, err := Db.Exec(
		"INSERT INTO conseils (id, title, description, created_by, updated_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)",
		newID, tip.Title, tip.Description, tip.CreatedBy, updatedBy, currentTIme, currentTIme,
	)

	if err != nil {
		return uuid.Nil, fmt.Errorf("failed to insert tip: %v", err)
	}

	return newID, nil
}

func GetTipByIDFromDB(tipIDStr string) (models.Tip, error) {

	var tip models.Tip

	var createdByStr, updatedByStr string
	row := Db.QueryRow("SELECT id, title, description, created_by, updated_by, created_at, updated_at FROM conseils WHERE id = ?", tipIDStr)
	err := row.Scan(&tip.ID, &tip.Title, &tip.Description, &createdByStr, &updatedByStr, &tip.CreatedAt, &tip.UpdatedAt)

	if err != nil {
		return tip, fmt.Errorf("failed to query tip by ID: %v", err)
	}

	if err = row.Err(); err != nil {
		return tip, fmt.Errorf("error iterating over tip rows: %v", err)
	}

	if tip.CreatedBy, err = uuid.Parse(createdByStr); err != nil {
		return tip, fmt.Errorf("invalid created_by UUID: %v", err)
	}
	if updatedByStr != "" {
		if tip.UpdatedBy, err = uuid.Parse(updatedByStr); err != nil {
			return tip, fmt.Errorf("invalid updated_by UUID: %v", err)
		}
	}

	return tip, nil
}

func UpdateTipInDB(tipIDStr uuid.UUID, tip models.Tip) error {

	tipID, err := uuid.Parse(tipIDStr.String())
	if err != nil {
		return fmt.Errorf("invalid tip ID format: %v", err)
	}

	old_tip, err := GetTipByIDFromDB(tipIDStr.String())

	if err != nil {
		return fmt.Errorf("failed to get existing tip: %v", err)
	}

	if tip.Title == "" {
		tip.Title = old_tip.Title
	}

	if tip.Description == "" {
		tip.Description = old_tip.Description
	}

	currentTIme := getCurrentTime()

	_, err = Db.Exec(
		"UPDATE conseils SET title = ?, description = ?, updated_by = ?, updated_at = ? WHERE id = ?",
		tip.Title, tip.Description, tip.UpdatedBy, currentTIme, tipID,
	)

	if err != nil {
		return fmt.Errorf("failed to update tip: %v", err)
	}

	return nil
}

func DeleteTipFromDB(tipIDStr uuid.UUID) error {

	tipID, err := uuid.Parse(tipIDStr.String())

	if err != nil {
		return fmt.Errorf("invalid tip ID format: %v", err)
	}

	_, err = Db.Exec("DELETE FROM conseils WHERE id = ?", tipID)

	if err != nil {
		return fmt.Errorf("failed to delete tip: %v", err)
	}

	return nil
}
