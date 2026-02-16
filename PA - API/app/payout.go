package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"

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

	if validationErrors := ValidatePayoutDto(payout); len(validationErrors) > 0 {
		fmt.Println("[ERROR] CreatePayout validation:", validationErrors)
		sendError(w, fmt.Sprintf("Validation errors: %v", validationErrors), http.StatusBadRequest)
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

func ValidatePayoutDto(p models.Payout) []string {
	var errs []string
	if p.Amount <= 0 {
		errs = append(errs, "Amount must be greater than 0")
	}
	if p.PaymentRequestID == uuid.Nil {
		errs = append(errs, "PaymentRequestID is required and must be a valid UUID")
	}
	if p.UserID == uuid.Nil {
		errs = append(errs, "UserID is required and must be a valid UUID")
	}
	if p.DoneBy == uuid.Nil {
		errs = append(errs, "DoneBy is required and must be a valid UUID")
	}
	return errs
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
