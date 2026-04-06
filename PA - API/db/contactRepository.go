package db

import (
	"API/models"
)

func GetContactsFromDB(limit int, offset int) ([]models.Contact, int, error) {
	rows, err := Db.Query("SELECT id, name, email, message, created_at FROM contacts ORDER BY created_at DESC LIMIT ? OFFSET ?", limit, offset)
	if err != nil {
		return nil, 0, err
	}
	defer rows.Close()

	contacts := []models.Contact{}
	for rows.Next() {
		var contact models.Contact
		if err := rows.Scan(&contact.ID, &contact.Name, &contact.Email, &contact.Message, &contact.CreatedAt); err != nil {
			return nil, 0, err
		}
		contacts = append(contacts, contact)
	}

	var total int
	row := Db.QueryRow("SELECT COUNT(id) FROM contacts")
	if err := row.Scan(&total); err != nil {
		return nil, 0, err
	}

	return contacts, total, nil
}

func CreateContactInDB(contact models.Contact) error {
	_, err := Db.Exec("INSERT INTO contacts (id, name, email, message) VALUES (?, ?, ?, ?)", contact.ID.String(), contact.Name, contact.Email, contact.Message)
	return err
}
