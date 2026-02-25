package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"

	"github.com/google/uuid"
)

func GetTips(w http.ResponseWriter, r *http.Request) {

	tips, err := db.GetTipsFromDB()
	if err != nil {
		fmt.Println("[ERROR] GetTips:", err)
		sendError(w, "Unable to fetch tips", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(tips)
}

func ValidateTipDto(tipDto models.Tip, isUpdate bool) []string {

	var validationErrors []string

	if tipDto.Title == "" {
		validationErrors = append(validationErrors, "Title is required")
	}

	if tipDto.Description == "" {
		validationErrors = append(validationErrors, "Description is required")
	}

	if !isUpdate {
		if tipDto.CreatedBy == uuid.Nil {
			validationErrors = append(validationErrors, "CreatedBy is required and must be a valid UUID")
		}
	} else {
		if tipDto.UpdatedBy == uuid.Nil {
			validationErrors = append(validationErrors, "UpdatedBy is required and must be a valid UUID")
		}
	}

	return validationErrors
}

func CreateTip(w http.ResponseWriter, r *http.Request) {

	var tipDto models.Tip

	err := json.NewDecoder(r.Body).Decode(&tipDto)

	if err != nil {
		http.Error(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	validationErrors := ValidateTipDto(tipDto, false)

	if len(validationErrors) > 0 {
		fmt.Println("[ERROR] CreateTip validation:", validationErrors)
		sendError(w, fmt.Sprintf("Validation errors: %s", validationErrors), http.StatusBadRequest)
		return
	}

	newID, err := db.CreateTipInDB(tipDto)

	if err != nil {
		fmt.Println("[ERROR] CreateTip DB insert:", err)
		sendError(w, "Unable to create tip", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]string{"id": newID.String()})

}

func UpdateTip(w http.ResponseWriter, r *http.Request) {

	idStr := r.URL.Path[len("/tips/"):]
	tipID, err := uuid.Parse(idStr)

	if err != nil {
		fmt.Println("[ERROR] UpdateTip parse UUID:", err)
		sendError(w, "Invalid tip ID format", http.StatusBadRequest)
		return
	}

	var tipDto models.Tip

	err = json.NewDecoder(r.Body).Decode(&tipDto)

	if err != nil {
		fmt.Println("[ERROR] UpdateTip decode:", err)
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	validationErrors := ValidateTipDto(tipDto, true)

	if len(validationErrors) > 0 {
		fmt.Println("[ERROR] UpdateTip validation:", validationErrors)
		sendError(w, fmt.Sprintf("Validation errors: %s", validationErrors), http.StatusBadRequest)
		return
	}

	err = db.UpdateTipInDB(tipID, tipDto)

	if err != nil {
		fmt.Println("[ERROR] UpdateTip DB update:", err)
		sendError(w, "Unable to update tip", http.StatusInternalServerError)
		return
	}

	w.WriteHeader(http.StatusNoContent)
}

func DeleteTip(w http.ResponseWriter, r *http.Request) {

	idStr := r.URL.Path[len("/tips/"):]
	tipID, err := uuid.Parse(idStr)

	if err != nil {
		fmt.Println("[ERROR] DeleteTip parse UUID:", err)
		sendError(w, "Invalid tip ID format", http.StatusBadRequest)
		return
	}

	err = db.DeleteTipFromDB(tipID)

	if err != nil {
		fmt.Println("[ERROR] DeleteTip DB delete:", err)
		sendError(w, "Unable to delete tip", http.StatusInternalServerError)
		return
	}

	w.WriteHeader(http.StatusNoContent)

}
