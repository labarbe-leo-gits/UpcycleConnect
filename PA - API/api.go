package main

import (
	"API/app"
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"
	"os"

	"github.com/joho/godotenv"
)

var registeredEndpoints []models.Endpoint

func registerRoute(method, path, description string, handler func(http.ResponseWriter, *http.Request)) {
	pattern := method + " " + path
	http.HandleFunc(pattern, handler)
	registeredEndpoints = append(registeredEndpoints, models.Endpoint{
		Method:      method,
		Path:        path,
		Description: description,
	})
}

func healthCheck(w http.ResponseWriter, r *http.Request) {

	err := db.Db.Ping()

	if err != nil {
		fmt.Println("[ERROR] Health check - DB ping failed:", err)
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusServiceUnavailable)
		json.NewEncoder(w).Encode(map[string]string{"error": "Service unavailable"})
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"status": "OK", "database": "connected"})

}

func notFoundHandler(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusNotFound)

	response := map[string]interface{}{
		"error":               "Endpoint not found",
		"path":                r.URL.Path,
		"method":              r.Method,
		"available_endpoints": registeredEndpoints,
	}

	json.NewEncoder(w).Encode(response)
}

func main() {

	err := godotenv.Load("../PA - Site Principal/.env")
	if err != nil {
		fmt.Printf("Error loading .env file: %s", err.Error())
		return
	}

	port := os.Getenv("API_PORT")
	host := os.Getenv("API_HOST")

	db.Db = db.NewDB()

	registerRoute("GET", "/{$}", "Health check - verify API and database connection", healthCheck)
	registerRoute("GET", "/users", "Get all users", app.GetAllUsers)
	registerRoute("POST", "/users", "Create a new user", app.CreateUser)
	registerRoute("POST", "/users/email", "Get user by email - for OAuth lookup", app.GetUserByEmail)
	registerRoute("POST", "/login", "User login - authenticate and return user data", app.LoginUser)
	registerRoute("GET", "/products/services", "Services listing - for the catalog", app.GetServices)
	registerRoute("POST", "/products/services", "Create a new service", app.CreateService)
	registerRoute("GET", "/users/{id}", "Get a specific user by his UUID", app.GetUserByID)
	registerRoute("GET", "/products/services/{id}", "Get a specific service by its UUID", app.GetServiceByID)
	registerRoute("GET", "/orders", "List all orders", app.GetOrders)
	registerRoute("POST", "/orders", "Create a new order", app.CreateOrder)

	http.HandleFunc("/", notFoundHandler)

	fmt.Println("Listening at : " + host + ":" + port)
	http.ListenAndServe(host+":"+port, nil)

}
