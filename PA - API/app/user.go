// User-related handlers for the API

package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"
	"strings"

	"github.com/google/uuid"
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

func sendError(w http.ResponseWriter, message string, statusCode int) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(statusCode)
	errorResponse := map[string]string{"error": message}
	json.NewEncoder(w).Encode(errorResponse)
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

	if user.Email == "" || !strings.Contains(user.Email, "@") || !strings.Contains(user.Email, ".") {
		errs = append(errs, "Email must be a valid email address.")
	}

	numbers := "0123456789"
	uppercases := "ABCDEFGHIJKLMNOPQRSTUVWXYZ"
	lowercases := "abcdefghijklmnopqrstuvwxyz"
	special_chars := "!@#$%^&*()-_=+[]{}|;:,.<>?/~`"

	if user.Password == "" || len(user.Password) < 6 || !strings.ContainsAny(user.Password, numbers) || !strings.ContainsAny(user.Password, special_chars) || !strings.ContainsAny(user.Password, uppercases) || !strings.ContainsAny(user.Password, lowercases) {
		errs = append(errs, "Password must be at least 6 characters long and contain at least one number, one uppercase letter, one lowercase letter, and one special character.")
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
