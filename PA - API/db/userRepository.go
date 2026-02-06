// All database interactions for the API are handled in this package

package db

import (
	"API/models"
	"database/sql"
	"fmt"

	"github.com/google/uuid"
	"golang.org/x/crypto/bcrypt"
)

func GetAllUsersFromDB() ([]models.User, error) {

	users := []models.User{}
	rows, err := Db.Query("SELECT id, username, email, created_at, last_login, oauth_provider, oauth_id, profile_picture FROM users")

	if err != nil {
		return nil, fmt.Errorf("getUsers package db : %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {
		var user models.User
		var idStr string
		var createdAt, lastLogin sql.NullString
		var oauthProvider, oauthID, profilePicture sql.NullString
		err := rows.Scan(&idStr, &user.Username, &user.Email, &createdAt, &lastLogin, &oauthProvider, &oauthID, &profilePicture)
		if err != nil {
			return nil, fmt.Errorf("getUsers package db scan : %s", err.Error())
		}
		user.ID, err = uuid.Parse(idStr)
		if err != nil {
			return nil, fmt.Errorf("getUsers package db uuid parse : %s", err.Error())
		}
		if createdAt.Valid {
			user.CreatedAt = createdAt.String
		}
		if lastLogin.Valid {
			user.LastLogin = lastLogin.String
		}
		if oauthProvider.Valid {
			user.OAuthProvider = oauthProvider.String
		}
		if oauthID.Valid {
			user.OAuthID = oauthID.String
		}
		if profilePicture.Valid {
			user.ProfilePicture = profilePicture.String
		}
		users = append(users, user)
	}

	err = rows.Err()
	if err != nil {
		return nil, fmt.Errorf("getUsers package db rows : %s", err.Error())
	}

	return users, nil

}

func GetUserByUsernameFromDB(username string) (bool, error) {

	rows, err := Db.Query("SELECT id, username, email, created_at, last_login FROM users WHERE username = ?", username)

	if err != nil {
		return false, fmt.Errorf("getUserByUsername package db : %s", err.Error())
	}

	defer rows.Close()

	if rows.Next() {
		return true, nil
	}

	err = rows.Err()
	if err != nil {
		return false, fmt.Errorf("getUserByUsername package db rows : %s", err.Error())
	}

	return false, nil

}

func CreateUserInDB(user models.User) error {

	hashed, _ := bcrypt.GenerateFromPassword([]byte(user.Password), bcrypt.DefaultCost)
	user.Password = string(hashed)

	_, err := Db.Exec(
		"INSERT INTO users (id, username, email, password_hash, oauth_provider, oauth_id, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?)",
		user.ID.String(), user.Username, user.Email, user.Password, user.OAuthProvider, user.OAuthID, user.ProfilePicture,
	)

	if err != nil {
		return fmt.Errorf("createUser package db : %s", err.Error())
	}
	return nil

}

func GetUserByIdentifierFromDB(identifier string) (models.User, error) {
	var user models.User
	var idStr string
	var createdAt, lastLogin sql.NullString
	var oauthProvider, oauthID, profilePicture sql.NullString

	err := Db.QueryRow(
		"SELECT id, username, email, password_hash, created_at, last_login, oauth_provider, oauth_id, profile_picture FROM users WHERE username = ? OR email = ?",
		identifier, identifier,
	).Scan(&idStr, &user.Username, &user.Email, &user.Password, &createdAt, &lastLogin, &oauthProvider, &oauthID, &profilePicture)

	if err != nil {
		if err == sql.ErrNoRows {
			return user, fmt.Errorf("user not found")
		}
		return user, fmt.Errorf("getUserByIdentifier package db : %s", err.Error())
	}

	user.ID, err = uuid.Parse(idStr)
	if err != nil {
		return user, fmt.Errorf("getUserByIdentifier package db uuid parse : %s", err.Error())
	}

	if createdAt.Valid {
		user.CreatedAt = createdAt.String
	}
	if lastLogin.Valid {
		user.LastLogin = lastLogin.String
	}
	if oauthProvider.Valid {
		user.OAuthProvider = oauthProvider.String
	}
	if oauthID.Valid {
		user.OAuthID = oauthID.String
	}
	if profilePicture.Valid {
		user.ProfilePicture = profilePicture.String
	}

	return user, nil
}

func UpdateLastLoginInDB(userID uuid.UUID) error {
	_, err := Db.Exec("UPDATE users SET last_login = NOW() WHERE id = ?", userID.String())

	if err != nil {
		return fmt.Errorf("updateLastLogin package db : %s", err.Error())
	}
	return nil
}

func GetUserByEmailFromDB(email string) (models.User, error) {
	var user models.User
	var idStr string
	var createdAt, lastLogin sql.NullString
	var oauthProvider, oauthID, profilePicture sql.NullString

	err := Db.QueryRow(
		"SELECT id, username, email, password_hash, created_at, last_login, oauth_provider, oauth_id, profile_picture FROM users WHERE email = ?",
		email,
	).Scan(&idStr, &user.Username, &user.Email, &user.Password, &createdAt, &lastLogin, &oauthProvider, &oauthID, &profilePicture)

	if err != nil {
		if err == sql.ErrNoRows {
			return user, fmt.Errorf("user not found")
		}
		return user, fmt.Errorf("getUserByEmail package db : %s", err.Error())
	}

	user.ID, err = uuid.Parse(idStr)
	if err != nil {
		return user, fmt.Errorf("getUserByEmail package db uuid parse : %s", err.Error())
	}

	if createdAt.Valid {
		user.CreatedAt = createdAt.String
	}
	if lastLogin.Valid {
		user.LastLogin = lastLogin.String
	}
	if oauthProvider.Valid {
		user.OAuthProvider = oauthProvider.String
	}
	if oauthID.Valid {
		user.OAuthID = oauthID.String
	}
	if profilePicture.Valid {
		user.ProfilePicture = profilePicture.String
	}

	return user, nil
}
