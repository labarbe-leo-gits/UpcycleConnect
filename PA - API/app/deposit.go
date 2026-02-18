package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"
	"strings"

	"github.com/google/uuid"
)

func GetDeposits(w http.ResponseWriter, r *http.Request) {

	deposits, err := db.GetAllDepositsFromDB()
	if err != nil {
		http.Error(w, "Failed to retrieve deposits", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(deposits)

}

func ValidateDepositDto(depositDto models.Deposit) []string {

	var validationErrors []string

	if depositDto.UserID == uuid.Nil {
		validationErrors = append(validationErrors, "UserID is required and must be a valid UUID")
	}

	if depositDto.ConteneurID == uuid.Nil {
		validationErrors = append(validationErrors, "ConteneurID is required and must be a valid UUID")
	}

	if depositDto.ObjectName == "" {
		validationErrors = append(validationErrors, "ObjectName is required")
	}

	if depositDto.ObjectDescription == "" {
		validationErrors = append(validationErrors, "ObjectDescription is required")
	}

	return validationErrors
}

func CreateDeposit(w http.ResponseWriter, r *http.Request) {

	var depositDto models.Deposit

	err := json.NewDecoder(r.Body).Decode(&depositDto)
	if err != nil {
		http.Error(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	validationErrors := ValidateDepositDto(depositDto)
	if len(validationErrors) > 0 {
		http.Error(w, fmt.Sprintf("Validation errors: %v", validationErrors), http.StatusBadRequest)
		return
	}

	newID, err := db.CreateDepositInDB(depositDto)
	if err != nil {
		http.Error(w, "Failed to create deposit", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]interface{}{
		"id": newID,
	})
}

func UpdateDepositStatus(w http.ResponseWriter, r *http.Request) {

	var requestData struct {
		Status int `json:"status"`
	}

	err := json.NewDecoder(r.Body).Decode(&requestData)
	if err != nil {
		http.Error(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	idStr := r.URL.Query().Get("id")
	if idStr == "" {
		http.Error(w, "Deposit ID is required", http.StatusBadRequest)
		return
	}

	err = db.UpdateDepositStatusInDB(idStr, requestData.Status)
	if err != nil {
		http.Error(w, "Failed to update deposit status", http.StatusInternalServerError)
		return
	}

	w.WriteHeader(http.StatusNoContent)

}

func GetDepositByID(w http.ResponseWriter, r *http.Request) {
	idStr := r.URL.Query().Get("id")
	if idStr == "" {
		parts := strings.Split(strings.Trim(r.URL.Path, "/"), "/")
		if len(parts) >= 4 && parts[len(parts)-2] == "deposits" {
			idStr = parts[len(parts)-1]
		}
	}

	if idStr == "" {
		http.Error(w, "Deposit ID is required", http.StatusBadRequest)
		return
	}

	deposit, err := db.GetDepositByIDFromDB(idStr)
	if err != nil {
		fmt.Println("[ERROR] GetDepositByID - id=", idStr, "error=", err)
		if strings.Contains(err.Error(), "no rows") {
			http.Error(w, "Deposit not found", http.StatusNotFound)
			return
		}

		http.Error(w, "Failed to retrieve deposit", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(deposit)

}
