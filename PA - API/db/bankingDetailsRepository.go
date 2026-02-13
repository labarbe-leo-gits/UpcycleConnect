package db

import (
	"API/models"
	"database/sql"
	"fmt"

	"github.com/google/uuid"
)

func GetBankingDetailsFromDB() ([]models.BankingDetails, error) {

	var bankingDetailsList []models.BankingDetails

	rows, err := Db.Query("SELECT id, user_id, rib, iban, bic, account_holder_name, created_at, updated_at FROM bankingDetails")
	if err != nil {
		return nil, fmt.Errorf("getBankingDetailsFromDB: %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {

		var bankingDetails models.BankingDetails
		var idStr, userIDStr string
		var createdAt, updatedAt string

		err := rows.Scan(&idStr, &userIDStr, &bankingDetails.RIB, &bankingDetails.IBAN, &bankingDetails.BIC, &bankingDetails.HolderName, &createdAt, &updatedAt)
		if err != nil {
			return nil, fmt.Errorf("getBankingDetailsFromDB scan: %s", err.Error())
		}

		bankingDetails.ID, err = uuid.Parse(idStr)
		if err != nil {
			return nil, fmt.Errorf("getBankingDetailsFromDB uuid parse id: %s", err.Error())
		}

		bankingDetails.UserID, err = uuid.Parse(userIDStr)
		if err != nil {
			return nil, fmt.Errorf("getBankingDetailsFromDB uuid parse user_id: %s", err.Error())
		}

		bankingDetails.CreatedAt = createdAt
		bankingDetails.UpdatedAt = updatedAt
		bankingDetailsList = append(bankingDetailsList, bankingDetails)
	}

	err = rows.Err()
	if err != nil {
		return nil, fmt.Errorf("getBankingDetailsFromDB rows: %s", err.Error())
	}

	return bankingDetailsList, nil
}

func GetBankingDetailsByIDFromDB(id uuid.UUID) (models.BankingDetails, error) {

	var bankingDetails models.BankingDetails
	var idStr, userIDStr string

	row := Db.QueryRow("SELECT id, user_id, rib, iban, bic, account_holder_name, created_at, updated_at FROM bankingDetails WHERE id = ?", id.String())
	var createdAt, updatedAt string
	err := row.Scan(&idStr, &userIDStr, &bankingDetails.RIB, &bankingDetails.IBAN, &bankingDetails.BIC, &bankingDetails.HolderName, &createdAt, &updatedAt)

	if err != nil {
		if err == sql.ErrNoRows {
			return bankingDetails, fmt.Errorf("banking details not found")
		}
		return bankingDetails, fmt.Errorf("getBankingDetailsByIDFromDB scan: %s", err.Error())
	}

	bankingDetails.CreatedAt = createdAt
	bankingDetails.UpdatedAt = updatedAt

	bankingDetails.ID, err = uuid.Parse(idStr)
	if err != nil {
		return bankingDetails, fmt.Errorf("getBankingDetailsByIDFromDB uuid parse id: %s", err.Error())
	}

	bankingDetails.UserID, err = uuid.Parse(userIDStr)
	if err != nil {
		return bankingDetails, fmt.Errorf("getBankingDetailsByIDFromDB uuid parse user_id: %s", err.Error())
	}

	return bankingDetails, nil
}

func GetBankingDetailsByUserIDFromDB(userID uuid.UUID) ([]models.BankingDetails, error) {

	var bankingDetailsList []models.BankingDetails

	rows, err := Db.Query("SELECT id, user_id, rib, iban, bic, account_holder_name, created_at, updated_at FROM bankingDetails WHERE user_id = ? ORDER BY created_at DESC", userID.String())
	if err != nil {
		return nil, fmt.Errorf("getBankingDetailsByUserIDFromDB: %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {
		var bankingDetails models.BankingDetails
		var idStr, userIDStr string
		var createdAt, updatedAt string

		err := rows.Scan(&idStr, &userIDStr, &bankingDetails.RIB, &bankingDetails.IBAN, &bankingDetails.BIC, &bankingDetails.HolderName, &createdAt, &updatedAt)
		if err != nil {
			return nil, fmt.Errorf("getBankingDetailsByUserIDFromDB scan: %s", err.Error())
		}

		bankingDetails.ID, err = uuid.Parse(idStr)
		if err != nil {
			return nil, fmt.Errorf("getBankingDetailsByUserIDFromDB uuid parse id: %s", err.Error())
		}

		bankingDetails.UserID, err = uuid.Parse(userIDStr)
		if err != nil {
			return nil, fmt.Errorf("getBankingDetailsByUserIDFromDB uuid parse user_id: %s", err.Error())
		}

		bankingDetails.CreatedAt = createdAt
		bankingDetails.UpdatedAt = updatedAt
		bankingDetailsList = append(bankingDetailsList, bankingDetails)
	}

	if err := rows.Err(); err != nil {
		return nil, fmt.Errorf("getBankingDetailsByUserIDFromDB rows: %s", err.Error())
	}

	return bankingDetailsList, nil
}

func CreateBankingDetailsInDB(bankingDetails models.BankingDetails) error {

	newID := uuid.New()
	currentTime := getCurrentTime()

	_, err := Db.Exec("INSERT INTO bankingDetails (id, user_id, rib, iban, bic, account_holder_name, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
		newID.String(), bankingDetails.UserID.String(), bankingDetails.RIB, bankingDetails.IBAN, bankingDetails.BIC, bankingDetails.HolderName, currentTime, currentTime)

	if err != nil {
		return fmt.Errorf("createBankingDetailsInDB: %s", err.Error())
	}

	return nil
}
