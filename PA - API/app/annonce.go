package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"
	"strconv"

	"github.com/google/uuid"
)

func GetAnnonces(w http.ResponseWriter, r *http.Request) {
	query := r.URL.Query()
	pageParam := query.Get("page")
	limitParam := query.Get("limit")

	if pageParam == "" && limitParam == "" {
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
		return
	}

	page := 1
	limit := 20
	if pageParam != "" {
		if parsed, err := strconv.Atoi(pageParam); err == nil && parsed > 0 {
			page = parsed
		}
	}
	if limitParam != "" {
		if parsed, err := strconv.Atoi(limitParam); err == nil && parsed > 0 {
			limit = parsed
		}
	}
	if limit > 100 {
		limit = 100
	}

	offset := (page - 1) * limit

	total, err := db.CountAnnoncesFromDB()
	if err != nil {
		fmt.Println("[ERROR] GetAnnonces count:", err)
		sendError(w, "Unable to fetch annonces", http.StatusInternalServerError)
		return
	}

	annonces, err := db.GetAnnoncesPageFromDB(limit, offset)
	if err != nil {
		fmt.Println("[ERROR] GetAnnonces page:", err)
		sendError(w, "Unable to fetch annonces", http.StatusInternalServerError)
		return
	}

	response := map[string]interface{}{
		"items": annonces,
		"total": total,
		"page":  page,
		"limit": limit,
	}

	w.Header().Set("Content-Type", "application/json")
	jsonResponse, err := json.Marshal(response)

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
