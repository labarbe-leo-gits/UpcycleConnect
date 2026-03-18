package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"
	"time"

	"github.com/google/uuid"
)

func CompletePromotion(w http.ResponseWriter, r *http.Request) {
	var payload struct {
		UserID              string  `json:"user_id"`
		OfferID             string  `json:"offer_id"`
		StripeCustomerID    string  `json:"stripe_customer_id"`
		StripePaymentIntent string  `json:"stripe_payment_intent_id"`
		Amount              float64 `json:"amount"`
		Currency            string  `json:"currency"`
		DurationDays        int     `json:"duration_days"`
		Budget              float64 `json:"budget"`
		Name                string  `json:"name"`
		Description         string  `json:"description"`
	}

	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		sendError(w, "Invalid request body", http.StatusBadRequest)
		return
	}

	if payload.UserID == "" || payload.OfferID == "" || payload.StripePaymentIntent == "" {
		sendError(w, "Missing required fields", http.StatusBadRequest)
		return
	}

	userID, err := uuid.Parse(payload.UserID)
	if err != nil {
		sendError(w, "Invalid user ID", http.StatusBadRequest)
		return
	}

	offer, err := db.GetAnnonceByIDFromDB(payload.OfferID)
	if err != nil {
		fmt.Println("[ERROR] CompletePromotion get annonce:", err)
		sendError(w, "Offer not found", http.StatusNotFound)
		return
	}
	if offer == nil {
		sendError(w, "Offer not found", http.StatusNotFound)
		return
	}

	if offer.UserID != userID {
		sendError(w, "Unauthorized to promote this offer", http.StatusForbidden)
		return
	}

	if payload.DurationDays <= 0 {
		payload.DurationDays = 1
	}

	if payload.Currency == "" {
		payload.Currency = "EUR"
	}

	now := time.Now().UTC()
	start := now.Format("2006-01-02")
	end := now.AddDate(0, 0, payload.DurationDays).Format("2006-01-02")

	metadata := map[string]any{
		"offer_id":    payload.OfferID,
		"budget":      payload.Budget,
		"duration":    payload.DurationDays,
		"name":        payload.Name,
		"description": payload.Description,
	}

	contractID, err := db.UpsertContractForPromotion(userID, payload.StripeCustomerID, payload.StripePaymentIntent, payload.Amount, payload.Currency, start, end, metadata)
	if err != nil {
		fmt.Println("[ERROR] CompletePromotion upsert contract:", err)
		sendError(w, "Failed to create contract", http.StatusInternalServerError)
		return
	}

	campaignID, err := db.GetAdCampaignIDByPaymentIntent(payload.StripePaymentIntent)
	if err != nil {
		fmt.Println("[ERROR] CompletePromotion get campaign:", err)
		sendError(w, "Failed to create campaign", http.StatusInternalServerError)
		return
	}
	if campaignID == nil {
		camp := models.AdCampaign{
			UserID:                userID,
			ContractID:            &contractID,
			Name:                  payload.Name,
			Description:           payload.Description,
			Status:                1,
			StartDate:             start,
			EndDate:               end,
			Budget:                payload.Budget,
			Currency:              payload.Currency,
			StripePaymentIntentID: payload.StripePaymentIntent,
		}
		cid, err := db.CreateAdCampaign(camp)
		if err != nil {
			fmt.Println("[ERROR] CompletePromotion create campaign:", err)
			sendError(w, "Failed to create campaign", http.StatusInternalServerError)
			return
		}
		campaignID = &cid
	}

	if err := db.LinkAnnonceToAdCampaign(payload.OfferID, *campaignID); err != nil {
		fmt.Println("[ERROR] CompletePromotion link annonce:", err)
		sendError(w, "Failed to link campaign to offer", http.StatusInternalServerError)
		return
	}

	invoice := models.Invoice{
		UserID:                userID,
		ContractID:            &contractID,
		StripeInvoiceID:       payload.StripePaymentIntent,
		StripePaymentIntentID: payload.StripePaymentIntent,
		AmountDue:             payload.Amount,
		AmountPaid:            payload.Amount,
		Currency:              payload.Currency,
		Status:                "paid",
		DueDate:               start,
		PeriodStart:           start,
		PeriodEnd:             end,
		InvoiceURL:            "",
		ReceiptURL:            "",
	}
	if err := db.UpsertInvoice(invoice); err != nil {
		fmt.Println("[ERROR] CompletePromotion upsert invoice:", err)
		sendError(w, "Failed to create invoice", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"status": "ok"})
}
