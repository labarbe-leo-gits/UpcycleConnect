package db

import (
	"API/models"
	"database/sql"
	"fmt"
	"time"

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

func CreateAnnonceImageInDB(image models.Image) error {

	newID := uuid.New()
	currentTime := time.Now().UTC().Format("2006-01-02 15:04:05")

	_, err := Db.Exec("INSERT INTO images (id, annonce_id, file_name, created_at) VALUES (?, ?, ?, ?)", newID, image.AnnonceID, image.FileName, currentTime)
	if err != nil {
		return fmt.Errorf("createAnnonceImage package db : %s", err.Error())
	}

	return nil
}

func GetStepImagesFromDB(stepID string) ([]models.Image, error) {
	rows, err := Db.Query("SELECT id, step_id, file_name, created_at FROM images WHERE step_id = ?", stepID)
	if err != nil {
		return nil, fmt.Errorf("GetStepImagesFromDB: %w", err)
	}
	defer rows.Close()

	images := []models.Image{}
	for rows.Next() {
		var img models.Image
		var idStr string
		var stepIDStr sql.NullString
		var createdAt sql.NullString
		if err := rows.Scan(&idStr, &stepIDStr, &img.FileName, &createdAt); err != nil {
			return nil, err
		}
		img.ID, _ = uuid.Parse(idStr)
		if stepIDStr.Valid {
			img.StepID = stepIDStr.String
		}
		if createdAt.Valid {
			img.CreatedAt = createdAt.String
		}
		images = append(images, img)
	}
	return images, rows.Err()
}

func CreateStepImageInDB(image models.Image) (*models.Image, error) {
	newID := uuid.New()
	currentTime := time.Now().UTC().Format("2006-01-02 15:04:05")

	_, err := Db.Exec("INSERT INTO images (id, step_id, file_name, created_at) VALUES (?, ?, ?, ?)",
		newID.String(), image.StepID, image.FileName, currentTime)
	if err != nil {
		return nil, fmt.Errorf("CreateStepImageInDB: %w", err)
	}

	image.ID = newID
	image.CreatedAt = currentTime
	return &image, nil
}
