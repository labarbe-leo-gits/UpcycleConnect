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