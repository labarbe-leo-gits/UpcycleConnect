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

	registerRoute("GET", "/users", "Get all users", app.GetAllUsers)
	registerRoute("GET", "/users/{id}", "Get a specific user by his UUID", app.GetUserByID, app.JWTAuthMiddleware)
	registerRoute("GET", "/users/{id}/orders", "Get all orders for a specific user by their UUID", app.GetOrdersByUserID, app.JWTAuthMiddleware)
	registerRoute("GET", "/products/services", "Services listing - for the catalog", app.GetServices, app.JWTAuthMiddleware)
	registerRoute("POST", "/products/services", "Create a new service", app.CreateService, app.JWTAuthMiddleware)
	registerRoute("GET", "/products/services/{id}", "Get a specific service by its UUID", app.GetServiceByID, app.JWTAuthMiddleware)
	registerRoute("GET", "/orders", "List all orders", app.GetOrders, app.JWTAuthMiddleware)
	registerRoute("POST", "/orders", "Create a new order", app.CreateOrder, app.JWTAuthMiddleware)
	registerRoute("GET", "/annonces", "List all annonces", app.GetAnnonces, app.JWTAuthMiddleware)
	registerRoute("GET", "/facteurs", "List all available material factors", app.GetFacteurs, app.JWTAuthMiddleware)
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
	//registerRoute("PATCH", "/users/{id}/notifications/read", "Mark all users notification as read", app.MarkAllNotificationAsRead, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/users/{id}/planning", "Delete a planning entry for a user", app.DeletePlanning, app.JWTAuthMiddleware)
	registerRoute("GET", "/forums", "List all forums", app.GetForums)
	registerRoute("GET", "/forums/{id}/posts", "List all posts in a specific forum by its UUID", app.GetForumPosts)
	registerRoute("POST", "/forums", "Create a new forum", app.CreateForum, app.JWTAuthMiddleware)
	registerRoute("POST", "/forums/{id}/posts", "Create a new post in a specific forum by its UUID", app.CreateForumPost, app.JWTAuthMiddleware)
	registerRoute("GET", "/users/{id}/planning", "Get the planning data for the next 7 days", app.GetPlanning, app.JWTAuthMiddleware)
	registerRoute("POST", "/users/{id}/planning", "Create a new planning entry for a user", app.CreatePlanning, app.JWTAuthMiddleware)
	registerRoute("GET", "/planning", "Get all planning entries in the system (admin only)", app.GetAllPlanning)
	registerRoute("PATCH", "/users/{id}", "Update a user's profile", app.UpdateUser, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/annonces/{id}/views", "Increment the view count for an annonce", app.IncrementAnnonceViewCount, app.JWTAuthMiddleware)
	registerRoute("GET", "/forums/{id}", "Get details of a specific forum by its UUID", app.GetForumByID)

	registerRoute("GET", "/conteneurs", "List all conteneurs in the system", app.GetConteneurs, app.JWTAuthMiddleware)
	registerRoute("POST", "/conteneurs", "Create a new conteneur", app.CreateConteneur, app.JWTAuthMiddleware)
	registerRoute("GET", "/deposits", "List all deposits in the system", app.GetDeposits, app.JWTAuthMiddleware)
	registerRoute("POST", "/deposits", "Create a new deposit request", app.CreateDeposit, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/deposits/{id}/status", "Update the status of a deposit request", app.UpdateDepositStatus, app.JWTAuthMiddleware)
	registerRoute("GET", "/users/{id}/deposits", "List all deposits for a specific user by their UUID", app.GetDepositsByUserID, app.JWTAuthMiddleware)
	registerRoute("GET", "/conteneurs/{id}", "Get details of a specific conteneur by its UUID", app.GetConteneurByID, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/conteneurs/{id}", "Update details of a specific conteneur by its UUID", app.UpdateConteneur, app.JWTAuthMiddleware)
	registerRoute("GET", "/users/{id}/deposits/{depid}", "Get a specific user's deposit", app.GetDepositByID, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/users/{id}/password", "Change a user's password", app.ChangePassword, app.JWTAuthMiddleware)
	/* registerRoute("GET", "/users/{id}/discussions", "List all discussions for a specific user by their UUID", app.GetUserDiscussions, app.JWTAuthMiddleware)
	registerRoute("POST", "/users/{id}/discussions", "Create a new discussion for a specific user by their UUID", app.CreateDiscussion, app.JWTAuthMiddleware)
	registerRoute("GET", "/discussions/{id}/messages", "List all messages in a specific discussion by its UUID", app.GetDiscussionMessages, app.JWTAuthMiddleware)
	registerRoute("POST", "/discussions/{id}/messages", "Create a new message in a specific discussion by its UUID", app.CreateMessage, app.JWTAuthMiddleware) */
	/* registerRoute("GET", "/tips", "Get the tips from the Database", app.GetTips, app.JWTAuthMiddleware)
	registerRoute("POST", "/tips", "Create a tip in the Database", app.CreateTip, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/tips/{id}", "Update a tip in the database", app.UpdateTip, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/tips/{id}", "Delete a tip from the database", app.DeleteTip, app.JWTAuthMiddleware) */

	http.HandleFunc("/", notFoundHandler)

	fmt.Println("Listening at : " + host + ":" + port)
	http.ListenAndServe(host+":"+port, nil)

}
