package db

import (
	"API/models"
	"database/sql"
	"strings"

	"github.com/google/uuid"
)

func GetFavoritesByUserID(userID uuid.UUID) ([]models.Favorite, error) {
	rows, err := Db.Query("SELECT id, user_id, annonce_id, created_at FROM favorites WHERE user_id = ?", userID.String())

	if err != nil {
		return nil, err
	}

	defer rows.Close()

	var favorites []models.Favorite

	for rows.Next() {
		var fav models.Favorite
		err := rows.Scan(&fav.ID, &fav.UserID, &fav.AnnonceID, &fav.CreatedAt)

		if err != nil {
			return nil, err
		}

		favorites = append(favorites, fav)
	}

	return favorites, nil
}

func GetFavoriteByUserAndAnnonceID(userID, annonceID uuid.UUID) (models.Favorite, error) {
	var fav models.Favorite
	row := Db.QueryRow("SELECT id, user_id, annonce_id, created_at FROM favorites WHERE user_id = ? AND annonce_id = ?", userID.String(), annonceID.String())
	if err := row.Scan(&fav.ID, &fav.UserID, &fav.AnnonceID, &fav.CreatedAt); err != nil {
		if err == sql.ErrNoRows {
			return fav, nil
		}
		return fav, err
	}
	return fav, nil
}

func CreateFavorite(userID, annonceID uuid.UUID) (models.Favorite, error) {
	_, err := Db.Exec("INSERT INTO favorites (id, user_id, annonce_id, created_at) VALUES (UUID(), ?, ?, NOW())", userID.String(), annonceID.String())
	if err != nil {
		if strings.Contains(err.Error(), "Duplicate entry") {
			return GetFavoriteByUserAndAnnonceID(userID, annonceID)
		}
		return models.Favorite{}, err
	}
	return GetFavoriteByUserAndAnnonceID(userID, annonceID)
}

func DeleteFavoriteByID(userID, favoriteID uuid.UUID) (bool, error) {
	result, err := Db.Exec("DELETE FROM favorites WHERE id = ? AND user_id = ?", favoriteID.String(), userID.String())
	if err != nil {
		return false, err
	}
	rowsAffected, err := result.RowsAffected()
	if err != nil {
		return false, err
	}
	return rowsAffected > 0, nil
}
