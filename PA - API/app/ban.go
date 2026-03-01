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

func ValidateBanDTO(banDTO models.Ban) error {

	validationErrors := []string{}

	if banDTO.UserID == uuid.Nil {
		validationErrors = append(validationErrors, "user_id is required and must be a valid UUID")
	}

	if banDTO.Reason == "" {
		validationErrors = append(validationErrors, "reason is required")
	}

	if banDTO.DurationDays < 0 {
		validationErrors = append(validationErrors, "duration_days must be a non-negative integer")
	}

	if banDTO.BannedBy == uuid.Nil {
		validationErrors = append(validationErrors, "banned_by is required and must be a valid UUID")
	}

	if len(validationErrors) > 0 {
		return fmt.Errorf("validation errors: %s", strings.Join(validationErrors, "; "))
	}

	return nil

}

func CreateBan(w http.ResponseWriter, r *http.Request) {

	var banDTO models.Ban
	err := json.NewDecoder(r.Body).Decode(&banDTO)
	fmt.Println("[DEBUG] CreateBan payload:", banDTO)
	if err != nil {
		fmt.Println("[ERROR] CreateBan decode:", err)
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	err = ValidateBanDTO(banDTO)
	if err != nil {
		fmt.Println("[ERROR] CreateBan validation:", err)
		sendError(w, err.Error(), http.StatusBadRequest)
		return
	}

	err = db.CreateBanRecord(banDTO.UserID, banDTO.Reason, banDTO.BannedBy, banDTO.DurationDays)
	if err != nil {
		fmt.Println("[ERROR] CreateBan DB:", err)
		sendError(w, "Unable to create ban record", http.StatusInternalServerError)
		return
	}
	fmt.Println("[DEBUG] CreateBan success for user", banDTO.UserID)

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(banDTO)

}

func DeleteBan(w http.ResponseWriter, r *http.Request) {

	banIDStr := strings.TrimPrefix(r.URL.Path, "/ban/")
	banID, err := uuid.Parse(banIDStr)

	if err != nil {
		fmt.Println("[ERROR] DeleteBan parse UUID:", err)
		sendError(w, "Invalid ban ID", http.StatusBadRequest)
		return
	}

	err = db.DeleteBanRecord(banID)
	if err != nil {
		fmt.Println("[ERROR] DeleteBan DB:", err)
		sendError(w, "Unable to delete ban record", http.StatusInternalServerError)
		return
	}

	w.WriteHeader(http.StatusNoContent)
}
