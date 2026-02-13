package app

import (
	"API/db"
	"encoding/json"
	"fmt"
	"net/http"
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
