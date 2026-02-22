package db

import (
	"API/models"
	"database/sql"
	"fmt"

	"github.com/google/uuid"
)

func GetNotificationsFromDB()([]models.Notification, error){

	notifications := []models.Notification{}
	rows, err := Db.Query("SELECT id, user_id, message, is_read, created_at FROM notifications")

	if err != nil {
		return nil, fmt.Errorf("getNotifications package db : %s", err.Error())
	}

	defer rows.Close()
	for rows.Next() {
		var notification models.Notification
		var idStr, userIDStr sql.NullString
		var createdAt sql.NullString
		err := rows.Scan(&idStr, &userIDStr, &notification.Message, &notification.Read, &createdAt)

		if err != nil {
			return nil, fmt.Errorf("getNotifications package db scan : %s", err.Error())
		}
		if idStr.Valid {
			notification.ID, err = uuid.Parse(idStr.String)
			if err != nil {
				return nil, fmt.Errorf("getNotifications package db uuid parse id : %s", err.Error())
			}

		}

		if userIDStr.Valid {
			notification.UserID, err = uuid.Parse(userIDStr.String)
			if err != nil {
				return nil, fmt.Errorf("getNotifications package db uuid parse user_id : %s", err.Error())
			}
		}

		if createdAt.Valid {
			notification.CreatedAt = createdAt.String
		}

		notifications = append(notifications, notification)
	}

	err = rows.Err()
	if err != nil {
		return nil, fmt.Errorf("getNotifications package db rows : %s", err.Error())
	}

	return notifications, nil

}

func CreateNotificationInDB(notification models.Notification) error {

	newID := uuid.New()
	userID := notification.UserID

	_, err := Db.Exec("INSERT INTO notifications (id, user_id, annonce_id, message, created_at) VALUES (?, ?, ?, ?, NOW())", newID, userID, notification.AnnonceID, notification.Message)

	if err != nil {
		return fmt.Errorf("createNotification package db : %s", err.Error())
	}

	return nil

}

func GetNotificationsByUserIDFromDB(userID uuid.UUID) ([]models.Notification, error) {

	notifications := []models.Notification{}
	rows, err := Db.Query("SELECT id, user_id, annonce_id, message, is_read, created_at FROM notifications WHERE user_id = ?", userID.String())

	if err != nil {
		return nil, fmt.Errorf("getNotificationsByUserID package db : %s", err.Error())
	}

	defer rows.Close()
	for rows.Next() {
		var notification models.Notification
		var idStr, userIDStr, annonceIDStr sql.NullString
		var createdAt sql.NullString
		err := rows.Scan(&idStr, &userIDStr, &annonceIDStr, &notification.Message, &notification.Read, &createdAt)

		if err != nil {
			return nil, fmt.Errorf("getNotificationsByUserID package db scan : %s", err.Error())
		}

		if idStr.Valid {
			notification.ID, err = uuid.Parse(idStr.String)
			if err != nil {
				return nil, fmt.Errorf("getNotificationsByUserID package db uuid parse id : %s", err.Error())
			}
		}

		if userIDStr.Valid {
			notification.UserID, err = uuid.Parse(userIDStr.String)
			if err != nil {
				return nil, fmt.Errorf("getNotificationsByUserID package db uuid parse user_id : %s", err.Error())
			}
		}

		if annonceIDStr.Valid {
			notification.AnnonceID, err = uuid.Parse(annonceIDStr.String)
			if err != nil {
				return nil, fmt.Errorf("getNotificationsByUserID package db uuid parse annonce_id : %s", err.Error())
			}
		}

		if createdAt.Valid {
			notification.CreatedAt = createdAt.String
		}

		notifications = append(notifications, notification)
	}

	err = rows.Err()
	if err != nil {
		return nil, fmt.Errorf("getNotificationsByUserID package db rows : %s", err.Error())
	}

	return notifications, nil

}

func MarkNotificationAsReadInDB(notificationID string) error {

	_, err := Db.Exec("UPDATE notifications SET is_read = true WHERE id = ?", notificationID)

	if err != nil {
		return fmt.Errorf("markNotificationAsRead package db : %s", err.Error())
	}

	return nil

}

func MarkAllNotificationsAsReadInDB(userID string) error {

	_, err := Db.Exec("UPDATE notifications SET is_read = true WHERE user_id = ?", userID)

	if err != nil {
		return fmt.Errorf("markAllNotificationsAsRead package db : %s", err.Error())
	}

	return nil

}
