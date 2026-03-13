package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"
	"strconv"
	"strings"
)

func GetCategories(w http.ResponseWriter, r *http.Request) {

	categories, err := db.GetAllCategoriesFromDB()
	if err != nil {
		sendError(w, fmt.Sprintf("Failed to retrieve categories: %s", err.Error()), http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(categories)
}

func ValidateCategoryDTO(categoryDTO models.Category) error {

	if strings.TrimSpace(categoryDTO.Name) == "" {
		return fmt.Errorf("Name is required")
	}

	return nil
}

func CreateCategory(w http.ResponseWriter, r *http.Request) {

	var categoryDTO models.Category

	err := json.NewDecoder(r.Body).Decode(&categoryDTO)
	if err != nil {
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	if err := ValidateCategoryDTO(categoryDTO); err != nil {
		sendError(w, fmt.Sprintf("Validation error: %s", err.Error()), http.StatusBadRequest)
		return
	}

	id, err := db.CreateCategoryInDB(categoryDTO)
	if err != nil {
		sendError(w, fmt.Sprintf("Failed to create category: %s", err.Error()), http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]string{"id": strconv.Itoa(id)})

}

func UpdateCategory(w http.ResponseWriter, r *http.Request) {

	idStr := strings.TrimPrefix(r.URL.Path, "/categories/")
	id, err := strconv.Atoi(idStr)

	if err != nil {
		sendError(w, "Invalid category ID", http.StatusBadRequest)
		return
	}

	var categoryDTO models.Category
	err = json.NewDecoder(r.Body).Decode(&categoryDTO)

	if err != nil {
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	existingCategory, err := db.GetCategoryByIDFromDB(id)	
	if err != nil {
		sendError(w, fmt.Sprintf("Failed to retrieve category: %s", err.Error()), http.StatusInternalServerError)
		return
	}

	if strings.TrimSpace(categoryDTO.Name) == "" {
		categoryDTO.Name = existingCategory.Name
	}

	if err := ValidateCategoryDTO(categoryDTO); err != nil {
		sendError(w, fmt.Sprintf("Validation error: %s", err.Error()), http.StatusBadRequest)
		return
	}

	err = db.UpdateCategoryInDB(id, categoryDTO)
	if err != nil {
		sendError(w, fmt.Sprintf("Failed to update category: %s", err.Error()), http.StatusInternalServerError)
		return
	}

	w.WriteHeader(http.StatusNoContent)
}

func DeleteCategory(w http.ResponseWriter, r *http.Request) {

	idStr := strings.TrimPrefix(r.URL.Path, "/categories/")
	id, err := strconv.Atoi(idStr)

	if err != nil {
		sendError(w, "Invalid category ID", http.StatusBadRequest)
		return
	}

	err = db.DeleteCategoryFromDB(id)
	if err != nil {
		sendError(w, fmt.Sprintf("Failed to delete category: %s", err.Error()), http.StatusInternalServerError)

		return
	}

	w.WriteHeader(http.StatusNoContent)

}

func GetCategoryByID(w http.ResponseWriter, r *http.Request) {	

	idStr := strings.TrimPrefix(r.URL.Path, "/categories/")
	id, err := strconv.Atoi(idStr)

	if err != nil {
		sendError(w, "Invalid category ID", http.StatusBadRequest)
		return
	}

	category, err := db.GetCategoryByIDFromDB(id)
	if err != nil {
		sendError(w, fmt.Sprintf("Failed to retrieve category: %s", err.Error()), http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(category)

}
