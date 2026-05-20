package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"

	"github.com/google/uuid"
)

func GetCommissionSettings(w http.ResponseWriter, r *http.Request) {
	settings, err := db.GetCommissionSettingsFromDB()
	if err != nil {
		fmt.Println("[ERROR] GetCommissionSettings:", err)
		sendError(w, "Unable to fetch commission settings", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(settings)
}

func UpdateCommissionSettings(w http.ResponseWriter, r *http.Request) {
	var payload struct {
		CommissionMin  float64 `json:"commission_rate_min"`
		CommissionMax  float64 `json:"commission_rate_max"`
		EffectiveFrom  string  `json:"effective_from"`
		EffectiveTo    *string `json:"effective_to"`
	}

	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		sendError(w, "Invalid request body", http.StatusBadRequest)
		return
	}

	if payload.CommissionMin < 0 || payload.CommissionMax < 0 || payload.CommissionMin > payload.CommissionMax {
		sendError(w, "Invalid commission rates", http.StatusBadRequest)
		return
	}

	if err := db.UpdateCommissionSettingsInDB(payload.CommissionMin, payload.CommissionMax, payload.EffectiveFrom, payload.EffectiveTo); err != nil {
		fmt.Println("[ERROR] UpdateCommissionSettings:", err)
		sendError(w, "Failed to update commission settings", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"status": "updated"})
}

func GetCommissionTransactions(w http.ResponseWriter, r *http.Request) {
	sellerIDStr := r.URL.Query().Get("seller_id")
	var transactions []models.CommissionTransaction
	var err error

	if sellerIDStr != "" {
		sellerID, err := uuid.Parse(sellerIDStr)
		if err != nil {
			sendError(w, "Invalid seller ID", http.StatusBadRequest)
			return
		}
		transactions, err = db.GetCommissionTransactionsBySellerFromDB(sellerID)
	} else {
		transactions, err = db.GetAllCommissionTransactionsFromDB()
	}

	if err != nil {
		fmt.Println("[ERROR] GetCommissionTransactions:", err)
		sendError(w, "Unable to fetch commission transactions", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(transactions)
}

func GetCommissionTransactionByID(w http.ResponseWriter, r *http.Request) {
	transIDStr := r.URL.Query().Get("id")
	if transIDStr == "" {
		sendError(w, "Transaction ID is required", http.StatusBadRequest)
		return
	}

	transID, err := uuid.Parse(transIDStr)
	if err != nil {
		sendError(w, "Invalid transaction ID", http.StatusBadRequest)
		return
	}

	transaction, err := db.GetCommissionTransactionByIDFromDB(transID)
	if err != nil {
		fmt.Println("[ERROR] GetCommissionTransactionByID:", err)
		sendError(w, "Transaction not found", http.StatusNotFound)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(transaction)
}

func CreateCommissionTransaction(w http.ResponseWriter, r *http.Request) {
	var payload struct {
		OrderID          string  `json:"order_id"`
		SellerID         string  `json:"seller_id"`
		AmountBeforeComm float64 `json:"amount_before_commission"`
		CommissionRate   float64 `json:"commission_rate"`
		Notes            *string `json:"notes"`
	}

	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		sendError(w, "Invalid request body", http.StatusBadRequest)
		return
	}

	orderID, err := uuid.Parse(payload.OrderID)
	if err != nil {
		sendError(w, "Invalid order ID", http.StatusBadRequest)
		return
	}

	sellerID, err := uuid.Parse(payload.SellerID)
	if err != nil {
		sendError(w, "Invalid seller ID", http.StatusBadRequest)
		return
	}

	if payload.AmountBeforeComm < 0 || payload.CommissionRate < 0 {
		sendError(w, "Invalid amount or commission rate", http.StatusBadRequest)
		return
	}

	commissionAmount := payload.AmountBeforeComm * (payload.CommissionRate / 100)
	amountAfterComm := payload.AmountBeforeComm - commissionAmount

	transaction := models.CommissionTransaction{
		ID:               uuid.New(),
		OrderID:          orderID,
		SellerID:         sellerID,
		AmountBeforeComm: payload.AmountBeforeComm,
		CommissionRate:   payload.CommissionRate,
		CommissionAmount: commissionAmount,
		AmountAfterComm:  amountAfterComm,
		Status:           0,
		Notes:            payload.Notes,
	}

	if err := db.CreateCommissionTransactionInDB(transaction); err != nil {
		fmt.Println("[ERROR] CreateCommissionTransaction:", err)
		sendError(w, "Failed to create commission transaction", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(transaction)
}

func UpdateCommissionTransactionStatus(w http.ResponseWriter, r *http.Request) {
	transIDStr := r.URL.Query().Get("id")
	if transIDStr == "" {
		sendError(w, "Transaction ID is required", http.StatusBadRequest)
		return
	}

	transID, err := uuid.Parse(transIDStr)
	if err != nil {
		sendError(w, "Invalid transaction ID", http.StatusBadRequest)
		return
	}

	var payload struct {
		Status int `json:"status"`
	}

	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		sendError(w, "Invalid request body", http.StatusBadRequest)
		return
	}

	if err := db.UpdateCommissionTransactionStatusInDB(transID, payload.Status); err != nil {
		fmt.Println("[ERROR] UpdateCommissionTransactionStatus:", err)
		sendError(w, "Failed to update commission transaction", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"status": "updated"})
}
