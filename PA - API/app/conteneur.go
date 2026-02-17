package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"
	"strings"
)

func GetConteneurs(w http.ResponseWriter, r *http.Request) {

	conteneurs, err := db.GetAllConteneursFromDB()
	if err != nil {
		http.Error(w, "Failed to retrieve conteneurs", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(conteneurs)

}

func ValidateConteneurDto(conteneurDto models.Conteneur) []string {

	var validationErrors []string

	if conteneurDto.Name == "" {
		validationErrors = append(validationErrors, "Name is required")
	}

	if conteneurDto.City == "" {
		validationErrors = append(validationErrors, "City is required")
	}

	if conteneurDto.Road == "" {
		validationErrors = append(validationErrors, "Road is required")
	}

	if conteneurDto.PostalCode <= 0 {
		validationErrors = append(validationErrors, "PostalCode is required and must be a positive integer")
	}

	if conteneurDto.PostalCode > 99999 {
		validationErrors = append(validationErrors, "PostalCode must be a valid postal code")
	}

	if conteneurDto.Number <= 0 {
		validationErrors = append(validationErrors, "Number is required and must be a positive integer")
	}

	return validationErrors
}

func CreateConteneur(w http.ResponseWriter, r *http.Request) {

	var conteneurDto models.Conteneur

	err := json.NewDecoder(r.Body).Decode(&conteneurDto)

	if err != nil {
		fmt.Println("[ERROR] CreateConteneur - JSON decode error:", err)
		http.Error(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	validationErrors := ValidateConteneurDto(conteneurDto)

	if len(validationErrors) > 0 {
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]interface{}{
			"errors": validationErrors,
		})
		return
	}

	createdConteneur, err := db.CreateConteneurInDB(conteneurDto)

	if err != nil {
		fmt.Println("[ERROR] CreateConteneur - DB error:", err)
		http.Error(w, "Failed to create conteneur", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(createdConteneur)

}

func GetConteneurByID(w http.ResponseWriter, r *http.Request) {

	id := strings.TrimPrefix(r.URL.Path, "/conteneurs/")
	if id == "" {
		http.Error(w, "Conteneur ID is required", http.StatusBadRequest)
		return
	}

	conteneur, err := db.GetConteneurByIDFromDB(id)
	if err != nil {
		if strings.Contains(err.Error(), "no rows in result set") {
			http.Error(w, "Conteneur not found", http.StatusNotFound)
			return
		}

		fmt.Println("[ERROR] GetConteneurByID - DB error:", err)
		http.Error(w, "Failed to retrieve conteneur", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(conteneur)

}

func UpdateConteneur(w http.ResponseWriter, r *http.Request) {

	id := r.URL.Query().Get("id")

	err := json.NewDecoder(r.Body).Decode(&models.Conteneur{})

	if err != nil {
		fmt.Println("[ERROR] UpdateConteneur - JSON decode error:", err)
		http.Error(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	err2 := db.UpdateConteneurInDB(id, models.Conteneur{})

	if err2 != nil {
		fmt.Println("[ERROR] UpdateConteneur - DB error:", err)
		http.Error(w, "Failed to update conteneur", http.StatusInternalServerError)
		return
	}

	w.WriteHeader(http.StatusNoContent)

}
