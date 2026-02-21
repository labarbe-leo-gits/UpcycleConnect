package app

import (
	"API/db"
	"encoding/json"
	"fmt"
	"net/http"
)

func GetFacteurs(w http.ResponseWriter, r *http.Request) {
	facteurs, err := db.GetAllFacteurs()
	if err != nil {
		fmt.Println("[ERROR] GetFacteurs DB:", err)
		sendError(w, "Unable to fetch materials", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	if err := json.NewEncoder(w).Encode(facteurs); err != nil {
		fmt.Println("[ERROR] GetFacteurs encode:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}
}
