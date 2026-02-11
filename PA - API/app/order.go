package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"

	"github.com/google/uuid"
)

func GetOrders(w http.ResponseWriter, r *http.Request) {

	orders, err := db.GetOrdersFromDB()

	if err != nil {
		fmt.Println("[ERROR] GetOrders:", err)
		sendError(w, "Unable to fetch orders", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	jsonResponse, err := json.Marshal(orders)

	if err != nil {
		fmt.Println("[ERROR] GetOrders marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)
}

func ValidateOrderDto(orderDto models.Order) []string {

	var validationErrors []string

	if orderDto.UserID == uuid.Nil {
		validationErrors = append(validationErrors, "CustomerID is required and must be a valid UUID")
	}

	if orderDto.EventID == nil && orderDto.ProductID == nil {
		validationErrors = append(validationErrors, "EventID or ProductID is required")
	}

	if orderDto.Amount < 0 {
		validationErrors = append(validationErrors, "Amount must be 0 or greater")
	}

	if orderDto.Amount > 0 && orderDto.TransactionID == "" {
		validationErrors = append(validationErrors, "TransactionID is required for paid orders")
	}

	return validationErrors
}

func CreateOrder(w http.ResponseWriter, r *http.Request) {

	var orderDto models.Order
	err := json.NewDecoder(r.Body).Decode(&orderDto)

	if err != nil {
		fmt.Println("[ERROR] CreateOrder decode:", err)
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	validationErrors := ValidateOrderDto(orderDto)

	if len(validationErrors) > 0 {
		fmt.Println("[ERROR] CreateOrder validation:", validationErrors)
		sendError(w, fmt.Sprintf("Validation errors: %s", validationErrors), http.StatusBadRequest)
		return
	}

	err = db.CreateOrderInDB(orderDto)

	if err != nil {
		fmt.Println("[ERROR] CreateOrder DB insert:", err)
		if err.Error() == "event_full" {
			sendError(w, "Service is full", http.StatusConflict)
			return
		}
		if err.Error() == "event not found" {
			sendError(w, "Service not found", http.StatusNotFound)
			return
		}
		sendError(w, "Unable to create order", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(orderDto)

}
