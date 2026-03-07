package db

import (
	"API/models"
	"fmt"

	"github.com/google/uuid"
)

func GetAllConteneursFromDB() ([]models.Conteneur, error) {

	rows, err := Db.Query("SELECT id, conteneur_name, conteneur_city, conteneur_road, conteneur_zip_code, conteneur_number, created_at, updated_at FROM conteneurs")
	if err != nil {
		return nil, fmt.Errorf("failed to query conteneurs: %v", err)
	}

	defer rows.Close()

	var conteneurs []models.Conteneur

	for rows.Next() {
		var conteneur models.Conteneur
		err := rows.Scan(&conteneur.ID, &conteneur.Name, &conteneur.City, &conteneur.Road, &conteneur.PostalCode, &conteneur.Number, &conteneur.CreatedAt, &conteneur.UpdatedAt)
		if err != nil {
			return nil, fmt.Errorf("failed to scan conteneur: %v", err)
		}

		conteneurs = append(conteneurs, conteneur)
	}

	if err = rows.Err(); err != nil {
		return nil, fmt.Errorf("error iterating over conteneur rows: %v", err)
	}

	if conteneurs == nil {
		conteneurs = []models.Conteneur{}
	}

	return conteneurs, nil
}

func CountConteneursFromDB() (int, error) {
	var total int
	err := Db.QueryRow("SELECT COUNT(*) FROM conteneurs").Scan(&total)
	if err != nil {
		return 0, fmt.Errorf("countConteneursFromDB: %s", err.Error())
	}
	return total, nil
}

func GetConteneursPageFromDB(limit int, offset int) ([]models.Conteneur, error) {
	rows, err := Db.Query("SELECT id, conteneur_name, conteneur_city, conteneur_road, conteneur_zip_code, conteneur_number, created_at, updated_at FROM conteneurs ORDER BY created_at DESC LIMIT ? OFFSET ?", limit, offset)
	if err != nil {
		return nil, fmt.Errorf("getConteneursPageFromDB query error: %s", err.Error())
	}
	defer rows.Close()

	var conteneurs []models.Conteneur
	for rows.Next() {
		var conteneur models.Conteneur
		err := rows.Scan(&conteneur.ID, &conteneur.Name, &conteneur.City, &conteneur.Road, &conteneur.PostalCode, &conteneur.Number, &conteneur.CreatedAt, &conteneur.UpdatedAt)
		if err != nil {
			return nil, fmt.Errorf("getConteneursPageFromDB scan error: %s", err.Error())
		}
		conteneurs = append(conteneurs, conteneur)
	}

	if err = rows.Err(); err != nil {
		return nil, fmt.Errorf("getConteneursPageFromDB rows error: %s", err.Error())
	}

	return conteneurs, nil
}

func CreateConteneurInDB(conteneur models.Conteneur) (uuid.UUID, error) {

	newID := uuid.New()
	currentTIme := getCurrentTime()

	_, err := Db.Exec(
		"INSERT INTO conteneurs (id, conteneur_name, conteneur_city, conteneur_road, conteneur_zip_code, conteneur_number, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
		newID, conteneur.Name, conteneur.City, conteneur.Road, conteneur.PostalCode, conteneur.Number, currentTIme, currentTIme,
	)

	if err != nil {
		return uuid.Nil, fmt.Errorf("failed to insert conteneur: %v", err)
	}

	return newID, nil
}

func GetConteneurByIDFromDB(conteneurIDStr string) (models.Conteneur, error) {

	var conteneur models.Conteneur

	row := Db.QueryRow("SELECT id, conteneur_name, conteneur_city, conteneur_road, conteneur_zip_code, conteneur_number, created_at, updated_at FROM conteneurs WHERE id = ?", conteneurIDStr)
	err := row.Scan(&conteneur.ID, &conteneur.Name, &conteneur.City, &conteneur.Road, &conteneur.PostalCode, &conteneur.Number, &conteneur.CreatedAt, &conteneur.UpdatedAt)
	if err != nil {
		return conteneur, fmt.Errorf("failed to query conteneur by ID: %v", err)
	}

	if err = row.Err(); err != nil {
		return conteneur, fmt.Errorf("error scanning conteneur row: %v", err)
	}

	conteneur.ID, err = uuid.Parse(conteneurIDStr)
	if err != nil {
		return conteneur, fmt.Errorf("invalid conteneur ID format: %v", err)
	}

	return conteneur, nil

}

func UpdateConteneurInDB(conteneurIDStr string, conteneur models.Conteneur) error {

	conteneurID, err := uuid.Parse(conteneurIDStr)
	old_Conteneur, err := GetConteneurByIDFromDB(conteneurIDStr)
	if err != nil {
		return fmt.Errorf("failed to get conteneur by ID: %v", err)
	}

	if conteneur.Name == "" {
		conteneur.Name = old_Conteneur.Name
	}

	if conteneur.City == "" {
		conteneur.City = old_Conteneur.City
	}

	if conteneur.Road == "" {
		conteneur.Road = old_Conteneur.Road
	}

	if conteneur.PostalCode == "" {
		conteneur.PostalCode = old_Conteneur.PostalCode
	}

	if conteneur.Number == "" {
		conteneur.Number = old_Conteneur.Number
	}

	_, err = Db.Exec(
		"UPDATE conteneurs SET conteneur_name = ?, conteneur_city = ?, conteneur_road = ?, conteneur_zip_code = ?, conteneur_number = ?, updated_at = ? WHERE id = ?",
		conteneur.Name, conteneur.City, conteneur.Road, conteneur.PostalCode, conteneur.Number, getCurrentTime(), conteneurID,
	)

	if err != nil {
		return fmt.Errorf("failed to update conteneur: %v", err)
	}

	return nil

}

func DeleteConteneurFromDB(conteneurIDStr string) error {

	conteneurID, err := uuid.Parse(conteneurIDStr)

	_, err = Db.Exec("DELETE FROM conteneurs WHERE id = ?", conteneurID)

	if err != nil {
		return fmt.Errorf("failed to delete conteneur: %v", err)
	}

	return nil
}

func GetItemsByConteneurIDFromDB(conteneurIDStr string) ([]models.ConteneurItem, error) {
	rows, err := Db.Query(
		`SELECT id, user_id, conteneur_id, object_name, object_description, status, created_at, updated_at
		 FROM demandes_depot
		 WHERE conteneur_id = ? AND status = 1
		 ORDER BY created_at ASC`,
		conteneurIDStr,
	)
	if err != nil {
		return nil, fmt.Errorf("GetItemsByConteneurIDFromDB query error: %v", err)
	}
	defer rows.Close()

	var items []models.ConteneurItem
	for rows.Next() {
		var item models.ConteneurItem
		if err := rows.Scan(
			&item.ID, &item.UserID, &item.ConteneurID,
			&item.ObjectName, &item.ObjectDescription,
			&item.Status, &item.CreatedAt, &item.UpdatedAt,
		); err != nil {
			return nil, fmt.Errorf("GetItemsByConteneurIDFromDB scan error: %v", err)
		}
		files, err := GetDepositFilesByDepositIDFromDB(item.ID.String())
		if err != nil {
			return nil, fmt.Errorf("GetItemsByConteneurIDFromDB files error: %v", err)
		}
		item.Files = files
		items = append(items, item)
	}
	if err = rows.Err(); err != nil {
		return nil, fmt.Errorf("GetItemsByConteneurIDFromDB rows error: %v", err)
	}
	if items == nil {
		items = []models.ConteneurItem{}
	}
	return items, nil
}


