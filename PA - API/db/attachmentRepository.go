package db

import (
	"log"

	"API/models"

	"github.com/google/uuid"
)

func InsertAttachment(messageID string, attachment models.Attachment) error {
	attachmentID := uuid.New()
	query := `
		INSERT INTO message_attachments (id, message_id, file_name, file_size, file_type, file_path) 
		VALUES (?, ?, ?, ?, ?, ?)
	`

	_, err := Db.Exec(query,
		attachmentID.String(),
		messageID,
		attachment.FileName,
		attachment.FileSize,
		attachment.FileType,
		attachment.FilePath,
	)

	if err != nil {
		log.Printf("Error inserting attachment: %v\n", err)
		return err
	}

	return nil
}

func InsertAttachments(messageID string, attachments []models.Attachment) error {
	for _, attachment := range attachments {
		if err := InsertAttachment(messageID, attachment); err != nil {
			return err
		}
	}
	return nil
}

func GetAttachmentsByMessageID(messageID string) ([]models.Attachment, error) {
	query := `
		SELECT file_name, file_size, file_type, file_path 
		FROM message_attachments 
		WHERE message_id = ?
		ORDER BY created_at ASC
	`

	rows, err := Db.Query(query, messageID)
	if err != nil {
		log.Printf("Error querying attachments: %v\n", err)
		return nil, err
	}
	defer rows.Close()

	var attachments []models.Attachment
	for rows.Next() {
		var a models.Attachment
		if err := rows.Scan(&a.FileName, &a.FileSize, &a.FileType, &a.FilePath); err != nil {
			log.Printf("Error scanning attachment: %v\n", err)
			continue
		}
		attachments = append(attachments, a)
	}

	return attachments, nil
}
