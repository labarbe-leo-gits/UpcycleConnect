package db

import (
	"API/models"
	"database/sql"
	"fmt"

	"github.com/google/uuid"
)

func GetAllCategoriesFromDB() ([]models.Category, error) {

	rows, err := Db.Query("SELECT id, name, created_at FROM categories")
	if err != nil {
		return nil, fmt.Errorf("failed to query categories: %v", err)
	}

	defer rows.Close()

	var categories []models.Category

	for rows.Next() {
		var category models.Category
		err := rows.Scan(&category.ID, &category.Name, &category.CreatedAt)

		if err != nil {
			return nil, fmt.Errorf("failed to scan category: %v", err)
		}

		categories = append(categories, category)
	}

	if err = rows.Err(); err != nil {
		return nil, fmt.Errorf("error iterating over category rows: %v", err)
	}

	if categories == nil {
		categories = []models.Category{}
	}

	return categories, nil
}

func CreateCategoryInDB(categoryDTO models.Category) (int, error) {

	newID := uuid.New()
	currentTime := getCurrentTime()

	result, err := Db.Exec("INSERT INTO categories (id, name, created_at) VALUES (?, ?, ?)", newID, categoryDTO.Name, currentTime)
	if err != nil {
		return 0, fmt.Errorf("failed to insert category: %v", err)
	}

	insertedID, err := result.LastInsertId()
	if err != nil {
		return 0, fmt.Errorf("failed to retrieve last insert ID: %v", err)
	}

	return int(insertedID), nil
}

func GetCategoryByIDFromDB(id int) (models.Category, error) {
	var category models.Category
	err := Db.QueryRow("SELECT id, name, created_at FROM categories WHERE id = ?", id).Scan(&category.ID, &category.Name, &category.CreatedAt)
	if err != nil {
		if err == sql.ErrNoRows {
			return models.Category{}, fmt.Errorf("category with ID %d not found", id)
		}

		return models.Category{}, fmt.Errorf("failed to query category: %v", err)
	}

	return category, nil
}

func UpdateCategoryInDB(id int, categoryDTO models.Category) error {

	_, err := Db.Exec("UPDATE categories SET name = ? WHERE id = ?", categoryDTO.Name, id)
	if err != nil {
		return fmt.Errorf("failed to update category: %v", err)
	}

	return nil
}

func DeleteCategoryFromDB(id int) error {

	_, err := Db.Exec("DELETE FROM categories WHERE id = ?", id)
	if err != nil {
		return fmt.Errorf("failed to delete category: %v", err)
	}

	return nil
}
