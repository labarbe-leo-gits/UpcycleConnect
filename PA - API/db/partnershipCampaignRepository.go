package db

import (
	"API/models"
	"database/sql"
	"fmt"

	"github.com/google/uuid"
)

func GetPartnershipCampaignOwnerIDFromDB(campaignID uuid.UUID) (*uuid.UUID, error) {
	query := `
		SELECT a.user_id
		FROM partnership_campaign_items pci
		JOIN annonces a ON a.id = pci.annonce_id
		WHERE pci.campaign_id = ?
		ORDER BY pci.position_priority ASC
		LIMIT 1
	`

	var userIDStr sql.NullString
	if err := Db.QueryRow(query, campaignID.String()).Scan(&userIDStr); err != nil {
		return nil, err
	}
	if !userIDStr.Valid || userIDStr.String == "" {
		return nil, fmt.Errorf("campaign owner not found")
	}

	ownerID, err := uuid.Parse(userIDStr.String)
	if err != nil {
		return nil, err
	}

	return &ownerID, nil
}

func GetPartnershipCampaignIDsByUserFromDB(userID uuid.UUID, status *int) ([]uuid.UUID, error) {
	query := `
		SELECT DISTINCT pc.id
		FROM partnership_campaigns pc
		JOIN partnership_campaign_items pci ON pci.campaign_id = pc.id
		JOIN annonces a ON a.id = pci.annonce_id
		WHERE a.user_id = ?
	`
	args := []interface{}{userID.String()}
	if status != nil {
		query += " AND pc.status = ?"
		args = append(args, *status)
	}
	query += " ORDER BY pc.created_at DESC"

	rows, err := Db.Query(query, args...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	ids := make([]uuid.UUID, 0)
	for rows.Next() {
		var idStr string
		if err := rows.Scan(&idStr); err != nil {
			return nil, err
		}
		id, err := uuid.Parse(idStr)
		if err != nil {
			return nil, err
		}
		ids = append(ids, id)
	}
	return ids, rows.Err()
}

func GetPartnershipCampaignsByUserFromDB(userID uuid.UUID, status *int) ([]models.PartnershipCampaign, error) {
	ids, err := GetPartnershipCampaignIDsByUserFromDB(userID, status)
	if err != nil {
		return nil, err
	}

	campaigns := make([]models.PartnershipCampaign, 0, len(ids))
	for _, id := range ids {
		campaign, err := GetPartnershipCampaignByIDFromDB(id)
		if err != nil || campaign == nil {
			continue
		}
		campaigns = append(campaigns, *campaign)
	}
	return campaigns, nil
}

func GetPartnershipCampaignsFromDB() ([]models.PartnershipCampaign, error) {
	query := `
		SELECT id, partner_name, partner_logo, description, website_url, status,
		       monthly_price, currency, contract_id, start_date, end_date,
		       stripe_payment_intent_id, created_at, updated_at
		FROM partnership_campaigns
		ORDER BY created_at DESC
	`

	rows, err := Db.Query(query)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var campaigns []models.PartnershipCampaign
	for rows.Next() {
		var campaign models.PartnershipCampaign
		if err := rows.Scan(
			&campaign.ID, &campaign.PartnerName, &campaign.PartnerLogo, &campaign.Description,
			&campaign.WebsiteURL, &campaign.Status, &campaign.MonthlyPrice, &campaign.Currency,
			&campaign.ContractID, &campaign.StartDate, &campaign.EndDate,
			&campaign.StripePaymentIntentID, &campaign.CreatedAt, &campaign.UpdatedAt,
		); err != nil {
			return nil, err
		}
		campaigns = append(campaigns, campaign)
	}

	return campaigns, rows.Err()
}

func GetPartnershipCampaignByIDFromDB(campaignID uuid.UUID) (*models.PartnershipCampaign, error) {
	query := `
		SELECT id, partner_name, partner_logo, description, website_url, status,
		       monthly_price, currency, contract_id, start_date, end_date,
		       stripe_payment_intent_id, created_at, updated_at
		FROM partnership_campaigns
		WHERE id = ?
	`

	var campaign models.PartnershipCampaign
	err := Db.QueryRow(query, campaignID.String()).Scan(
		&campaign.ID, &campaign.PartnerName, &campaign.PartnerLogo, &campaign.Description,
		&campaign.WebsiteURL, &campaign.Status, &campaign.MonthlyPrice, &campaign.Currency,
		&campaign.ContractID, &campaign.StartDate, &campaign.EndDate,
		&campaign.StripePaymentIntentID, &campaign.CreatedAt, &campaign.UpdatedAt,
	)

	if err != nil {
		return nil, err
	}

	itemsQuery := `
		SELECT id, campaign_id, annonce_id, position_type, position_priority, created_at
		FROM partnership_campaign_items
		WHERE campaign_id = ?
		ORDER BY position_priority ASC
	`

	itemsRows, err := Db.Query(itemsQuery, campaignID.String())
	if err == nil {
		defer itemsRows.Close()
		for itemsRows.Next() {
			var item models.PartnershipCampaignItem
			if err := itemsRows.Scan(
				&item.ID, &item.CampaignID, &item.AnnonceID, &item.PositionType,
				&item.PositionPriority, &item.CreatedAt,
			); err == nil {
				campaign.Items = append(campaign.Items, item)
			}
		}
	}

	return &campaign, nil
}

func CreatePartnershipCampaignInDB(campaign models.PartnershipCampaign) error {
	query := `
		INSERT INTO partnership_campaigns
		(id, partner_name, partner_logo, description, website_url, status,
		 monthly_price, currency, contract_id, start_date, end_date,
		 stripe_payment_intent_id, created_at, updated_at)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
	`

	_, err := Db.Exec(query,
		campaign.ID.String(), campaign.PartnerName, campaign.PartnerLogo,
		campaign.Description, campaign.WebsiteURL, campaign.Status,
		campaign.MonthlyPrice, campaign.Currency, campaign.ContractID,
		campaign.StartDate, campaign.EndDate, campaign.StripePaymentIntentID,
	)

	return err
}

func UpdatePartnershipCampaignInDB(campaignID uuid.UUID, updates map[string]interface{}) error {
	query := `
		UPDATE partnership_campaigns
		SET partner_name = COALESCE(?, partner_name),
		    partner_logo = COALESCE(?, partner_logo),
		    description = COALESCE(?, description),
		    website_url = COALESCE(?, website_url),
		    status = ?,
		    monthly_price = COALESCE(?, monthly_price),
		    currency = COALESCE(?, currency),
		    start_date = COALESCE(?, start_date),
		    end_date = COALESCE(?, end_date),
		    updated_at = NOW()
		WHERE id = ?
	`

	_, err := Db.Exec(query,
		updates["partner_name"], updates["partner_logo"], updates["description"],
		updates["website_url"], updates["status"], updates["monthly_price"],
		updates["currency"], updates["start_date"], updates["end_date"], campaignID.String(),
	)

	return err
}

func UpdatePartnershipCampaignStatusInDB(campaignID uuid.UUID, status int) error {
	query := `
		UPDATE partnership_campaigns
		SET status = ?,
		    updated_at = NOW()
		WHERE id = ?
	`

	_, err := Db.Exec(query, status, campaignID.String())
	return err
}

func UpdatePartnershipCampaignPaymentIntentInDB(campaignID uuid.UUID, paymentIntentID string, status int) error {
	query := `
		UPDATE partnership_campaigns
		SET stripe_payment_intent_id = ?,
		    status = ?,
		    updated_at = NOW()
		WHERE id = ?
	`

	_, err := Db.Exec(query, paymentIntentID, status, campaignID.String())
	return err
}

func DeletePartnershipCampaignInDB(campaignID uuid.UUID) error {
	query := "DELETE FROM partnership_campaigns WHERE id = ?"
	_, err := Db.Exec(query, campaignID.String())
	return err
}

func AddPartnershipCampaignItemInDB(item models.PartnershipCampaignItem) error {
	query := `
		INSERT INTO partnership_campaign_items
		(id, campaign_id, annonce_id, position_type, position_priority, created_at)
		VALUES (?, ?, ?, ?, ?, NOW())
	`

	annonceIDStr := ""
	if item.AnnonceID != nil {
		annonceIDStr = item.AnnonceID.String()
	}

	_, err := Db.Exec(query,
		item.ID.String(), item.CampaignID.String(), annonceIDStr,
		item.PositionType, item.PositionPriority,
	)

	return err
}

func RemovePartnershipCampaignItemInDB(itemID uuid.UUID) error {
	query := "DELETE FROM partnership_campaign_items WHERE id = ?"
	_, err := Db.Exec(query, itemID.String())
	return err
}

func GetActiveCampaignItemsInDB() ([]models.PartnershipCampaignItem, error) {
	query := `
		SELECT pci.id, pci.campaign_id, pci.annonce_id, pci.position_type, pci.position_priority, pci.created_at
		FROM partnership_campaign_items pci
		JOIN partnership_campaigns pc ON pci.campaign_id = pc.id
		WHERE pc.status = 1 AND CURDATE() BETWEEN pc.start_date AND pc.end_date
		ORDER BY pci.position_priority ASC
	`

	rows, err := Db.Query(query)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var items []models.PartnershipCampaignItem
	for rows.Next() {
		var item models.PartnershipCampaignItem
		if err := rows.Scan(
			&item.ID, &item.CampaignID, &item.AnnonceID, &item.PositionType,
			&item.PositionPriority, &item.CreatedAt,
		); err != nil {
			return nil, err
		}
		items = append(items, item)
	}

	return items, rows.Err()
}
