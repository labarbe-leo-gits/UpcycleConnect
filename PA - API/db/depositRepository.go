package db

import (
	"API/models"
	"fmt"

	"github.com/google/uuid"
)

func GetAllDepositsFromDB() ([]models.Deposit, error) {

	rows, err := Db.Query("SELECT id, user_id, conteneur_id, object_name, object_description, status, created_at, updated_at FROM demandes_depot")
	if err != nil {
		return nil, fmt.Errorf("failed to query deposits: %v", err)
	}

	defer rows.Close()

	var deposits []models.Deposit

	for rows.Next() {
		var deposit models.Deposit
		err := rows.Scan(&deposit.ID, &deposit.UserID, &deposit.ConteneurID, &deposit.ObjectName, &deposit.ObjectDescription, &deposit.Status, &deposit.CreatedAt, &deposit.UpdatedAt)

		if err != nil {
			return nil, fmt.Errorf("failed to scan deposit: %v", err)
		}

		deposits = append(deposits, deposit)
	}

	if err = rows.Err(); err != nil {
		return nil, fmt.Errorf("error iterating over deposit rows: %v", err)
	}

	return deposits, nil
}

func CreateDepositInDB(deposit models.Deposit) (uuid.UUID, error) {

	newID := uuid.New()
	currentTime := getCurrentTime()

	_, err := Db.Exec(
		"INSERT INTO demandes_depot (id, user_id, conteneur_id, object_name, object_description, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
		newID, deposit.UserID, deposit.ConteneurID, deposit.ObjectName, deposit.ObjectDescription, 0, currentTime, currentTime,
	)

	if err != nil {
		return uuid.Nil, fmt.Errorf("failed to insert deposit: %v", err)
	}

	return newID, nil
}

func UpdateDepositStatusInDB(depositIDStr string, status int) error {

	depositID, err := uuid.Parse(depositIDStr)
	if err != nil {
		return fmt.Errorf("invalid deposit ID format: %v", err)
	}

	_, err = Db.Exec(
		"UPDATE demandes_depot SET status = ?, updated_at = ? WHERE id = ?",
		status, getCurrentTime(), depositID,
	)

	if err != nil {
		return fmt.Errorf("failed to update deposit status: %v", err)
	}

	return nil
}

func GetDepositByIDFromDB(depositIDStr string) (models.Deposit, error) {

	var deposit models.Deposit

	row := Db.QueryRow("SELECT id, user_id, conteneur_id, object_name, object_description, status, created_at, updated_at FROM demandes_depot WHERE id = ?", depositIDStr)
	err := row.Scan(&deposit.ID, &deposit.UserID, &deposit.ConteneurID, &deposit.ObjectName, &deposit.ObjectDescription, &deposit.Status, &deposit.CreatedAt, &deposit.UpdatedAt)

	if err != nil {
		return deposit, fmt.Errorf("failed to query deposit by ID: %v", err)
	}

	if err = row.Err(); err != nil {
		return deposit, fmt.Errorf("error scanning deposit row: %v", err)
	}

	deposit.ID, err = uuid.Parse(depositIDStr)
	if err != nil {
		return deposit, fmt.Errorf("invalid deposit ID format: %v", err)
	}

	return deposit, nil
}
