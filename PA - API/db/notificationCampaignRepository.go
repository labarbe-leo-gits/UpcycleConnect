package db

import (
	"API/models"
	"database/sql"
	"fmt"
	"strings"
	"time"

	"github.com/google/uuid"
)

func GetNotificationCampaignsFromDB(page, limit int, search string, status *int, targetUserType *int) ([]models.NotificationCampaign, int, error) {
	if page < 1 {
		page = 1
	}
	if limit < 1 {
		limit = 10
	}

	whereParts := []string{"1=1"}
	args := []interface{}{}

	if strings.TrimSpace(search) != "" {
		whereParts = append(whereParts, "(title LIKE ? OR message LIKE ?)")
		term := "%" + strings.TrimSpace(search) + "%"
		args = append(args, term, term)
	}

	if status != nil {
		whereParts = append(whereParts, "status = ?")
		args = append(args, *status)
	}

	if targetUserType != nil {
		whereParts = append(whereParts, "target_user_type = ?")
		args = append(args, *targetUserType)
	}

	whereClause := " WHERE " + strings.Join(whereParts, " AND ")

	countQuery := "SELECT COUNT(*) FROM notification_campaigns" + whereClause
	var totalCount int
	if err := Db.QueryRow(countQuery, args...).Scan(&totalCount); err != nil {
		return nil, 0, fmt.Errorf("getNotificationCampaigns count: %w", err)
	}

	offset := (page - 1) * limit
	listQuery := `
		SELECT c.id, c.title, c.message, c.target_user_type, c.status, c.scheduled_at, c.created_by_user_id,
		       c.created_at, c.updated_at,
		       COUNT(r.id) AS recipient_count,
		       COALESCE(SUM(CASE WHEN r.delivery_status = 1 THEN 1 ELSE 0 END), 0) AS sent_count,
		       COALESCE(SUM(CASE WHEN r.delivery_status = 2 THEN 1 ELSE 0 END), 0) AS failed_count,
		       COALESCE(SUM(CASE WHEN n.is_read = 1 THEN 1 ELSE 0 END), 0) AS read_count
		FROM notification_campaigns c
		LEFT JOIN notification_campaign_recipients r ON r.campaign_id = c.id
		LEFT JOIN notifications n ON n.id = r.notification_id
	` + whereClause + `
		GROUP BY c.id, c.title, c.message, c.target_user_type, c.status, c.scheduled_at, c.created_by_user_id, c.created_at, c.updated_at
		ORDER BY c.created_at DESC
		LIMIT ? OFFSET ?`

	listArgs := append([]interface{}{}, args...)
	listArgs = append(listArgs, limit, offset)
	rows, err := Db.Query(listQuery, listArgs...)
	if err != nil {
		return nil, 0, fmt.Errorf("getNotificationCampaigns query: %w", err)
	}
	defer rows.Close()

	campaigns := []models.NotificationCampaign{}
	for rows.Next() {
		var campaign models.NotificationCampaign
		var campaignIDStr string
		var creatorIDStr string
		var scheduledAt sql.NullString
		if err := rows.Scan(
			&campaignIDStr,
			&campaign.Title,
			&campaign.Message,
			&campaign.TargetUserType,
			&campaign.Status,
			&scheduledAt,
			&creatorIDStr,
			&campaign.CreatedAt,
			&campaign.UpdatedAt,
			&campaign.RecipientCount,
			&campaign.SentCount,
			&campaign.FailedCount,
			&campaign.ReadCount,
		); err != nil {
			return nil, 0, fmt.Errorf("getNotificationCampaigns scan: %w", err)
		}

		parsedCampaignID, err := uuid.Parse(campaignIDStr)
		if err != nil {
			return nil, 0, fmt.Errorf("getNotificationCampaigns parse campaign id: %w", err)
		}
		campaign.ID = parsedCampaignID
		campaign.CreatedByUserID = creatorIDStr

		if scheduledAt.Valid {
			tmp := scheduledAt.String
			campaign.ScheduledAt = &tmp
		}

		campaigns = append(campaigns, campaign)
	}

	if err := rows.Err(); err != nil {
		return nil, 0, fmt.Errorf("getNotificationCampaigns rows: %w", err)
	}

	return campaigns, totalCount, nil
}

func GetNotificationCampaignByIDFromDB(campaignID string) (models.NotificationCampaign, error) {
	var campaign models.NotificationCampaign
	query := `
		SELECT c.id, c.title, c.message, c.target_user_type, c.status, c.scheduled_at, c.created_by_user_id,
		       c.created_at, c.updated_at,
		       COUNT(r.id) AS recipient_count,
		       COALESCE(SUM(CASE WHEN r.delivery_status = 1 THEN 1 ELSE 0 END), 0) AS sent_count,
		       COALESCE(SUM(CASE WHEN r.delivery_status = 2 THEN 1 ELSE 0 END), 0) AS failed_count,
		       COALESCE(SUM(CASE WHEN n.is_read = 1 THEN 1 ELSE 0 END), 0) AS read_count
		FROM notification_campaigns c
		LEFT JOIN notification_campaign_recipients r ON r.campaign_id = c.id
		LEFT JOIN notifications n ON n.id = r.notification_id
		WHERE c.id = ?
		GROUP BY c.id, c.title, c.message, c.target_user_type, c.status, c.scheduled_at, c.created_by_user_id, c.created_at, c.updated_at`

	var campaignIDStr string
	var creatorIDStr string
	var scheduledAt sql.NullString

	err := Db.QueryRow(query, campaignID).Scan(
		&campaignIDStr,
		&campaign.Title,
		&campaign.Message,
		&campaign.TargetUserType,
		&campaign.Status,
		&scheduledAt,
		&creatorIDStr,
		&campaign.CreatedAt,
		&campaign.UpdatedAt,
		&campaign.RecipientCount,
		&campaign.SentCount,
		&campaign.FailedCount,
		&campaign.ReadCount,
	)
	if err != nil {
		if err == sql.ErrNoRows {
			return campaign, fmt.Errorf("notification campaign not found")
		}
		return campaign, fmt.Errorf("getNotificationCampaignByID: %w", err)
	}

	parsedCampaignID, err := uuid.Parse(campaignIDStr)
	if err != nil {
		return campaign, fmt.Errorf("getNotificationCampaignByID parse campaign id: %w", err)
	}
	campaign.ID = parsedCampaignID

	campaign.CreatedByUserID = creatorIDStr

	if scheduledAt.Valid {
		tmp := scheduledAt.String
		campaign.ScheduledAt = &tmp
	}

	return campaign, nil
}

func CreateNotificationCampaignInDB(title, message string, targetUserType, status int, scheduledAt *string, createdByUserID string) (uuid.UUID, error) {
	newID := uuid.New()
	query := `INSERT INTO notification_campaigns (id, title, message, target_user_type, status, scheduled_at, created_by_user_id, created_at, updated_at)
		      VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())`

	var scheduledValue interface{} = nil
	if scheduledAt != nil && strings.TrimSpace(*scheduledAt) != "" {
		scheduledValue = strings.TrimSpace(*scheduledAt)
	}

	_, err := Db.Exec(query, newID.String(), strings.TrimSpace(title), strings.TrimSpace(message), targetUserType, status, scheduledValue, createdByUserID)
	if err != nil {
		return uuid.Nil, fmt.Errorf("createNotificationCampaign: %w", err)
	}
	return newID, nil
}

func UpdateNotificationCampaignInDB(campaignID, title, message string, targetUserType, status int, scheduledAt *string) error {
	query := `UPDATE notification_campaigns
		      SET title = ?, message = ?, target_user_type = ?, status = ?, scheduled_at = ?, updated_at = NOW()
		      WHERE id = ?`

	var scheduledValue interface{} = nil
	if scheduledAt != nil && strings.TrimSpace(*scheduledAt) != "" {
		scheduledValue = strings.TrimSpace(*scheduledAt)
	}

	res, err := Db.Exec(query, strings.TrimSpace(title), strings.TrimSpace(message), targetUserType, status, scheduledValue, campaignID)
	if err != nil {
		return fmt.Errorf("updateNotificationCampaign: %w", err)
	}

	rowsAffected, err := res.RowsAffected()
	if err != nil {
		return fmt.Errorf("updateNotificationCampaign rows affected: %w", err)
	}
	if rowsAffected == 0 {
		return fmt.Errorf("notification campaign not found")
	}

	return nil
}

func DeleteNotificationCampaignFromDB(campaignID string) error {
	res, err := Db.Exec("DELETE FROM notification_campaigns WHERE id = ?", campaignID)
	if err != nil {
		return fmt.Errorf("deleteNotificationCampaign: %w", err)
	}
	rowsAffected, err := res.RowsAffected()
	if err != nil {
		return fmt.Errorf("deleteNotificationCampaign rows affected: %w", err)
	}
	if rowsAffected == 0 {
		return fmt.Errorf("notification campaign not found")
	}
	return nil
}

func SetNotificationCampaignStatusFromDB(campaignID string, status int) error {
	res, err := Db.Exec("UPDATE notification_campaigns SET status = ?, updated_at = NOW() WHERE id = ?", status, campaignID)
	if err != nil {
		return fmt.Errorf("setNotificationCampaignStatus: %w", err)
	}
	rowsAffected, err := res.RowsAffected()
	if err != nil {
		return fmt.Errorf("setNotificationCampaignStatus rows affected: %w", err)
	}
	if rowsAffected == 0 {
		return fmt.Errorf("notification campaign not found")
	}
	return nil
}

func SendNotificationCampaignFromDB(campaignID string) (int, int, error) {
	campaign, err := GetNotificationCampaignByIDFromDB(campaignID)
	if err != nil {
		return 0, 0, err
	}

	if campaign.Status == 2 {
		return campaign.SentCount, campaign.FailedCount, nil
	}

	tx, err := Db.Begin()
	if err != nil {
		return 0, 0, fmt.Errorf("sendNotificationCampaign begin tx: %w", err)
	}

	recipientsQuery := "SELECT id FROM users WHERE is_active = 1"
	recipientsArgs := []interface{}{}
	if campaign.TargetUserType == 0 {
		recipientsQuery += " AND user_type IN (?, ?)"
		recipientsArgs = append(recipientsArgs, 1, 2)
	} else {
		recipientsQuery += " AND user_type = ?"
		recipientsArgs = append(recipientsArgs, campaign.TargetUserType)
	}
	rows, err := tx.Query(recipientsQuery, recipientsArgs...)
	if err != nil {
		tx.Rollback()
		return 0, 0, fmt.Errorf("sendNotificationCampaign recipients query: %w", err)
	}

	userIDs := []string{}
	for rows.Next() {
		var uid string
		if scanErr := rows.Scan(&uid); scanErr != nil {
			rows.Close()
			tx.Rollback()
			return 0, 0, fmt.Errorf("sendNotificationCampaign recipients scan: %w", scanErr)
		}
		userIDs = append(userIDs, uid)
	}
	if rowsErr := rows.Err(); rowsErr != nil {
		rows.Close()
		tx.Rollback()
		return 0, 0, fmt.Errorf("sendNotificationCampaign recipients rows: %w", rowsErr)
	}
	rows.Close()

	sentCount := 0
	failedCount := 0

	for _, uid := range userIDs {
		notificationID := uuid.New().String()
		_, notifErr := tx.Exec(
			"INSERT INTO notifications (id, campaign_id, user_id, annonce_id, message, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())",
			notificationID,
			campaign.ID.String(),
			uid,
			nil,
			campaign.Message,
		)

		deliveryStatus := 1
		var notificationIDValue interface{} = notificationID
		var sentAtValue interface{} = time.Now().UTC().Format("2006-01-02 15:04:05")
		if notifErr != nil {
			deliveryStatus = 2
			notificationIDValue = nil
			sentAtValue = nil
			failedCount++
		} else {
			sentCount++
		}

		_, recErr := tx.Exec(
			`INSERT INTO notification_campaign_recipients (id, campaign_id, user_id, notification_id, delivery_status, sent_at, created_at)
			 VALUES (?, ?, ?, ?, ?, ?, NOW())
			 ON DUPLICATE KEY UPDATE notification_id = VALUES(notification_id), delivery_status = VALUES(delivery_status), sent_at = VALUES(sent_at)`,
			uuid.New().String(),
			campaign.ID.String(),
			uid,
			notificationIDValue,
			deliveryStatus,
			sentAtValue,
		)
		if recErr != nil {
			tx.Rollback()
			return 0, 0, fmt.Errorf("sendNotificationCampaign recipient insert: %w", recErr)
		}
	}

	newStatus := 2
	if sentCount == 0 && len(userIDs) > 0 {
		newStatus = 3
	}
	if _, err := tx.Exec("UPDATE notification_campaigns SET status = ?, updated_at = NOW() WHERE id = ?", newStatus, campaign.ID.String()); err != nil {
		tx.Rollback()
		return 0, 0, fmt.Errorf("sendNotificationCampaign update status: %w", err)
	}

	if err := tx.Commit(); err != nil {
		return 0, 0, fmt.Errorf("sendNotificationCampaign commit: %w", err)
	}

	return sentCount, failedCount, nil
}
