package db

import (
	"database/sql"

	"API/models"

	"github.com/google/uuid"
)

func CheckIfUserInDiscussion(userID, discussionID string) (bool, error) {
	var count int
	query := `
		SELECT COUNT(*) 
		FROM discussions 
		WHERE id = ? AND (user1_id = ? OR user2_id = ?)
	`
	err := Db.QueryRow(query, discussionID, userID, userID).Scan(&count)
	if err != nil {
		return false, err
	}
	return count > 0, nil
}

func CreateGroupDiscussion(title, imageUrl, createdBy string) (uuid.UUID, error) {
	newID := uuid.New()
	query := `INSERT INTO group_discussions (id, title, image_url, created_by) VALUES (?, ?, ?, ?)`
	var img sql.NullString
	if imageUrl != "" {
		img.String = imageUrl
		img.Valid = true
	}
	_, err := Db.Exec(query, newID.String(), title, img, createdBy)
	return newID, err
}

func AddUserToGroupDiscussion(groupID, userID string) error {
	newID := uuid.New()
	query := `INSERT INTO group_discussion_members (id, group_discussion_id, user_id) VALUES (?, ?, ?)`
	_, err := Db.Exec(query, newID.String(), groupID, userID)
	return err
}

func CheckIfUserInGroup(userID, groupID string) (bool, error) {
	var count int
	query := `
		SELECT COUNT(*) 
		FROM group_discussion_members 
		WHERE group_discussion_id = ? AND user_id = ?
	`
	err := Db.QueryRow(query, groupID, userID).Scan(&count)
	if err != nil {
		return false, err
	}
	return count > 0, nil
}

func GetUserGroupDiscussions(userID string) ([]models.GroupDiscussion, error) {
	query := `
		SELECT g.id, g.title, g.image_url, g.created_by, g.created_at
		FROM group_discussions g
		JOIN group_discussion_members m ON g.id = m.group_discussion_id
		WHERE m.user_id = ?
	`
	rows, err := Db.Query(query, userID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var groups []models.GroupDiscussion
	for rows.Next() {
		var g models.GroupDiscussion
		var img sql.NullString
		if err := rows.Scan(&g.ID, &g.Title, &img, &g.CreatedBy, &g.CreatedAt); err != nil {
			return nil, err
		}
		if img.Valid {
			g.ImageUrl = img.String
		}
		groups = append(groups, g)
	}
	return groups, nil
}

func GetGroupMembers(groupID string) ([]models.GroupDiscussionMember, error) {
	query := `
		SELECT id, group_discussion_id, user_id, joined_at
		FROM group_discussion_members
		WHERE group_discussion_id = ?
	`
	rows, err := Db.Query(query, groupID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var members []models.GroupDiscussionMember
	for rows.Next() {
		var m models.GroupDiscussionMember
		if err := rows.Scan(&m.ID, &m.GroupDiscussionID, &m.UserID, &m.JoinedAt); err != nil {
			return nil, err
		}
		members = append(members, m)
	}
	return members, nil
}
