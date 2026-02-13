package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"

	"github.com/google/uuid"
)

func GetNotifications(w http.ResponseWriter, r *http.Request) {

	notifications, err := db.GetNotificationsFromDB()

	if err != nil {
		fmt.Println("[ERROR] GetNotifications:", err)
		sendError(w, "Unable to fetch notifications", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(notifications)

}

func ValidateNotificationDto(notificationDto models.Notification) []string {

	var validationErrors []string

	if notificationDto.UserID == uuid.Nil {
		validationErrors = append(validationErrors, "UserID is required and must be a valid UUID")
	}

	if notificationDto.AnnonceID != uuid.Nil {
		annonce, err := db.GetAnnonceByIDFromDB(notificationDto.AnnonceID.String())
		if err != nil {
			validationErrors = append(validationErrors, "AnnonceID does not exist")
		}

		if annonce.UserID != notificationDto.UserID {
			validationErrors = append(validationErrors, "AnnonceID must belong to the user")
		}

	}

	if notificationDto.Message == "" {
		validationErrors = append(validationErrors, "Message is required")
	}

	return validationErrors
}

func CreateNotification(w http.ResponseWriter, r *http.Request) {

	var notificationDto models.Notification

	err := json.NewDecoder(r.Body).Decode(&notificationDto)

	if err != nil {
		fmt.Println("[ERROR] CreateNotification decode:", err)
		sendError(w, "Unable to process request body", http.StatusBadRequest)
		return
	}

	validationErrors := ValidateNotificationDto(notificationDto)

	if len(validationErrors) > 0 {
		fmt.Println("[ERROR] CreateNotification validation:", validationErrors)
		sendError(w, "Validation failed: "+fmt.Sprintf("%v", validationErrors), http.StatusBadRequest)
		return
	}

	err = db.CreateNotificationInDB(notificationDto)

	if err != nil {
		fmt.Println("[ERROR] CreateNotification DB:", err)
		sendError(w, "Unable to create notification", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(notificationDto)

}

func MarkNotificationAsRead(w http.ResponseWriter, r *http.Request) {

	notificationID := r.URL.Path[len("/notifications/") : len(r.URL.Path)-len("/read")]

	err := db.MarkNotificationAsReadInDB(notificationID)

	if err != nil {
		fmt.Println("[ERROR] MarkNotificationAsRead:", err)
		sendError(w, "Unable to mark notification as read", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]bool{"success": true})

}
