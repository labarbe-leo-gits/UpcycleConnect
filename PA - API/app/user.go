// User-related handlers for the API

package app

import (
	"API/db"
	"API/models"
	"context"
	"encoding/json"
	"fmt"
	"net/http"
	"strconv"
	"strings"

	"os"
	"time"

	"github.com/golang-jwt/jwt/v4"

	"github.com/google/uuid"
	"golang.org/x/crypto/bcrypt"
)

func GetAllUsers(w http.ResponseWriter, r *http.Request) {

	q := r.URL.Query()

	page := 1
	if p, err := strconv.Atoi(q.Get("page")); err == nil && p > 0 {
		page = p
	}
	offset, _ := strconv.Atoi(q.Get("offset"))
	limit, _ := strconv.Atoi(q.Get("limit"))
	if limit <= 0 {
		limit = 20
	}
	if offset < 0 {
		offset = (page - 1) * limit
	}
	search := strings.TrimSpace(q.Get("search"))
	if search == "undefined" || search == "null" {
		search = ""
	}

	var userTypes []int
	for _, v := range q["user_type"] {
		for _, part := range strings.Split(v, ",") {
			part = strings.TrimSpace(part)
			if ut, err := strconv.Atoi(part); err == nil {
				userTypes = append(userTypes, ut)
			}
		}
	}

	users, total, err := db.GetUsersFromDB(offset, limit, search, userTypes...)
	if err != nil {
		fmt.Println("[ERROR] GetAllUsers:", err)
		sendError(w, "Unable to fetch users", http.StatusInternalServerError)
		return
	}
	if total == 0 {
		fmt.Printf("[DEBUG] GetAllUsers no results (offset=%d limit=%d search='%s')\n", offset, limit, search)
	}

	resp := map[string]interface{}{
		"items":  users,
		"total":  total,
		"offset": offset,
		"limit":  limit,
		"page":   page,
	}

	w.Header().Set("Content-Type", "application/json")
	jsonResponse, err := json.Marshal(resp)
	if err != nil {
		fmt.Println("[ERROR] GetAllUsers marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}
	fmt.Fprintf(w, "%s", jsonResponse)
}

func GetUserByID(w http.ResponseWriter, r *http.Request) {
	idStr := strings.TrimPrefix(r.URL.Path, "/users/")
	userID, err := uuid.Parse(idStr)
	if err != nil {
		fmt.Println("[ERROR] GetUserByID parse UUID:", err)
		sendError(w, "Invalid user ID format", http.StatusBadRequest)
		return
	}

	user, err := db.GetUserByIDFromDB(userID)
	if err != nil {
		fmt.Println("[ERROR] GetUserByID DB query:", err)
		sendError(w, "User not found", http.StatusNotFound)
		return
	}

	user.Password = ""
	w.Header().Set("Content-Type", "application/json")
	jsonResponse, err := json.Marshal(user)
	if err != nil {
		fmt.Println("[ERROR] GetUserByID marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}
	fmt.Fprintf(w, "%s", jsonResponse)
}

func CheckForExistingUsername(username string) (bool, error) {

	existing, err := db.GetUserByUsernameFromDB(username)

	if err != nil {
		return true, err
	}

	if existing {
		return true, nil
	}

	return false, nil

}

func ValidateUserDto(user models.User) []string {

	var errs []string
	if user.Username == "" || len(user.Username) < 3 || len(user.Username) > 20 {
		errs = append(errs, "Username must be between 3 and 20 characters long.")
	}

	if user.FirstName == "" || len(user.FirstName) > 60 {
		errs = append(errs, "First name is required and must be at most 60 characters long.")
	}

	if user.LastName == "" || len(user.LastName) > 60 {
		errs = append(errs, "Last name is required and must be at most 60 characters long.")
	}

	if user.UserType != 1 && user.UserType != 2 && user.UserType != 3 && user.UserType != 4 {
		errs = append(errs, "User type must be 1 (customer), 2 (artisan/professional), 3 (admin) or 4 (part-time employee).")
	}

	if user.Email == "" || !strings.Contains(user.Email, "@") || !strings.Contains(user.Email, ".") {
		errs = append(errs, "Email must be a valid email address.")
	}

	if user.OAuthProvider == "" {
		numbers := "0123456789"
		uppercases := "ABCDEFGHIJKLMNOPQRSTUVWXYZ"
		lowercases := "abcdefghijklmnopqrstuvwxyz"
		special_chars := "!@#$%^&*()-_=+[]{}|;:,.<>?/~`"

		if user.Password == "" || len(user.Password) < 6 || !strings.ContainsAny(user.Password, numbers) || !strings.ContainsAny(user.Password, special_chars) || !strings.ContainsAny(user.Password, uppercases) || !strings.ContainsAny(user.Password, lowercases) {
			errs = append(errs, "Password must be at least 6 characters long and contain at least one number, one uppercase letter, one lowercase letter, and one special character.")
		}
	}

	return errs

}

func CreateUser(w http.ResponseWriter, r *http.Request) {

	var userDto models.User
	err := json.NewDecoder(r.Body).Decode(&userDto)

	if err != nil {
		fmt.Println("[ERROR] CreateUser decode:", err)
		if err.Error() == "EOF" {
			sendError(w, "Request body is empty", http.StatusBadRequest)
		} else {
			sendError(w, "Invalid JSON format", http.StatusBadRequest)
		}
		return
	}

	errs := ValidateUserDto(userDto)

	if len(errs) > 0 {
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]interface{}{"errors": errs})
		return
	}

	existing, err := CheckForExistingUsername(userDto.Username)

	if err != nil {
		fmt.Println("[ERROR] CreateUser check username:", err)
		sendError(w, "Unable to verify username availability", http.StatusInternalServerError)
		return
	}

	if existing {
		sendError(w, "Username already exists", http.StatusConflict)
		return
	}

	userDto.ID = uuid.New()

	err = db.CreateUserInDB(userDto)

	if err != nil {
		fmt.Println("[ERROR] CreateUser DB insert:", err)

		if strings.Contains(err.Error(), "Duplicate entry") && strings.Contains(err.Error(), "email") {
			sendError(w, "Email already exists", http.StatusConflict)
			return
		}

		sendError(w, "Unable to create user", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	userDto.Password = ""
	json.NewEncoder(w).Encode(userDto)

}

type LoginRequest struct {
	Identifier string `json:"identifier"`
	Password   string `json:"password"`
}

func LoginUser(w http.ResponseWriter, r *http.Request) {
	var loginReq LoginRequest
	err := json.NewDecoder(r.Body).Decode(&loginReq)

	if err != nil {
		fmt.Println("[ERROR] LoginUser decode:", err)
		if err.Error() == "EOF" {
			sendError(w, "Request body is empty", http.StatusBadRequest)
		} else {
			sendError(w, "Invalid JSON format", http.StatusBadRequest)
		}
		return
	}

	if loginReq.Identifier == "" || loginReq.Password == "" {
		sendError(w, "Username/email and password are required", http.StatusBadRequest)
		return
	}

	user, err := db.GetUserByIdentifierFromDB(loginReq.Identifier)
	if err != nil {
		fmt.Println("[ERROR] LoginUser get user:", err)
		message := "Invalid username or password"
		if strings.Contains(err.Error(), "user not found") {
			message = "Username or password incorrect"
		}
		sendError(w, message, http.StatusUnauthorized)
		return
	}

	err = bcrypt.CompareHashAndPassword([]byte(user.Password), []byte(loginReq.Password))
	if err != nil {
		fmt.Println("[ERROR] LoginUser password mismatch")
		sendError(w, "Invalid username/email or password", http.StatusUnauthorized)
		return
	}

	twoFAEnabled, _, tfaErr := db.Get2FAInfoFromDB(user.ID.String())
	if tfaErr != nil {
		fmt.Println("[WARN] LoginUser get2FAInfo:", tfaErr)
	}

	jwtSecret := os.Getenv("JWT_SECRET")
	if jwtSecret == "" {
		jwtSecret = "changeme_secret"
	}

	if twoFAEnabled {
		pendingToken := jwt.NewWithClaims(jwt.SigningMethodHS256, jwt.MapClaims{
			"user_id": user.ID.String(),
			"type":    "mfa_pending",
			"exp":     time.Now().Add(5 * time.Minute).Unix(),
		})
		pendingTokenString, err := pendingToken.SignedString([]byte(jwtSecret))
		if err != nil {
			fmt.Println("[ERROR] LoginUser pending JWT sign:", err)
			sendError(w, "Could not generate token", http.StatusInternalServerError)
			return
		}
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]interface{}{
			"twofa_required": true,
			"temp_token":     pendingTokenString,
		})
		return
	}

	err = db.UpdateLastLoginInDB(user.ID)
	if err != nil {
		fmt.Println("[ERROR] LoginUser update last_login:", err)
	}

	token := jwt.NewWithClaims(jwt.SigningMethodHS256, jwt.MapClaims{
		"user_id": user.ID.String(),
		"email":   user.Email,
		"exp":     time.Now().Add(time.Hour * 24).Unix(),
	})
	tokenString, err := token.SignedString([]byte(jwtSecret))
	if err != nil {
		fmt.Println("[ERROR] JWT signing:", err)
		sendError(w, "Could not generate token", http.StatusInternalServerError)
		return
	}

	user.Password = ""
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]interface{}{
		"user":  user,
		"token": tokenString,
	})
}

func JWTAuthMiddleware(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		authHeader := r.Header.Get("Authorization")

		if authHeader == "" || !strings.HasPrefix(authHeader, "Bearer ") {
			sendError(w, "Missing or invalid Authorization header", http.StatusUnauthorized)
			return
		}
		tokenString := strings.TrimPrefix(authHeader, "Bearer ")
		jwtSecret := os.Getenv("JWT_SECRET")
		if jwtSecret == "" {
			jwtSecret = "changeme_secret"
		}
		token, err := jwt.Parse(tokenString, func(token *jwt.Token) (interface{}, error) {
			if _, ok := token.Method.(*jwt.SigningMethodHMAC); !ok {
				return nil, fmt.Errorf("Unexpected signing method: %v", token.Header["alg"])
			}
			return []byte(jwtSecret), nil
		})
		if err != nil || !token.Valid {
			sendError(w, "Invalid or expired token", http.StatusUnauthorized)
			return
		}
		if claims, ok := token.Claims.(jwt.MapClaims); ok {
			if uid, ok2 := claims["user_id"].(string); ok2 && uid != "" {
				r = r.WithContext(context.WithValue(r.Context(), "user_id", uid))
			}
		}
		next(w, r)
	}
}

func GetUserByEmail(w http.ResponseWriter, r *http.Request) {
	var requestData struct {
		Email string `json:"email"`
	}

	err := json.NewDecoder(r.Body).Decode(&requestData)
	if err != nil {
		fmt.Println("[ERROR] GetUserByEmail decode:", err)
		sendError(w, "Invalid JSON format", http.StatusBadRequest)
		return
	}

	if requestData.Email == "" {
		sendError(w, "Email is required", http.StatusBadRequest)
		return
	}

	user, err := db.GetUserByEmailFromDB(requestData.Email)
	if err != nil {
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusNotFound)
		json.NewEncoder(w).Encode(map[string]string{"message": "User not found"})
		return
	}

	user.Password = ""
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(user)
}

func OAuthLogin(w http.ResponseWriter, r *http.Request) {
	var req struct {
		OAuthProvider string `json:"oauth_provider"`
		OAuthID       string `json:"oauth_id"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		fmt.Println("[ERROR] OAuthLogin decode:", err)
		sendError(w, "Invalid JSON format", http.StatusBadRequest)
		return
	}
	if req.OAuthProvider == "" || req.OAuthID == "" {
		sendError(w, "oauth_provider and oauth_id are required", http.StatusBadRequest)
		return
	}

	user, err := db.GetUserByOAuthFromDB(req.OAuthProvider, req.OAuthID)
	if err != nil {
		fmt.Println("[ERROR] OAuthLogin get user:", err)
		sendError(w, "User not found", http.StatusUnauthorized)
		return
	}

	jwtSecret := os.Getenv("JWT_SECRET")
	if jwtSecret == "" {
		jwtSecret = "changeme_secret"
	}

	if err := db.UpdateLastLoginInDB(user.ID); err != nil {
		fmt.Println("[WARN] OAuthLogin update last_login:", err)
	}

	token := jwt.NewWithClaims(jwt.SigningMethodHS256, jwt.MapClaims{
		"user_id": user.ID.String(),
		"email":   user.Email,
		"exp":     time.Now().Add(time.Hour * 24).Unix(),
	})
	tokenString, err := token.SignedString([]byte(jwtSecret))
	if err != nil {
		fmt.Println("[ERROR] OAuthLogin JWT signing:", err)
		sendError(w, "Could not generate token", http.StatusInternalServerError)
		return
	}

	user.Password = ""
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]interface{}{
		"user":  user,
		"token": tokenString,
	})
}

func GetNotificationsByUserID(w http.ResponseWriter, r *http.Request) {

	idStr := strings.TrimPrefix(r.URL.Path, "/users/")
	idStr = strings.TrimSuffix(idStr, "/notifications")
	userID, err := uuid.Parse(idStr)
	if err != nil {
		fmt.Println("[ERROR] GetNotificationsByUserID parse UUID:", err)
		sendError(w, "Invalid user ID format", http.StatusBadRequest)
		return
	}

	notifications, err := db.GetNotificationsByUserIDFromDB(userID)

	if err != nil {
		fmt.Println("[ERROR] GetNotificationsByUserID:", err)
		sendError(w, "Unable to fetch notifications for user", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(notifications)

}

func UpdateUser(w http.ResponseWriter, r *http.Request) {
	idStr := strings.TrimPrefix(r.URL.Path, "/users/")
	userID, err := uuid.Parse(idStr)
	if err != nil {
		fmt.Println("[ERROR] UpdateUser parse UUID:", err)
		sendError(w, "Invalid user ID", http.StatusBadRequest)
		return
	}

	var updates map[string]interface{}
	err = json.NewDecoder(r.Body).Decode(&updates)
	if err != nil {
		fmt.Println("[ERROR] UpdateUser decode:", err)
		sendError(w, "Invalid JSON", http.StatusBadRequest)
		return
	}
	if len(updates) == 0 {
		w.WriteHeader(http.StatusOK)
		json.NewEncoder(w).Encode(map[string]string{"status": "no changes"})
		return
	}

	err = db.UpdateUserInDB(userID, updates)
	if err != nil {
		fmt.Println("[ERROR] UpdateUser DB:", err)
		sendError(w, "Unable to update user", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]string{"status": "ok"})
}

func GetDepositsByUserID(w http.ResponseWriter, r *http.Request) {
	idStr := strings.TrimPrefix(r.URL.Path, "/users/")
	idStr = strings.TrimSuffix(idStr, "/deposits")
	userID, err := uuid.Parse(idStr)

	if err != nil {
		fmt.Println("[ERROR] GetDepositsByUserID parse UUID:", err)
		sendError(w, "Invalid user ID format", http.StatusBadRequest)
		return
	}

	query := r.URL.Query()
	pageParam := query.Get("page")
	limitParam := query.Get("limit")

	if pageParam == "" && limitParam == "" {
		deposits, err := db.GetDepositsByUserIDFromDB(userID)
		if err != nil {
			fmt.Println("[ERROR] GetDepositsByUserID:", err)
			sendError(w, "Unable to fetch deposits for user", http.StatusInternalServerError)
			return
		}

		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusOK)
		json.NewEncoder(w).Encode(deposits)
		return
	}

	page := 1
	limit := 20
	if pageParam != "" {
		if p, err := strconv.Atoi(pageParam); err == nil && p > 0 {
			page = p
		}
	}
	if limitParam != "" {
		if l, err := strconv.Atoi(limitParam); err == nil && l > 0 {
			limit = l
		}
	}
	if limit > 100 {
		limit = 100
	}
	offset := (page - 1) * limit

	total, err := db.CountDepositsByUserID(userID)
	if err != nil {
		fmt.Println("[ERROR] GetDepositsByUserID count:", err)
		sendError(w, "Unable to fetch deposits", http.StatusInternalServerError)
		return
	}

	items, err := db.GetDepositsPageByUserIDFromDB(userID, limit, offset)
	if err != nil {
		fmt.Println("[ERROR] GetDepositsByUserID page:", err)
		sendError(w, "Unable to fetch deposits", http.StatusInternalServerError)
		return
	}

	response := map[string]interface{}{
		"items": items,
		"total": total,
		"page":  page,
		"limit": limit,
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(response)

}

func ValidatePasswordChangeRequest(req struct {
	OldPassword string `json:"old_password"`
	NewPassword string `json:"new_password"`
}) []string {

	var validationErrors []string

	if req.OldPassword == "" {
		validationErrors = append(validationErrors, "Old password is required")
	}

	if req.NewPassword == "" {
		validationErrors = append(validationErrors, "New password is required")
	}

	if req.NewPassword != "" {
		numbers := "0123456789"
		uppercases := "ABCDEFGHIJKLMNOPQRSTUVWXYZ"
		lowercases := "abcdefghijklmnopqrstuvwxyz"
		special_chars := "!@#$%^&*()-_=+[]{}|;:,.<>?/~`"
		if len(req.NewPassword) < 8 || !strings.ContainsAny(req.NewPassword, numbers) || !strings.ContainsAny(req.NewPassword, special_chars) || !strings.ContainsAny(req.NewPassword, uppercases) || !strings.ContainsAny(req.NewPassword, lowercases) {
			validationErrors = append(validationErrors, "New password must be at least 6 characters long and contain at least one number, one uppercase letter, one lowercase letter, and one special character.")
		}
	}

	return validationErrors

}

func ChangePassword(w http.ResponseWriter, r *http.Request) {

	var passwordChangeRequest struct {
		OldPassword string `json:"old_password"`
		NewPassword string `json:"new_password"`
	}

	err := json.NewDecoder(r.Body).Decode(&passwordChangeRequest)
	if err != nil {
		fmt.Println("[ERROR] ChangePassword decode:", err)
		sendError(w, "Invalid JSON format", http.StatusBadRequest)
		return
	}

	validationsErrors := ValidatePasswordChangeRequest(passwordChangeRequest)

	if len(validationsErrors) > 0 {
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]interface{}{
			"errors": validationsErrors,
		})
		return
	}

	userID := strings.TrimPrefix(r.URL.Path, "/users/")
	userID = strings.TrimSuffix(userID, "/password")

	err = db.ChangeUserPasswordInDB(userID, passwordChangeRequest.NewPassword)

	if err != nil {
		fmt.Println("[ERROR] ChangePassword DB update:", err)
		sendError(w, "Unable to change password", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]string{"message": "Password changed successfully"})

}

func MarkAllNotificationAsRead(w http.ResponseWriter, r *http.Request) {

	userID := strings.TrimPrefix(r.URL.Path, "/users/")
	userID = strings.TrimSuffix(userID, "/notifications/read")

	err := db.MarkAllNotificationsAsReadInDB(userID)

	if err != nil {
		fmt.Println("[ERROR] MarkAllNotificationAsRead:", err)
		sendError(w, "Unable to mark notifications as read", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]bool{"success": true})

}

func GetRoleByUserID(w http.ResponseWriter, r *http.Request) {

	userID := strings.TrimPrefix(r.URL.Path, "/users/")
	userID = strings.TrimSuffix(userID, "/role")

	role, err := db.GetUserRoleByIDFromDB(userID)

	if err != nil {
		fmt.Println("[ERROR] GetRoleByUserID:", err)
		sendError(w, "Unable to fetch user role", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]int{"role": role})

}

func GetBansByUserID(w http.ResponseWriter, r *http.Request) {

	userID := strings.TrimPrefix(r.URL.Path, "/users/")
	userID = strings.TrimSuffix(userID, "/bans")

	if _, err := uuid.Parse(userID); err != nil {
		fmt.Println("[WARN] GetBansByUserID: invalid UUID", userID)
		sendError(w, "Invalid user ID format", http.StatusBadRequest)
		return
	}

	bans, err := db.GetBansByUserIDFromDB(userID)

	if err != nil {

		fmt.Println("[ERROR] GetBansByUserID:", err)
		sendError(w, "Unable to fetch user bans", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(bans)

}

func DeleteUser(w http.ResponseWriter, r *http.Request) {
	idStr := strings.TrimPrefix(r.URL.Path, "/users/")
	userID, err := uuid.Parse(idStr)
	if err != nil {
		fmt.Println("[ERROR] DeleteUser parse UUID:", err)
		sendError(w, "Invalid user ID format", http.StatusBadRequest)
		return
	}

	if err := db.DeleteUserFromDB(userID); err != nil {
		fmt.Println("[ERROR] DeleteUser DB:", err)
		sendError(w, "Unable to delete user", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]string{"message": "User deleted successfully"})
}

func GetRefundRequestsByUserID(w http.ResponseWriter, r *http.Request) {

	userID := strings.TrimPrefix(r.URL.Path, "/users/")
	userID = strings.TrimSuffix(userID, "/refund-requests")

	if _, err := uuid.Parse(userID); err != nil {
		fmt.Println("[WARN] GetRefundRequestsByUserID: invalid UUID", userID)
		sendError(w, "Invalid user ID format", http.StatusBadRequest)
		return
	}

	refundRequests, err := db.GetRefundRequestsByUserIDFromDB(userID)

	if err != nil {
		fmt.Println("[ERROR] GetRefundRequestsByUserID:", err)
		sendError(w, "Unable to fetch refund requests for user", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(refundRequests)

}

func GetSubscriptionByUserID(w http.ResponseWriter, r *http.Request) {

	userID := strings.TrimPrefix(r.URL.Path, "/users/")
	userID = strings.TrimSuffix(userID, "/subscription")

	if _, err := uuid.Parse(userID); err != nil {
		fmt.Println("[WARN] GetSubscriptionByUserID: invalid UUID", userID)
		sendError(w, "Invalid user ID format", http.StatusBadRequest)
		return
	}

	subscription, err := db.GetSubscriptionByUserIDFromDB(userID)

	if err != nil {
		fmt.Println("[ERROR] GetSubscriptionByUserID:", err)
		sendError(w, "Unable to fetch subscription for user", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(subscription)

}

func GetProfilePicture(w http.ResponseWriter, r *http.Request) {

	userID := strings.TrimPrefix(r.URL.Path, "/users/")
	userID = strings.TrimSuffix(userID, "/profile-picture")

	if _, err := uuid.Parse(userID); err != nil {
		fmt.Println("[WARN] GetProfilePicture: invalid UUID", userID)
		sendError(w, "Invalid user ID format", http.StatusBadRequest)
		return
	}

	profilePictureURL, err := db.GetProfilePictureURLFromDB(userID)
	if err != nil {
		fmt.Println("[ERROR] GetProfilePicture:", err)
		sendError(w, "Unable to fetch profile picture URL for user", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]string{"profile_picture_url": profilePictureURL})

}

func GetLLMUsage(w http.ResponseWriter, r *http.Request) {

	userID := strings.TrimPrefix(r.URL.Path, "/users/")
	userID = strings.TrimSuffix(userID, "/llm")

	if _, err := uuid.Parse(userID); err != nil {
		fmt.Println("[WARN] GetLLMUsage: invalid UUID", userID)
		sendError(w, "Invalid user ID format", http.StatusBadRequest)
		return
	}

	quota, usage, err := db.GetLLMUsageByUserIDFromDB(userID)

	if err != nil {
		fmt.Println("[ERROR] GetLLMUsage:", err)
		sendError(w, "Unable to fetch LLM usage for user", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]int{
		"usage_today": usage,
		"quota":       quota,
	})

}

func UpdateLLMUsage(w http.ResponseWriter, r *http.Request) {

	userID := strings.TrimPrefix(r.URL.Path, "/users/")
	userID = strings.TrimSuffix(userID, "/llm")

	if _, err := uuid.Parse(userID); err != nil {
		fmt.Println("[WARN] UpdateLLMUsage: invalid UUID", userID)
		sendError(w, "Invalid user ID format", http.StatusBadRequest)
		return
	}

	var req struct {
		UsageDelta int  `json:"usage_delta"`
		Quota      *int `json:"quota"`
	}

	err := json.NewDecoder(r.Body).Decode(&req)
	if err != nil {
		fmt.Println("[ERROR] UpdateLLMUsage decode:", err)
		sendError(w, "Invalid JSON format", http.StatusBadRequest)
		return
	}

	if req.Quota != nil && *req.Quota < 0 {
		sendError(w, "Quota cannot be negative", http.StatusBadRequest)
		return
	}

	// Only admins (user_type == 3) may change the quota
	if req.Quota != nil {
		callerID, _ := r.Context().Value("user_id").(string)
		role, roleErr := db.GetUserRoleByIDFromDB(callerID)
		if roleErr != nil {
			fmt.Println("[ERROR] UpdateLLMUsage get caller role:", roleErr)
			sendError(w, "Unable to verify caller permissions", http.StatusInternalServerError)
			return
		}
		if role != 3 {
			sendError(w, "Only admins can update the quota", http.StatusForbidden)
			return
		}
	}

	err = db.UpdateLLMUsageInDB(userID, req.Quota, &req.UsageDelta)
	if err != nil {
		fmt.Println("[ERROR] UpdateLLMUsage DB:", err)
		sendError(w, "Unable to update LLM usage for user", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]bool{"success": true})

}
