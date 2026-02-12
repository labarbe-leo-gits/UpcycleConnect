package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"

	"github.com/google/uuid"
)

func GetAnnonces(w http.ResponseWriter, r *http.Request) {

	annonces, err := db.GetAnnoncesFromDB()

	if err != nil {
		fmt.Println("[ERROR] GetAnnonces:", err)
		sendError(w, "Unable to fetch annonces", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	jsonResponse, err := json.Marshal(annonces)

	if err != nil {
		fmt.Println("[ERROR] GetAnnonces marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)

}

func ValidateAnnonceDto(annonceDto models.Annonce) []string {

	var validationErrors []string
	if annonceDto.Title == "" {
		validationErrors = append(validationErrors, "Title is required")
	}

	if annonceDto.Price < 0 {
		validationErrors = append(validationErrors, "Price cannot be negative")
	}

	if annonceDto.UserID == uuid.Nil {
		validationErrors = append(validationErrors, "UserID is required")
	}

	if annonceDto.Description == "" || len(annonceDto.Description) > 1000 {
		validationErrors = append(validationErrors, "Description must be between 1 and 1000 characters")
	}

	return validationErrors
}

func CreateAnnonce(w http.ResponseWriter, r *http.Request) {

	var annonceDto models.Annonce
	err := json.NewDecoder(r.Body).Decode(&annonceDto)

	if err != nil {
		fmt.Println("[ERROR] CreateAnnonce decode:", err)
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	validationErrors := ValidateAnnonceDto(annonceDto)

	if len(validationErrors) > 0 {
		fmt.Println("[ERROR] CreateAnnonce validation:", validationErrors)
		sendError(w, "Validation errors: "+fmt.Sprintf("%v", validationErrors), http.StatusBadRequest)
		return
	}

	err = db.CreateAnnonceInDB(annonceDto)

	if err != nil {
		fmt.Println("[ERROR] CreateAnnonce DB:", err)
		sendError(w, "Unable to create annonce", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(annonceDto)
}
