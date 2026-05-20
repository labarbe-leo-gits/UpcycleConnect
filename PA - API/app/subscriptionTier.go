package app

import (
	"API/db"
	"API/models"
	"database/sql"
	"encoding/json"
	"fmt"
	"net/http"

	"github.com/google/uuid"
)

func GetSubscriptionTiers(w http.ResponseWriter, r *http.Request) {
	tiers, err := db.GetSubscriptionTiersFromDB()
	if err != nil {
		fmt.Println("[ERROR] GetSubscriptionTiers:", err)
		sendError(w, "Unable to fetch subscription tiers", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(tiers)
}

func GetSubscriptionTierByID(w http.ResponseWriter, r *http.Request) {
	tierIDStr := r.URL.Query().Get("id")
	if tierIDStr == "" {
		sendError(w, "Tier ID is required", http.StatusBadRequest)
		return
	}

	tierID, err := uuid.Parse(tierIDStr)
	if err != nil {
		sendError(w, "Invalid tier ID", http.StatusBadRequest)
		return
	}

	tier, err := db.GetSubscriptionTierByIDFromDB(tierID)
	if err != nil {
		fmt.Println("[ERROR] GetSubscriptionTierByID:", err)
		sendError(w, "Tier not found", http.StatusNotFound)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(tier)
}

func CreateSubscriptionTier(w http.ResponseWriter, r *http.Request) {
	var payload struct {
		Name             string          `json:"name"`
		Description      string          `json:"description"`
		TierLevel        int             `json:"tier_level"`
		MonthlyPrice     float64         `json:"monthly_price"`
		Currency         string          `json:"currency"`
		StripePriceID    string          `json:"stripe_price_id"`
		Features         json.RawMessage `json:"features"`
		DashboardAccess  bool            `json:"dashboard_access"`
		AnalyticsAccess  bool            `json:"analytics_access"`
		MaterialStats    bool            `json:"material_stats"`
		CollectionAlerts bool            `json:"collection_alerts"`
		MaxAnnonces      *int            `json:"max_annonces"`
	}

	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		sendError(w, "Invalid request body", http.StatusBadRequest)
		return
	}

	if payload.Name == "" || payload.MonthlyPrice < 0 {
		sendError(w, "Name and monthly_price are required", http.StatusBadRequest)
		return
	}

	tier := models.SubscriptionTier{
		ID:               uuid.New(),
		Name:             payload.Name,
		Description:      payload.Description,
		TierLevel:        payload.TierLevel,
		MonthlyPrice:     payload.MonthlyPrice,
		Currency:         payload.Currency,
		StripePriceID:    payload.StripePriceID,
		Features:         payload.Features,
		DashboardAccess:  payload.DashboardAccess,
		AnalyticsAccess:  payload.AnalyticsAccess,
		MaterialStats:    payload.MaterialStats,
		CollectionAlerts: payload.CollectionAlerts,
		MaxAnnonces:      payload.MaxAnnonces,
		IsActive:         true,
	}

	if err := db.CreateSubscriptionTierInDB(tier); err != nil {
		fmt.Println("[ERROR] CreateSubscriptionTier:", err)
		sendError(w, "Failed to create subscription tier", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(tier)
}

func UpdateSubscriptionTier(w http.ResponseWriter, r *http.Request) {
	tierIDStr := r.URL.Query().Get("id")
	if tierIDStr == "" {
		sendError(w, "Tier ID is required", http.StatusBadRequest)
		return
	}

	tierID, err := uuid.Parse(tierIDStr)
	if err != nil {
		sendError(w, "Invalid tier ID", http.StatusBadRequest)
		return
	}

	var payload struct {
		Name             string          `json:"name"`
		Description      string          `json:"description"`
		MonthlyPrice     float64         `json:"monthly_price"`
		Currency         string          `json:"currency"`
		StripePriceID    string          `json:"stripe_price_id"`
		Features         json.RawMessage `json:"features"`
		DashboardAccess  bool            `json:"dashboard_access"`
		AnalyticsAccess  bool            `json:"analytics_access"`
		MaterialStats    bool            `json:"material_stats"`
		CollectionAlerts bool            `json:"collection_alerts"`
		MaxAnnonces      *int            `json:"max_annonces"`
		IsActive         bool            `json:"is_active"`
	}

	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		sendError(w, "Invalid request body", http.StatusBadRequest)
		return
	}

	updates := map[string]interface{}{
		"name":              payload.Name,
		"description":       payload.Description,
		"monthly_price":     payload.MonthlyPrice,
		"currency":          payload.Currency,
		"stripe_price_id":   payload.StripePriceID,
		"features":          payload.Features,
		"dashboard_access":  payload.DashboardAccess,
		"analytics_access":  payload.AnalyticsAccess,
		"material_stats":    payload.MaterialStats,
		"collection_alerts": payload.CollectionAlerts,
		"max_annonces":      payload.MaxAnnonces,
		"is_active":         payload.IsActive,
	}

	if err := db.UpdateSubscriptionTierInDB(tierID, updates); err != nil {
		fmt.Println("[ERROR] UpdateSubscriptionTier:", err)
		sendError(w, "Failed to update subscription tier", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"status": "updated"})
}

func DeleteSubscriptionTier(w http.ResponseWriter, r *http.Request) {
	tierIDStr := r.URL.Query().Get("id")
	if tierIDStr == "" {
		sendError(w, "Tier ID is required", http.StatusBadRequest)
		return
	}

	tierID, err := uuid.Parse(tierIDStr)
	if err != nil {
		sendError(w, "Invalid tier ID", http.StatusBadRequest)
		return
	}

	tier, err := db.GetSubscriptionTierByIDFromDB(tierID)
	if err != nil {
		fmt.Println("[ERROR] DeleteSubscriptionTier load tier:", err)
		sendError(w, "Tier not found", http.StatusNotFound)
		return
	}

	if tier.IsSystem {
		sendError(w, "Default subscription tiers cannot be deleted", http.StatusForbidden)
		return
	}

	if err := db.DeleteSubscriptionTierInDB(tierID); err != nil {
		fmt.Println("[ERROR] DeleteSubscriptionTier:", err)
		if err == sql.ErrNoRows {
			sendError(w, "Tier not found", http.StatusNotFound)
			return
		}
		sendError(w, "Failed to delete subscription tier", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"status": "deleted"})
}

func GetUserCurrentTier(w http.ResponseWriter, r *http.Request) {
	userIDStr := r.PathValue("id")
	if userIDStr == "" {
		userIDStr = r.URL.Query().Get("user_id")
	}
	if userIDStr == "" {
		sendError(w, "User ID is required", http.StatusBadRequest)
		return
	}

	userID, err := uuid.Parse(userIDStr)
	if err != nil {
		sendError(w, "Invalid user ID", http.StatusBadRequest)
		return
	}

	tier, err := db.GetUserCurrentTierFromDB(userID)
	if err != nil {
		fmt.Println("[ERROR] GetUserCurrentTier:", err)
		sendError(w, "Unable to fetch user tier", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(tier)
}
