package db

import (
	"API/models"
	"database/sql"
	"fmt"
	"time"

	"github.com/google/uuid"
)

func GetAllTypePrestations() ([]models.TypePrestation, error) {

	var typePrestations []models.TypePrestation

	query := "SELECT id, name, created_at FROM typesPrestations"
	rows, err := Db.Query(query)
	if err != nil {
		fmt.Println("[ERROR] GetAllTypePrestations query:", err)
		return nil, err

	}

	defer rows.Close()

	for rows.Next() {
		var tp models.TypePrestation
		err := rows.Scan(&tp.ID, &tp.Name, &tp.CreatedAt)
		if err != nil {
			fmt.Println("[ERROR] GetAllTypePrestations scan:", err)
			return nil, err
		}
		typePrestations = append(typePrestations, tp)
	}

	return typePrestations, nil
}

func CreateTypePrestationInDB(typePrestation models.TypePrestation) error {

	typePrestation.ID = uuid.New()
	currentTime := time.Now().UTC().Format("2006-01-02 15:04:05")

	query := "INSERT INTO typesPrestations (id, name, created_at) VALUES (?, ?, ?)"
	_, err := Db.Exec(query, typePrestation.ID, typePrestation.Name, currentTime)

	if err != nil {
		fmt.Println("[ERROR] CreateTypePrestationInDB:", err)
		return err
	}

	return nil

}

func GetTypePrestationByIDFromDB(id uuid.UUID) (*models.TypePrestation, error) {

	var typePrestation models.TypePrestation

	query := "SELECT id, name, created_at FROM typesPrestations WHERE id = ?"
	row := Db.QueryRow(query, id)

	err := row.Scan(&typePrestation.ID, &typePrestation.Name, &typePrestation.CreatedAt)
	if err != nil {
		if err == sql.ErrNoRows {
			return nil, nil // not found
		}
		fmt.Println("[ERROR] GetTypePrestationByID:", err)
		return nil, err
	}

	return &typePrestation, nil

}

func UpdateTypePrestationInDB(typePrestation models.TypePrestation) error {

	query := "UPDATE typesPrestations SET name = ? WHERE id = ?"
	_, err := Db.Exec(query, typePrestation.Name, typePrestation.ID)

	if err != nil {
		fmt.Println("[ERROR] UpdateTypePrestationInDB:", err)

		return err
	}

	return nil

}

func DeleteTypePrestationInDB(id uuid.UUID) error {

	updateQuery := "UPDATE evenements SET event_type = '00000000-0000-0000-0000-000000000000' WHERE event_type = ?"
	_, err := Db.Exec(updateQuery, id)

	if err != nil {
		fmt.Println("[ERROR] DeleteTypePrestationInDB update prestations:", err)
		return err
	}

	query := "DELETE FROM typesPrestations WHERE id = ?"
	_, err = Db.Exec(query, id)

	if err != nil {
		fmt.Println("[ERROR] DeleteTypePrestationInDB:", err)

		return err
	}

	return nil

}
