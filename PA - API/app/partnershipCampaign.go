package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"
	"strconv"
	"time"

	"github.com/google/uuid"
)

func GetPartnershipCampaigns(w http.ResponseWriter, r *http.Request) {
	statusParam := r.URL.Query().Get("status")
	var statusFilter *int
	if statusParam != "" {
		if parsed, err := strconv.Atoi(statusParam); err == nil {
			statusFilter = &parsed
		}
	}

	if mineParam := r.URL.Query().Get("mine"); mineParam == "1" || mineParam == "true" {
		uidRaw := r.Context().Value("user_id")
		uidStr, ok := uidRaw.(string)
		if !ok || uidStr == "" {
			sendError(w, "Missing user identity", http.StatusUnauthorized)
			return
		}
		userID, err := uuid.Parse(uidStr)
		if err != nil {
			sendError(w, "Invalid user identity", http.StatusUnauthorized)
			return
		}
		campaigns, err := db.GetPartnershipCampaignsByUserFromDB(userID, statusFilter)
		if err != nil {
			fmt.Println("[ERROR] GetPartnershipCampaigns by user:", err)
			sendError(w, "Unable to fetch partnership campaigns", http.StatusInternalServerError)
			return
		}
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(campaigns)
		return
	}

	campaigns, err := db.GetPartnershipCampaignsFromDB()
	if err != nil {
		fmt.Println("[ERROR] GetPartnershipCampaigns:", err)
		sendError(w, "Unable to fetch partnership campaigns", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(campaigns)
}

func GetPartnershipCampaignByID(w http.ResponseWriter, r *http.Request) {
	campaignIDStr := r.URL.Query().Get("id")
	if campaignIDStr == "" {
		sendError(w, "Campaign ID is required", http.StatusBadRequest)
		return
	}

	campaignID, err := uuid.Parse(campaignIDStr)
	if err != nil {
		sendError(w, "Invalid campaign ID", http.StatusBadRequest)
		return
	}

	campaign, err := db.GetPartnershipCampaignByIDFromDB(campaignID)
	if err != nil {
		fmt.Println("[ERROR] GetPartnershipCampaignByID:", err)
		sendError(w, "Campaign not found", http.StatusNotFound)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(campaign)
}

func CreatePartnershipCampaign(w http.ResponseWriter, r *http.Request) {
	var payload struct {
		PartnerName  string  `json:"partner_name"`
		PartnerLogo  *string `json:"partner_logo"`
		Description  *string `json:"description"`
		WebsiteURL   *string `json:"website_url"`
		MonthlyPrice float64 `json:"monthly_price"`
		Currency     string  `json:"currency"`
		StartDate    string  `json:"start_date"`
		EndDate      string  `json:"end_date"`
	}

	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		sendError(w, "Invalid request body", http.StatusBadRequest)
		return
	}

	if payload.PartnerName == "" || payload.MonthlyPrice < 0 {
		sendError(w, "Partner name and monthly_price are required", http.StatusBadRequest)
		return
	}

	campaign := models.PartnershipCampaign{
		ID:           uuid.New(),
		PartnerName:  payload.PartnerName,
		PartnerLogo:  payload.PartnerLogo,
		Description:  payload.Description,
		WebsiteURL:   payload.WebsiteURL,
		Status:       0,
		MonthlyPrice: payload.MonthlyPrice,
		Currency:     payload.Currency,
		StartDate:    payload.StartDate,
		EndDate:      payload.EndDate,
	}

	if err := db.CreatePartnershipCampaignInDB(campaign); err != nil {
		fmt.Println("[ERROR] CreatePartnershipCampaign:", err)
		sendError(w, "Failed to create partnership campaign", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(campaign)
}

func RequestPartnershipCampaign(w http.ResponseWriter, r *http.Request) {
	uidRaw := r.Context().Value("user_id")
	uidStr, ok := uidRaw.(string)
	if !ok || uidStr == "" {
		sendError(w, "Missing user identity", http.StatusUnauthorized)
		return
	}

	userID, err := uuid.Parse(uidStr)
	if err != nil {
		sendError(w, "Invalid user identity", http.StatusUnauthorized)
		return
	}

	var payload struct {
		PartnerName  string   `json:"partner_name"`
		PartnerLogo  *string  `json:"partner_logo"`
		Description  *string  `json:"description"`
		WebsiteURL   *string  `json:"website_url"`
		MonthlyPrice float64  `json:"monthly_price"`
		Currency     string   `json:"currency"`
		StartDate    string   `json:"start_date"`
		EndDate      string   `json:"end_date"`
		AnnonceIDs   []string `json:"annonce_ids"`
	}

	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		sendError(w, "Invalid request body", http.StatusBadRequest)
		return
	}

	if len(payload.AnnonceIDs) == 0 {
		sendError(w, "At least one offer must be selected", http.StatusBadRequest)
		return
	}

	if payload.MonthlyPrice <= 0 {
		sendError(w, "monthly_price is required", http.StatusBadRequest)
		return
	}

	if payload.Currency == "" {
		payload.Currency = "EUR"
	}

	if payload.StartDate == "" {
		payload.StartDate = time.Now().UTC().Format("2006-01-02")
	}
	if payload.EndDate == "" {
		payload.EndDate = time.Now().UTC().AddDate(0, 1, 0).Format("2006-01-02")
	}

	if payload.PartnerName == "" {
		if user, userErr := db.GetUserByIDFromDB(userID); userErr == nil {
			if user.CompanyName != "" {
				payload.PartnerName = user.CompanyName
			} else if user.Username != "" {
				payload.PartnerName = user.Username
			}
		}
	}
	if payload.PartnerName == "" {
		sendError(w, "partner_name is required", http.StatusBadRequest)
		return
	}

	for _, annonceIDStr := range payload.AnnonceIDs {
		annonceID, parseErr := uuid.Parse(annonceIDStr)
		if parseErr != nil {
			sendError(w, "One of the selected offers is invalid", http.StatusBadRequest)
			return
		}
		annonce, annonceErr := db.GetAnnonceByIDFromDB(annonceID.String())
		if annonceErr != nil || annonce == nil {
			sendError(w, "One of the selected offers could not be found", http.StatusNotFound)
			return
		}
		if annonce.UserID != userID {
			sendError(w, "You can only bundle your own offers", http.StatusForbidden)
			return
		}
	}

	campaign := models.PartnershipCampaign{
		ID:           uuid.New(),
		PartnerName:  payload.PartnerName,
		PartnerLogo:  payload.PartnerLogo,
		Description:  payload.Description,
		WebsiteURL:   payload.WebsiteURL,
		Status:       0,
		MonthlyPrice: payload.MonthlyPrice,
		Currency:     payload.Currency,
		StartDate:    payload.StartDate,
		EndDate:      payload.EndDate,
	}

	if err := db.CreatePartnershipCampaignInDB(campaign); err != nil {
		fmt.Println("[ERROR] RequestPartnershipCampaign create campaign:", err)
		sendError(w, "Failed to create partnership campaign request", http.StatusInternalServerError)
		return
	}

	for idx, annonceIDStr := range payload.AnnonceIDs {
		annonceID, _ := uuid.Parse(annonceIDStr)
		item := models.PartnershipCampaignItem{
			ID:               uuid.New(),
			CampaignID:       campaign.ID,
			AnnonceID:        &annonceID,
			PositionType:     "bundle",
			PositionPriority: idx + 1,
		}
		if err := db.AddPartnershipCampaignItemInDB(item); err != nil {
			fmt.Println("[ERROR] RequestPartnershipCampaign add item:", err)
			sendError(w, "Failed to attach selected offers to the request", http.StatusInternalServerError)
			return
		}
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(campaign)
}

func UpdatePartnershipCampaign(w http.ResponseWriter, r *http.Request) {
	campaignIDStr := r.URL.Query().Get("id")
	if campaignIDStr == "" {
		sendError(w, "Campaign ID is required", http.StatusBadRequest)
		return
	}

	campaignID, err := uuid.Parse(campaignIDStr)
	if err != nil {
		sendError(w, "Invalid campaign ID", http.StatusBadRequest)
		return
	}

	var payload struct {
		PartnerName  string  `json:"partner_name"`
		PartnerLogo  *string `json:"partner_logo"`
		Description  *string `json:"description"`
		WebsiteURL   *string `json:"website_url"`
		MonthlyPrice float64 `json:"monthly_price"`
		Currency     string  `json:"currency"`
		Status       int     `json:"status"`
		StartDate    string  `json:"start_date"`
		EndDate      string  `json:"end_date"`
	}

	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		sendError(w, "Invalid request body", http.StatusBadRequest)
		return
	}

	updates := map[string]interface{}{
		"partner_name":  payload.PartnerName,
		"partner_logo":  payload.PartnerLogo,
		"description":   payload.Description,
		"website_url":   payload.WebsiteURL,
		"monthly_price": payload.MonthlyPrice,
		"currency":      payload.Currency,
		"status":        payload.Status,
		"start_date":    payload.StartDate,
		"end_date":      payload.EndDate,
	}

	if err := db.UpdatePartnershipCampaignInDB(campaignID, updates); err != nil {
		fmt.Println("[ERROR] UpdatePartnershipCampaign:", err)
		sendError(w, "Failed to update partnership campaign", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"status": "updated"})
}

func UpdatePartnershipCampaignStatus(w http.ResponseWriter, r *http.Request) {
	campaignIDStr := r.URL.Query().Get("id")
	if campaignIDStr == "" {
		sendError(w, "Campaign ID is required", http.StatusBadRequest)
		return
	}

	campaignID, err := uuid.Parse(campaignIDStr)
	if err != nil {
		sendError(w, "Invalid campaign ID", http.StatusBadRequest)
		return
	}

	var payload struct {
		Status int `json:"status"`
	}

	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		sendError(w, "Invalid request body", http.StatusBadRequest)
		return
	}

	if payload.Status < 0 || payload.Status > 4 {
		sendError(w, "Invalid campaign status", http.StatusBadRequest)
		return
	}

	ownerID, ownerErr := db.GetPartnershipCampaignOwnerIDFromDB(campaignID)
	if ownerErr != nil {
		fmt.Println("[WARN] UpdatePartnershipCampaignStatus owner lookup:", ownerErr)
	}

	if err := db.UpdatePartnershipCampaignStatusInDB(campaignID, payload.Status); err != nil {
		fmt.Println("[ERROR] UpdatePartnershipCampaignStatus:", err)
		sendError(w, "Failed to update campaign status", http.StatusInternalServerError)
		return
	}

	if payload.Status == 4 && ownerID != nil {
		notif := models.Notification{
			UserID:  *ownerID,
			Message: "Your partnership bundle was approved. Please complete payment to activate it.",
		}
		if err := db.CreateNotificationInDB(notif); err != nil {
			fmt.Println("[WARN] UpdatePartnershipCampaignStatus notification:", err)
		}
	} else if payload.Status == 1 && ownerID != nil {
		notif := models.Notification{
			UserID:  *ownerID,
			Message: "Your partnership bundle payment was verified and your campaign is now active.",
		}
		if err := db.CreateNotificationInDB(notif); err != nil {
			fmt.Println("[WARN] UpdatePartnershipCampaignStatus activation notification:", err)
		}
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"status": "updated"})
}

func DeletePartnershipCampaign(w http.ResponseWriter, r *http.Request) {
	campaignIDStr := r.URL.Query().Get("id")
	if campaignIDStr == "" {
		sendError(w, "Campaign ID is required", http.StatusBadRequest)
		return
	}

	campaignID, err := uuid.Parse(campaignIDStr)
	if err != nil {
		sendError(w, "Invalid campaign ID", http.StatusBadRequest)
		return
	}

	if err := db.DeletePartnershipCampaignInDB(campaignID); err != nil {
		fmt.Println("[ERROR] DeletePartnershipCampaign:", err)
		sendError(w, "Failed to delete partnership campaign", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"status": "deleted"})
}

func CompletePartnershipCampaignPayment(w http.ResponseWriter, r *http.Request) {
	uidRaw := r.Context().Value("user_id")
	uidStr, ok := uidRaw.(string)
	if !ok || uidStr == "" {
		sendError(w, "Missing user identity", http.StatusUnauthorized)
		return
	}

	userID, err := uuid.Parse(uidStr)
	if err != nil {
		sendError(w, "Invalid user identity", http.StatusUnauthorized)
		return
	}

	var payload struct {
		CampaignID      string `json:"campaign_id"`
		PaymentIntentID string `json:"payment_intent"`
	}

	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		sendError(w, "Invalid request body", http.StatusBadRequest)
		return
	}

	if payload.CampaignID == "" || payload.PaymentIntentID == "" {
		sendError(w, "Campaign ID and payment intent are required", http.StatusBadRequest)
		return
	}

	campaignID, err := uuid.Parse(payload.CampaignID)
	if err != nil {
		sendError(w, "Invalid campaign ID", http.StatusBadRequest)
		return
	}

	campaign, err := db.GetPartnershipCampaignByIDFromDB(campaignID)
	if err != nil || campaign == nil {
		sendError(w, "Campaign not found", http.StatusNotFound)
		return
	}

	if campaign.Status != 4 {
		sendError(w, "Campaign is not awaiting payment", http.StatusConflict)
		return
	}

	ownerID, ownerErr := db.GetPartnershipCampaignOwnerIDFromDB(campaignID)
	if ownerErr != nil || ownerID == nil || *ownerID != userID {
		sendError(w, "You cannot pay for this campaign", http.StatusForbidden)
		return
	}

	if campaign.StripePaymentIntentID != nil && *campaign.StripePaymentIntentID != "" && *campaign.StripePaymentIntentID == payload.PaymentIntentID {
		sendError(w, "This campaign has already been paid", http.StatusConflict)
		return
	}

	if err := db.UpdatePartnershipCampaignPaymentIntentInDB(campaignID, payload.PaymentIntentID, 1); err != nil {
		fmt.Println("[ERROR] CompletePartnershipCampaignPayment update:", err)
		sendError(w, "Unable to activate campaign", http.StatusInternalServerError)
		return
	}

	if err := db.UpdatePartnershipCampaignStatusInDB(campaignID, 1); err != nil {
		fmt.Println("[ERROR] CompletePartnershipCampaignPayment status:", err)
		sendError(w, "Unable to activate campaign", http.StatusInternalServerError)
		return
	}

	notif := models.Notification{
		UserID:  userID,
		Message: "Your partnership bundle payment was verified and your campaign is now active.",
	}
	if err := db.CreateNotificationInDB(notif); err != nil {
		fmt.Println("[WARN] CompletePartnershipCampaignPayment notification:", err)
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"status": "succeeded"})
}

func AddItemToPartnershipCampaign(w http.ResponseWriter, r *http.Request) {
	campaignIDStr := r.URL.Query().Get("campaign_id")
	if campaignIDStr == "" {
		sendError(w, "Campaign ID is required", http.StatusBadRequest)
		return
	}

	campaignID, err := uuid.Parse(campaignIDStr)
	if err != nil {
		sendError(w, "Invalid campaign ID", http.StatusBadRequest)
		return
	}

	var payload struct {
		AnnonceID        string `json:"annonce_id"`
		PositionType     string `json:"position_type"`
		PositionPriority int    `json:"position_priority"`
	}

	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		sendError(w, "Invalid request body", http.StatusBadRequest)
		return
	}

	annonceID, err := uuid.Parse(payload.AnnonceID)
	if err != nil {
		sendError(w, "Invalid annonce ID", http.StatusBadRequest)
		return
	}

	item := models.PartnershipCampaignItem{
		ID:               uuid.New(),
		CampaignID:       campaignID,
		AnnonceID:        &annonceID,
		PositionType:     payload.PositionType,
		PositionPriority: payload.PositionPriority,
	}

	if err := db.AddPartnershipCampaignItemInDB(item); err != nil {
		fmt.Println("[ERROR] AddItemToPartnershipCampaign:", err)
		sendError(w, "Failed to add campaign item", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(item)
}

func RemoveItemFromPartnershipCampaign(w http.ResponseWriter, r *http.Request) {
	itemIDStr := r.URL.Query().Get("item_id")
	if itemIDStr == "" {
		sendError(w, "Item ID is required", http.StatusBadRequest)
		return
	}

	itemID, err := uuid.Parse(itemIDStr)
	if err != nil {
		sendError(w, "Invalid item ID", http.StatusBadRequest)
		return
	}

	if err := db.RemovePartnershipCampaignItemInDB(itemID); err != nil {
		fmt.Println("[ERROR] RemoveItemFromPartnershipCampaign:", err)
		sendError(w, "Failed to remove campaign item", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"status": "deleted"})
}
