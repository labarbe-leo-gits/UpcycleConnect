package app

import (
	"API/db"
	"encoding/json"
	"fmt"
	"net/http"
	"strconv"
	"strings"
)

type notificationCampaignPayload struct {
	Title           string  `json:"title"`
	Message         string  `json:"message"`
	TargetUserType  int     `json:"target_user_type"`
	Status          int     `json:"status"`
	ScheduledAt     *string `json:"scheduled_at,omitempty"`
	CreatedByUserID string  `json:"created_by_user_id"`
}

func validateNotificationCampaignPayload(payload notificationCampaignPayload, forCreate bool) []string {
	errors := []string{}
	if strings.TrimSpace(payload.Title) == "" {
		errors = append(errors, "title is required")
	}
	if strings.TrimSpace(payload.Message) == "" {
		errors = append(errors, "message is required")
	}
	if payload.TargetUserType != 0 && payload.TargetUserType != 1 && payload.TargetUserType != 2 {
		errors = append(errors, "target_user_type must be 0 (all), 1 (customers) or 2 (professionals)")
	}
	if payload.Status < 0 || payload.Status > 1 {
		errors = append(errors, "status must be 0 (draft) or 1 (scheduled)")
	}
	if payload.Status == 1 {
		if payload.ScheduledAt == nil || strings.TrimSpace(*payload.ScheduledAt) == "" {
			errors = append(errors, "scheduled_at is required when status is scheduled")
		}
	}
	if forCreate && strings.TrimSpace(payload.CreatedByUserID) == "" {
		errors = append(errors, "created_by_user_id is required")
	}
	return errors
}

func GetNotificationCampaigns(w http.ResponseWriter, r *http.Request) {
	page := 1
	limit := 10

	if pageStr := strings.TrimSpace(r.URL.Query().Get("page")); pageStr != "" {
		if parsed, err := strconv.Atoi(pageStr); err == nil && parsed > 0 {
			page = parsed
		}
	}
	if limitStr := strings.TrimSpace(r.URL.Query().Get("limit")); limitStr != "" {
		if parsed, err := strconv.Atoi(limitStr); err == nil && parsed > 0 && parsed <= 100 {
			limit = parsed
		}
	}

	search := strings.TrimSpace(r.URL.Query().Get("search"))

	var status *int
	if statusStr := strings.TrimSpace(r.URL.Query().Get("status")); statusStr != "" {
		if parsed, err := strconv.Atoi(statusStr); err == nil {
			status = &parsed
		}
	}

	var targetUserType *int
	if targetStr := strings.TrimSpace(r.URL.Query().Get("target_user_type")); targetStr != "" {
		if parsed, err := strconv.Atoi(targetStr); err == nil {
			targetUserType = &parsed
		}
	}

	campaigns, total, err := db.GetNotificationCampaignsFromDB(page, limit, search, status, targetUserType)
	if err != nil {
		fmt.Println("[ERROR] GetNotificationCampaigns:", err)
		sendError(w, "Unable to fetch notification campaigns", http.StatusInternalServerError)
		return
	}

	totalPages := (total + limit - 1) / limit
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]interface{}{
		"success":   true,
		"campaigns": campaigns,
		"pagination": map[string]interface{}{
			"page":        page,
			"limit":       limit,
			"total_count": total,
			"total_pages": totalPages,
		},
	})
}

func GetNotificationCampaign(w http.ResponseWriter, r *http.Request) {
	parts := strings.Split(strings.Trim(r.URL.Path, "/"), "/")
	if len(parts) < 2 {
		sendError(w, "Invalid campaign ID", http.StatusBadRequest)
		return
	}
	id := parts[1]

	campaign, err := db.GetNotificationCampaignByIDFromDB(id)
	if err != nil {
		fmt.Println("[ERROR] GetNotificationCampaign:", err)
		if strings.Contains(strings.ToLower(err.Error()), "not found") {
			sendError(w, "Notification campaign not found", http.StatusNotFound)
			return
		}
		sendError(w, "Unable to fetch notification campaign", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]interface{}{
		"success":  true,
		"campaign": campaign,
	})
}

func CreateNotificationCampaign(w http.ResponseWriter, r *http.Request) {
	var payload notificationCampaignPayload
	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	validationErrors := validateNotificationCampaignPayload(payload, true)
	if len(validationErrors) > 0 {
		sendError(w, "Validation failed: "+strings.Join(validationErrors, ", "), http.StatusBadRequest)
		return
	}

	newID, err := db.CreateNotificationCampaignInDB(payload.Title, payload.Message, payload.TargetUserType, payload.Status, payload.ScheduledAt, payload.CreatedByUserID)
	if err != nil {
		fmt.Println("[ERROR] CreateNotificationCampaign:", err)
		sendError(w, "Unable to create notification campaign", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]interface{}{
		"success": true,
		"id":      newID,
		"message": "Notification campaign created",
	})
}

func UpdateNotificationCampaign(w http.ResponseWriter, r *http.Request) {
	parts := strings.Split(strings.Trim(r.URL.Path, "/"), "/")
	if len(parts) < 2 {
		sendError(w, "Invalid campaign ID", http.StatusBadRequest)
		return
	}
	id := parts[1]

	campaign, err := db.GetNotificationCampaignByIDFromDB(id)
	if err != nil {
		fmt.Println("[ERROR] UpdateNotificationCampaign get campaign:", err)
		sendError(w, "Notification campaign not found", http.StatusNotFound)
		return
	}
	if campaign.Status == 2 {
		sendError(w, "Cannot modify a sent campaign", http.StatusForbidden)
		return
	}

	var payload notificationCampaignPayload
	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	validationErrors := validateNotificationCampaignPayload(payload, false)
	if len(validationErrors) > 0 {
		sendError(w, "Validation failed: "+strings.Join(validationErrors, ", "), http.StatusBadRequest)
		return
	}

	if err := db.UpdateNotificationCampaignInDB(id, payload.Title, payload.Message, payload.TargetUserType, payload.Status, payload.ScheduledAt); err != nil {
		fmt.Println("[ERROR] UpdateNotificationCampaign:", err)
		sendError(w, "Unable to update notification campaign", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]interface{}{
		"success": true,
		"message": "Notification campaign updated",
	})
}

func DeleteNotificationCampaign(w http.ResponseWriter, r *http.Request) {
	parts := strings.Split(strings.Trim(r.URL.Path, "/"), "/")
	if len(parts) < 2 {
		sendError(w, "Invalid campaign ID", http.StatusBadRequest)
		return
	}
	id := parts[1]

	campaign, err := db.GetNotificationCampaignByIDFromDB(id)
	if err != nil {
		fmt.Println("[ERROR] DeleteNotificationCampaign get campaign:", err)
		sendError(w, "Notification campaign not found", http.StatusNotFound)
		return
	}
	if campaign.Status == 2 {
		sendError(w, "Cannot delete a sent campaign", http.StatusForbidden)
		return
	}

	if err := db.DeleteNotificationCampaignFromDB(id); err != nil {
		fmt.Println("[ERROR] DeleteNotificationCampaign:", err)
		sendError(w, "Unable to delete notification campaign", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]interface{}{
		"success": true,
		"message": "Notification campaign deleted",
	})
}

func SendNotificationCampaign(w http.ResponseWriter, r *http.Request) {
	path := strings.Trim(r.URL.Path, "/")
	parts := strings.Split(path, "/")
	if len(parts) < 3 || parts[0] != "notification-campaigns" || parts[2] != "send" {
		sendError(w, "Invalid campaign send endpoint", http.StatusBadRequest)
		return
	}
	id := parts[1]

	sentCount, failedCount, err := db.SendNotificationCampaignFromDB(id)
	if err != nil {
		fmt.Println("[ERROR] SendNotificationCampaign:", err)
		if strings.Contains(strings.ToLower(err.Error()), "not found") {
			sendError(w, "Notification campaign not found", http.StatusNotFound)
			return
		}
		sendError(w, "Unable to send notification campaign", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]interface{}{
		"success":      true,
		"sent_count":   sentCount,
		"failed_count": failedCount,
		"message":      "Notification campaign sent",
	})
}
