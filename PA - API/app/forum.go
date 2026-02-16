package app

import (
	"API/db"
	"encoding/json"
	"fmt"
	"net/http"
)

func sendError(w http.ResponseWriter, message string, statusCode int) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(statusCode)
	json.NewEncoder(w).Encode(map[string]string{"error": message})
}

func GetForums(w http.ResponseWriter, r *http.Request) {

	forums, err := db.GetForumsFromDB()
	if err != nil {
		fmt.Println("[ERROR] GetForums:", err)
		sendError(w, "Unable to fetch forums", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	jsonResponse, err := json.Marshal(forums)
	if err != nil {
		fmt.Println("[ERROR] GetForums marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)
}
