package models

import "github.com/google/uuid"

type RevenueReport struct {
	ID                   uuid.UUID `json:"id"`
	ReportPeriodStart    string    `json:"report_period_start"`
	ReportPeriodEnd      string    `json:"report_period_end"`
	SubscriptionRevenue  float64   `json:"subscription_revenue"`
	CommissionRevenue    float64   `json:"commission_revenue"`
	PartnershipRevenue   float64   `json:"partnership_revenue"`
	TrainingRevenue      float64   `json:"training_revenue"`
	TotalRevenue         float64   `json:"total_revenue"`
	SubscriptionCount    int       `json:"subscription_count"`
	CommissionCount      int       `json:"commission_count"`
	PartnershipCount     int       `json:"partnership_count"`
	TrainingParticipants int       `json:"training_participants"`
	GeneratedAt          string    `json:"generated_at,omitempty"`
}
