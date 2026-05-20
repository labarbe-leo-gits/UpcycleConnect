package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"

	"github.com/google/uuid"
)

func GetTrainingSessions(w http.ResponseWriter, r *http.Request) {
	sessions, err := db.GetTrainingSessionsFromDB()
	if err != nil {
		fmt.Println("[ERROR] GetTrainingSessions:", err)
		sendError(w, "Unable to fetch training sessions", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(sessions)
}

func GetTrainingSessionByID(w http.ResponseWriter, r *http.Request) {
	sessionIDStr := r.URL.Query().Get("id")
	if sessionIDStr == "" {
		sendError(w, "Session ID is required", http.StatusBadRequest)
		return
	}

	sessionID, err := uuid.Parse(sessionIDStr)
	if err != nil {
		sendError(w, "Invalid session ID", http.StatusBadRequest)
		return
	}

	session, err := db.GetTrainingSessionByIDFromDB(sessionID)
	if err != nil {
		fmt.Println("[ERROR] GetTrainingSessionByID:", err)
		sendError(w, "Session not found", http.StatusNotFound)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(session)
}

func CreateTrainingSession(w http.ResponseWriter, r *http.Request) {
	var payload struct {
		EventID         string  `json:"event_id"`
		CreatorID       string  `json:"creator_id"`
		Title           string  `json:"title"`
		Description     *string `json:"description"`
		SessionType     string  `json:"session_type"`
		PricePerPerson  float64 `json:"price_per_person"`
		Currency        string  `json:"currency"`
		MaxParticipants *int    `json:"max_participants"`
		IsOnline        bool    `json:"is_online"`
		OnlineLink      *string `json:"online_link"`
	}

	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		sendError(w, "Invalid request body", http.StatusBadRequest)
		return
	}

	eventID, err := uuid.Parse(payload.EventID)
	if err != nil {
		sendError(w, "Invalid event ID", http.StatusBadRequest)
		return
	}

	creatorID, err := uuid.Parse(payload.CreatorID)
	if err != nil {
		sendError(w, "Invalid creator ID", http.StatusBadRequest)
		return
	}

	if payload.Title == "" || payload.PricePerPerson < 0 {
		sendError(w, "Title and price_per_person are required", http.StatusBadRequest)
		return
	}

	session := models.TrainingSession{
		ID:                  uuid.New(),
		EventID:             eventID,
		CreatorID:           creatorID,
		Title:               payload.Title,
		Description:         payload.Description,
		SessionType:         payload.SessionType,
		PricePerPerson:      payload.PricePerPerson,
		Currency:            payload.Currency,
		MaxParticipants:     payload.MaxParticipants,
		CurrentParticipants: 0,
		IsOnline:            payload.IsOnline,
		OnlineLink:          payload.OnlineLink,
		Status:              0,
	}

	if err := db.CreateTrainingSessionInDB(session); err != nil {
		fmt.Println("[ERROR] CreateTrainingSession:", err)
		sendError(w, "Failed to create training session", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(session)
}

func UpdateTrainingSession(w http.ResponseWriter, r *http.Request) {
	sessionIDStr := r.URL.Query().Get("id")
	if sessionIDStr == "" {
		sendError(w, "Session ID is required", http.StatusBadRequest)
		return
	}

	sessionID, err := uuid.Parse(sessionIDStr)
	if err != nil {
		sendError(w, "Invalid session ID", http.StatusBadRequest)
		return
	}

	var payload struct {
		Title           string  `json:"title"`
		Description     *string `json:"description"`
		PricePerPerson  float64 `json:"price_per_person"`
		Currency        string  `json:"currency"`
		MaxParticipants *int    `json:"max_participants"`
		IsOnline        bool    `json:"is_online"`
		OnlineLink      *string `json:"online_link"`
		Status          int     `json:"status"`
	}

	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		sendError(w, "Invalid request body", http.StatusBadRequest)
		return
	}

	updates := map[string]interface{}{
		"title":            payload.Title,
		"description":      payload.Description,
		"price_per_person": payload.PricePerPerson,
		"currency":         payload.Currency,
		"max_participants": payload.MaxParticipants,
		"is_online":        payload.IsOnline,
		"online_link":      payload.OnlineLink,
		"status":           payload.Status,
	}

	if err := db.UpdateTrainingSessionInDB(sessionID, updates); err != nil {
		fmt.Println("[ERROR] UpdateTrainingSession:", err)
		sendError(w, "Failed to update training session", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"status": "updated"})
}

func DeleteTrainingSession(w http.ResponseWriter, r *http.Request) {
	sessionIDStr := r.URL.Query().Get("id")
	if sessionIDStr == "" {
		sendError(w, "Session ID is required", http.StatusBadRequest)
		return
	}

	sessionID, err := uuid.Parse(sessionIDStr)
	if err != nil {
		sendError(w, "Invalid session ID", http.StatusBadRequest)
		return
	}

	if err := db.DeleteTrainingSessionInDB(sessionID); err != nil {
		fmt.Println("[ERROR] DeleteTrainingSession:", err)
		sendError(w, "Failed to delete training session", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"status": "deleted"})
}

func RegisterTrainingSession(w http.ResponseWriter, r *http.Request) {
	var payload struct {
		SessionID  string  `json:"session_id"`
		UserID     string  `json:"user_id"`
		OrderID    *string `json:"order_id"`
		AmountPaid float64 `json:"amount_paid"`
	}

	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		sendError(w, "Invalid request body", http.StatusBadRequest)
		return
	}

	sessionID, err := uuid.Parse(payload.SessionID)
	if err != nil {
		sendError(w, "Invalid session ID", http.StatusBadRequest)
		return
	}

	userID, err := uuid.Parse(payload.UserID)
	if err != nil {
		sendError(w, "Invalid user ID", http.StatusBadRequest)
		return
	}

	var orderID *uuid.UUID
	if payload.OrderID != nil {
		oid, err := uuid.Parse(*payload.OrderID)
		if err != nil {
			sendError(w, "Invalid order ID", http.StatusBadRequest)
			return
		}
		orderID = &oid
	}

	registration := models.TrainingSessionRegistration{
		ID:         uuid.New(),
		SessionID:  sessionID,
		UserID:     userID,
		OrderID:    orderID,
		AmountPaid: payload.AmountPaid,
		Status:     0,
	}

	if err := db.CreateTrainingSessionRegistrationInDB(registration); err != nil {
		fmt.Println("[ERROR] RegisterTrainingSession:", err)
		if err.Error() == "session_full" {
			sendError(w, "Training session is full", http.StatusConflict)
			return
		}
		sendError(w, "Failed to register training session", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(registration)
}

func GetTrainingSessionRegistrations(w http.ResponseWriter, r *http.Request) {
	sessionIDStr := r.URL.Query().Get("session_id")
	userIDStr := r.URL.Query().Get("user_id")

	var registrations []models.TrainingSessionRegistration
	var err error

	if sessionIDStr != "" {
		sessionID, err := uuid.Parse(sessionIDStr)
		if err != nil {
			sendError(w, "Invalid session ID", http.StatusBadRequest)
			return
		}
		registrations, err = db.GetTrainingSessionRegistrationsBySessionFromDB(sessionID)
	} else if userIDStr != "" {
		userID, err := uuid.Parse(userIDStr)
		if err != nil {
			sendError(w, "Invalid user ID", http.StatusBadRequest)
			return
		}
		registrations, err = db.GetTrainingSessionRegistrationsByUserFromDB(userID)
	} else {
		registrations, err = db.GetAllTrainingSessionRegistrationsFromDB()
	}

	if err != nil {
		fmt.Println("[ERROR] GetTrainingSessionRegistrations:", err)
		sendError(w, "Unable to fetch registrations", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(registrations)
}

func UpdateTrainingSessionRegistrationStatus(w http.ResponseWriter, r *http.Request) {
	regIDStr := r.URL.Query().Get("id")
	if regIDStr == "" {
		sendError(w, "Registration ID is required", http.StatusBadRequest)
		return
	}

	regID, err := uuid.Parse(regIDStr)
	if err != nil {
		sendError(w, "Invalid registration ID", http.StatusBadRequest)
		return
	}

	var payload struct {
		Status int `json:"status"`
	}

	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		sendError(w, "Invalid request body", http.StatusBadRequest)
		return
	}

	if err := db.UpdateTrainingSessionRegistrationStatusInDB(regID, payload.Status); err != nil {
		fmt.Println("[ERROR] UpdateTrainingSessionRegistrationStatus:", err)
		sendError(w, "Failed to update registration", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"status": "updated"})
}
