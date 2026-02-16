// User-related handlers for the API

package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"
	"strings"

	"os"
	"time"

	"github.com/golang-jwt/jwt/v4"

	"github.com/google/uuid"
	"golang.org/x/crypto/bcrypt"
)

func GetAllUsers(w http.ResponseWriter, r *http.Request) {
	users, err := db.GetAllUsersFromDB()

	if err != nil {
		fmt.Println("[ERROR] GetAllUsers:", err)
		sendError(w, "Unable to fetch users", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	jsonResponse, err := json.Marshal(users)

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

	if user.UserType != 1 && user.UserType != 2 {
		errs = append(errs, "User type must be 1 (customer) or 2 (artisan/professional).")
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
		sendError(w, "Invalid username/email or password", http.StatusUnauthorized)
		return
	}

	err = bcrypt.CompareHashAndPassword([]byte(user.Password), []byte(loginReq.Password))
	if err != nil {
		fmt.Println("[ERROR] LoginUser password mismatch")
		sendError(w, "Invalid username/email or password", http.StatusUnauthorized)
		return
	}

	err = db.UpdateLastLoginInDB(user.ID)
	if err != nil {
		fmt.Println("[ERROR] LoginUser update last_login:", err)
	}

	// Generate JWT
	jwtSecret := os.Getenv("JWT_SECRET")
	if jwtSecret == "" {
		jwtSecret = "changeme_secret" // fallback for dev
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
	// Do something
}
