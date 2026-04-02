package db

import (
	"API/models"
	"database/sql"
	"errors"
)

func SendFriendRequest(userID, friendUsername string, message string) error {

	friend, err := GetUserObjectByUsername(friendUsername)
	if err != nil {
		return errors.New("user not found")
	}
	friendIDStr := friend.ID.String()

	if userID == friendIDStr {
		return errors.New("you cannot add yourself")
	}

	var count int
	err = Db.QueryRow("SELECT COUNT(*) FROM friendships WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)", userID, friendIDStr, friendIDStr, userID).Scan(&count)
	if err != nil {
		return err
	}
	if count > 0 {
		return errors.New("friendship or request already exists")
	}

	var msgPtr *string
	if message != "" {
		msgPtr = &message
	}
	_, err = Db.Exec("INSERT INTO friendships (id, user_id, friend_id, status, message) VALUES (UUID(), ?, ?, 0, ?)", userID, friendIDStr, msgPtr)
	return err
}

func AcceptFriendRequest(userID, friendshipID string) error {
	res, err := Db.Exec("UPDATE friendships SET status = 1 WHERE id = ? AND friend_id = ? AND status = 0", friendshipID, userID)
	if err != nil {
		return err
	}
	affected, err := res.RowsAffected()
	if err != nil {
		return err
	}
	if affected == 0 {
		return errors.New("request not found or you are not the receiver")
	}
	return nil
}

func RemoveFriendOrRequest(userID, friendshipID string) error {
	res, err := Db.Exec("DELETE FROM friendships WHERE id = ? AND (user_id = ? OR friend_id = ?)", friendshipID, userID, userID)
	if err != nil {
		return err
	}
	affected, err := res.RowsAffected()
	if err != nil {
		return err
	}
	if affected == 0 {
		return errors.New("friendship not found or unauthorized")
	}
	return nil
}

func GetUserFriends(userID string) ([]models.FriendshipWithUser, error) {
	rows, err := Db.Query(`
		SELECT f.id, f.user_id, f.friend_id, f.status, f.message, u.username, u.first_name, u.last_name
		FROM friendships f
		JOIN users u ON (f.user_id = u.id AND f.user_id != ?) OR (f.friend_id = u.id AND f.friend_id != ?)
		WHERE (f.user_id = ? OR f.friend_id = ?)
	`, userID, userID, userID, userID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var friendships []models.FriendshipWithUser
	for rows.Next() {
		var f models.FriendshipWithUser
		var msg sql.NullString
		if err := rows.Scan(&f.ID, &f.UserID, &f.FriendID, &f.Status, &msg, &f.Username, &f.FirstName, &f.LastName); err != nil {
			return nil, err
		}
		if msg.Valid {
			f.Message = msg.String
		}
		friendships = append(friendships, f)
	}
	if friendships == nil {
		return []models.FriendshipWithUser{}, nil
	}
	return friendships, nil
}
