package app

import (
	"API/db"
	"database/sql"
	"encoding/json"
	"fmt"
	"net/http"
	"os"
	"strings"

	"github.com/google/uuid"
)

func GetContractsByUserID(w http.ResponseWriter, r *http.Request) {
	idStr := strings.TrimPrefix(r.URL.Path, "/users/")
	idStr = strings.TrimSuffix(idStr, "/contracts")

	userID, err := uuid.Parse(idStr)
	if err != nil {
		sendError(w, "Invalid user ID", http.StatusBadRequest)
		return
	}

	if envKey := os.Getenv("APP_API_KEY"); envKey != "" {
		if r.Header.Get("X-Internal-Key") != envKey {
			if uidRaw, ok := r.Context().Value("user_id").(string); !ok || uidRaw != userID.String() {
				sendError(w, "Forbidden", http.StatusForbidden)
				return
			}
		}
	}

	contracts, err := db.GetContractsByUserID(userID)
	if err != nil {
		fmt.Println("[ERROR] GetContractsByUserID:", err)
		sendError(w, "Unable to fetch contracts", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(contracts)
}

func GetAllContracts(w http.ResponseWriter, r *http.Request) {
	contracts, err := db.GetAllContractsWithUser()
	if err != nil {
		fmt.Println("[ERROR] GetAllContracts:", err)
		sendError(w, "Unable to fetch contracts", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(contracts)
}

func GetContractByID(w http.ResponseWriter, r *http.Request) {
	idStr := strings.TrimPrefix(r.URL.Path, "/internal/contracts/")
	idStr = strings.TrimSpace(idStr)

	contractID, err := uuid.Parse(idStr)
	if err != nil {
		sendError(w, "Invalid contract ID", http.StatusBadRequest)
		return
	}

	contract, err := db.GetContractWithUserByID(contractID)
	if err != nil {
		if err == sql.ErrNoRows {
			sendError(w, "Contract not found", http.StatusNotFound)
			return
		}
		fmt.Println("[ERROR] GetContractByID:", err)
		sendError(w, "Unable to fetch contract", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(contract)
}
