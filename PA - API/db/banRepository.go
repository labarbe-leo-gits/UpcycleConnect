package db

import (
	"fmt"

	"github.com/google/uuid"
)

func CreateBanRecord(userID uuid.UUID, reason string, bannedBy uuid.UUID, durationDays int) error {

	id := uuid.New()
	_, err := Db.Exec("INSERT INTO ban (id, user_id, reason, banned_by, duration_days) VALUES (?, ?, ?, ?, ?)",
		id.String(), userID.String(), reason, bannedBy.String(), durationDays)
	if err != nil {
		return fmt.Errorf("createBan package db: %s", err.Error())
	}
	return nil
}
