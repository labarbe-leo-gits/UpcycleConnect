package db

import (
	"API/models"
	"database/sql"
	"fmt"

	"github.com/google/uuid"
)

func GetAdCampaignIDByPaymentIntent(paymentIntentID string) (*uuid.UUID, error) {
	var idStr sql.NullString
	err := Db.QueryRow("SELECT id FROM ad_campaigns WHERE stripe_payment_intent_id = ?", paymentIntentID).Scan(&idStr)
	if err != nil {
		if err == sql.ErrNoRows {
			return nil, nil
		}
		return nil, fmt.Errorf("getAdCampaignIDByPaymentIntent: %w", err)
	}
	if !idStr.Valid {
		return nil, nil
	}
	id, err := uuid.Parse(idStr.String)
	if err != nil {
		return nil, fmt.Errorf("getAdCampaignIDByPaymentIntent parse uuid: %w", err)
	}
	return &id, nil
}

func CreateAdCampaign(c models.AdCampaign) (uuid.UUID, error) {
	id := uuid.New()
	_, err := Db.Exec(
		`INSERT INTO ad_campaigns (id, user_id, contract_id, name, description, status, start_date, end_date, budget, currency, stripe_payment_intent_id, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())`,
		id.String(),
		c.UserID.String(),
		nullableUUID(c.ContractID),
		c.Name,
		c.Description,
		c.Status,
		c.StartDate,
		c.EndDate,
		c.Budget,
		c.Currency,
		c.StripePaymentIntentID,
	)
	if err != nil {
		return uuid.Nil, fmt.Errorf("createAdCampaign: %w", err)
	}
	return id, nil
}

func LinkAnnonceToAdCampaign(annonceID string, campaignID uuid.UUID) error {
	_, err := Db.Exec(`UPDATE annonces SET ad_campaign_id = ? WHERE id = ?`, campaignID.String(), annonceID)
	if err != nil {
		return fmt.Errorf("linkAnnonceToAdCampaign: %w", err)
	}
	return nil
}

func GetAnnonceByID(id string) (*models.Annonce, error) {
	return GetAnnonceByIDFromDB(id)
}
