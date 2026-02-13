package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"
	"strconv"
	"strings"

	"github.com/google/uuid"
)

func GetAnnonces(w http.ResponseWriter, r *http.Request) {
	query := r.URL.Query()
	pageParam := query.Get("page")
	limitParam := query.Get("limit")
	statusParam := query.Get("status")
	statusFilter := (*int)(nil)
	if statusParam != "" {
		parsedStatus, err := strconv.Atoi(statusParam)
		if err != nil {
			sendError(w, "Invalid status value", http.StatusBadRequest)
			return
		}
		statusFilter = &parsedStatus
	}

	if pageParam == "" && limitParam == "" {
		var annonces []models.Annonce
		var err error
		if statusFilter != nil {
			annonces, err = db.GetAnnoncesByStatusFromDB(*statusFilter)
		} else {
			annonces, err = db.GetAnnoncesFromDB()
		}

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

	var total int
	var err error
	if statusFilter != nil {
		total, err = db.CountAnnoncesByStatusFromDB(*statusFilter)
	} else {
		total, err = db.CountAnnoncesFromDB()
	}
	if err != nil {
		fmt.Println("[ERROR] GetAnnonces count:", err)
		sendError(w, "Unable to fetch annonces", http.StatusInternalServerError)
		return
	}

	var annonces []models.Annonce
	if statusFilter != nil {
		annonces, err = db.GetAnnoncesPageByStatusFromDB(limit, offset, *statusFilter)
	} else {
		annonces, err = db.GetAnnoncesPageFromDB(limit, offset)
	}
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

func UpdateAnnonce(w http.ResponseWriter, r *http.Request) {

	idStr := r.URL.Query().Get("id")
	if idStr == "" {
		path := strings.TrimPrefix(r.URL.Path, "/annonces/")
		if path != "" {
			parts := strings.Split(path, "/")
			if len(parts) > 0 {
				idStr = parts[0]
			}
		}
	}
	if idStr == "" {
		sendError(w, "Annonce ID is required", http.StatusBadRequest)
		return
	}

	var annonceDto models.Annonce
	err := json.NewDecoder(r.Body).Decode(&annonceDto)

	if err != nil {
		fmt.Println("[ERROR] UpdateAnnonce decode:", err)
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	if annonceDto.Title == "" && annonceDto.Description == "" && annonceDto.UserID == uuid.Nil && annonceDto.Price == 0 {
		updated, updateErr := db.UpdateAnnonceStatusInDB(idStr, annonceDto.Status)
		if updateErr != nil {
			fmt.Println("[ERROR] UpdateAnnonce status DB:", updateErr)
			sendError(w, "Unable to update annonce", http.StatusInternalServerError)
			return
		}
		if !updated {
			sendError(w, "Annonce not available", http.StatusConflict)
			return
		}
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusNoContent)
		return
	}

	validationErrors := ValidateAnnonceDto(annonceDto)

	if len(validationErrors) > 0 {
		fmt.Println("[ERROR] UpdateAnnonce validation:", validationErrors)
		sendError(w, "Validation errors: "+fmt.Sprintf("%v", validationErrors), http.StatusBadRequest)
		return
	}

	err = db.UpdateAnnonceInDB(idStr, annonceDto)

	if err != nil {
		fmt.Println("[ERROR] UpdateAnnonce DB:", err)
		sendError(w, "Unable to update annonce", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusNoContent)

}

func GetAnnonceByID(w http.ResponseWriter, r *http.Request) {
	idStr := r.URL.Query().Get("id")

	if idStr == "" {
		path := strings.TrimPrefix(r.URL.Path, "/annonces/")
		if path != "" {
			parts := strings.Split(path, "/")
			if len(parts) > 0 {
				idStr = parts[0]
			}
		}
	}

	if idStr == "" || idStr == "images" {
		sendError(w, "Annonce ID is required", http.StatusBadRequest)
		return
	}

	annonce, err := db.GetAnnonceByIDFromDB(idStr)

	if err != nil {
		fmt.Println("[ERROR] GetAnnonceByID DB:", err)
		sendError(w, "Unable to fetch annonce", http.StatusInternalServerError)
		return
	}

	if annonce == nil {
		sendError(w, "Annonce not found", http.StatusNotFound)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(annonce)

}

func GetAnnoncesByUserID(w http.ResponseWriter, r *http.Request) {

	idStr := strings.TrimPrefix(r.URL.Path, "/users/")

	idStr = strings.TrimSuffix(idStr, "/annonces")

	userID, err := uuid.Parse(idStr)

	if err != nil {
		fmt.Println("[ERROR] GetAnnoncesByUserID parse UUID:", err)
		sendError(w, "Invalid user ID format", http.StatusBadRequest)
		return
	}

	annonces, err := db.GetAnnoncesByUserIDFromDB(userID.String())

	if err != nil {
		fmt.Println("[ERROR] GetAnnoncesByUserID DB:", err)
		sendError(w, "Unable to fetch annonces for user", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	jsonResponse, err := json.Marshal(annonces)

	if err != nil {
		fmt.Println("[ERROR] GetAnnoncesByUserID marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)

}
