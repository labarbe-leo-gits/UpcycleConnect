package models

import (
	"encoding/json"

	"github.com/google/uuid"
)

type SubscriptionTier struct {
	ID               uuid.UUID       `json:"id"`
	Name             string          `json:"name"`
	Description      string          `json:"description,omitempty"`
	TierLevel        int             `json:"tier_level"`
	MonthlyPrice     float64         `json:"monthly_price"`
	Currency         string          `json:"currency"`
	StripePriceID    string          `json:"stripe_price_id,omitempty"`
	Features         json.RawMessage `json:"features,omitempty"`
	DashboardAccess  bool            `json:"dashboard_access"`
	AnalyticsAccess  bool            `json:"analytics_access"`
	MaterialStats    bool            `json:"material_stats"`
	CollectionAlerts bool            `json:"collection_alerts"`
	MaxAnnonces      *int            `json:"max_annonces,omitempty"`
	IsSystem         bool            `json:"is_system"`
	IsActive         bool            `json:"is_active"`
	CreatedAt        string          `json:"created_at,omitempty"`
	UpdatedAt        string          `json:"updated_at,omitempty"`
}

type UserSubscriptionTier struct {
	ID         uuid.UUID         `json:"id"`
	UserID     uuid.UUID         `json:"user_id"`
	TierID     uuid.UUID         `json:"tier_id"`
	ContractID *uuid.UUID        `json:"contract_id,omitempty"`
	StartedAt  string            `json:"started_at,omitempty"`
	EndedAt    *string           `json:"ended_at,omitempty"`
	Tier       *SubscriptionTier `json:"tier,omitempty"`
}
