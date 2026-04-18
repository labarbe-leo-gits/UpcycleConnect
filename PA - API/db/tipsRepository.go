package db

import (
	"API/models"
	"database/sql"
	"fmt"

	"github.com/google/uuid"
)

func GetTipsFromDB() ([]models.Tip, error) {

	rows, err := Db.Query("SELECT id, title, description, poll_id, created_by, updated_by, created_at, updated_at FROM conseils")
	if err != nil {
		return nil, fmt.Errorf("failed to query tips: %v", err)
	}

	defer rows.Close()

	var tips []models.Tip

	for rows.Next() {
		var tip models.Tip
		var pollIDStr sql.NullString
		var createdByStr sql.NullString
		var updatedByStr sql.NullString
		err := rows.Scan(&tip.ID, &tip.Title, &tip.Description, &pollIDStr, &createdByStr, &updatedByStr, &tip.CreatedAt, &tip.UpdatedAt)
		if err != nil {
			return nil, fmt.Errorf("failed to scan tip: %v", err)
		}

		if pollIDStr.Valid {
			if parsed, parseErr := uuid.Parse(pollIDStr.String); parseErr == nil {
				tip.PollID = &parsed
			} else {
				return nil, fmt.Errorf("failed to parse poll_id: %v", parseErr)
			}
		} else {
			tip.PollID = nil
		}

		if createdByStr.Valid {
			if tip.CreatedBy, err = uuid.Parse(createdByStr.String); err != nil {
				return nil, fmt.Errorf("failed to parse created_by: %v", err)
			}
		} else {
			tip.CreatedBy = uuid.Nil
		}

		if updatedByStr.Valid {
			if tip.UpdatedBy, err = uuid.Parse(updatedByStr.String); err != nil {
				return nil, fmt.Errorf("failed to parse updated_by: %v", err)
			}
		} else {
			tip.UpdatedBy = uuid.Nil
		}

		tips = append(tips, tip)
	}

	if err = rows.Err(); err != nil {
		return nil, fmt.Errorf("error iterating over tip rows: %v", err)
	}

	return tips, nil
}

func CreateTipInDB(tip models.Tip) (uuid.UUID, error) {

	newID := uuid.New()
	currentTIme := getCurrentTime()

	updatedBy := tip.UpdatedBy
	if updatedBy == uuid.Nil {
		updatedBy = tip.CreatedBy
	}
	_, err := Db.Exec(
		"INSERT INTO conseils (id, title, description, poll_id, created_by, updated_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
		newID, tip.Title, tip.Description, tip.PollID, tip.CreatedBy, updatedBy, currentTIme, currentTIme,
	)

	if err != nil {
		return uuid.Nil, fmt.Errorf("failed to insert tip: %v", err)
	}

	return newID, nil
}

func GetTipByIDFromDB(tipIDStr string) (models.Tip, error) {

	var tip models.Tip

	var pollIDStr sql.NullString
	var createdByStr sql.NullString
	var updatedByStr sql.NullString
	row := Db.QueryRow("SELECT id, title, description, poll_id, created_by, updated_by, created_at, updated_at FROM conseils WHERE id = ?", tipIDStr)
	err := row.Scan(&tip.ID, &tip.Title, &tip.Description, &pollIDStr, &createdByStr, &updatedByStr, &tip.CreatedAt, &tip.UpdatedAt)

	if err != nil {
		return tip, fmt.Errorf("failed to query tip by ID: %v", err)
	}

	if err = row.Err(); err != nil {
		return tip, fmt.Errorf("error iterating over tip rows: %v", err)
	}

	if pollIDStr.Valid {
		if parsedPoll, parseErr := uuid.Parse(pollIDStr.String); parseErr == nil {
			tip.PollID = &parsedPoll
		} else {
			return tip, fmt.Errorf("invalid poll_id UUID: %v", parseErr)
		}
	} else {
		tip.PollID = nil
	}

	if createdByStr.Valid {
		if tip.CreatedBy, err = uuid.Parse(createdByStr.String); err != nil {
			return tip, fmt.Errorf("invalid created_by UUID: %v", err)
		}
	} else {
		tip.CreatedBy = uuid.Nil
	}

	if updatedByStr.Valid {
		if tip.UpdatedBy, err = uuid.Parse(updatedByStr.String); err != nil {
			return tip, fmt.Errorf("invalid updated_by UUID: %v", err)
		}
	} else {
		tip.UpdatedBy = uuid.Nil
	}

	return tip, nil
}

func UpdateTipInDB(tipIDStr uuid.UUID, tip models.Tip) error {

	tipID, err := uuid.Parse(tipIDStr.String())
	if err != nil {
		return fmt.Errorf("invalid tip ID format: %v", err)
	}

	old_tip, err := GetTipByIDFromDB(tipIDStr.String())

	if err != nil {
		return fmt.Errorf("failed to get existing tip: %v", err)
	}

	if tip.Title == "" {
		tip.Title = old_tip.Title
	}

	if tip.Description == "" {
		tip.Description = old_tip.Description
	}

	currentTIme := getCurrentTime()

	_, err = Db.Exec(
		"UPDATE conseils SET title = ?, description = ?, poll_id = ?, updated_by = ?, updated_at = ? WHERE id = ?",
		tip.Title, tip.Description, tip.PollID, tip.UpdatedBy, currentTIme, tipID,
	)

	if err != nil {
		return fmt.Errorf("failed to update tip: %v", err)
	}

	return nil
}

func DeleteTipFromDB(tipIDStr uuid.UUID) error {

	tipID, err := uuid.Parse(tipIDStr.String())

	if err != nil {
		return fmt.Errorf("invalid tip ID format: %v", err)
	}

	_, err = Db.Exec("DELETE FROM conseils WHERE id = ?", tipID)

	if err != nil {
		return fmt.Errorf("failed to delete tip: %v", err)
	}

	return nil
}

func GetTipCommentsFromDB(tipID string) ([]models.TipComment, error) {
	rows, err := Db.Query(
		`SELECT cc.id, cc.conseil_id, cc.user_id, COALESCE(u.username, '') AS username,
		        cc.parent_id, cc.content, cc.created_at, cc.updated_at
		 FROM conseils_comments cc
		 LEFT JOIN users u ON u.id = cc.user_id
		 WHERE cc.conseil_id = ?
		 ORDER BY cc.created_at ASC`,
		tipID,
	)
	if err != nil {
		return nil, fmt.Errorf("GetTipCommentsFromDB: %w", err)
	}
	defer rows.Close()

	comments := []models.TipComment{}
	for rows.Next() {
		var c models.TipComment
		var idStr, tipIDStr, userIDStr string
		var parentIDStr sql.NullString
		var createdAt, updatedAt sql.NullString
		if err := rows.Scan(&idStr, &tipIDStr, &userIDStr, &c.Username, &parentIDStr, &c.Content, &createdAt, &updatedAt); err != nil {
			return nil, err
		}
		c.ID, _ = uuid.Parse(idStr)
		c.TipID, _ = uuid.Parse(tipIDStr)
		c.UserID, _ = uuid.Parse(userIDStr)
		if parentIDStr.Valid {
			uid, _ := uuid.Parse(parentIDStr.String)
			c.ParentID = &uid
		}
		if createdAt.Valid {
			c.CreatedAt = createdAt.String
		}
		if updatedAt.Valid {
			c.UpdatedAt = updatedAt.String
		}
		comments = append(comments, c)
	}
	return comments, rows.Err()
}

func CreateTipCommentInDB(c models.TipComment) (*models.TipComment, error) {
	id := uuid.New()
	_, err := Db.Exec(
		"INSERT INTO conseils_comments (id, conseil_id, user_id, parent_id, content) VALUES (?, ?, ?, ?, ?)",
		id.String(), c.TipID.String(), c.UserID.String(), nullableUUID(c.ParentID), c.Content,
	)
	if err != nil {
		return nil, fmt.Errorf("CreateTipCommentInDB: %w", err)
	}
	c.ID = id
	_ = Db.QueryRow("SELECT COALESCE(username,'') FROM users WHERE id = ?", c.UserID.String()).Scan(&c.Username)
	_ = Db.QueryRow("SELECT created_at FROM conseils_comments WHERE id = ?", id.String()).Scan(&c.CreatedAt)
	return &c, nil
}

func UpdateTipCommentInDB(id, content string) error {
	_, err := Db.Exec("UPDATE conseils_comments SET content = ? WHERE id = ?", content, id)
	return err
}

func DeleteTipCommentFromDB(id string) error {
	_, err := Db.Exec("DELETE FROM conseils_comments WHERE id = ?", id)
	return err
}

func GetTipCommentByIDFromDB(id string) (*models.TipComment, error) {
	var c models.TipComment
	var idStr, tipIDStr, userIDStr string
	var parentIDStr sql.NullString
	var createdAt, updatedAt sql.NullString
	err := Db.QueryRow(
		`SELECT cc.id, cc.conseil_id, cc.user_id, COALESCE(u.username,'') AS username,
		        cc.parent_id, cc.content, cc.created_at, cc.updated_at
		 FROM conseils_comments cc
		 LEFT JOIN users u ON u.id = cc.user_id
		 WHERE cc.id = ?`,
		id,
	).Scan(&idStr, &tipIDStr, &userIDStr, &c.Username, &parentIDStr, &c.Content, &createdAt, &updatedAt)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}
	c.ID, _ = uuid.Parse(idStr)
	c.TipID, _ = uuid.Parse(tipIDStr)
	c.UserID, _ = uuid.Parse(userIDStr)
	if parentIDStr.Valid {
		uid, _ := uuid.Parse(parentIDStr.String)
		c.ParentID = &uid
	}
	if createdAt.Valid {
		c.CreatedAt = createdAt.String
	}
	if updatedAt.Valid {
		c.UpdatedAt = updatedAt.String
	}
	return &c, nil
}

func GetTipReactionsFromDB(tipID string) (int, int, error) {
	var likes sql.NullInt64
	var dislikes sql.NullInt64
	row := Db.QueryRow(`SELECT
		SUM(CASE WHEN reaction_type = 1 THEN 1 ELSE 0 END) AS likes,
		SUM(CASE WHEN reaction_type = 0 THEN 1 ELSE 0 END) AS dislikes
		FROM conseils_reactions WHERE conseil_id = ?`, tipID)
	if err := row.Scan(&likes, &dislikes); err != nil {
		return 0, 0, err
	}

	likesCount := int64(0)
	if likes.Valid {
		likesCount = likes.Int64
	}

	dislikesCount := int64(0)
	if dislikes.Valid {
		dislikesCount = dislikes.Int64
	}

	return int(likesCount), int(dislikesCount), nil
}

func GetTipReactionByUserFromDB(tipID, userID string) (int, error) {
	var reactionType int
	err := Db.QueryRow(`SELECT reaction_type FROM conseils_reactions WHERE conseil_id = ? AND user_id = ?`, tipID, userID).Scan(&reactionType)
	if err == sql.ErrNoRows {
		return -1, nil
	}
	if err != nil {
		return -1, err
	}
	return reactionType, nil
}

func SetTipReactionInDB(tipID, userID string, reactionType int) error {
	if reactionType != 0 && reactionType != 1 {
		return fmt.Errorf("invalid reaction type")
	}
	_, err := Db.Exec(`INSERT INTO conseils_reactions (id, conseil_id, user_id, reaction_type) VALUES (UUID(), ?, ?, ?) ON DUPLICATE KEY UPDATE reaction_type = ?, updated_at = CURRENT_TIMESTAMP`, tipID, userID, reactionType, reactionType)
	return err
}

func RemoveTipReactionFromDB(tipID, userID string) error {
	_, err := Db.Exec(`DELETE FROM conseils_reactions WHERE conseil_id = ? AND user_id = ?`, tipID, userID)
	return err
}
