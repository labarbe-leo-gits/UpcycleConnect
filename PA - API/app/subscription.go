package app

import (
	"API/db"
	"encoding/json"
	"fmt"
	"net/http"

	"github.com/google/uuid"
)

func ActivateSubscription(w http.ResponseWriter, r *http.Request) {
	var payload struct {
		UserID               string `json:"user_id"`
		StripeCustomerID     string `json:"stripe_customer_id"`
		StripeSubscriptionID string `json:"stripe_subscription_id"`
		TierID               string `json:"tier_id,omitempty"`
	}

	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		sendError(w, "Invalid request body", http.StatusBadRequest)
		return
	}

	userID, err := uuid.Parse(payload.UserID)
	if err != nil {
		sendError(w, "Invalid user ID", http.StatusBadRequest)
		return
	}

	if payload.StripeCustomerID == "" || payload.StripeSubscriptionID == "" {
		sendError(w, "Missing stripe_customer_id or stripe_subscription_id", http.StatusBadRequest)
		return
	}

	if payload.TierID != "" {
		tierID, err := uuid.Parse(payload.TierID)
		if err != nil {
			sendError(w, "Invalid tier ID", http.StatusBadRequest)
			return
		}

		if err := db.EndUserSubscriptionTierInDB(userID); err != nil {
			fmt.Println("[WARN] ActivateSubscription: failed to close previous tier:", err)
		}
		if err := db.AssignSubscriptionTierToUserInDB(userID, tierID, nil); err != nil {
			fmt.Println("[WARN] ActivateSubscription: failed to assign tier:", err)
		}
	}

	if err := db.UpdateSubscriptionInDB(userID, 1, payload.StripeCustomerID, payload.StripeSubscriptionID); err != nil {
		fmt.Println("[ERROR] ActivateSubscription:", err)
		sendError(w, "Failed to activate subscription", http.StatusInternalServerError)
		return
	}

	if err := db.UpsertContractFromStripe(userID, payload.StripeCustomerID, payload.StripeSubscriptionID); err != nil {
		fmt.Println("[WARN] ActivateSubscription: failed to upsert contract:", err)
	}

	premiumQuota := 25
	if err := db.UpdateLLMUsageInDB(userID.String(), &premiumQuota, nil); err != nil {
		fmt.Println("[WARN] ActivateSubscription: failed to set LLM quota:", err)
	}

	projectQuota := 0
	if err := db.UpdateProjectQuotaInDB(userID.String(), projectQuota); err != nil {
		fmt.Println("[WARN] ActivateSubscription: failed to set project quota:", err)
	}

	fmt.Printf("[INFO] Subscription activated for user %s (customer=%s sub=%s)\n",
		userID, payload.StripeCustomerID, payload.StripeSubscriptionID)

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"status": "activated"})
}

func RevokeSubscription(w http.ResponseWriter, r *http.Request) {
	var payload struct {
		StripeCustomerID     string `json:"stripe_customer_id"`
		StripeSubscriptionID string `json:"stripe_subscription_id"`
	}

	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		sendError(w, "Invalid request body", http.StatusBadRequest)
		return
	}

	if payload.StripeSubscriptionID != "" {
		if userID, err := db.GetUserIDByStripeSubscriptionID(payload.StripeSubscriptionID); err == nil {
			if err := db.EndUserSubscriptionTierInDB(userID); err != nil {
				fmt.Println("[WARN] RevokeSubscription: failed to end tier:", err)
			}
		}
		if err := db.RevokePremiumByStripeSubscriptionID(payload.StripeSubscriptionID); err != nil {
			fmt.Println("[ERROR] RevokeSubscription by sub:", err)
			sendError(w, "Failed to revoke subscription", http.StatusInternalServerError)
			return
		}
		if err := db.RevokeContractBySubscriptionID(payload.StripeSubscriptionID); err != nil {
			fmt.Println("[WARN] RevokeSubscription: failed to revoke contract:", err)
		}
		fmt.Printf("[INFO] Premium revoked for subscription %s\n", payload.StripeSubscriptionID)
	} else if payload.StripeCustomerID != "" {
		if userID, err := db.GetUserIDByStripeCustomerID(payload.StripeCustomerID); err == nil {
			if err := db.EndUserSubscriptionTierInDB(userID); err != nil {
				fmt.Println("[WARN] RevokeSubscription: failed to end tier:", err)
			}
		}
		if err := db.RevokePremiumByStripeCustomerID(payload.StripeCustomerID); err != nil {
			fmt.Println("[ERROR] RevokeSubscription by customer:", err)
			sendError(w, "Failed to revoke subscription", http.StatusInternalServerError)
			return
		}
		fmt.Printf("[INFO] Premium revoked for customer %s\n", payload.StripeCustomerID)
	} else {
		sendError(w, "Provide stripe_customer_id or stripe_subscription_id", http.StatusBadRequest)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"status": "revoked"})
}
