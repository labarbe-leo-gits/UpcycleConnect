package models

import "github.com/google/uuid"

type Contract struct {
	ID                       uuid.UUID      `json:"id"`
	ContractRef              string         `json:"contract_ref,omitempty"`
	ContractType             int            `json:"contract_type"`
	SubscriptionID           string         `json:"subscription_id"`
	StripeCustomerID         string         `json:"stripe_customer_id,omitempty"`
	StripePriceID            string         `json:"stripe_price_id,omitempty"`
	StripeProductID          string         `json:"stripe_product_id,omitempty"`
	UserID                   uuid.UUID      `json:"user_id"`
	Amount                   float64        `json:"amount,omitempty"`
	Currency                 string         `json:"currency,omitempty"`
	BillingInterval          string         `json:"billing_interval,omitempty"`
	StartDate                string         `json:"start_date"`
	EndDate                  string         `json:"end_date"`
	CancelAtPeriodEnd        bool           `json:"cancel_at_period_end"`
	CancelledAt              string         `json:"cancelled_at,omitempty"`
	StripeSubscriptionStatus string         `json:"stripe_subscription_status,omitempty"`
	Status                   int            `json:"status"`
	Metadata                 map[string]any `json:"metadata,omitempty"`
	LastBilledAt             string         `json:"last_billed_at,omitempty"`
	NextBillingAt            string         `json:"next_billing_at,omitempty"`
	CreatedAt                string         `json:"created_at,omitempty"`
	UpdatedAt                string         `json:"updated_at,omitempty"`
}

type ContractWithUser struct {
	Contract
	UserFirstName string `json:"user_first_name,omitempty"`
	UserLastName  string `json:"user_last_name,omitempty"`
	UserEmail     string `json:"user_email,omitempty"`
	Username      string `json:"username,omitempty"`
}
