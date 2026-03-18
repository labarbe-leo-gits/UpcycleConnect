package app

import (
	"API/db"
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
