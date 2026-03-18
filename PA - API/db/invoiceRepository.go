package db

import (
	"API/models"
	"database/sql"
	"fmt"

	"github.com/google/uuid"
)

func GetContractIDBySubscription(subscriptionID string) (*uuid.UUID, error) {
	var idStr sql.NullString
	err := Db.QueryRow("SELECT id FROM contracts WHERE subscriptionID = ?", subscriptionID).Scan(&idStr)
	if err != nil {
		if err == sql.ErrNoRows {
			return nil, nil
		}
		return nil, fmt.Errorf("getContractIDBySubscription: %w", err)
	}
	if !idStr.Valid {
		return nil, nil
	}
	id, err := uuid.Parse(idStr.String)
	if err != nil {
		return nil, fmt.Errorf("getContractIDBySubscription parse uuid: %w", err)
	}
	return &id, nil
}

func UpsertInvoice(inv models.Invoice) error {
	contractID := sql.NullString{Valid: false}
	if inv.ContractID != nil {
		contractID = sql.NullString{String: inv.ContractID.String(), Valid: true}
	}
	_, err := Db.Exec(
		`INSERT INTO invoices (id, user_id, contract_id, stripe_invoice_id, stripe_payment_intent_id, amount_due, amount_paid, currency, status, due_date, period_start, period_end, invoice_url, receipt_url, created_at, updated_at)
         VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
           amount_due = VALUES(amount_due),
           amount_paid = VALUES(amount_paid),
           currency = VALUES(currency),
           status = VALUES(status),
           due_date = VALUES(due_date),
           period_start = VALUES(period_start),
           period_end = VALUES(period_end),
           invoice_url = VALUES(invoice_url),
           receipt_url = VALUES(receipt_url),
           updated_at = NOW()`,
		inv.UserID.String(),
		contractID,
		inv.StripeInvoiceID,
		inv.StripePaymentIntentID,
		inv.AmountDue,
		inv.AmountPaid,
		inv.Currency,
		inv.Status,
		nullString(inv.DueDate),
		nullString(inv.PeriodStart),
		nullString(inv.PeriodEnd),
		nullString(inv.InvoiceURL),
		nullString(inv.ReceiptURL),
	)
	if err != nil {
		return fmt.Errorf("upsertInvoice: %w", err)
	}
	return nil
}

func GetInvoicesByUserID(userID uuid.UUID) ([]models.Invoice, error) {
	rows, err := Db.Query(
		`SELECT id, user_id, contract_id, stripe_invoice_id, stripe_payment_intent_id, amount_due, amount_paid, currency, status, due_date, period_start, period_end, invoice_url, receipt_url, created_at, updated_at
         FROM invoices WHERE user_id = ? ORDER BY created_at DESC`,
		userID.String(),
	)
	if err != nil {
		return nil, fmt.Errorf("getInvoicesByUserID: %w", err)
	}
	defer rows.Close()

	invoices := []models.Invoice{}

	for rows.Next() {
		var inv models.Invoice
		var idStr string
		var userIDStr string
		var contractID sql.NullString
		var dueDate sql.NullString
		var periodStart sql.NullString
		var periodEnd sql.NullString
		var invoiceURL sql.NullString
		var receiptURL sql.NullString
		var createdAt sql.NullString
		var updatedAt sql.NullString

		err := rows.Scan(
			&idStr,
			&userIDStr,
			&contractID,
			&inv.StripeInvoiceID,
			&inv.StripePaymentIntentID,
			&inv.AmountDue,
			&inv.AmountPaid,
			&inv.Currency,
			&inv.Status,
			&dueDate,
			&periodStart,
			&periodEnd,
			&invoiceURL,
			&receiptURL,
			&createdAt,
			&updatedAt,
		)
		if err != nil {
			return nil, fmt.Errorf("getInvoicesByUserID scan: %w", err)
		}

		inv.ID, _ = uuid.Parse(idStr)
		inv.UserID, _ = uuid.Parse(userIDStr)
		if contractID.Valid {
			if cid, err := uuid.Parse(contractID.String); err == nil {
				inv.ContractID = &cid
			}
		}
		if dueDate.Valid {
			inv.DueDate = dueDate.String
		}
		if periodStart.Valid {
			inv.PeriodStart = periodStart.String
		}
		if periodEnd.Valid {
			inv.PeriodEnd = periodEnd.String
		}
		if invoiceURL.Valid {
			inv.InvoiceURL = invoiceURL.String
		}
		if receiptURL.Valid {
			inv.ReceiptURL = receiptURL.String
		}
		if createdAt.Valid {
			inv.CreatedAt = createdAt.String
		}
		if updatedAt.Valid {
			inv.UpdatedAt = updatedAt.String
		}

		invoices = append(invoices, inv)
	}

	if err = rows.Err(); err != nil {
		return nil, fmt.Errorf("getInvoicesByUserID rows: %w", err)
	}

	return invoices, nil
}

func nullString(s string) sql.NullString {
	if s == "" {
		return sql.NullString{Valid: false}
	}
	return sql.NullString{String: s, Valid: true}
}
