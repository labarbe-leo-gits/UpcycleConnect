package db

import (
	"API/models"
	"database/sql"
	"fmt"
	"time"

	"github.com/google/uuid"
)

func GetCommissionSettingsFromDB() (*models.CommissionSettings, error) {
	query := `
		SELECT id, commission_rate_min, commission_rate_max, is_global, effective_from, 
		       effective_to, created_at, updated_at
		FROM commission_settings
		WHERE is_global = 1
		ORDER BY effective_from DESC
		LIMIT 1
	`

	var settings models.CommissionSettings
	var effectiveTo sql.NullString
	err := Db.QueryRow(query).Scan(
		&settings.ID, &settings.CommissionMin, &settings.CommissionMax,
		&settings.IsGlobal, &settings.EffectiveFrom, &effectiveTo,
		&settings.CreatedAt, &settings.UpdatedAt,
	)

	if err != nil {
		if err == sql.ErrNoRows {
			return &models.CommissionSettings{
				CommissionMin: 5,
				CommissionMax: 10,
				IsGlobal:      true,
				EffectiveFrom: time.Now().Format("2006-01-02"),
			}, nil
		}
		return nil, err
	}
	if effectiveTo.Valid {
		settings.EffectiveTo = &effectiveTo.String
	}

	return &settings, nil
}

func UpdateCommissionSettingsInDB(minRate float64, maxRate float64, effectiveFrom string, effectiveTo *string) error {
	query := `
		INSERT INTO commission_settings (id, commission_rate_min, commission_rate_max, is_global, effective_from, effective_to, created_at, updated_at)
		VALUES (?, ?, ?, 1, ?, ?, NOW(), NOW())
	`

	_, err := Db.Exec(query, uuid.New().String(), minRate, maxRate, effectiveFrom, effectiveTo)
	return err
}

func GetAllCommissionTransactionsFromDB() ([]models.CommissionTransaction, error) {
	query := `
		SELECT id, order_id, seller_id, amount_before_commission, commission_rate,
		       commission_amount, amount_after_commission, status, notes, created_at, updated_at
		FROM commission_transactions
		ORDER BY created_at DESC
	`

	rows, err := Db.Query(query)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var transactions []models.CommissionTransaction
	for rows.Next() {
		var trans models.CommissionTransaction
		var notes sql.NullString
		if err := rows.Scan(
			&trans.ID, &trans.OrderID, &trans.SellerID, &trans.AmountBeforeComm,
			&trans.CommissionRate, &trans.CommissionAmount, &trans.AmountAfterComm,
			&trans.Status, &notes, &trans.CreatedAt, &trans.UpdatedAt,
		); err != nil {
			return nil, err
		}
		if notes.Valid {
			value := notes.String
			trans.Notes = &value
		}
		transactions = append(transactions, trans)
	}

	return transactions, rows.Err()
}

func GetCommissionTransactionsBySellerFromDB(sellerID uuid.UUID) ([]models.CommissionTransaction, error) {
	query := `
		SELECT id, order_id, seller_id, amount_before_commission, commission_rate,
		       commission_amount, amount_after_commission, status, notes, created_at, updated_at
		FROM commission_transactions
		WHERE seller_id = ?
		ORDER BY created_at DESC
	`

	rows, err := Db.Query(query, sellerID.String())
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var transactions []models.CommissionTransaction
	for rows.Next() {
		var trans models.CommissionTransaction
		var notes sql.NullString
		if err := rows.Scan(
			&trans.ID, &trans.OrderID, &trans.SellerID, &trans.AmountBeforeComm,
			&trans.CommissionRate, &trans.CommissionAmount, &trans.AmountAfterComm,
			&trans.Status, &notes, &trans.CreatedAt, &trans.UpdatedAt,
		); err != nil {
			return nil, err
		}
		if notes.Valid {
			value := notes.String
			trans.Notes = &value
		}
		transactions = append(transactions, trans)
	}

	return transactions, rows.Err()
}

func GetCommissionTransactionByIDFromDB(transID uuid.UUID) (*models.CommissionTransaction, error) {
	query := `
		SELECT id, order_id, seller_id, amount_before_commission, commission_rate,
		       commission_amount, amount_after_commission, status, notes, created_at, updated_at
		FROM commission_transactions
		WHERE id = ?
	`

	var trans models.CommissionTransaction
	var notes sql.NullString
	err := Db.QueryRow(query, transID.String()).Scan(
		&trans.ID, &trans.OrderID, &trans.SellerID, &trans.AmountBeforeComm,
		&trans.CommissionRate, &trans.CommissionAmount, &trans.AmountAfterComm,
		&trans.Status, &notes, &trans.CreatedAt, &trans.UpdatedAt,
	)

	if err != nil {
		return nil, err
	}
	if notes.Valid {
		value := notes.String
		trans.Notes = &value
	}

	return &trans, nil
}

func CreateCommissionTransactionInDB(transaction models.CommissionTransaction) error {
	query := `
		INSERT INTO commission_transactions
		(id, order_id, seller_id, amount_before_commission, commission_rate, commission_amount,
		 amount_after_commission, status, notes, created_at, updated_at)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
	`

	_, err := Db.Exec(query,
		transaction.ID.String(), transaction.OrderID.String(), transaction.SellerID.String(),
		transaction.AmountBeforeComm, transaction.CommissionRate, transaction.CommissionAmount,
		transaction.AmountAfterComm, transaction.Status, transaction.Notes,
	)

	return err
}

func CreateCommissionTransactionWithTx(tx *sql.Tx, transaction models.CommissionTransaction) error {
	query := `
		INSERT INTO commission_transactions
		(id, order_id, seller_id, amount_before_commission, commission_rate, commission_amount,
		 amount_after_commission, status, notes, created_at, updated_at)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
	`

	_, err := tx.Exec(query,
		transaction.ID.String(), transaction.OrderID.String(), transaction.SellerID.String(),
		transaction.AmountBeforeComm, transaction.CommissionRate, transaction.CommissionAmount,
		transaction.AmountAfterComm, transaction.Status, transaction.Notes,
	)

	return err
}

func UpdateCommissionTransactionStatusInDB(transID uuid.UUID, status int) error {
	query := `
		UPDATE commission_transactions
		SET status = ?, updated_at = NOW()
		WHERE id = ?
	`

	_, err := Db.Exec(query, status, transID.String())
	return err
}

func GetSellerTotalCommissionInDB(sellerID uuid.UUID) (float64, error) {
	query := `
		SELECT COALESCE(SUM(commission_amount), 0)
		FROM commission_transactions
		WHERE seller_id = ? AND status = 0
	`

	var total float64
	err := Db.QueryRow(query, sellerID.String()).Scan(&total)
	if err != nil {
		fmt.Println("[ERROR] GetSellerTotalCommissionInDB:", err)
		return 0, err
	}

	return total, nil
}
