package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"math"
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

	if annonceDto.Price > 1000 {
		validationErrors = append(validationErrors, "Price cannot exceed 1000. Please contact support if you want to list an item above this price.")
	}

	if annonceDto.UserID == uuid.Nil {
		validationErrors = append(validationErrors, "UserID is required")
	}

	if annonceDto.Description == "" || len(annonceDto.Description) > 1000 {
		validationErrors = append(validationErrors, "Description must be between 1 and 1000 characters")
	}

	if annonceDto.PoidsMateriaux < 0 {
		validationErrors = append(validationErrors, "PoidsMateriaux cannot be negative")
	}
	if annonceDto.PoidsMateriaux > 0 && annonceDto.FacteurID == nil && annonceDto.TypeMateriaux == "" {
		validationErrors = append(validationErrors, "TypeMateriaux or FacteurID is required when PoidsMateriaux is provided")
	}
	if annonceDto.FacteurID != nil {
		if f, err := db.GetFacteurByID(annonceDto.FacteurID.String()); err != nil || f == nil {
			validationErrors = append(validationErrors, "FacteurID must reference an existing material")
		}
	}
	if strings.ToLower(annonceDto.TypeMateriaux) == "other" && annonceDto.EstimationScore <= 0 {
		validationErrors = append(validationErrors, "EstimationScore is required when material type is other")
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

	if annonceDto.EstimationScore > 0 {
		annonceDto.UpcyclingScore = annonceDto.EstimationScore
	} else {
		if annonceDto.FacteurID != nil {
			if f, _ := db.GetFacteurByID(annonceDto.FacteurID.String()); f == nil {
				annonceDto.FacteurID = nil
			}
		}
		annonceDto.UpcyclingScore = CalculateUpcyclingScore(annonceDto.PoidsMateriaux, annonceDto.FacteurID, annonceDto.TypeMateriaux)
		if annonceDto.FacteurID == nil && annonceDto.TypeMateriaux != "" {
			if f, _ := db.GetFacteurByName(annonceDto.TypeMateriaux); f != nil {
				annonceDto.FacteurID = &f.ID
			}
		}
	}

	annonceDto.ID = uuid.New()

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

		if annonceDto.Status > 0 {

			ann, annErr := db.GetAnnonceByIDFromDB(idStr)
			if annErr == nil && ann != nil {
				if ann.UpcyclingScore == 0 {
					ann.UpcyclingScore = CalculateUpcyclingScore(ann.PoidsMateriaux, ann.FacteurID, ann.TypeMateriaux)
					if ann.FacteurID == nil && ann.TypeMateriaux != "" {
						if f, _ := db.GetFacteurByName(ann.TypeMateriaux); f != nil {
							ann.FacteurID = &f.ID
						}
					}
					_ = db.UpdateAnnonceInDB(idStr, *ann)
				}
				if uid, parseErr := uuid.Parse(ann.UserID.String()); parseErr == nil {
					_ = db.UpdateUserUpcyclingScore(uid)
				}
				if buyerIDs, buyErr := db.GetAnnonceBuyerIDsFromDB(idStr); buyErr == nil {
					for _, buyerID := range buyerIDs {
						_ = db.UpdateUserUpcyclingScore(buyerID)
					}
				}
			}
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

	if annonceDto.EstimationScore > 0 {
		annonceDto.UpcyclingScore = annonceDto.EstimationScore
	} else {
		// drop invalid factor id when updating
		if annonceDto.FacteurID != nil {
			if f, _ := db.GetFacteurByID(annonceDto.FacteurID.String()); f == nil {
				annonceDto.FacteurID = nil
			}
		}
		annonceDto.UpcyclingScore = CalculateUpcyclingScore(annonceDto.PoidsMateriaux, annonceDto.FacteurID, annonceDto.TypeMateriaux)
		if annonceDto.FacteurID == nil && annonceDto.TypeMateriaux != "" {
			if f, _ := db.GetFacteurByName(annonceDto.TypeMateriaux); f != nil {
				annonceDto.FacteurID = &f.ID
			}
		}
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

func CalculateUpcyclingScore(poids float64, facteurID *uuid.UUID, matType string) float64 {
	if poids <= 0 {
		return 0
	}
	var f *models.FacteurMateriaux
	if facteurID != nil {
		if fetched, err := db.GetFacteurByID(facteurID.String()); err == nil {
			f = fetched
		}
	}
	if f == nil && matType != "" {
		if fetched, err := db.GetFacteurByName(matType); err == nil {
			f = fetched
		}
	}
	if f == nil {
		return 0
	}
	score := poids * f.FacteurCO2
	return math.Round(score*100) / 100
}

func CalculateScore(w http.ResponseWriter, r *http.Request) {
	q := r.URL.Query()
	poidsStr := q.Get("poids")
	matType := q.Get("matType")
	factIDStr := q.Get("facteurId")
	var score float64
	if poidsStr != "" {
		if p, err := strconv.ParseFloat(poidsStr, 64); err == nil {
			var factID *uuid.UUID
			if factIDStr != "" {
				if uid, err2 := uuid.Parse(factIDStr); err2 == nil {
					factID = &uid
				}
			}
			score = CalculateUpcyclingScore(p, factID, matType)
		}
	}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]float64{"score": score})
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

func IncrementAnnonceViewCount(w http.ResponseWriter, r *http.Request) {

	idStr := strings.TrimPrefix(r.URL.Path, "/annonces/")
	idStr = strings.TrimSuffix(idStr, "/views")

	if idStr == "" {
		sendError(w, "Annonce ID is required", http.StatusBadRequest)
		return
	}

	err := db.IncrementAnnonceViewCountInDB(idStr)

	if err != nil {
		fmt.Println("[ERROR] IncrementAnnonceViewCount DB:", err)
		sendError(w, "Unable to increment view count", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusNoContent)
}

func DeleteAnnonce(w http.ResponseWriter, r *http.Request) {
	idStr := strings.TrimPrefix(r.URL.Path, "/annonces/")
	if idStr == "" {
		sendError(w, "Annonce ID is required", http.StatusBadRequest)
		return
	}

	ann, err := db.GetAnnonceByIDFromDB(idStr)
	if err != nil || ann == nil {
		sendError(w, "Annonce not found", http.StatusNotFound)
		return
	}

	if deleteErr := db.DeleteAnnonceFromDB(idStr); deleteErr != nil {
		fmt.Println("[ERROR] DeleteAnnonce DB:", deleteErr)
		sendError(w, "Unable to delete annonce", http.StatusInternalServerError)
		return
	}

	if uid, parseErr := uuid.Parse(ann.UserID.String()); parseErr == nil {
		_ = db.UpdateUserUpcyclingScore(uid)
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusNoContent)
}

func AdminUpdateAnnonceStatus(w http.ResponseWriter, r *http.Request) {
	idStr := strings.TrimPrefix(r.URL.Path, "/annonces/")
	idStr = strings.TrimSuffix(idStr, "/status")

	if idStr == "" {
		sendError(w, "Annonce ID is required", http.StatusBadRequest)
		return
	}

	var body struct {
		Status int `json:"status"`
	}
	if err := json.NewDecoder(r.Body).Decode(&body); err != nil {
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}
	if body.Status < 0 || body.Status > 2 {
		sendError(w, "Status must be 0 (pending), 1 (approved) or 2 (rejected)", http.StatusBadRequest)
		return
	}

	ann, err := db.GetAnnonceByIDFromDB(idStr)
	if err != nil || ann == nil {
		sendError(w, "Annonce not found", http.StatusNotFound)
		return
	}

	if updateErr := db.AdminUpdateAnnonceStatusInDB(idStr, body.Status); updateErr != nil {
		fmt.Println("[ERROR] AdminUpdateAnnonceStatus DB:", updateErr)
		sendError(w, "Unable to update annonce status", http.StatusInternalServerError)
		return
	}

	if ann.UpcyclingScore == 0 && body.Status > 0 {
		ann.UpcyclingScore = CalculateUpcyclingScore(ann.PoidsMateriaux, ann.FacteurID, ann.TypeMateriaux)
		if ann.FacteurID == nil && ann.TypeMateriaux != "" {
			if f, _ := db.GetFacteurByName(ann.TypeMateriaux); f != nil {
				ann.FacteurID = &f.ID
			}
		}
		_ = db.UpdateAnnonceInDB(idStr, *ann)
	}
	if uid, parseErr := uuid.Parse(ann.UserID.String()); parseErr == nil {
		_ = db.UpdateUserUpcyclingScore(uid)
	}
	if buyerIDs, buyErr := db.GetAnnonceBuyerIDsFromDB(idStr); buyErr == nil {
		for _, buyerID := range buyerIDs {
			_ = db.UpdateUserUpcyclingScore(buyerID)
		}
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusNoContent)
}
