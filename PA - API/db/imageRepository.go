package db

import (
	"API/models"
	"database/sql"
	"fmt"
	"github.com/google/uuid"
)

func GetAnnonceImagesFromDB(annonceID uuid.UUID) ([]models.Image, error) {

	images := []models.Image{}
	rows, err := Db.Query("SELECT id, file_name, created_at FROM images WHERE annonce_id = ?", annonceID)

	if err != nil {
		return nil, fmt.Errorf("getAnnonceImages package db : %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {
		var image models.Image
		var idStr string
		var createdAt sql.NullString

		err := rows.Scan(&idStr, &image.FileName, &createdAt)
		if err != nil {
			return nil, fmt.Errorf("getAnnonceImages package db scan : %s", err.Error())
		}

		image.ID, err = uuid.Parse(idStr)
		if err != nil {
			return nil, fmt.Errorf("getAnnonceImages package db uuid parse : %s", err.Error())
		}

		if createdAt.Valid {
			image.CreatedAt = createdAt.String
		}

		images = append(images, image)
	}

	err = rows.Err()
	if err != nil {
		return nil, fmt.Errorf("getAnnonceImages package db rows : %s", err.Error())
	}

	return images, nil
}

func CreateAnnonceImageInDB(image models.Image) (error) {

	newID := uuid.New()
	currentTime := getCurrentTime()

	_, err := Db.Exec("INSERT INTO images (id, annonce_id, file_name, created_at) VALUES (?, ?, ?, ?)", newID, image.ProductID, image.FileName, currentTime)
	if err != nil {
		return fmt.Errorf("createAnnonceImage package db : %s", err.Error())
	}

	return nil
}
