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

	var requestData models.UpdateDepositStatusDto

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

func CreateDepositFiles(w http.ResponseWriter, r *http.Request) {
	depositIDStr := r.PathValue("id")
	if depositIDStr == "" {
		http.Error(w, "Missing deposit ID", http.StatusBadRequest)
		return
	}

	depositID, err := uuid.Parse(depositIDStr)
	if err != nil {
		http.Error(w, "Invalid deposit ID", http.StatusBadRequest)
		return
	}

	var inputs []models.DepositFileInput
	if err := json.NewDecoder(r.Body).Decode(&inputs); err != nil {
		http.Error(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	count := 0
	for _, inp := range inputs {
		if inp.Filename == "" || inp.OriginalName == "" {
			continue
		}
		f := models.DepositFile{
			DepositID:    depositID,
			Filename:     inp.Filename,
			OriginalName: inp.OriginalName,
		}
		if _, err := db.CreateDepositFileInDB(f); err != nil {
			fmt.Println("[ERROR] CreateDepositFiles:", err)
			http.Error(w, "Failed to save file record", http.StatusInternalServerError)
			return
		}
		count++
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]interface{}{"created": count})
}

func GetDepositFiles(w http.ResponseWriter, r *http.Request) {
	depositIDStr := r.PathValue("id")
	if depositIDStr == "" {
		http.Error(w, "Missing deposit ID", http.StatusBadRequest)
		return
	}

	files, err := db.GetDepositFilesByDepositIDFromDB(depositIDStr)
	if err != nil {
		fmt.Println("[ERROR] GetDepositFiles:", err)
		http.Error(w, "Failed to retrieve deposit files", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(files)
}
