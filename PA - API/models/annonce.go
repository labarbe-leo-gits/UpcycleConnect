package models

import "github.com/google/uuid"

type Annonce struct {
	ID          uuid.UUID `json:"id" gorm:"type:uuid;primaryKey"`
	UserID      uuid.UUID `json:"user_id" gorm:"type:uuid;not null"`
	Title       string    `json:"title" gorm:"not null"`
	Status      int       `json:"status" gorm:"not null;default:0"`
	ViewCount   int       `json:"view_count" gorm:"not null;default:0"`
	Description string    `json:"description,omitempty"`
	Price       float64   `json:"price,omitempty"`

	PoidsMateriaux  float64    `json:"poids_materiaux,omitempty"`
	FacteurID       *uuid.UUID `json:"facteur_id,omitempty"`
	TypeMateriaux   string     `json:"type_materiaux,omitempty"`
	EstimationScore float64    `json:"estimation_score,omitempty"`
	UpcyclingScore  float64    `json:"upcycling_score,omitempty"`
	CategoryID      *uuid.UUID `json:"category_id,omitempty"`
	CategoryName    string     `json:"category_name,omitempty"`
	ItemState       int        `json:"item_state"`

	AdCampaignID   *uuid.UUID `json:"ad_campaign_id,omitempty"`
	SellerUserType *int       `json:"seller_user_type,omitempty"`
	Promoted       bool       `json:"promoted,omitempty"`

	CreatedAt string `json:"created_at,omitempty"`
	UpdatedAt string `json:"updated_at,omitempty"`
}
