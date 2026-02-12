package db

import (
	"API/models"
	"database/sql"
	"fmt"
	"github.com/google/uuid"
)

func GetAnnoncesFromDB() ([]models.Annonce, error) {

	annonces := []models.Annonce{}
	rows, err := Db.Query("SELECT id, user_id, title, description, price, created_at, updated_at FROM annonces")

	if err != nil {
		return nil, fmt.Errorf("getAnnonces package db : %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {
		var annonce models.Annonce
		var idStr, userIDStr string
		var createdAt, updatedAt sql.NullString
		var description sql.NullString
		var price sql.NullFloat64

		err := rows.Scan(&idStr, &userIDStr, &annonce.Title, &description, &price, &createdAt, &updatedAt)
		if err != nil {
			return nil, fmt.Errorf("getAnnonces package db scan : %s", err.Error())
		}

		annonce.ID, err = uuid.Parse(idStr)
		if err != nil {
			return nil, fmt.Errorf("getAnnonces package db uuid parse : %s", err.Error())
		}

		annonce.UserID, err = uuid.Parse(userIDStr)
		if err != nil {
			return nil, fmt.Errorf("getAnnonces package db uuid parse user_id : %s", err.Error())
		}

		if createdAt.Valid {
			annonce.CreatedAt = createdAt.String
		}

		if updatedAt.Valid {
			annonce.UpdatedAt = updatedAt.String
		}

		if description.Valid {
			annonce.Description = description.String
		}

		if price.Valid {
			annonce.Price = price.Float64
		}

		annonces = append(annonces, annonce)
	}

	err = rows.Err()

	if err != nil {
		return nil, fmt.Errorf("getAnnonces package db rows : %s", err.Error())
	}

	return annonces, nil

}

func CreateAnnonceInDB(annonce models.Annonce) error {

	newID := uuid.New()
	currentTime := getCurrentTime()

	_, err := Db.Exec("INSERT INTO annonces (id, user_id, title, description, price, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)", newID.String(), annonce.UserID.String(), annonce.Title, annonce.Description, annonce.Price, currentTime, currentTime)

	if err != nil {
		return fmt.Errorf("createAnnonce package db : %s", err.Error())
	}

	return nil
}
