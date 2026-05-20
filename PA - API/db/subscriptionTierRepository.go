package db

import (
	"API/models"
	"database/sql"
	"encoding/json"

	"github.com/google/uuid"
)

func GetSubscriptionTiersFromDB() ([]models.SubscriptionTier, error) {
	query := `
		SELECT id, name, description, tier_level, monthly_price, currency, stripe_price_id,
			   features, dashboard_access, analytics_access, material_stats, collection_alerts,
			   max_annonces, is_system, is_active, created_at, updated_at
		FROM subscription_tiers
		WHERE is_active = 1
		ORDER BY tier_level ASC
	`

	rows, err := Db.Query(query)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var tiers []models.SubscriptionTier
	for rows.Next() {
		var tier models.SubscriptionTier
		var stripePriceID sql.NullString
		var features []byte
		if err := rows.Scan(
			&tier.ID, &tier.Name, &tier.Description, &tier.TierLevel, &tier.MonthlyPrice,
			&tier.Currency, &stripePriceID, &features, &tier.DashboardAccess,
			&tier.AnalyticsAccess, &tier.MaterialStats, &tier.CollectionAlerts,
			&tier.MaxAnnonces, &tier.IsSystem, &tier.IsActive, &tier.CreatedAt, &tier.UpdatedAt,
		); err != nil {
			return nil, err
		}
		if stripePriceID.Valid {
			tier.StripePriceID = stripePriceID.String
		}
		if features != nil {
			tier.Features = json.RawMessage(features)
		}
		tiers = append(tiers, tier)
	}

	return tiers, rows.Err()
}

func GetSubscriptionTierByIDFromDB(tierID uuid.UUID) (*models.SubscriptionTier, error) {
	query := `
		SELECT id, name, description, tier_level, monthly_price, currency, stripe_price_id,
			   features, dashboard_access, analytics_access, material_stats, collection_alerts,
			   max_annonces, is_system, is_active, created_at, updated_at
		FROM subscription_tiers
		WHERE id = ?
	`

	var tier models.SubscriptionTier
	var stripePriceID sql.NullString
	var features []byte
	err := Db.QueryRow(query, tierID).Scan(
		&tier.ID, &tier.Name, &tier.Description, &tier.TierLevel, &tier.MonthlyPrice,
		&tier.Currency, &stripePriceID, &features, &tier.DashboardAccess,
		&tier.AnalyticsAccess, &tier.MaterialStats, &tier.CollectionAlerts,
		&tier.MaxAnnonces, &tier.IsSystem, &tier.IsActive, &tier.CreatedAt, &tier.UpdatedAt,
	)

	if err != nil {
		return nil, err
	}

	if features != nil {
		tier.Features = json.RawMessage(features)
	}
	if stripePriceID.Valid {
		tier.StripePriceID = stripePriceID.String
	}

	return &tier, nil
}

func CreateSubscriptionTierInDB(tier models.SubscriptionTier) error {
	query := `
		INSERT INTO subscription_tiers 
		(id, name, description, tier_level, monthly_price, currency, stripe_price_id,
		 features, dashboard_access, analytics_access, material_stats, collection_alerts,
		 max_annonces, is_system, is_active, created_at, updated_at)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
	`

	featuresJSON, err := json.Marshal(tier.Features)
	if err != nil {
		featuresJSON = []byte("null")
	}

	_, err = Db.Exec(query,
		tier.ID.String(), tier.Name, tier.Description, tier.TierLevel, tier.MonthlyPrice,
		tier.Currency, tier.StripePriceID, featuresJSON, tier.DashboardAccess,
		tier.AnalyticsAccess, tier.MaterialStats, tier.CollectionAlerts,
		tier.MaxAnnonces, tier.IsSystem, tier.IsActive,
	)

	return err
}

func UpdateSubscriptionTierInDB(tierID uuid.UUID, updates map[string]interface{}) error {
	query := `
		UPDATE subscription_tiers
		SET name = COALESCE(?, name),
		    description = COALESCE(?, description),
		    monthly_price = COALESCE(?, monthly_price),
		    currency = COALESCE(?, currency),
		    stripe_price_id = COALESCE(?, stripe_price_id),
		    features = COALESCE(?, features),
		    dashboard_access = ?,
		    analytics_access = ?,
		    material_stats = ?,
		    collection_alerts = ?,
		    max_annonces = COALESCE(?, max_annonces),
			is_active = ?,
		    updated_at = NOW()
		WHERE id = ?
	`

	featuresJSON, _ := json.Marshal(updates["features"])

	_, err := Db.Exec(query,
		updates["name"], updates["description"], updates["monthly_price"],
		updates["currency"], updates["stripe_price_id"], featuresJSON,
		updates["dashboard_access"], updates["analytics_access"],
		updates["material_stats"], updates["collection_alerts"],
		updates["max_annonces"], updates["is_active"], tierID.String(),
	)

	return err
}

func DeleteSubscriptionTierInDB(tierID uuid.UUID) error {
	query := "DELETE FROM subscription_tiers WHERE id = ? AND is_system = 0"
	res, err := Db.Exec(query, tierID.String())
	if err != nil {
		return err
	}
	rowsAffected, err := res.RowsAffected()
	if err != nil {
		return err
	}
	if rowsAffected == 0 {
		return sql.ErrNoRows
	}
	return nil
}

func GetUserCurrentTierFromDB(userID uuid.UUID) (*models.UserSubscriptionTier, error) {
	query := `
		SELECT ust.id, ust.user_id, ust.tier_id, ust.contract_id, ust.started_at, ust.ended_at
		FROM user_subscription_tiers ust
		WHERE ust.user_id = ? AND ust.ended_at IS NULL
		ORDER BY ust.started_at DESC
		LIMIT 1
	`

	var subscription models.UserSubscriptionTier
	err := Db.QueryRow(query, userID.String()).Scan(
		&subscription.ID, &subscription.UserID, &subscription.TierID,
		&subscription.ContractID, &subscription.StartedAt, &subscription.EndedAt,
	)

	if err != nil {
		return nil, err
	}

	tier, err := GetSubscriptionTierByIDFromDB(subscription.TierID)
	if err == nil {
		subscription.Tier = tier
	}

	return &subscription, nil
}

func AssignSubscriptionTierToUserInDB(userID uuid.UUID, tierID uuid.UUID, contractID *uuid.UUID) error {
	query := `
		INSERT INTO user_subscription_tiers (id, user_id, tier_id, contract_id, started_at)
		VALUES (?, ?, ?, ?, NOW())
	`

	contractIDStr := ""
	if contractID != nil {
		contractIDStr = contractID.String()
	}

	_, err := Db.Exec(query, uuid.New().String(), userID.String(), tierID.String(), contractIDStr)
	return err
}

func EndUserSubscriptionTierInDB(userID uuid.UUID) error {
	query := `
		UPDATE user_subscription_tiers
		SET ended_at = NOW()
		WHERE user_id = ? AND ended_at IS NULL
	`

	_, err := Db.Exec(query, userID.String())
	return err
}
