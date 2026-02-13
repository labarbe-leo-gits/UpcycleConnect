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

func registerRoute(method, path, description string, handler func(http.ResponseWriter, *http.Request), middlewares ...func(http.HandlerFunc) http.HandlerFunc) {
	pattern := method + " " + path
	finalHandler := handler
	for _, mw := range middlewares {
		finalHandler = mw(finalHandler)
	}
	http.HandleFunc(pattern, finalHandler)
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
	registerRoute("POST", "/login", "User login - authenticate and return user data", app.LoginUser)
	registerRoute("POST", "/users", "Create a new user", app.CreateUser)
	registerRoute("POST", "/users/email", "Get user by email - for OAuth lookup", app.GetUserByEmail)
	registerRoute("GET", "/docs", "Show the API documentation", notFoundHandler)

	registerRoute("GET", "/users", "Get all users", app.GetAllUsers, app.JWTAuthMiddleware)
	registerRoute("GET", "/users/{id}", "Get a specific user by his UUID", app.GetUserByID, app.JWTAuthMiddleware)
	registerRoute("GET", "/users/{id}/orders", "Get all orders for a specific user by their UUID", app.GetOrdersByUserID, app.JWTAuthMiddleware)
	registerRoute("GET", "/products/services", "Services listing - for the catalog", app.GetServices, app.JWTAuthMiddleware)
	registerRoute("POST", "/products/services", "Create a new service", app.CreateService, app.JWTAuthMiddleware)
	registerRoute("GET", "/products/services/{id}", "Get a specific service by its UUID", app.GetServiceByID, app.JWTAuthMiddleware)
	registerRoute("GET", "/orders", "List all orders", app.GetOrders, app.JWTAuthMiddleware)
	registerRoute("POST", "/orders", "Create a new order", app.CreateOrder, app.JWTAuthMiddleware)
	registerRoute("GET", "/annonces", "List all annonces", app.GetAnnonces, app.JWTAuthMiddleware)
	registerRoute("GET", "/annonces/{id}/images", "List all images associated with an annonce", app.GetAnnonceImages, app.JWTAuthMiddleware)
	registerRoute("POST", "/annonces", "Create a new annonce", app.CreateAnnonce, app.JWTAuthMiddleware)
	registerRoute("GET", "/annonces/{id}", "Get a specific annonce by its UUID", app.GetAnnonceByID, app.JWTAuthMiddleware)
	registerRoute("POST", "/annonces/{id}/images", "Upload an image for a specific annonce", app.UploadAnnonceImage, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/annonces/{id}", "Update an existing annonce", app.UpdateAnnonce, app.JWTAuthMiddleware)
	registerRoute("GET", "/notifications", "List all notifications in the system", app.GetNotifications, app.JWTAuthMiddleware)
	registerRoute("POST", "/notifications", "Create a new notification", app.CreateNotification, app.JWTAuthMiddleware)
	registerRoute("GET", "/users/{id}/notifications", "List all notifications for a specific user by their UUID", app.GetNotificationsByUserID, app.JWTAuthMiddleware)
	registerRoute("GET", "/payment-requests", "List all payment requests in the system", app.GetPaymentRequests, app.JWTAuthMiddleware)
	registerRoute("POST", "/payment-requests", "Create a new payment request", app.CreatePaymentRequest, app.JWTAuthMiddleware)
	registerRoute("GET", "/payouts", "List all payouts in the system", app.GetPayouts, app.JWTAuthMiddleware)
	registerRoute("POST", "/payouts", "Create a new payout", app.CreatePayout, app.JWTAuthMiddleware)
	registerRoute("GET", "/users/{id}/payouts", "List use's payout", app.GetPayoutsByUserID, app.JWTAuthMiddleware)
	registerRoute("GET", "/banking-details", "List all banking details in the system", app.GetBankingDetails, app.JWTAuthMiddleware)
	registerRoute("GET", "/users/{id}/banking-details", "Get banking details for a specific user by their UUID", app.GetBankingDetailsByUserID, app.JWTAuthMiddleware)
	registerRoute("POST", "/banking-details", "Create banking details for a user", app.CreateBankingDetails, app.JWTAuthMiddleware)
	registerRoute("GET", "/users/{id}/annonces", "List all annonces for a specific user by their UUID", app.GetAnnoncesByUserID, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/notifications/{id}/read", "Mark a notification as read by its UUID", app.MarkNotificationAsRead, app.JWTAuthMiddleware)
	
	// Delete routes
	/* registerRoute("DELETE", "/annonces/{id}", "Delete an annonce by its UUID", app.DeleteAnnonce, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/users/{id}", "Delete a user by their UUID", app.DeleteUser, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/orders/{id}", "Delete an order by its UUID", app.DeleteOrder, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/notifications/{id}", app.DeleteNotification, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/payment-requests/{id}", "Delete a payment request by its UUID", app.DeletePaymentRequest, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/payouts/{id}", "Delete a payout by its UUID", app.DeletePayout, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/banking-details/{id}", "Delete banking details by its UUID", app.DeleteBankingDetails, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/products/services/{id}", "Delete a service by its UUID", app.DeleteService, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/annonces/{id}/images/{image_id}", "Delete an image from an annonce by their UUIDs", app.DeleteAnnonceImage, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/images/{id}", "Delete an image by its UUID", app.DeleteImage, app.JWTAuthMiddleware) */

	registerRoute("GET", "/forums", "List all forums", app.GetForums)
	//registerRoute("GET", "/forums/{id}/posts", "List all posts in a specific forum by its UUID", app.GetForumPosts)

	http.HandleFunc("/", notFoundHandler)

	fmt.Println("Listening at : " + host + ":" + port)
	http.ListenAndServe(host+":"+port, nil)

}
