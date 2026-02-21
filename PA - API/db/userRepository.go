// All database interactions for the API are handled in this package

package db

import (
	"API/models"
	"database/sql"
	"fmt"
	"strings"

	"github.com/google/uuid"
	"golang.org/x/crypto/bcrypt"
)

func GetAllUsersFromDB() ([]models.User, error) {

	users := []models.User{}
	rows, err := Db.Query("SELECT id, first_name, last_name, company_name, user_type, username, email, balance, upcycling_score, created_at, last_login, oauth_provider, oauth_id, profile_picture FROM users")

	if err != nil {
		return nil, fmt.Errorf("getUsers package db : %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {
		var user models.User
		var idStr string
		var createdAt, lastLogin sql.NullString
		var companyName sql.NullString
		var oauthProvider, oauthID, profilePicture sql.NullString
		err := rows.Scan(&idStr, &user.FirstName, &user.LastName, &companyName, &user.UserType, &user.Username, &user.Email, &user.Balance, &user.UpcyclingScore, &createdAt, &lastLogin, &oauthProvider, &oauthID, &profilePicture)
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
		if companyName.Valid {
			user.CompanyName = companyName.String
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

	oauthProvider := sql.NullString{String: user.OAuthProvider, Valid: user.OAuthProvider != ""}
	oauthID := sql.NullString{String: user.OAuthID, Valid: user.OAuthID != ""}
	companyName := sql.NullString{String: user.CompanyName, Valid: strings.TrimSpace(user.CompanyName) != ""}

	_, err := Db.Exec(
		"INSERT INTO users (id, first_name, last_name, company_name, user_type, username, email, password_hash, oauth_provider, oauth_id, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
		user.ID.String(), user.FirstName, user.LastName, companyName, user.UserType, user.Username, user.Email, user.Password, oauthProvider, oauthID, user.ProfilePicture,
	)

	if err != nil {
		return fmt.Errorf("createUser package db : %s", err.Error())
	}
	return nil

}

func validateUser(user models.User) error {

	return nil

}

func GetUserByIDFromDB(id uuid.UUID) (models.User, error) {
	var user models.User
	var idStr string
	var createdAt, lastLogin sql.NullString
	var companyName, oauthProvider, oauthID, profilePicture sql.NullString

	err := Db.QueryRow(
		"SELECT id, first_name, last_name, company_name, user_type, username, email, password_hash, balance, upcycling_score, created_at, last_login, oauth_provider, oauth_id, profile_picture FROM users WHERE id = ?",
		id.String(),
	).Scan(&idStr, &user.FirstName, &user.LastName, &companyName, &user.UserType, &user.Username, &user.Email, &user.Password, &user.Balance, &user.UpcyclingScore, &createdAt, &lastLogin, &oauthProvider, &oauthID, &profilePicture)
	if err != nil {
		if err == sql.ErrNoRows {
			return user, fmt.Errorf("user not found")
		}
		return user, fmt.Errorf("getUserByID package db : %s", err.Error())
	}

	user.ID, err = uuid.Parse(idStr)
	if err != nil {
		return user, fmt.Errorf("getUserByID package db uuid parse : %s", err.Error())
	}
	if companyName.Valid {
		user.CompanyName = companyName.String
	}

	err = validateUser(user)
	if err != nil {
		return user, fmt.Errorf("getUserByID package db validate : %s", err.Error())
	}

	return user, nil
}

func GetUserByIdentifierFromDB(identifier string) (models.User, error) {
	var user models.User
	var idStr string
	var createdAt, lastLogin sql.NullString
	var companyName, oauthProvider, oauthID, profilePicture sql.NullString

	err := Db.QueryRow(
		"SELECT id, first_name, last_name, company_name, user_type, username, email, password_hash, balance, upcycling_score, created_at, last_login, oauth_provider, oauth_id, profile_picture FROM users WHERE username = ? OR email = ?",
		identifier, identifier,
	).Scan(&idStr, &user.FirstName, &user.LastName, &companyName, &user.UserType, &user.Username, &user.Email, &user.Password, &user.Balance, &user.UpcyclingScore, &createdAt, &lastLogin, &oauthProvider, &oauthID, &profilePicture)

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
	if companyName.Valid {
		user.CompanyName = companyName.String
	}

	err = validateUser(user)
	if err != nil {
		return user, fmt.Errorf("getUserByIdentifier package db validate : %s", err.Error())
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

func UpdateUserUpcyclingScore(userID uuid.UUID) error {
	var total sql.NullFloat64
	err := Db.QueryRow("SELECT COALESCE(SUM(upcycling_score),0) FROM annonces WHERE user_id = ?", userID.String()).Scan(&total)
	if err != nil {
		return fmt.Errorf("updateUserUpcyclingScore sum: %s", err.Error())
	}
	val := 0.0
	if total.Valid {
		val = total.Float64
	}
	_, err = Db.Exec("UPDATE users SET upcycling_score = ? WHERE id = ?", val, userID.String())
	if err != nil {
		return fmt.Errorf("updateUserUpcyclingScore update: %s", err.Error())
	}
	return nil
}

func GetUserByEmailFromDB(email string) (models.User, error) {
	var user models.User
	var idStr string
	var createdAt, lastLogin sql.NullString
	var companyName, oauthProvider, oauthID, profilePicture sql.NullString

	err := Db.QueryRow(
		"SELECT id, first_name, last_name, company_name, user_type, username, email, password_hash, created_at, last_login, oauth_provider, oauth_id, profile_picture FROM users WHERE email = ?",
		email,
	).Scan(&idStr, &user.FirstName, &user.LastName, &companyName, &user.UserType, &user.Username, &user.Email, &user.Password, &createdAt, &lastLogin, &oauthProvider, &oauthID, &profilePicture)

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
	if companyName.Valid {
		user.CompanyName = companyName.String
	}
	if profilePicture.Valid {
		user.ProfilePicture = profilePicture.String
	}

	return user, nil
}

func GetDepositsByUserIDFromDB(userID uuid.UUID) ([]models.Deposit, error) {

	rows, err := Db.Query("SELECT id, user_id, conteneur_id, object_name, object_description, status, created_at, updated_at FROM demandes_depot WHERE user_id = ?", userID.String())
	if err != nil {
		return nil, fmt.Errorf("failed to query deposits: %v", err)
	}

	defer rows.Close()

	var deposits []models.Deposit

	for rows.Next() {
		var deposit models.Deposit
		err := rows.Scan(&deposit.ID, &deposit.UserID, &deposit.ConteneurID, &deposit.ObjectName, &deposit.ObjectDescription, &deposit.Status, &deposit.CreatedAt, &deposit.UpdatedAt)

		if err != nil {
			return nil, fmt.Errorf("failed to scan deposit: %v", err)
		}

		deposits = append(deposits, deposit)
	}

	if err = rows.Err(); err != nil {
		return nil, fmt.Errorf("error iterating over deposit rows: %v", err)
	}

	return deposits, nil

}

func GetDepositsPageByUserIDFromDB(userID uuid.UUID, limit int, offset int) ([]models.Deposit, error) {
	rows, err := Db.Query(
		"SELECT id, user_id, conteneur_id, object_name, object_description, status, created_at, updated_at FROM demandes_depot WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?",
		userID.String(), limit, offset,
	)
	if err != nil {
		return nil, fmt.Errorf("failed to query deposits page: %v", err)
	}
	defer rows.Close()

	var deposits []models.Deposit
	for rows.Next() {
		var deposit models.Deposit
		err := rows.Scan(&deposit.ID, &deposit.UserID, &deposit.ConteneurID, &deposit.ObjectName, &deposit.ObjectDescription, &deposit.Status, &deposit.CreatedAt, &deposit.UpdatedAt)
		if err != nil {
			return nil, fmt.Errorf("failed to scan deposit row: %v", err)
		}
		deposits = append(deposits, deposit)
	}

	if err = rows.Err(); err != nil {
		return nil, fmt.Errorf("error iterating deposit page rows: %v", err)
	}

	return deposits, nil
}

func CountDepositsByUserID(userID uuid.UUID) (int, error) {
	var count int
	err := Db.QueryRow("SELECT COUNT(*) FROM demandes_depot WHERE user_id = ?", userID.String()).Scan(&count)
	if err != nil {
		return 0, fmt.Errorf("failed to count deposits: %v", err)
	}
	return count, nil
}

func ChangeUserPasswordInDB(userID string, newPassword string) error {

	hashed, err := bcrypt.GenerateFromPassword([]byte(newPassword), bcrypt.DefaultCost)
	if err != nil {
		return fmt.Errorf("failed to hash password: %v", err)
	}

	_, err = Db.Exec("UPDATE users SET password_hash = ? WHERE id = ?", string(hashed), userID)
	if err != nil {
		return fmt.Errorf("failed to update password: %v", err)
	}

	return nil

}
