package models

import "github.com/google/uuid"

type RefundRequest struct {
	ID           uuid.UUID `json:"id" gorm:"type:uuid;primaryKey"`
	OrderID      uuid.UUID `json:"order_id" gorm:"type:uuid;not null"`
	UserID       uuid.UUID `json:"user_id" gorm:"type:uuid;not null"`
	Reason       string    `json:"reason" gorm:"type:text;not null"`
	Status       int       `json:"status" gorm:"type:int;default:1"`
	AdminComment string    `json:"admin_comment,omitempty" gorm:"type:text"`
	CreatedAt    string    `json:"created_at"`
	UpdatedAt    string    `json:"updated_at"`
	ApproverID   uuid.UUID `json:"approver_id" gorm:"type:uuid;default:null"`
	UserName     string    `json:"user_name,omitempty"`
	OrderTitle   string    `json:"order_title,omitempty"`
	ApproverName string    `json:"approver_name,omitempty"`
}
