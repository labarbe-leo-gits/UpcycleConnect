package db

import (
	"database/sql"
	"log"

	"API/models"

	"github.com/google/uuid"
)

func InsertMessage(msg models.Message) (uuid.UUID, error) {
	newID := uuid.New()
	query := `
		INSERT INTO messages (id, discussion_id, group_discussion_id, sender_id, content) 
		VALUES (?, ?, ?, ?, ?)
	`

	var discID, groupID interface{}

	if msg.DiscussionID == uuid.Nil {
		discID = nil
	} else {
		discID = msg.DiscussionID.String()
	}

	if msg.GroupDiscussionID == uuid.Nil {
		groupID = nil
	} else {
		groupID = msg.GroupDiscussionID.String()
	}

	_, err := Db.Exec(query, newID.String(), discID, groupID, msg.SenderID.String(), msg.Content)
	if err != nil {
		log.Printf("Error inserting message: %v\n", err)
		return uuid.Nil, err
	}

	return newID, nil
}

func GetMessagesByDiscussionID(discussionID string) ([]models.Message, error) {
	query := `
		SELECT id, discussion_id, group_discussion_id, sender_id, content, created_at 
		FROM messages 
		WHERE discussion_id = ? 
		ORDER BY created_at ASC
	`
	rows, err := Db.Query(query, discussionID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var messages []models.Message
	for rows.Next() {
		var msg models.Message
		var dID, gID sql.NullString

		if err := rows.Scan(&msg.ID, &dID, &gID, &msg.SenderID, &msg.Content, &msg.CreatedAt); err != nil {
			return nil, err
		}

		if dID.Valid {
			msg.DiscussionID = uuid.MustParse(dID.String)
		}
		if gID.Valid {
			msg.GroupDiscussionID = uuid.MustParse(gID.String)
		}

		attachments, err := GetAttachmentsByMessageID(msg.ID.String())
		if err == nil {
			msg.Attachments = attachments
		}

		messages = append(messages, msg)
	}

	return messages, nil
}

func GetMessagesByGroupDiscussionID(groupID string) ([]models.Message, error) {
	query := `
		SELECT id, discussion_id, group_discussion_id, sender_id, content, created_at 
		FROM messages 
		WHERE group_discussion_id = ? 
		ORDER BY created_at ASC
	`
	rows, err := Db.Query(query, groupID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var messages []models.Message
	for rows.Next() {
		var msg models.Message
		var dID, gID sql.NullString

		if err := rows.Scan(&msg.ID, &dID, &gID, &msg.SenderID, &msg.Content, &msg.CreatedAt); err != nil {
			return nil, err
		}

		if dID.Valid {
			msg.DiscussionID = uuid.MustParse(dID.String)
		}
		if gID.Valid {
			msg.GroupDiscussionID = uuid.MustParse(gID.String)
		}

		attachments, err := GetAttachmentsByMessageID(msg.ID.String())
		if err == nil {
			msg.Attachments = attachments
		}

		messages = append(messages, msg)
	}

	return messages, nil
}

func GetDiscussionMembersFromDB(discussionID string) ([]string, error) {
	query := `
		SELECT user1_id, user2_id
		FROM discussions
		WHERE id = ?
	`
	var user1, user2 string
	err := Db.QueryRow(query, discussionID).Scan(&user1, &user2)
	if err != nil {
		return nil, err
	}

	return []string{user1, user2}, nil
}

func GetGlobalMessages() ([]models.Message, error) {
	query := `
		SELECT id, discussion_id, group_discussion_id, sender_id, content, created_at 
		FROM messages 
		WHERE discussion_id IS NULL AND group_discussion_id IS NULL 
		ORDER BY created_at ASC
	`
	rows, err := Db.Query(query)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var messages []models.Message
	for rows.Next() {
		var msg models.Message
		var dID, gID sql.NullString

		if err := rows.Scan(&msg.ID, &dID, &gID, &msg.SenderID, &msg.Content, &msg.CreatedAt); err != nil {
			return nil, err
		}

		attachments, err := GetAttachmentsByMessageID(msg.ID.String())
		if err == nil {
			msg.Attachments = attachments
		}

		messages = append(messages, msg)
	}
	return messages, nil
}
