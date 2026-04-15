package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"
	"os"
	"path/filepath"
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
		parts := strings.Split(strings.Trim(r.URL.Path, "/"), "/")
		n := len(parts)
		if n >= 3 && parts[n-3] == "deposits" && parts[n-1] == "status" {
			idStr = parts[n-2]
		} else if n >= 2 && parts[n-2] == "deposits" {
			idStr = parts[n-1]
		}
	}

	if idStr == "" && requestData.ID != "" {
		idStr = requestData.ID
	}

	if idStr == "" {
		http.Error(w, "Deposit ID is required", http.StatusBadRequest)
		return
	}

	err = db.UpdateDepositStatusInDB(idStr, requestData.Status)
	if err != nil {
		http.Error(w, "Failed to update deposit status", http.StatusInternalServerError)
		return
	}

	depositOwner, ownerErr := db.GetDepositByIDFromDB(idStr)
	if ownerErr == nil && depositOwner.UserID != uuid.Nil {
		actorName := "the pro team"
		if rawUserID := r.Context().Value("user_id"); rawUserID != nil {
			if userIDStr, ok := rawUserID.(string); ok {
				if userID, err := uuid.Parse(userIDStr); err == nil {
					user, err := db.GetUserByIDFromDB(userID)
					if err == nil {
						fullName := strings.TrimSpace(user.FirstName + " " + user.LastName)
						if fullName != "" {
							actorName = fullName
						} else if user.Username != "" {
							actorName = user.Username
						} else if user.CompanyName != "" {
							actorName = user.CompanyName
						}
					}
				}
			}
		}

		notificationText := ""
		switch requestData.Status {
		case 1:
			notificationText = fmt.Sprintf("Your deposit request '%s' is now pending.", depositOwner.ObjectName)
		case 2:
			notificationText = fmt.Sprintf("Your deposit request '%s' has been accepted. Please mark it as deposited on your side.", depositOwner.ObjectName)
		case 3:
			notificationText = fmt.Sprintf("Your deposit request '%s' has been rejected by %s.", depositOwner.ObjectName, actorName)
		case 4:
			notificationText = fmt.Sprintf("Your deposit request '%s' has been marked as deposited by %s.", depositOwner.ObjectName, actorName)
		case 5:
			notificationText = fmt.Sprintf("Your deposit request '%s' is now completed by %s.", depositOwner.ObjectName, actorName)
		}

		if notificationText != "" {
			notif := models.Notification{
				UserID:  depositOwner.UserID,
				Message: notificationText,
			}
			if err := db.CreateNotificationInDB(notif); err != nil {
				fmt.Println("[ERROR] UpdateDepositStatus CreateNotificationInDB:", err)
			}
		}
	} else if ownerErr != nil {
		fmt.Println("[WARN] UpdateDepositStatus get deposit owner failed:", ownerErr)
	}

	deposit, err := db.GetDepositByIDFromDB(idStr)
	if err != nil {
		fmt.Println("[ERROR] UpdateDepositStatus - GetDepositByID after status update failed:", err)
		w.WriteHeader(http.StatusNoContent)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(deposit)

}

func UpdateDeposit(w http.ResponseWriter, r *http.Request) {
	idStr := r.URL.Query().Get("id")
	if idStr == "" {
		parts := strings.Split(strings.Trim(r.URL.Path, "/"), "/")
		n := len(parts)
		if n >= 2 && parts[n-2] == "deposits" {
			idStr = parts[n-1]
		}
	}

	if idStr == "" {
		http.Error(w, "Deposit ID is required", http.StatusBadRequest)
		return
	}

	var requestData struct {
		ConteneurID       string `json:"conteneur_id"`
		ObjectName        string `json:"object_name"`
		ObjectDescription string `json:"object_description"`
		ObjectState       int    `json:"object_state"`
	}

	err := json.NewDecoder(r.Body).Decode(&requestData)
	if err != nil {
		http.Error(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	deposit, err := db.GetDepositByIDFromDB(idStr)
	if err != nil {
		http.Error(w, "Deposit not found", http.StatusNotFound)
		return
	}

	callerID, _ := r.Context().Value("user_id").(string)
	if callerID == "" || callerID != deposit.UserID.String() {
		http.Error(w, "Unauthorized", http.StatusForbidden)
		return
	}

	if deposit.Status != 1 {
		http.Error(w, "Only pending deposits can be edited", http.StatusForbidden)
		return
	}

	if err := db.UpdateDepositInDB(idStr, requestData.ConteneurID, requestData.ObjectName, requestData.ObjectDescription, requestData.ObjectState); err != nil {
		http.Error(w, err.Error(), http.StatusBadRequest)
		return
	}

	updatedDeposit, err := db.GetDepositByIDFromDB(idStr)
	if err != nil {
		logMsg := "Failed to fetch updated deposit"
		fmt.Println("[ERROR] UpdateDeposit -", err)
		http.Error(w, logMsg, http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(updatedDeposit)
}

func DeleteDepositFile(w http.ResponseWriter, r *http.Request) {
	depositIDStr := r.PathValue("id")
	fileIDStr := r.PathValue("fileId")
	if depositIDStr == "" || fileIDStr == "" {
		http.Error(w, "Missing deposit or file ID", http.StatusBadRequest)
		return
	}

	deposit, err := db.GetDepositByIDFromDB(depositIDStr)
	if err != nil {
		http.Error(w, "Deposit not found", http.StatusNotFound)
		return
	}

	callerID, _ := r.Context().Value("user_id").(string)
	if callerID == "" || callerID != deposit.UserID.String() {
		http.Error(w, "Unauthorized", http.StatusForbidden)
		return
	}

	if deposit.Status != 1 {
		http.Error(w, "Only pending deposits can be modified", http.StatusForbidden)
		return
	}

	fileRecord, err := db.GetDepositFileByIDFromDB(fileIDStr)
	if err != nil {
		http.Error(w, "File not found", http.StatusNotFound)
		return
	}

	if fileRecord.DepositID.String() != deposit.ID.String() {
		http.Error(w, "File does not belong to the deposit", http.StatusForbidden)
		return
	}

	if err := db.DeleteDepositFileFromDB(fileIDStr); err != nil {
		http.Error(w, "Failed to delete file record", http.StatusInternalServerError)
		return
	}

	storageDir := filepath.Join("..", "files", "uploads", "deposit")
	filePath := filepath.Join(storageDir, fileRecord.Filename)
	if err := os.Remove(filePath); err != nil && !os.IsNotExist(err) {
		fmt.Println("[WARN] DeleteDepositFile - failed to remove file from disk:", err)
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"status": "ok"})
}

func GetDepositByID(w http.ResponseWriter, r *http.Request) {
	idStr := r.URL.Query().Get("id")
	if idStr == "" {
		parts := strings.Split(strings.Trim(r.URL.Path, "/"), "/")
		if len(parts) >= 2 && parts[len(parts)-2] == "deposits" {
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
