package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"

	"github.com/google/uuid"
)

func GetBankingDetails(w http.ResponseWriter, r *http.Request) {

	bankingDetails, err := db.GetBankingDetailsFromDB()

	if err != nil {
		fmt.Println("[ERROR] GetBankingDetails:", err)
		sendError(w, "Unable to fetch banking details", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	jsonResponse, err := json.Marshal(bankingDetails)
	if err != nil {
		fmt.Println("[ERROR] GetBankingDetails marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)
}

func GetBankingDetailsByUserID(w http.ResponseWriter, r *http.Request) {

	idStr := r.URL.Path[len("/users/") : len(r.URL.Path)-len("/banking-details")]
	_, err := uuid.Parse(idStr)

	bankingDetails, err := db.GetBankingDetailsByUserIDFromDB(uuid.MustParse(idStr))
	if err != nil {
		fmt.Println("[ERROR] GetBankingDetailsByUserID:", err)
		sendError(w, "Unable to fetch banking details for user", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	jsonResponse, err := json.Marshal(bankingDetails)
	if err != nil {
		fmt.Println("[ERROR] GetBankingDetailsByUserID marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)
}

func GetBankingDetailsByID(w http.ResponseWriter, r *http.Request) {

	idStr := r.URL.Path[len("/banking-details/"):]
	_, err := uuid.Parse(idStr)

	bankingDetails, err := db.GetBankingDetailsByIDFromDB(uuid.MustParse(idStr))
	if err != nil {
		fmt.Println("[ERROR] GetBankingDetailsByID:", err)
		sendError(w, "Unable to fetch banking details", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	jsonResponse, err := json.Marshal(bankingDetails)
	if err != nil {
		fmt.Println("[ERROR] GetBankingDetailsByID marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)
}

func ValidateBankingDetailsDto(bankingDetailsDto models.BankingDetails) []string {

	var validationErrors []string

	if bankingDetailsDto.UserID == uuid.Nil {
		validationErrors = append(validationErrors, "UserID is required and must be a valid UUID")
	}

	if bankingDetailsDto.HolderName == "" {
		validationErrors = append(validationErrors, "HolderName is required")
	}

	return validationErrors
}

func CreateBankingDetails(w http.ResponseWriter, r *http.Request) {

	var bankingDetailsDto models.BankingDetails
	err := json.NewDecoder(r.Body).Decode(&bankingDetailsDto)

	if err != nil {
		fmt.Println("[ERROR] CreateBankingDetails decode:", err)
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	validationErrors := ValidateBankingDetailsDto(bankingDetailsDto)

	if len(validationErrors) > 0 {
		fmt.Println("[ERROR] CreateBankingDetails validation:", validationErrors)
		sendError(w, "Validation errors: "+fmt.Sprint(validationErrors), http.StatusBadRequest)
		return
	}

	newID, err := db.CreateBankingDetailsInDB(&bankingDetailsDto)

	if err != nil {
		fmt.Println("[ERROR] CreateBankingDetails:", err)
		sendError(w, "Unable to create banking details", http.StatusInternalServerError)
		return
	}

	bankingDetailsDto.ID = newID

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(bankingDetailsDto)

}
