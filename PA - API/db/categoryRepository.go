package db

import (
	"API/models"
	"database/sql"
	"fmt"
	"strings"

	"github.com/google/uuid"
)

func GetCategoriesFromDB(limit, offset int, sort, search string) ([]models.Category, int, error) {
	baseQuery := "SELECT id, name, created_at FROM categories"
	countQuery := "SELECT COUNT(*) FROM categories"
	var whereClauses []string
	var args []interface{}

	if strings.TrimSpace(search) != "" {
		whereClauses = append(whereClauses, "name LIKE ?")
		args = append(args, "%"+strings.TrimSpace(search)+"%")
	}

	whereSQL := ""
	if len(whereClauses) > 0 {
		whereSQL = " WHERE " + strings.Join(whereClauses, " AND ")
	}

	orderSQL := " ORDER BY name ASC"
	switch sort {
	case "created":
		orderSQL = " ORDER BY created_at DESC"
	case "created_asc":
		orderSQL = " ORDER BY created_at ASC"
	}

	var total int
	if err := Db.QueryRow(countQuery+whereSQL, args...).Scan(&total); err != nil {
		return nil, 0, fmt.Errorf("failed to count categories: %v", err)
	}

	if limit <= 0 {
		query := baseQuery + whereSQL + orderSQL
		rows, err := Db.Query(query, args...)
		if err != nil {
			return nil, 0, fmt.Errorf("failed to query categories: %v", err)
		}
		defer rows.Close()

		var categories []models.Category
		for rows.Next() {
			var category models.Category
			err := rows.Scan(&category.ID, &category.Name, &category.CreatedAt)
			if err != nil {
				return nil, 0, fmt.Errorf("failed to scan category: %v", err)
			}
			categories = append(categories, category)
		}
		if err = rows.Err(); err != nil {
			return nil, 0, fmt.Errorf("error iterating over category rows: %v", err)
		}

		if categories == nil {
			categories = []models.Category{}
		}

		return categories, total, nil
	}

	query := baseQuery + whereSQL + orderSQL + " LIMIT ? OFFSET ?"
	argsWithLimits := append(args, limit, offset)

	rows, err := Db.Query(query, argsWithLimits...)
	if err != nil {
		return nil, 0, fmt.Errorf("failed to query categories: %v", err)
	}
	defer rows.Close()

	var categories []models.Category
	for rows.Next() {
		var category models.Category
		err := rows.Scan(&category.ID, &category.Name, &category.CreatedAt)
		if err != nil {
			return nil, 0, fmt.Errorf("failed to scan category: %v", err)
		}
		categories = append(categories, category)
	}

	if err = rows.Err(); err != nil {
		return nil, 0, fmt.Errorf("error iterating over category rows: %v", err)
	}

	if categories == nil {
		categories = []models.Category{}
	}

	return categories, total, nil
}

func GetAllCategoriesFromDB() ([]models.Category, error) {
	categories, _, err := GetCategoriesFromDB(0, 0, "", "")
	return categories, err
}

func CreateCategoryInDB(categoryDTO models.Category) (uuid.UUID, error) {

	newID := uuid.New()
	currentTime := getCurrentTime()

	_, err := Db.Exec("INSERT INTO categories (id, name, created_at) VALUES (?, ?, ?)", newID, categoryDTO.Name, currentTime)
	if err != nil {
		return uuid.Nil, fmt.Errorf("failed to insert category: %v", err)
	}

	return newID, nil
}

func GetCategoryByIDFromDB(id uuid.UUID) (models.Category, error) {
	var category models.Category
	err := Db.QueryRow("SELECT id, name, created_at FROM categories WHERE id = ?", id.String()).Scan(&category.ID, &category.Name, &category.CreatedAt)
	if err != nil {
		if err == sql.ErrNoRows {
			return models.Category{}, fmt.Errorf("category with ID %s not found", id.String())
		}

		return models.Category{}, fmt.Errorf("failed to query category: %v", err)
	}

	return category, nil
}

func UpdateCategoryInDB(id uuid.UUID, categoryDTO models.Category) error {

	_, err := Db.Exec("UPDATE categories SET name = ? WHERE id = ?", categoryDTO.Name, id.String())
	if err != nil {
		return fmt.Errorf("failed to update category: %v", err)
	}

	return nil
}

func DeleteCategoryFromDB(id uuid.UUID) error {

	_, err := Db.Exec("DELETE FROM categories WHERE id = ?", id.String())
	if err != nil {
		return fmt.Errorf("failed to delete category: %v", err)
	}

	return nil
}
