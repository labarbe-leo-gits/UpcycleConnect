package db

import (
	"API/models"
	"database/sql"
	"fmt"
	"strings"

	"github.com/google/uuid"
)

func generateDepositBarcode() string {
	return strings.ToUpper("UPC-" + strings.ReplaceAll(uuid.New().String(), "-", "")[:16])
}

func GetAllDepositsFromDB() ([]models.Deposit, error) {

	rows, err := Db.Query("SELECT id, user_id, conteneur_id, object_name, object_description, status, barcode, created_at, updated_at FROM demandes_depot")
	if err != nil {
		return nil, fmt.Errorf("failed to query deposits: %v", err)
	}

	defer rows.Close()

	var deposits []models.Deposit

	for rows.Next() {
		var deposit models.Deposit
		var barcode sql.NullString
		err := rows.Scan(&deposit.ID, &deposit.UserID, &deposit.ConteneurID, &deposit.ObjectName, &deposit.ObjectDescription, &deposit.Status, &barcode, &deposit.CreatedAt, &deposit.UpdatedAt)

		if err != nil {
			return nil, fmt.Errorf("failed to scan deposit: %v", err)
		}
		if barcode.Valid {
			deposit.Barcode = barcode.String
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

	var barcodeValue interface{} = nil
	if strings.TrimSpace(deposit.Barcode) != "" {
		barcodeValue = deposit.Barcode
	}

	_, err := Db.Exec(
		"INSERT INTO demandes_depot (id, user_id, conteneur_id, object_name, object_description, status, barcode, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
		newID, deposit.UserID, deposit.ConteneurID, deposit.ObjectName, deposit.ObjectDescription, 0, barcodeValue, currentTime, currentTime,
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

	currentTime := getCurrentTime()
	if status == 1 {
		barcode := generateDepositBarcode()
		_, err = Db.Exec(
			"UPDATE demandes_depot SET status = ?, barcode = COALESCE(NULLIF(barcode, ''), ?), updated_at = ? WHERE id = ?",
			status, barcode, currentTime, depositID,
		)
	} else {
		_, err = Db.Exec(
			"UPDATE demandes_depot SET status = ?, updated_at = ? WHERE id = ?",
			status, currentTime, depositID,
		)
	}

	if err != nil {
		return fmt.Errorf("failed to update deposit status: %v", err)
	}

	return nil
}

func GetDepositByIDFromDB(depositIDStr string) (models.Deposit, error) {

	var deposit models.Deposit

	row := Db.QueryRow("SELECT id, user_id, conteneur_id, object_name, object_description, status, barcode, created_at, updated_at FROM demandes_depot WHERE id = ?", depositIDStr)
	var barcode sql.NullString
	err := row.Scan(&deposit.ID, &deposit.UserID, &deposit.ConteneurID, &deposit.ObjectName, &deposit.ObjectDescription, &deposit.Status, &barcode, &deposit.CreatedAt, &deposit.UpdatedAt)
	if err == nil && barcode.Valid {
		deposit.Barcode = barcode.String
	}

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

func GetDepositsByConteneurIDFromDB(conteneurIDStr string) ([]models.Deposit, error) {
	rows, err := Db.Query(
		"SELECT id, user_id, conteneur_id, object_name, object_description, status, barcode, created_at, updated_at FROM demandes_depot WHERE conteneur_id = ?",
		conteneurIDStr,
	)
	if err != nil {
		return nil, fmt.Errorf("getDepositsByConteneurIDFromDB query error: %v", err)
	}
	defer rows.Close()

	var deposits []models.Deposit
	for rows.Next() {
		var d models.Deposit
		var barcode sql.NullString
		if err := rows.Scan(&d.ID, &d.UserID, &d.ConteneurID, &d.ObjectName, &d.ObjectDescription, &d.Status, &barcode, &d.CreatedAt, &d.UpdatedAt); err != nil {
			return nil, fmt.Errorf("getDepositsByConteneurIDFromDB scan error: %v", err)
		}
		if barcode.Valid {
			d.Barcode = barcode.String
		}
		deposits = append(deposits, d)
	}
	if err = rows.Err(); err != nil {
		return nil, fmt.Errorf("getDepositsByConteneurIDFromDB rows error: %v", err)
	}
	return deposits, nil
}

func CreateDepositFileInDB(file models.DepositFile) (uuid.UUID, error) {
	newID := uuid.New()
	currentTime := getCurrentTime()
	_, err := Db.Exec(
		"INSERT INTO demandes_depot_files (id, deposit_id, filename, original_name, created_at) VALUES (?, ?, ?, ?, ?)",
		newID, file.DepositID, file.Filename, file.OriginalName, currentTime,
	)
	if err != nil {
		return uuid.Nil, fmt.Errorf("failed to insert deposit file: %v", err)
	}
	return newID, nil
}

func GetDepositFilesByDepositIDFromDB(depositIDStr string) ([]models.DepositFile, error) {
	rows, err := Db.Query(
		"SELECT id, deposit_id, filename, original_name, created_at FROM demandes_depot_files WHERE deposit_id = ? ORDER BY created_at ASC",
		depositIDStr,
	)
	if err != nil {
		return nil, fmt.Errorf("getDepositFilesByDepositIDFromDB query error: %v", err)
	}
	defer rows.Close()

	var files []models.DepositFile
	for rows.Next() {
		var f models.DepositFile
		if err := rows.Scan(&f.ID, &f.DepositID, &f.Filename, &f.OriginalName, &f.CreatedAt); err != nil {
			return nil, fmt.Errorf("getDepositFilesByDepositIDFromDB scan error: %v", err)
		}
		files = append(files, f)
	}
	if err = rows.Err(); err != nil {
		return nil, fmt.Errorf("getDepositFilesByDepositIDFromDB rows error: %v", err)
	}
	if files == nil {
		files = []models.DepositFile{}
	}
	return files, nil
}
