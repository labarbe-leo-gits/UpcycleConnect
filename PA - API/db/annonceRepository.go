package db

import (
	"API/models"
	"database/sql"
	"fmt"

	"github.com/google/uuid"
)

func nullableUUID(u *uuid.UUID) interface{} {
	if u == nil {
		return nil
	}
	return u.String()
}

func GetAnnoncesFromDB() ([]models.Annonce, error) {

	annonces := []models.Annonce{}
	rows, err := Db.Query("SELECT id, user_id, title, description, price, status, view_count, poids_materiaux, facteur_id, type_materiaux, upcycling_score, created_at, updated_at FROM annonces")

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
		var status sql.NullInt64
		var poids sql.NullFloat64
		var factorID sql.NullString
		var matType sql.NullString
		var score sql.NullFloat64

		err := rows.Scan(&idStr, &userIDStr, &annonce.Title, &description, &price, &status, &annonce.ViewCount, &poids, &factorID, &matType, &score, &createdAt, &updatedAt)
		if factorID.Valid {
			if uid, err := uuid.Parse(factorID.String); err == nil {
				annonce.FacteurID = &uid
			}
		}
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

		if status.Valid {
			annonce.Status = int(status.Int64)
		}
		if poids.Valid {
			annonce.PoidsMateriaux = poids.Float64
		}
		if matType.Valid {
			annonce.TypeMateriaux = matType.String
		}
		if score.Valid {
			annonce.UpcyclingScore = score.Float64
		}

		annonces = append(annonces, annonce)
	}

	err = rows.Err()

	if err != nil {
		return nil, fmt.Errorf("getAnnonces package db rows : %s", err.Error())
	}

	return annonces, nil

}

func GetAnnoncesPageFromDB(limit int, offset int) ([]models.Annonce, error) {

	annonces := []models.Annonce{}
	rows, err := Db.Query("SELECT id, user_id, title, description, price, status, view_count, poids_materiaux, facteur_id, type_materiaux, upcycling_score, created_at, updated_at FROM annonces ORDER BY created_at DESC LIMIT ? OFFSET ?", limit, offset)

	if err != nil {
		return nil, fmt.Errorf("getAnnoncesPage package db : %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {
		var annonce models.Annonce
		var idStr, userIDStr string
		var createdAt, updatedAt sql.NullString
		var description sql.NullString
		var price sql.NullFloat64
		var status sql.NullInt64
		var poids sql.NullFloat64
		var factorID sql.NullString
		var matType sql.NullString
		var score sql.NullFloat64

		err := rows.Scan(&idStr, &userIDStr, &annonce.Title, &description, &price, &status, &annonce.ViewCount, &poids, &factorID, &matType, &score, &createdAt, &updatedAt)
		if factorID.Valid {
			if uid, err := uuid.Parse(factorID.String); err == nil {
				annonce.FacteurID = &uid
			}
		}
		if err != nil {
			return nil, fmt.Errorf("getAnnoncesPage package db scan : %s", err.Error())
		}

		annonce.ID, err = uuid.Parse(idStr)
		if err != nil {
			return nil, fmt.Errorf("getAnnoncesPage package db uuid parse : %s", err.Error())
		}

		annonce.UserID, err = uuid.Parse(userIDStr)
		if err != nil {
			return nil, fmt.Errorf("getAnnoncesPage package db uuid parse user_id : %s", err.Error())
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

		if status.Valid {
			annonce.Status = int(status.Int64)
		}
		if poids.Valid {
			annonce.PoidsMateriaux = poids.Float64
		}
		if matType.Valid {
			annonce.TypeMateriaux = matType.String
		}
		if score.Valid {
			annonce.UpcyclingScore = score.Float64
		}

		annonces = append(annonces, annonce)
	}

	err = rows.Err()

	if err != nil {
		return nil, fmt.Errorf("getAnnoncesPage package db rows : %s", err.Error())
	}

	return annonces, nil
}

func GetAnnoncesByStatusFromDB(status int) ([]models.Annonce, error) {

	annonces := []models.Annonce{}
	rows, err := Db.Query("SELECT id, user_id, title, description, price, status, view_count, poids_materiaux, facteur_id, type_materiaux, upcycling_score, created_at, updated_at FROM annonces WHERE status = ?", status)

	if err != nil {
		return nil, fmt.Errorf("getAnnoncesByStatus package db : %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {
		var annonce models.Annonce
		var idStr, userIDStr string
		var createdAt, updatedAt sql.NullString
		var description sql.NullString
		var price sql.NullFloat64
		var statusValue sql.NullInt64
		var poids sql.NullFloat64
		var factorID sql.NullString
		var matType sql.NullString
		var score sql.NullFloat64

		err := rows.Scan(&idStr, &userIDStr, &annonce.Title, &description, &price, &statusValue, &annonce.ViewCount, &poids, &factorID, &matType, &score, &createdAt, &updatedAt)
		if factorID.Valid {
			if uid, err := uuid.Parse(factorID.String); err == nil {
				annonce.FacteurID = &uid
			}
		}
		if err != nil {
			return nil, fmt.Errorf("getAnnoncesByStatus package db scan : %s", err.Error())
		}

		annonce.ID, err = uuid.Parse(idStr)
		if err != nil {
			return nil, fmt.Errorf("getAnnoncesByStatus package db uuid parse : %s", err.Error())
		}

		annonce.UserID, err = uuid.Parse(userIDStr)
		if err != nil {
			return nil, fmt.Errorf("getAnnoncesByStatus package db uuid parse user_id : %s", err.Error())
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

		if statusValue.Valid {
			annonce.Status = int(statusValue.Int64)
		}
		if poids.Valid {
			annonce.PoidsMateriaux = poids.Float64
		}
		if matType.Valid {
			annonce.TypeMateriaux = matType.String
		}
		if score.Valid {
			annonce.UpcyclingScore = score.Float64
		}

		annonces = append(annonces, annonce)
	}

	err = rows.Err()

	if err != nil {
		return nil, fmt.Errorf("getAnnoncesByStatus package db rows : %s", err.Error())
	}

	return annonces, nil

}

func GetAnnoncesPageByStatusFromDB(limit int, offset int, status int) ([]models.Annonce, error) {

	annonces := []models.Annonce{}
	rows, err := Db.Query("SELECT id, user_id, title, description, price, status, view_count, poids_materiaux, facteur_id, type_materiaux, upcycling_score, created_at, updated_at FROM annonces WHERE status = ? ORDER BY created_at DESC LIMIT ? OFFSET ?", status, limit, offset)

	if err != nil {
		return nil, fmt.Errorf("getAnnoncesPageByStatus package db : %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {
		var annonce models.Annonce
		var idStr, userIDStr string
		var createdAt, updatedAt sql.NullString
		var description sql.NullString
		var price sql.NullFloat64
		var statusValue sql.NullInt64
		var poids sql.NullFloat64
		var factorID sql.NullString
		var matType sql.NullString
		var score sql.NullFloat64

		err := rows.Scan(&idStr, &userIDStr, &annonce.Title, &description, &price, &statusValue, &annonce.ViewCount, &poids, &factorID, &matType, &score, &createdAt, &updatedAt)
		if factorID.Valid {
			if uid, err := uuid.Parse(factorID.String); err == nil {
				annonce.FacteurID = &uid
			}
		}
		if err != nil {
			return nil, fmt.Errorf("getAnnoncesPageByStatus package db scan : %s", err.Error())
		}

		annonce.ID, err = uuid.Parse(idStr)
		if err != nil {
			return nil, fmt.Errorf("getAnnoncesPageByStatus package db uuid parse : %s", err.Error())
		}

		annonce.UserID, err = uuid.Parse(userIDStr)
		if err != nil {
			return nil, fmt.Errorf("getAnnoncesPageByStatus package db uuid parse user_id : %s", err.Error())
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

		if statusValue.Valid {
			annonce.Status = int(statusValue.Int64)
		}
		if poids.Valid {
			annonce.PoidsMateriaux = poids.Float64
		}
		if matType.Valid {
			annonce.TypeMateriaux = matType.String
		}
		if score.Valid {
			annonce.UpcyclingScore = score.Float64
		}

		annonces = append(annonces, annonce)
	}

	err = rows.Err()

	if err != nil {
		return nil, fmt.Errorf("getAnnoncesPageByStatus package db rows : %s", err.Error())
	}

	return annonces, nil

}

func CountAnnoncesFromDB() (int, error) {
	var total int
	err := Db.QueryRow("SELECT COUNT(*) FROM annonces").Scan(&total)
	if err != nil {
		return 0, fmt.Errorf("countAnnonces package db : %s", err.Error())
	}
	return total, nil
}

func CountAnnoncesByStatusFromDB(status int) (int, error) {
	var total int
	err := Db.QueryRow("SELECT COUNT(*) FROM annonces WHERE status = ?", status).Scan(&total)
	if err != nil {
		return 0, fmt.Errorf("countAnnoncesByStatus package db : %s", err.Error())
	}
	return total, nil
}

func CreateAnnonceInDB(annonce models.Annonce) error {

	currentTime := getCurrentTime()

	_, err := Db.Exec("INSERT INTO annonces (id, user_id, title, description, price, view_count, poids_materiaux, facteur_id, type_materiaux, upcycling_score, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", annonce.ID.String(), annonce.UserID.String(), annonce.Title, annonce.Description, annonce.Price, annonce.ViewCount, annonce.PoidsMateriaux, nullableUUID(annonce.FacteurID), annonce.TypeMateriaux, annonce.UpcyclingScore, currentTime, currentTime)

	if err != nil {
		return fmt.Errorf("createAnnonce package db : %s", err.Error())
	}
	return nil
}

func UpdateAnnonceInDB(id string, annonce models.Annonce) error {

	currentTime := getCurrentTime()

	_, err := Db.Exec("UPDATE annonces SET title = ?, description = ?, price = ?, status = ?, view_count = ?, poids_materiaux = ?, facteur_id = ?, type_materiaux = ?, upcycling_score = ?, updated_at = ? WHERE id = ?", annonce.Title, annonce.Description, annonce.Price, annonce.Status, annonce.ViewCount, annonce.PoidsMateriaux, nullableUUID(annonce.FacteurID), annonce.TypeMateriaux, annonce.UpcyclingScore, currentTime, id)
	if err != nil {
		return fmt.Errorf("updateAnnonce package db : %s", err.Error())
	}

	return nil

}

func UpdateAnnonceStatusInDB(id string, status int) (bool, error) {
	currentTime := getCurrentTime()

	result, err := Db.Exec("UPDATE annonces SET status = ?, updated_at = ? WHERE id = ? AND status = 0", status, currentTime, id)
	if err != nil {
		return false, fmt.Errorf("updateAnnonceStatus package db : %s", err.Error())
	}

	rows, err := result.RowsAffected()
	if err != nil {
		return false, fmt.Errorf("updateAnnonceStatus package db rows : %s", err.Error())
	}

	return rows > 0, nil
}

func AdminUpdateAnnonceStatusInDB(id string, status int) error {
	currentTime := getCurrentTime()
	_, err := Db.Exec("UPDATE annonces SET status = ?, updated_at = ? WHERE id = ?", status, currentTime, id)
	if err != nil {
		return fmt.Errorf("adminUpdateAnnonceStatus package db : %s", err.Error())
	}
	return nil
}

func DeleteAnnonceFromDB(id string) error {
	_, err := Db.Exec("DELETE FROM annonces WHERE id = ?", id)
	if err != nil {
		return fmt.Errorf("deleteAnnonce package db : %s", err.Error())
	}
	return nil
}

func GetAnnonceByIDFromDB(id string) (*models.Annonce, error) {

	var annonce models.Annonce

	row := Db.QueryRow("SELECT id, user_id, title, description, price, status, view_count, poids_materiaux, facteur_id, type_materiaux, upcycling_score, created_at, updated_at FROM annonces WHERE id = ?", id)

	var idStr, userIDStr string
	var createdAt, updatedAt sql.NullString
	var description sql.NullString
	var price sql.NullFloat64
	var status sql.NullInt64
	var poids sql.NullFloat64
	var factorID sql.NullString
	var matType sql.NullString
	var score sql.NullFloat64

	err := row.Scan(&idStr, &userIDStr, &annonce.Title, &description, &price, &status, &annonce.ViewCount, &poids, &factorID, &matType, &score, &createdAt, &updatedAt)
	if factorID.Valid {
		if uid, err := uuid.Parse(factorID.String); err == nil {
			annonce.FacteurID = &uid
		}
	}
	if err != nil {
		if err == sql.ErrNoRows {
			return nil, nil
		}
		return nil, fmt.Errorf("getAnnonceByID package db scan : %s", err.Error())
	}

	annonce.ID, err = uuid.Parse(idStr)
	if err != nil {
		return nil, fmt.Errorf("getAnnonceByID package db uuid parse : %s", err.Error())
	}

	annonce.UserID, err = uuid.Parse(userIDStr)
	if err != nil {
		return nil, fmt.Errorf("getAnnonceByID package db uuid parse user_id : %s", err.Error())
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

	if status.Valid {
		annonce.Status = int(status.Int64)
	}

	if poids.Valid {
		annonce.PoidsMateriaux = poids.Float64
	}
	if matType.Valid {
		annonce.TypeMateriaux = matType.String
	}
	if score.Valid {
		annonce.UpcyclingScore = score.Float64
	}

	if description.Valid {
		annonce.Description = description.String
	}

	if price.Valid {
		annonce.Price = price.Float64
	}

	if status.Valid {
		annonce.Status = int(status.Int64)
	}

	return &annonce, nil
}

func GetAnnoncesByUserIDFromDB(userID string) ([]models.Annonce, error) {

	annonces := []models.Annonce{}
	rows, err := Db.Query("SELECT id, user_id, title, description, price, status, view_count, poids_materiaux, facteur_id, type_materiaux, upcycling_score, created_at, updated_at FROM annonces WHERE user_id = ?", userID)

	if err != nil {
		return nil, fmt.Errorf("getAnnoncesByUserID package db : %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {
		var annonce models.Annonce
		var idStr, userIDStr string
		var createdAt, updatedAt sql.NullString
		var description sql.NullString
		var price sql.NullFloat64
		var status sql.NullInt64
		var poids sql.NullFloat64
		var factorID sql.NullString
		var matType sql.NullString
		var score sql.NullFloat64

		err := rows.Scan(&idStr, &userIDStr, &annonce.Title, &description, &price, &status, &annonce.ViewCount, &poids, &factorID, &matType, &score, &createdAt, &updatedAt)
		if factorID.Valid {
			if uid, err := uuid.Parse(factorID.String); err == nil {
				annonce.FacteurID = &uid
			}
		}
		if err != nil {
			return nil, fmt.Errorf("getAnnoncesByUserID package db scan : %s", err.Error())
		}

		annonce.ID, err = uuid.Parse(idStr)
		if err != nil {
			return nil, fmt.Errorf("getAnnoncesByUserID package db uuid parse : %s", err.Error())
		}

		annonce.UserID, err = uuid.Parse(userIDStr)
		if err != nil {
			return nil, fmt.Errorf("getAnnoncesByUserID package db uuid parse user_id : %s", err.Error())
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

		if status.Valid {
			annonce.Status = int(status.Int64)
		}
		if poids.Valid {
			annonce.PoidsMateriaux = poids.Float64
		}
		if matType.Valid {
			annonce.TypeMateriaux = matType.String
		}
		if score.Valid {
			annonce.UpcyclingScore = score.Float64
		}

		annonces = append(annonces, annonce)
	}

	err = rows.Err()

	if err != nil {
		return nil, fmt.Errorf("getAnnoncesByUserID package db rows : %s", err.Error())
	}

	return annonces, nil

}

func IncrementAnnonceViewCountInDB(id string) error {

	_, err := Db.Exec("UPDATE annonces SET view_count = view_count + 1 WHERE id = ?", id)
	if err != nil {
		return fmt.Errorf("incrementAnnonceViewCount package db : %s", err.Error())
	}

	return nil
}
