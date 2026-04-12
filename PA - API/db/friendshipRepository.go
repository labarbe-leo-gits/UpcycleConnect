package db

import (
	"API/models"
	"database/sql"
	"errors"
	"fmt"
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
	if count == 0 {
		err = Db.QueryRow("SELECT COUNT(*) FROM friendship_requests WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)", userID, friendIDStr, friendIDStr, userID).Scan(&count)
		if err != nil {
			return err
		}
	}
	if count > 0 {
		return errors.New("friendship or request already exists")
	}

	var msgPtr *string
	if message != "" {
		msgPtr = &message
	}
	_, err = Db.Exec("INSERT INTO friendship_requests (id, sender_id, receiver_id, message) VALUES (UUID(), ?, ?, ?)", userID, friendIDStr, msgPtr)
	return err
}

func FriendshipOrRequestExists(userID, targetUserID string) (bool, error) {
	var count int
	err := Db.QueryRow(
		"SELECT COUNT(*) FROM friendships WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)",
		userID, targetUserID, targetUserID, userID,
	).Scan(&count)
	if err != nil {
		return false, err
	}
	if count > 0 {
		return true, nil
	}
	err = Db.QueryRow(
		"SELECT COUNT(*) FROM friendship_requests WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)",
		userID, targetUserID, targetUserID, userID,
	).Scan(&count)
	if err != nil {
		return false, err
	}
	return count > 0, nil
}

func AcceptFriendRequest(userID, friendshipID string) error {
	var senderID string
	var receiverID string
	var msg sql.NullString
	err := Db.QueryRow("SELECT sender_id, receiver_id, message FROM friendship_requests WHERE id = ? AND receiver_id = ?", friendshipID, userID).Scan(&senderID, &receiverID, &msg)
	if err == sql.ErrNoRows {
		return errors.New("request not found or you are not the receiver")
	}
	if err != nil {
		return err
	}

	var msgPtr *string
	if msg.Valid {
		msgPtr = &msg.String
	}

	_, err = Db.Exec("INSERT INTO friendships (id, user_id, friend_id, status, message) VALUES (UUID(), ?, ?, 1, ?)", senderID, receiverID, msgPtr)
	if err != nil {
		return err
	}

	res, err := Db.Exec("DELETE FROM friendship_requests WHERE id = ?", friendshipID)
	if err != nil {
		return err
	}
	_, err = res.RowsAffected()
	return err
}

func RemoveFriendOrRequest(userID, friendshipID string) error {
	res, err := Db.Exec("DELETE FROM friendship_requests WHERE id = ? AND (sender_id = ? OR receiver_id = ?)", friendshipID, userID, userID)
	if err != nil {
		return err
	}
	affected, err := res.RowsAffected()
	if err != nil {
		return err
	}
	if affected > 0 {
		return nil
	}

	res, err = Db.Exec("DELETE FROM friendships WHERE id = ? AND (user_id = ? OR friend_id = ?)", friendshipID, userID, userID)
	if err != nil {
		return err
	}
	affected, err = res.RowsAffected()
	if err != nil {
		return err
	}
	if affected == 0 {
		return errors.New("friendship not found or unauthorized")
	}
	return nil
}

func GetUserFriends(userID string) ([]models.FriendshipWithUser, error) {
	var friendships []models.FriendshipWithUser

	rows, err := Db.Query(`
		SELECT f.id, f.user_id, f.friend_id, f.status, NULL AS message, u.username, u.first_name, u.last_name
		FROM friendships f
		JOIN users u ON f.friend_id = u.id
		WHERE f.user_id = ?
	`, userID)
	if err != nil {
		fmt.Println("[GetUserFriends] query1 error:", err)
		return nil, err
	}
	defer rows.Close()

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

	rows2, err := Db.Query(`
		SELECT f.id, f.user_id, f.friend_id, f.status, NULL AS message, u.username, u.first_name, u.last_name
		FROM friendships f
		JOIN users u ON f.user_id = u.id
		WHERE f.friend_id = ?
	`, userID)
	if err != nil {
		fmt.Println("[GetUserFriends] query2 error:", err)
		return nil, err
	}
	defer rows2.Close()

	for rows2.Next() {
		var f models.FriendshipWithUser
		var msg sql.NullString
		if err := rows2.Scan(&f.ID, &f.UserID, &f.FriendID, &f.Status, &msg, &f.Username, &f.FirstName, &f.LastName); err != nil {
			return nil, err
		}
		if msg.Valid {
			f.Message = msg.String
		}
		friendships = append(friendships, f)
	}

	rows3, err := Db.Query(`
		SELECT fr.id, fr.sender_id AS user_id, fr.receiver_id AS friend_id, 0 AS status, fr.message, u.username, u.first_name, u.last_name
		FROM friendship_requests fr
		JOIN users u ON fr.sender_id = u.id
		WHERE fr.receiver_id = ?
	`, userID)
	if err != nil {
		fmt.Println("[GetUserFriends] query3 error:", err)
		return nil, err
	}
	defer rows3.Close()

	for rows3.Next() {
		var f models.FriendshipWithUser
		var msg sql.NullString
		if err := rows3.Scan(&f.ID, &f.UserID, &f.FriendID, &f.Status, &msg, &f.Username, &f.FirstName, &f.LastName); err != nil {
			return nil, err
		}
		if msg.Valid {
			f.Message = msg.String
		}
		friendships = append(friendships, f)
	}

	rows4, err := Db.Query(`
		SELECT fr.id, fr.sender_id AS user_id, fr.receiver_id AS friend_id, 0 AS status, fr.message, u.username, u.first_name, u.last_name
		FROM friendship_requests fr
		JOIN users u ON fr.receiver_id = u.id
		WHERE fr.sender_id = ?
	`, userID)
	if err != nil {
		fmt.Println("[GetUserFriends] query4 error:", err)
		return nil, err
	}
	defer rows4.Close()

	for rows4.Next() {
		var f models.FriendshipWithUser
		var msg sql.NullString
		if err := rows4.Scan(&f.ID, &f.UserID, &f.FriendID, &f.Status, &msg, &f.Username, &f.FirstName, &f.LastName); err != nil {
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
