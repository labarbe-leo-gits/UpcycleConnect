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

	if conteneur.PostalCode == 0 {
		conteneur.PostalCode = old_Conteneur.PostalCode
	}

	if conteneur.Number == 0 {
		conteneur.Number = old_Conteneur.Number
	}

	_, err = Db.Exec(
		"UPDATE conteneurs SET name = ?, city = ?, road = ?, postal_code = ?, number = ?, updated_at = ? WHERE id = ?",
		conteneur.Name, conteneur.City, conteneur.Road, conteneur.PostalCode, conteneur.Number, getCurrentTime(), conteneurID,
	)

	if err != nil {
		return fmt.Errorf("failed to update conteneur: %v", err)
	}

	return nil

}