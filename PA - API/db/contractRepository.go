package db

import (
	"API/models"
	"database/sql"
	"encoding/json"
	"fmt"
	"time"

	"github.com/google/uuid"
)

func scanContractRow(rows *sql.Rows) (models.Contract, error) {
	var c models.Contract
	var idStr string
	var userIDStr string
	var contractRef sql.NullString
	var stripeCustomerID sql.NullString
	var stripePriceID sql.NullString
	var stripeProductID sql.NullString
	var currency sql.NullString
	var billingInterval sql.NullString
	var amount sql.NullFloat64
	var cancelledAt sql.NullString
	var stripeStatus sql.NullString
	var metadata sql.NullString
	var lastBilledAt sql.NullString
	var nextBillingAt sql.NullString
	var createdAt sql.NullString
	var updatedAt sql.NullString

	err := rows.Scan(
		&idStr,
		&contractRef,
		&c.ContractType,
		&c.SubscriptionID,
		&stripeCustomerID,
		&stripePriceID,
		&stripeProductID,
		&userIDStr,
		&amount,
		&currency,
		&billingInterval,
		&c.StartDate,
		&c.EndDate,
		&c.CancelAtPeriodEnd,
		&cancelledAt,
		&stripeStatus,
		&c.Status,
		&metadata,
		&lastBilledAt,
		&nextBillingAt,
		&createdAt,
		&updatedAt,
	)
	if err != nil {
		return c, err
	}

	c.ID, _ = uuid.Parse(idStr)
	c.UserID, _ = uuid.Parse(userIDStr)
	if contractRef.Valid {
		c.ContractRef = contractRef.String
	}
	if stripeCustomerID.Valid {
		c.StripeCustomerID = stripeCustomerID.String
	}
	if stripePriceID.Valid {
		c.StripePriceID = stripePriceID.String
	}
	if stripeProductID.Valid {
		c.StripeProductID = stripeProductID.String
	}
	if amount.Valid {
		c.Amount = amount.Float64
	}
	if currency.Valid {
		c.Currency = currency.String
	}
	if billingInterval.Valid {
		c.BillingInterval = billingInterval.String
	}
	if cancelledAt.Valid {
		c.CancelledAt = cancelledAt.String
	}
	if stripeStatus.Valid {
		c.StripeSubscriptionStatus = stripeStatus.String
	}
	if metadata.Valid {
		var m map[string]any
		if err := json.Unmarshal([]byte(metadata.String), &m); err == nil {
			c.Metadata = m
		}
	}
	if lastBilledAt.Valid {
		c.LastBilledAt = lastBilledAt.String
	}
	if nextBillingAt.Valid {
		c.NextBillingAt = nextBillingAt.String
	}
	if createdAt.Valid {
		c.CreatedAt = createdAt.String
	}
	if updatedAt.Valid {
		c.UpdatedAt = updatedAt.String
	}

	return c, nil
}

func scanContractWithUserRow(rows *sql.Rows) (models.ContractWithUser, error) {
	var result models.ContractWithUser
	var idStr string
	var userIDStr string
	var contractRef sql.NullString
	var stripeCustomerID sql.NullString
	var stripePriceID sql.NullString
	var stripeProductID sql.NullString
	var currency sql.NullString
	var billingInterval sql.NullString
	var amount sql.NullFloat64
	var cancelledAt sql.NullString
	var stripeStatus sql.NullString
	var metadata sql.NullString
	var lastBilledAt sql.NullString
	var nextBillingAt sql.NullString
	var createdAt sql.NullString
	var updatedAt sql.NullString
	var firstName sql.NullString
	var lastName sql.NullString
	var email sql.NullString
	var username sql.NullString

	err := rows.Scan(
		&idStr,
		&contractRef,
		&result.ContractType,
		&result.SubscriptionID,
		&stripeCustomerID,
		&stripePriceID,
		&stripeProductID,
		&userIDStr,
		&amount,
		&currency,
		&billingInterval,
		&result.StartDate,
		&result.EndDate,
		&result.CancelAtPeriodEnd,
		&cancelledAt,
		&stripeStatus,
		&result.Status,
		&metadata,
		&lastBilledAt,
		&nextBillingAt,
		&createdAt,
		&updatedAt,
		&firstName,
		&lastName,
		&email,
		&username,
	)
	if err != nil {
		return result, err
	}

	result.ID, _ = uuid.Parse(idStr)
	result.UserID, _ = uuid.Parse(userIDStr)
	if contractRef.Valid {
		result.ContractRef = contractRef.String
	}
	if stripeCustomerID.Valid {
		result.StripeCustomerID = stripeCustomerID.String
	}
	if stripePriceID.Valid {
		result.StripePriceID = stripePriceID.String
	}
	if stripeProductID.Valid {
		result.StripeProductID = stripeProductID.String
	}
	if amount.Valid {
		result.Amount = amount.Float64
	}
	if currency.Valid {
		result.Currency = currency.String
	}
	if billingInterval.Valid {
		result.BillingInterval = billingInterval.String
	}
	if cancelledAt.Valid {
		result.CancelledAt = cancelledAt.String
	}
	if stripeStatus.Valid {
		result.StripeSubscriptionStatus = stripeStatus.String
	}
	if metadata.Valid {
		var m map[string]any
		if err := json.Unmarshal([]byte(metadata.String), &m); err == nil {
			result.Metadata = m
		}
	}
	if lastBilledAt.Valid {
		result.LastBilledAt = lastBilledAt.String
	}
	if nextBillingAt.Valid {
		result.NextBillingAt = nextBillingAt.String
	}
	if createdAt.Valid {
		result.CreatedAt = createdAt.String
	}
	if updatedAt.Valid {
		result.UpdatedAt = updatedAt.String
	}
	if firstName.Valid {
		result.UserFirstName = firstName.String
	}
	if lastName.Valid {
		result.UserLastName = lastName.String
	}
	if email.Valid {
		result.UserEmail = email.String
	}
	if username.Valid {
		result.Username = username.String
	}

	return result, nil
}

func GetContractsByUserID(userID uuid.UUID) ([]models.Contract, error) {
	rows, err := Db.Query(
		`SELECT id, contract_ref, contract_type, subscriptionID, stripe_customer_id, stripe_price_id, stripe_product_id, user_id, amount, currency, billing_interval, start_date, end_date, cancel_at_period_end, cancelled_at, stripe_subscription_status, status, metadata, last_billed_at, next_billing_at, created_at, updated_at
         FROM contracts WHERE user_id = ? ORDER BY created_at DESC`,
		userID.String(),
	)
	if err != nil {
		return nil, fmt.Errorf("getContractsByUserID: %w", err)
	}
	defer rows.Close()

	contracts := []models.Contract{}
	for rows.Next() {
		c, err := scanContractRow(rows)
		if err != nil {
			return nil, fmt.Errorf("getContractsByUserID scan: %w", err)
		}
		contracts = append(contracts, c)
	}

	if err := rows.Err(); err != nil {
		return nil, fmt.Errorf("getContractsByUserID rows: %w", err)
	}

	return contracts, nil
}

func GetAllContractsWithUser() ([]models.ContractWithUser, error) {
	rows, err := Db.Query(
		`SELECT c.id, c.contract_ref, c.contract_type, c.subscriptionID, c.stripe_customer_id, c.stripe_price_id, c.stripe_product_id, c.user_id, c.amount, c.currency, c.billing_interval, c.start_date, c.end_date, c.cancel_at_period_end, c.cancelled_at, c.stripe_subscription_status, c.status, c.metadata, c.last_billed_at, c.next_billing_at, c.created_at, c.updated_at, u.first_name, u.last_name, u.email, u.username
		 FROM contracts c
		 LEFT JOIN users u ON c.user_id = u.id
		 ORDER BY c.created_at DESC`,
	)
	if err != nil {
		return nil, fmt.Errorf("getAllContractsWithUser: %w", err)
	}
	defer rows.Close()

	contracts := []models.ContractWithUser{}
	for rows.Next() {
		c, err := scanContractWithUserRow(rows)
		if err != nil {
			return nil, fmt.Errorf("getAllContractsWithUser scan: %w", err)
		}
		contracts = append(contracts, c)
	}

	if err := rows.Err(); err != nil {
		return nil, fmt.Errorf("getAllContractsWithUser rows: %w", err)
	}

	return contracts, nil
}

func GetContractWithUserByID(contractID uuid.UUID) (models.ContractWithUser, error) {
	rows, err := Db.Query(
		`SELECT c.id, c.contract_ref, c.contract_type, c.subscriptionID, c.stripe_customer_id, c.stripe_price_id, c.stripe_product_id, c.user_id, c.amount, c.currency, c.billing_interval, c.start_date, c.end_date, c.cancel_at_period_end, c.cancelled_at, c.stripe_subscription_status, c.status, c.metadata, c.last_billed_at, c.next_billing_at, c.created_at, c.updated_at, u.first_name, u.last_name, u.email, u.username
		 FROM contracts c
		 LEFT JOIN users u ON c.user_id = u.id
		 WHERE c.id = ?`,
		contractID.String(),
	)
	if err != nil {
		return models.ContractWithUser{}, fmt.Errorf("getContractWithUserByID: %w", err)
	}
	defer rows.Close()

	if rows.Next() {
		contract, err := scanContractWithUserRow(rows)
		if err != nil {
			return models.ContractWithUser{}, fmt.Errorf("getContractWithUserByID scan: %w", err)
		}
		return contract, nil
	}

	if err := rows.Err(); err != nil {
		return models.ContractWithUser{}, fmt.Errorf("getContractWithUserByID rows: %w", err)
	}

	return models.ContractWithUser{}, sql.ErrNoRows
}

func UpsertContractFromStripe(userID uuid.UUID, stripeCustomerID, stripeSubscriptionID string) error {
	now := time.Now().UTC()
	start := now.Format("2006-01-02")
	end := now.AddDate(0, 1, 0).Format("2006-01-02")

	_, err := Db.Exec(
		`INSERT INTO contracts (id, contract_ref, contract_type, subscriptionID, stripe_customer_id, user_id, start_date, end_date, status, cancel_at_period_end, stripe_subscription_status, created_at, updated_at)
         VALUES (UUID(), ?, 1, ?, ?, ?, ?, ?, 1, FALSE, 'active', NOW(), NOW())
         ON DUPLICATE KEY UPDATE
           stripe_customer_id = VALUES(stripe_customer_id),
           start_date = VALUES(start_date),
           end_date = VALUES(end_date),
           status = VALUES(status),
           cancel_at_period_end = VALUES(cancel_at_period_end),
           stripe_subscription_status = VALUES(stripe_subscription_status),
           updated_at = NOW()`,
		fmt.Sprintf("CTR-%s", stripeSubscriptionID),
		stripeSubscriptionID,
		stripeCustomerID,
		userID.String(),
		start,
		end,
	)
	if err != nil {
		return fmt.Errorf("upsertContractFromStripe: %w", err)
	}
	return nil
}

func UpsertContractForPromotion(userID uuid.UUID, stripeCustomerID, stripePaymentIntentID string, amount float64, currency, startDate, endDate string, metadata map[string]any) (uuid.UUID, error) {
	if stripePaymentIntentID == "" {
		return uuid.Nil, fmt.Errorf("missing payment intent id")
	}

	existing, err := GetContractIDBySubscription(stripePaymentIntentID)
	if err != nil {
		return uuid.Nil, err
	}
	if existing != nil {
		return *existing, nil
	}

	var metadataParam interface{} = nil
	if metadata != nil {
		if b, err := json.Marshal(metadata); err == nil {
			metadataParam = string(b)
		}
	}

	_, err = Db.Exec(
		`INSERT INTO contracts (id, contract_ref, contract_type, subscriptionID, stripe_customer_id, user_id, amount, currency, start_date, end_date, status, cancel_at_period_end, stripe_subscription_status, metadata, created_at, updated_at)
         VALUES (UUID(), ?, 2, ?, ?, ?, ?, ?, ?, ?, 1, FALSE, 'paid', ?, NOW(), NOW())`,
		fmt.Sprintf("PROMO-%s", stripePaymentIntentID),
		stripePaymentIntentID,
		stripeCustomerID,
		userID.String(),
		amount,
		currency,
		startDate,
		endDate,
		metadataParam,
	)
	if err != nil {
		return uuid.Nil, fmt.Errorf("upsertContractForPromotion: %w", err)
	}

	cid, err := GetContractIDBySubscription(stripePaymentIntentID)
	if err != nil {
		return uuid.Nil, err
	}
	if cid == nil {
		return uuid.Nil, fmt.Errorf("unable to fetch contract after insert")
	}
	return *cid, nil
}

func RevokeContractBySubscriptionID(subscriptionID string) error {
	_, err := Db.Exec(
		`UPDATE contracts SET status = 0, cancel_at_period_end = TRUE, cancelled_at = NOW(), stripe_subscription_status = 'canceled', updated_at = NOW() WHERE subscriptionID = ?`,
		subscriptionID,
	)
	if err != nil {
		return fmt.Errorf("revokeContractBySubscriptionID: %w", err)
	}
	return nil
}
