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

func GetUsersFromDB(offset, limit int, search string) ([]models.User, int, error) {
	if offset < 0 {
		offset = 0
	}
	if limit < 1 {
		limit = 20
	}

	baseQuery := "SELECT id, first_name, last_name, company_name, user_type, username, email, balance, upcycling_score, created_at, last_login, oauth_provider, oauth_id, profile_picture FROM users"
	countQuery := "SELECT COUNT(*) FROM users"
	args := []interface{}{}
	where := ""
	if search != "" {
		where = " WHERE username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?"
		term := "%" + search + "%"
		args = append(args, term, term, term, term)
	}

	query := baseQuery + where + " ORDER BY created_at DESC LIMIT ? OFFSET ?"
	args = append(args, limit, offset)

	rows, err := Db.Query(query, args...)
	if err != nil {
		return nil, 0, fmt.Errorf("getUsers package db : %s", err.Error())
	}
	defer rows.Close()

	users := []models.User{}
	for rows.Next() {
		var user models.User
		var idStr string
		var createdAt, lastLogin sql.NullString
		var companyName, oauthProvider, oauthID, profilePicture sql.NullString
		err := rows.Scan(&idStr, &user.FirstName, &user.LastName, &companyName, &user.UserType, &user.Username, &user.Email, &user.Balance, &user.UpcyclingScore, &createdAt, &lastLogin, &oauthProvider, &oauthID, &profilePicture)
		if err != nil {
			return nil, 0, fmt.Errorf("getUsers package db scan : %s", err.Error())
		}
		user.ID, err = uuid.Parse(idStr)
		if err != nil {
			return nil, 0, fmt.Errorf("getUsers package db uuid parse : %s", err.Error())
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
		return nil, 0, fmt.Errorf("getUsers package db rows : %s", err.Error())
	}

	total := 0
	countRow := Db.QueryRow(countQuery+where, args[:len(args)-2]...)
	countRow.Scan(&total)

	return users, total, nil
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

	var stripeCustomerID sql.NullString
	err := Db.QueryRow(
		"SELECT id, first_name, last_name, company_name, user_type, username, email, password_hash, balance, upcycling_score, created_at, last_login, oauth_provider, oauth_id, profile_picture, is_premium, stripe_customer_id FROM users WHERE id = ?",
		id.String(),
	).Scan(&idStr, &user.FirstName, &user.LastName, &companyName, &user.UserType, &user.Username, &user.Email, &user.Password, &user.Balance, &user.UpcyclingScore, &createdAt, &lastLogin, &oauthProvider, &oauthID, &profilePicture, &user.IsPremium, &stripeCustomerID)
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
	if stripeCustomerID.Valid {
		user.StripeCustomerID = stripeCustomerID.String
	}

	err = validateUser(user)
	if err != nil {
		return user, fmt.Errorf("getUserByID package db validate : %s", err.Error())
	}

	return user, nil
}

func UpdateUserInDB(id uuid.UUID, updates map[string]interface{}) error {
	if len(updates) == 0 {
		return nil
	}
	cols := []string{}
	args := []interface{}{}
	if v, ok := updates["first_name"].(string); ok {
		cols = append(cols, "first_name = ?")
		args = append(args, v)
	}
	if v, ok := updates["last_name"].(string); ok {
		cols = append(cols, "last_name = ?")
		args = append(args, v)
	}
	if v, ok := updates["email"].(string); ok {
		cols = append(cols, "email = ?")
		args = append(args, v)
	}
	if v, ok := updates["username"].(string); ok {
		cols = append(cols, "username = ?")
		args = append(args, v)
	}
	if v, ok := updates["company_name"].(string); ok {
		cols = append(cols, "company_name = ?")
		args = append(args, v)
	}
	if len(cols) == 0 {
		return nil
	}
	args = append(args, id.String())
	query := "UPDATE users SET " + strings.Join(cols, ", ") + " WHERE id = ?"
	_, err := Db.Exec(query, args...)
	if err != nil {
		return fmt.Errorf("updateUser package db : %s", err.Error())
	}
	return nil
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
	err := Db.QueryRow(`
		SELECT
			COALESCE((SELECT SUM(upcycling_score) FROM annonces WHERE user_id = ? AND status > 0), 0)
			+ COALESCE((
				SELECT SUM(a.upcycling_score)
				FROM annonces a
				JOIN orders o ON a.id = o.product_id
				WHERE o.user_id = ? AND a.status > 0 AND a.user_id != ?
			), 0)`,
		userID.String(), userID.String(), userID.String(),
	).Scan(&total)
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

func GetAnnonceBuyerIDsFromDB(annonceID string) ([]uuid.UUID, error) {
	rows, err := Db.Query("SELECT DISTINCT user_id FROM orders WHERE product_id = ?", annonceID)
	if err != nil {
		return nil, fmt.Errorf("getAnnonceBuyerIDs: %s", err.Error())
	}
	defer rows.Close()
	var ids []uuid.UUID
	for rows.Next() {
		var idStr string
		if scanErr := rows.Scan(&idStr); scanErr != nil {
			continue
		}
		if uid, parseErr := uuid.Parse(idStr); parseErr == nil {
			ids = append(ids, uid)
		}
	}
	return ids, rows.Err()
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

func GetUserByOAuthFromDB(provider, oauthID string) (models.User, error) {
	var user models.User
	var idStr string
	var createdAt, lastLogin sql.NullString
	var companyName, oauthProviderVal, oauthIDVal, profilePicture sql.NullString

	err := Db.QueryRow(
		"SELECT id, first_name, last_name, company_name, user_type, username, email, password_hash, created_at, last_login, oauth_provider, oauth_id, profile_picture FROM users WHERE oauth_provider = ? AND oauth_id = ?",
		provider, oauthID,
	).Scan(&idStr, &user.FirstName, &user.LastName, &companyName, &user.UserType, &user.Username, &user.Email, &user.Password, &createdAt, &lastLogin, &oauthProviderVal, &oauthIDVal, &profilePicture)

	if err != nil {
		if err == sql.ErrNoRows {
			return user, fmt.Errorf("user not found")
		}
		return user, fmt.Errorf("getUserByOAuth package db : %s", err.Error())
	}

	user.ID, err = uuid.Parse(idStr)
	if err != nil {
		return user, fmt.Errorf("getUserByOAuth package db uuid parse : %s", err.Error())
	}

	if createdAt.Valid {
		user.CreatedAt = createdAt.String
	}
	if lastLogin.Valid {
		user.LastLogin = lastLogin.String
	}
	if oauthProviderVal.Valid {
		user.OAuthProvider = oauthProviderVal.String
	}
	if oauthIDVal.Valid {
		user.OAuthID = oauthIDVal.String
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

	_, err := GetUserByIDFromDB(uuid.MustParse(userID))
	if err != nil {
		return fmt.Errorf("user not found: %v", err)
	}

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

func GetUserRoleByIDFromDB(userID string) (int, error) {
	var role int
	err := Db.QueryRow("SELECT user_type FROM users WHERE id = ?", userID).Scan(&role)

	if err != nil {
		if err == sql.ErrNoRows {
			return 0, fmt.Errorf("user not found")
		}

		return 0, fmt.Errorf("getUserRoleByID package db : %s", err.Error())
	}

	return role, nil
}

func Get2FAInfoFromDB(userID string) (bool, string, error) {
	var enabled bool
	var secret sql.NullString
	err := Db.QueryRow("SELECT twofa_enabled, twofa_secret FROM users WHERE id = ?", userID).Scan(&enabled, &secret)
	if err != nil {
		if err == sql.ErrNoRows {
			return false, "", fmt.Errorf("user not found")
		}
		return false, "", fmt.Errorf("get2FAInfo: %s", err.Error())
	}
	secretVal := ""
	if secret.Valid {
		secretVal = secret.String
	}
	return enabled, secretVal, nil
}

func Enable2FAInDB(userID string, secret string) error {
	_, err := Db.Exec("UPDATE users SET twofa_enabled = TRUE, twofa_secret = ? WHERE id = ?", secret, userID)
	if err != nil {
		return fmt.Errorf("enable2FA: %s", err.Error())
	}
	return nil
}

func Disable2FAInDB(userID string) error {
	_, err := Db.Exec("UPDATE users SET twofa_enabled = FALSE, twofa_secret = NULL WHERE id = ?", userID)
	if err != nil {
		return fmt.Errorf("disable2FA: %s", err.Error())
	}
	return nil
}

func GetBansByUserIDFromDB(userID string) ([]models.Ban, error) {

	query := "SELECT id, user_id, reason, banned_at, banned_by, duration_days FROM `ban` WHERE user_id = ?"

	rows, err := Db.Query(query, userID)

	if err != nil {

		errMsg := err.Error()
		if strings.Contains(errMsg, "doesn't exist") || strings.Contains(errMsg, "no such table") {
			fmt.Println("[WARN] GetBansByUserIDFromDB: ban table missing -> returning empty list")
			return []models.Ban{}, nil
		}
		return nil, fmt.Errorf("getBansByUserID package db : %s", errMsg)
	}

	defer rows.Close()

	var bans []models.Ban

	for rows.Next() {

		var rawID, rawUserID, rawReason, rawBannedBy string
		var rawBannedAt sql.NullString
		var rawDuration int

		if err := rows.Scan(&rawID, &rawUserID, &rawReason, &rawBannedAt, &rawBannedBy, &rawDuration); err != nil {
			return nil, fmt.Errorf("getBansByUserID package db scan : %s", err.Error())
		}

		ban := models.Ban{
			Reason:       rawReason,
			DurationDays: rawDuration,
		}

		if u, err := uuid.Parse(rawID); err == nil {
			ban.ID = u
		}
		if u, err := uuid.Parse(rawUserID); err == nil {
			ban.UserID = u
		}
		if u, err := uuid.Parse(rawBannedBy); err == nil {
			ban.BannedBy = u
		}
		if rawBannedAt.Valid {
			ban.BannedAt = rawBannedAt.String
		}

		bans = append(bans, ban)
	}

	if err = rows.Err(); err != nil {
		return nil, fmt.Errorf("getBansByUserID package db rows : %s", err.Error())
	}

	return bans, nil

}

func DeleteUserFromDB(id uuid.UUID) error {
	_, err := Db.Exec("DELETE FROM users WHERE id = ?", id)
	if err != nil {
		return fmt.Errorf("failed to delete user: %v", err)
	}
	return nil
}

func UpdateSubscriptionInDB(userID uuid.UUID, isPremium int, stripeCustomerID, stripeSubscriptionID string) error {
	_, err := Db.Exec(
		"UPDATE users SET is_premium=?, stripe_customer_id=?, stripe_subscription_id=? WHERE id=?",
		isPremium, stripeCustomerID, stripeSubscriptionID, userID.String(),
	)
	if err != nil {
		return fmt.Errorf("updateSubscription: %s", err.Error())
	}
	return nil
}

func RevokePremiumByStripeCustomerID(customerID string) error {
	_, err := Db.Exec("UPDATE users SET is_premium=0 WHERE stripe_customer_id=?", customerID)
	if err != nil {
		return fmt.Errorf("revokePremiumByCustomer: %s", err.Error())
	}
	return nil
}

func RevokePremiumByStripeSubscriptionID(subscID string) error {
	_, err := Db.Exec("UPDATE users SET is_premium=0 WHERE stripe_subscription_id=?", subscID)
	if err != nil {
		return fmt.Errorf("revokePremiumBySubscription: %s", err.Error())
	}
	return nil
}

func GetRefundRequestsByUserIDFromDB(userID string) ([]models.RefundRequest, error) {

	rows, err := Db.Query("SELECT id, order_id, user_id, reason, status, created_at, updated_at FROM refundsRequests WHERE user_id = ?", userID)
	if err != nil {
		return nil, fmt.Errorf("failed to query refund requests: %v", err)
	}

	defer rows.Close()

	var refundRequests []models.RefundRequest

	for rows.Next() {
		var refundRequest models.RefundRequest
		err := rows.Scan(&refundRequest.ID, &refundRequest.OrderID, &refundRequest.UserID, &refundRequest.Reason, &refundRequest.Status, &refundRequest.CreatedAt, &refundRequest.UpdatedAt)
		if err != nil {
			return nil, fmt.Errorf("failed to scan refund request: %v", err)
		}

		refundRequests = append(refundRequests, refundRequest)
	}

	if err = rows.Err(); err != nil {
		return nil, fmt.Errorf("error iterating over refund request rows: %v", err)
	}

	return refundRequests, nil

}

func GetSubscriptionByUserIDFromDB(userID string) (int, error) {
	var isPremium int
	err := Db.QueryRow("SELECT is_premium FROM users WHERE id = ?", userID).Scan(&isPremium)

	if err != nil {
		if err == sql.ErrNoRows {
			return 0, nil
		}

		fmt.Printf("[ERROR] GetSubscriptionByUserIDFromDB: %s\n", err.Error())
		return 0, nil
	}

	return isPremium, nil
}

func GetProfilePictureURLFromDB(userID string) (string, error) {
	var url sql.NullString
	err := Db.QueryRow("SELECT profile_picture FROM users WHERE id = ?", userID).Scan(&url)

	if err != nil {
		if err == sql.ErrNoRows {
			return "", nil
		}

		fmt.Printf("[ERROR] GetProfilePictureURLFromDB: %s\n", err.Error())
		return "", nil
	}

	if url.Valid {
		return url.String, nil
	}

	return "", nil
}

func GetLLMUsageByUserIDFromDB(userID string) (int, int, error) {
	var quota int
	var usage int
	err := Db.QueryRow("SELECT LLM_quota, LLM_usage_today FROM users WHERE id = ?", userID).Scan(&quota, &usage)
	if err != nil {
		if err == sql.ErrNoRows {
			return 0, 0, fmt.Errorf("user not found")
		}

		fmt.Printf("[ERROR] GetLLMUsageByUserIDFromDB: %s\n", err.Error())
		return 0, 0, fmt.Errorf("database error")
	}

	return quota, usage, nil
}

func UpdateLLMUsageInDB(userID string, newQuota *int, newUsage *int) error {

	if newQuota == nil && newUsage == nil {
		return nil
	}

	setClauses := []string{}
	args := []interface{}{}

	if newQuota != nil {
		setClauses = append(setClauses, "LLM_quota = ?")
		args = append(args, *newQuota)
	}

	if newUsage != nil {
		setClauses = append(setClauses, "LLM_usage_today = ?")
		args = append(args, *newUsage)
	}

	args = append(args, userID)

	query := "UPDATE users SET " + strings.Join(setClauses, ", ") + " WHERE id = ?"

	_, err := Db.Exec(query, args...)
	if err != nil {
		fmt.Printf("[ERROR] UpdateLLMUsageInDB: %s\n", err.Error())
		return fmt.Errorf("database error")
	}

	return nil

}
