package app

import (
	"API/db"
	"encoding/json"
	"fmt"
	"net/http"
	"API/models"
	"github.com/google/uuid"
)

func GetPayouts(w http.ResponseWriter, r *http.Request) {

	payouts, err := db.GetPayoutsFromDB()

	if err != nil {
		fmt.Println("[ERROR] GetPayouts:", err)
		sendError(w, "Unable to fetch payouts", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	jsonResponse, err := json.Marshal(payouts)
	if err != nil {
		fmt.Println("[ERROR] GetPayouts marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)
}

func CreatePayout(w http.ResponseWriter, r *http.Request) {

	var payout models.Payout

	err := json.NewDecoder(r.Body).Decode(&payout)
	if err != nil {
		fmt.Println("[ERROR] CreatePayout decode:", err)
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	err = db.CreatePayoutInDB(payout)
	if err != nil {
		fmt.Println("[ERROR] CreatePayout:", err)
		sendError(w, "Unable to create payout", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	jsonResponse, err := json.Marshal(map[string]string{"message": "Payout created successfully"})
	if err != nil {
		fmt.Println("[ERROR] CreatePayout marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)
}

func GetPayoutsByUserID(w http.ResponseWriter, r *http.Request) {

	idStr := r.URL.Path[len("/users/") : len(r.URL.Path)-len("/payouts")]
	_, err := uuid.Parse(idStr)

	payouts, err := db.GetPayoutsByUserIDFromDB(uuid.MustParse(idStr))
	if err != nil {
		fmt.Println("[ERROR] GetPayoutsByUserID:", err)
		sendError(w, "Unable to fetch payouts for user", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	jsonResponse, err := json.Marshal(payouts)
	if err != nil {
		fmt.Println("[ERROR] GetPayoutsByUserID marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)
}

