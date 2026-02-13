package db

import(
	"API/models"
	"database/sql"
	"fmt"

	"github.com/google/uuid"
)

func GetForumsFromDB() ([]models.Forum, error) {

	forums := []models.Forum{}
	rows, err := Db.Query("SELECT id, title, description, created_by, created_at, updated_at FROM forum")

	if err != nil {
		return nil, fmt.Errorf("getForums package db : %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {
		var forum models.Forum
		var idStr string
		var createdByStr string
		var createdAt sql.NullString
		var updatedAt sql.NullString
		err := rows.Scan(&idStr, &forum.Title, &forum.Description, &createdByStr, &createdAt, &updatedAt)
		if err != nil {
			return nil, fmt.Errorf("getForums package db scan : %s", err.Error())
		}

		forum.ID, err = uuid.Parse(idStr)
		if err != nil {
			return nil, fmt.Errorf("getForums package db uuid parse : %s", err.Error())
		}

		forum.CreatedBy, err = uuid.Parse(createdByStr)
		if err != nil {
			return nil, fmt.Errorf("getForums package db uuid parse created_by : %s", err.Error())
		}

		if createdAt.Valid {
			forum.CreatedAt = createdAt.String
		}

		if updatedAt.Valid {
			forum.UpdatedAt = updatedAt.String
		}

		forums = append(forums, forum)
	}

	err = rows.Err()
	if err != nil {
		return nil, fmt.Errorf("getForums package db rows : %s", err.Error())
	}

	return forums, nil
}