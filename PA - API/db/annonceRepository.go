package db

import (
	"API/models"
	"database/sql"
	"fmt"
	"math"
	"strings"

	"github.com/google/uuid"
)

type AnnonceFilter struct {
	Status     *int
	Promoted   *bool
	Search     string
	CategoryID string
	ItemState  *int
	MinPrice   *float64
	MaxPrice   *float64
	Sort       string
}

func nullableUUID(u *uuid.UUID) interface{} {
	if u == nil {
		return nil
	}
	return u.String()
}

func buildAnnoncesFilterQuery(filter AnnonceFilter) (string, []interface{}) {
	var whereClauses []string
	var args []interface{}

	if filter.Status != nil {
		whereClauses = append(whereClauses, "a.status = ?")
		args = append(args, *filter.Status)
	}

	if strings.TrimSpace(filter.Search) != "" {
		whereClauses = append(whereClauses, "a.title LIKE ?")
		args = append(args, "%"+strings.TrimSpace(filter.Search)+"%")
	}

	if strings.TrimSpace(filter.CategoryID) != "" {
		whereClauses = append(whereClauses, "a.category_id = ?")
		args = append(args, strings.TrimSpace(filter.CategoryID))
	}

	if filter.ItemState != nil {
		whereClauses = append(whereClauses, "a.item_state = ?")
		args = append(args, *filter.ItemState)
	}

	if filter.Promoted != nil {
		if *filter.Promoted {
			whereClauses = append(whereClauses, "a.ad_campaign_id IS NOT NULL")
		} else {
			whereClauses = append(whereClauses, "a.ad_campaign_id IS NULL")
		}
	}

	if filter.MinPrice != nil {
		whereClauses = append(whereClauses, "a.price >= ?")
		args = append(args, *filter.MinPrice)
	}

	if filter.MaxPrice != nil {
		whereClauses = append(whereClauses, "a.price <= ?")
		args = append(args, *filter.MaxPrice)
	}

	whereSQL := ""
	if len(whereClauses) > 0 {
		whereSQL = " WHERE " + strings.Join(whereClauses, " AND ")
	}

	return whereSQL, args
}

func buildAnnoncesOrderSQL(sort string) string {
	switch sort {
	case "created_asc":
		return " ORDER BY a.created_at ASC"
	case "price_asc":
		return " ORDER BY a.price ASC"
	case "price_desc":
		return " ORDER BY a.price DESC"
	case "title_asc":
		return " ORDER BY a.title ASC"
	case "title_desc":
		return " ORDER BY a.title DESC"
	case "created_desc":
		return " ORDER BY a.created_at DESC"
	default:
		return " ORDER BY a.created_at DESC"
	}
}

func GetAnnoncesFromDB() ([]models.Annonce, error) {

	annonces := []models.Annonce{}
	rows, err := Db.Query("SELECT a.id, a.user_id, a.title, a.description, a.price, a.status, a.view_count, a.poids_materiaux, a.facteur_id, a.type_materiaux, a.upcycling_score, a.item_state, a.category_id, c.name AS category_name, a.ad_campaign_id, u.user_type, a.created_at, a.updated_at FROM annonces a LEFT JOIN categories c ON a.category_id = c.id LEFT JOIN users u ON u.id = a.user_id")

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
		var factorID, categoryID, categoryName, adCampaignID sql.NullString
		var matType sql.NullString
		var score sql.NullFloat64
		var itemState sql.NullInt64
		var sellerUserType sql.NullInt64

		err := rows.Scan(&idStr, &userIDStr, &annonce.Title, &description, &price, &status, &annonce.ViewCount, &poids, &factorID, &matType, &score, &itemState, &categoryID, &categoryName, &adCampaignID, &sellerUserType, &createdAt, &updatedAt)
		if factorID.Valid {
			if uid, err := uuid.Parse(factorID.String); err == nil {
				annonce.FacteurID = &uid
			}
		}
		if categoryID.Valid {
			if uid, err := uuid.Parse(categoryID.String); err == nil {
				annonce.CategoryID = &uid
			}
		}
		if categoryName.Valid {
			annonce.CategoryName = categoryName.String
		}
		if itemState.Valid {
			annonce.ItemState = int(itemState.Int64)
		}
		if adCampaignID.Valid {
			if uid, err := uuid.Parse(adCampaignID.String); err == nil {
				annonce.AdCampaignID = &uid
				annonce.Promoted = true
			}
		}
		if sellerUserType.Valid {
			sellerType := int(sellerUserType.Int64)
			annonce.SellerUserType = &sellerType
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
	rows, err := Db.Query("SELECT a.id, a.user_id, a.title, a.description, a.price, a.status, a.view_count, a.poids_materiaux, a.facteur_id, a.type_materiaux, a.upcycling_score, a.item_state, a.category_id, c.name AS category_name, a.ad_campaign_id, u.user_type, a.created_at, a.updated_at FROM annonces a LEFT JOIN categories c ON a.category_id = c.id LEFT JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC LIMIT ? OFFSET ?", limit, offset)

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
		var factorID, categoryID, categoryName, adCampaignID sql.NullString
		var matType sql.NullString
		var score sql.NullFloat64
		var itemState sql.NullInt64
		var sellerUserType sql.NullInt64

		err := rows.Scan(&idStr, &userIDStr, &annonce.Title, &description, &price, &status, &annonce.ViewCount, &poids, &factorID, &matType, &score, &itemState, &categoryID, &categoryName, &adCampaignID, &sellerUserType, &createdAt, &updatedAt)
		if factorID.Valid {
			if uid, err := uuid.Parse(factorID.String); err == nil {
				annonce.FacteurID = &uid
			}
		}
		if categoryID.Valid {
			if uid, err := uuid.Parse(categoryID.String); err == nil {
				annonce.CategoryID = &uid
			}
		}
		if itemState.Valid {
			annonce.ItemState = int(itemState.Int64)
		}
		if categoryName.Valid {
			annonce.CategoryName = categoryName.String
		}
		if adCampaignID.Valid {
			if uid, err := uuid.Parse(adCampaignID.String); err == nil {
				annonce.AdCampaignID = &uid
				annonce.Promoted = true
			}
		}
		if sellerUserType.Valid {
			sellerType := int(sellerUserType.Int64)
			annonce.SellerUserType = &sellerType
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

func CountAnnoncesFilteredFromDB(filter AnnonceFilter) (int, error) {
	whereSQL, args := buildAnnoncesFilterQuery(filter)
	query := "SELECT COUNT(*) FROM annonces a" + whereSQL
	var total int
	if err := Db.QueryRow(query, args...).Scan(&total); err != nil {
		return 0, fmt.Errorf("countAnnonces package db : %s", err.Error())
	}
	return total, nil
}

func GetAnnoncesPageFilteredFromDB(limit int, offset int, filter AnnonceFilter) ([]models.Annonce, error) {
	orderSQL := buildAnnoncesOrderSQL(filter.Sort)
	whereSQL, args := buildAnnoncesFilterQuery(filter)

	baseQuery := "SELECT a.id, a.user_id, a.title, a.description, a.price, a.status, a.view_count, a.poids_materiaux, a.facteur_id, a.type_materiaux, a.upcycling_score, a.item_state, a.category_id, c.name AS category_name, a.ad_campaign_id, u.user_type, a.created_at, a.updated_at FROM annonces a LEFT JOIN categories c ON a.category_id = c.id LEFT JOIN users u ON u.id = a.user_id"

	query := baseQuery + whereSQL + orderSQL
	var argsWithLimits []interface{}
	if limit > 0 {
		query += " LIMIT ? OFFSET ?"
		argsWithLimits = append(args, limit, offset)
	} else {
		argsWithLimits = args
	}

	rows, err := Db.Query(query, argsWithLimits...)
	if err != nil {
		return nil, fmt.Errorf("getAnnoncesPageFiltered package db : %s", err.Error())
	}
	defer rows.Close()

	annonces := []models.Annonce{}
	for rows.Next() {
		var annonce models.Annonce
		var idStr, userIDStr string
		var createdAt, updatedAt sql.NullString
		var description sql.NullString
		var price sql.NullFloat64
		var status sql.NullInt64
		var poids sql.NullFloat64
		var factorID, categoryID, categoryName, adCampaignID sql.NullString
		var matType sql.NullString
		var score sql.NullFloat64
		var itemState sql.NullInt64
		var sellerUserType sql.NullInt64

		err := rows.Scan(&idStr, &userIDStr, &annonce.Title, &description, &price, &status, &annonce.ViewCount, &poids, &factorID, &matType, &score, &itemState, &categoryID, &categoryName, &adCampaignID, &sellerUserType, &createdAt, &updatedAt)
		if factorID.Valid {
			if uid, err := uuid.Parse(factorID.String); err == nil {
				annonce.FacteurID = &uid
			}
		}
		if categoryID.Valid {
			if uid, err := uuid.Parse(categoryID.String); err == nil {
				annonce.CategoryID = &uid
			}
		}
		if itemState.Valid {
			annonce.ItemState = int(itemState.Int64)
		}
		if categoryName.Valid {
			annonce.CategoryName = categoryName.String
		}
		if adCampaignID.Valid {
			if uid, err := uuid.Parse(adCampaignID.String); err == nil {
				annonce.AdCampaignID = &uid
				annonce.Promoted = true
			}
		}
		if sellerUserType.Valid {
			sellerType := int(sellerUserType.Int64)
			annonce.SellerUserType = &sellerType
		}
		if err != nil {
			return nil, fmt.Errorf("getAnnoncesPageFiltered package db scan : %s", err.Error())
		}

		annonce.ID, err = uuid.Parse(idStr)
		if err != nil {
			return nil, fmt.Errorf("getAnnoncesPageFiltered package db uuid parse : %s", err.Error())
		}

		annonce.UserID, err = uuid.Parse(userIDStr)
		if err != nil {
			return nil, fmt.Errorf("getAnnoncesPageFiltered package db uuid parse user_id : %s", err.Error())
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
		return nil, fmt.Errorf("getAnnoncesPageFiltered package db rows : %s", err.Error())
	}

	return annonces, nil
}

func GetAnnoncesByStatusFromDB(status int) ([]models.Annonce, error) {

	annonces := []models.Annonce{}
	rows, err := Db.Query("SELECT a.id, a.user_id, a.title, a.description, a.price, a.status, a.view_count, a.poids_materiaux, a.facteur_id, a.type_materiaux, a.upcycling_score, a.item_state, a.category_id, c.name AS category_name, a.created_at, a.updated_at FROM annonces a LEFT JOIN categories c ON a.category_id = c.id WHERE a.status = ?", status)

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
		var factorID, categoryID, categoryName sql.NullString
		var matType sql.NullString
		var score sql.NullFloat64
		var itemState sql.NullInt64

		err := rows.Scan(&idStr, &userIDStr, &annonce.Title, &description, &price, &statusValue, &annonce.ViewCount, &poids, &factorID, &matType, &score, &itemState, &categoryID, &categoryName, &createdAt, &updatedAt)
		if factorID.Valid {
			if uid, err := uuid.Parse(factorID.String); err == nil {
				annonce.FacteurID = &uid
			}
		}
		if categoryID.Valid {
			if uid, err := uuid.Parse(categoryID.String); err == nil {
				annonce.CategoryID = &uid
			}
		}
		if itemState.Valid {
			annonce.ItemState = int(itemState.Int64)
		}
		if categoryName.Valid {
			annonce.CategoryName = categoryName.String
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
	rows, err := Db.Query("SELECT a.id, a.user_id, a.title, a.description, a.price, a.status, a.view_count, a.poids_materiaux, a.facteur_id, a.type_materiaux, a.upcycling_score, a.item_state, a.category_id, c.name AS category_name, a.ad_campaign_id, u.user_type, a.created_at, a.updated_at FROM annonces a LEFT JOIN categories c ON a.category_id = c.id LEFT JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC LIMIT ? OFFSET ?", limit, offset)

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
		var factorID, categoryID, categoryName sql.NullString
		var matType sql.NullString
		var score sql.NullFloat64
		var itemState sql.NullInt64

		err := rows.Scan(&idStr, &userIDStr, &annonce.Title, &description, &price, &statusValue, &annonce.ViewCount, &poids, &factorID, &matType, &score, &itemState, &categoryID, &categoryName, &createdAt, &updatedAt)
		if factorID.Valid {
			if uid, err := uuid.Parse(factorID.String); err == nil {
				annonce.FacteurID = &uid
			}
		}
		if categoryID.Valid {
			if uid, err := uuid.Parse(categoryID.String); err == nil {
				annonce.CategoryID = &uid
			}
		}
		if itemState.Valid {
			annonce.ItemState = int(itemState.Int64)
		}
		if categoryName.Valid {
			annonce.CategoryName = categoryName.String
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

	_, err := Db.Exec("INSERT INTO annonces (id, user_id, title, description, price, view_count, poids_materiaux, facteur_id, type_materiaux, upcycling_score, item_state, category_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", annonce.ID.String(), annonce.UserID.String(), annonce.Title, annonce.Description, annonce.Price, annonce.ViewCount, annonce.PoidsMateriaux, nullableUUID(annonce.FacteurID), annonce.TypeMateriaux, annonce.UpcyclingScore, annonce.ItemState, nullableUUID(annonce.CategoryID), currentTime, currentTime)

	if err != nil {
		return fmt.Errorf("createAnnonce package db : %s", err.Error())
	}

	var count int
	err2 := Db.QueryRow("SELECT COUNT(*) FROM annonces WHERE user_id = ?", annonce.UserID.String()).Scan(&count)
	if err2 != nil {
		fmt.Println("[DEBUG] first annonce count query error:", err2)
	} else {
		fmt.Println("[DEBUG] user", annonce.UserID.String(), "annonce count", count)
	}
	if count == 1 {
		fmt.Println("[DEBUG] awarding pionnier badge to", annonce.UserID.String())

		res, err3 := Db.Exec("INSERT IGNORE INTO user_badges (id, user_id, badge_id) SELECT UUID(), ?, id FROM badges WHERE name = 'pionnier'", annonce.UserID.String())
		if err3 != nil {
			fmt.Println("[DEBUG] badge insert error:", err3)
		} else {
			rows, _ := res.RowsAffected()
			fmt.Println("[DEBUG] badge insert rows affected", rows)
		}
	}

	return nil
}

func UpdateAnnonceInDB(id string, annonce models.Annonce) error {

	currentTime := getCurrentTime()

	_, err := Db.Exec("UPDATE annonces SET title = ?, description = ?, price = ?, status = ?, view_count = ?, poids_materiaux = ?, facteur_id = ?, type_materiaux = ?, upcycling_score = ?, item_state = ?, category_id = ?, updated_at = ? WHERE id = ?", annonce.Title, annonce.Description, annonce.Price, annonce.Status, annonce.ViewCount, annonce.PoidsMateriaux, nullableUUID(annonce.FacteurID), annonce.TypeMateriaux, annonce.UpcyclingScore, annonce.ItemState, nullableUUID(annonce.CategoryID), currentTime, id)
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
	tx, err := Db.Begin()
	if err != nil {
		return fmt.Errorf("deleteAnnonce package db begin tx: %s", err.Error())
	}
	defer func() {
		if err != nil {
			_ = tx.Rollback()
		}
	}()

	var status int
	var priceHT float64
	err = tx.QueryRow("SELECT status, price FROM annonces WHERE id = ?", id).Scan(&status, &priceHT)
	if err != nil && err != sql.ErrNoRows {
		return fmt.Errorf("deleteAnnonce package db read annonce: %s", err.Error())
	}

	if status == 1 && priceHT > 0 {
		var buyerIDStr string
		qErr := tx.QueryRow("SELECT user_id FROM orders WHERE product_id = ? LIMIT 1", id).Scan(&buyerIDStr)
		if qErr == nil && buyerIDStr != "" {
			refund := math.Round(priceHT*(1+0.08)*100) / 100
			_, err = tx.Exec("UPDATE users SET balance = balance + ? WHERE id = ?", refund, buyerIDStr)
			if err != nil {
				return fmt.Errorf("deleteAnnonce package db refund buyer: %s", err.Error())
			}
		}
	}

	_, err = tx.Exec("DELETE FROM annonces WHERE id = ?", id)
	if err != nil {
		return fmt.Errorf("deleteAnnonce package db : %s", err.Error())
	}

	if commitErr := tx.Commit(); commitErr != nil {
		return fmt.Errorf("deleteAnnonce package db commit: %s", commitErr.Error())
	}
	return nil
}

func GetAnnonceByIDFromDB(id string) (*models.Annonce, error) {

	var annonce models.Annonce

	row := Db.QueryRow("SELECT a.id, a.user_id, a.title, a.description, a.price, a.status, a.view_count, a.poids_materiaux, a.facteur_id, a.type_materiaux, a.upcycling_score, a.item_state, a.category_id, c.name AS category_name, a.created_at, a.updated_at FROM annonces a LEFT JOIN categories c ON a.category_id = c.id WHERE a.id = ?", id)

	var idStr, userIDStr string
	var createdAt, updatedAt sql.NullString
	var description sql.NullString
	var price sql.NullFloat64
	var status sql.NullInt64
	var poids sql.NullFloat64
	var factorID, categoryID, categoryName sql.NullString
	var matType sql.NullString
	var score sql.NullFloat64
	var itemState sql.NullInt64

	err := row.Scan(&idStr, &userIDStr, &annonce.Title, &description, &price, &status, &annonce.ViewCount, &poids, &factorID, &matType, &score, &itemState, &categoryID, &categoryName, &createdAt, &updatedAt)
	if factorID.Valid {
		if uid, err := uuid.Parse(factorID.String); err == nil {
			annonce.FacteurID = &uid
		}
	}
	if categoryID.Valid {
		if uid, err := uuid.Parse(categoryID.String); err == nil {
			annonce.CategoryID = &uid
		}
	}
	if categoryName.Valid {
		annonce.CategoryName = categoryName.String
	}
	if itemState.Valid {
		annonce.ItemState = int(itemState.Int64)
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
	rows, err := Db.Query("SELECT a.id, a.user_id, a.title, a.description, a.price, a.status, a.view_count, a.poids_materiaux, a.facteur_id, a.type_materiaux, a.upcycling_score, a.item_state, a.category_id, c.name AS category_name, a.created_at, a.updated_at FROM annonces a LEFT JOIN categories c ON a.category_id = c.id WHERE a.user_id = ?", userID)

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
		var factorID, categoryID, categoryName sql.NullString
		var matType sql.NullString
		var score sql.NullFloat64
		var itemState sql.NullInt64

		err := rows.Scan(&idStr, &userIDStr, &annonce.Title, &description, &price, &status, &annonce.ViewCount, &poids, &factorID, &matType, &score, &itemState, &categoryID, &categoryName, &createdAt, &updatedAt)
		if factorID.Valid {
			if uid, err := uuid.Parse(factorID.String); err == nil {
				annonce.FacteurID = &uid
			}
		}
		if categoryID.Valid {
			if uid, err := uuid.Parse(categoryID.String); err == nil {
				annonce.CategoryID = &uid
			}
		}
		if categoryName.Valid {
			annonce.CategoryName = categoryName.String
		}
		if itemState.Valid {
			annonce.ItemState = int(itemState.Int64)
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
