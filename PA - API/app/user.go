// User-related handlers for the API

package app

import (
	"API/db"
	"encoding/json"
	"fmt"
	"net/http"
)

func GetAllUsers(w http.ResponseWriter, r *http.Request) {
	users, err := db.GetAllUsersFromDB()

	if err != nil {
		http.Error(w, "Error fetching users", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	jsonResponse, err := json.Marshal(users)

	if err != nil {
		http.Error(w, "Error encoding users to JSON", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)
}
