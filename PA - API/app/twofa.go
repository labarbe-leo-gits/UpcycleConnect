// Two-Factor Authentication handlers (TOTP via pquerna/otp)

package app

import (
	"API/db"
	"encoding/json"
	"fmt"
	"net/http"
	"os"
	"strings"
	"time"

	"github.com/golang-jwt/jwt/v4"
	"github.com/google/uuid"
	"github.com/pquerna/otp/totp"
)

func Get2FAInfo(w http.ResponseWriter, r *http.Request) {
	idStr := strings.TrimPrefix(r.URL.Path, "/users/")
	idStr = strings.TrimSuffix(idStr, "/2fa-info")

	if _, err := uuid.Parse(idStr); err != nil {
		sendError(w, "Invalid user ID", http.StatusBadRequest)
		return
	}

	enabled, _, err := db.Get2FAInfoFromDB(idStr)
	if err != nil {
		fmt.Println("[ERROR] Get2FAInfo:", err)
		sendError(w, "Unable to fetch 2FA info", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]bool{"enabled": enabled})
}

func Setup2FA(w http.ResponseWriter, r *http.Request) {
	idStr := strings.TrimPrefix(r.URL.Path, "/users/")
	idStr = strings.TrimSuffix(idStr, "/2fa/setup")

	userID, err := uuid.Parse(idStr)
	if err != nil {
		sendError(w, "Invalid user ID", http.StatusBadRequest)
		return
	}

	user, err := db.GetUserByIDFromDB(userID)
	if err != nil {
		sendError(w, "User not found", http.StatusNotFound)
		return
	}

	key, err := totp.Generate(totp.GenerateOpts{
		Issuer:      "UpcycleConnect",
		AccountName: user.Email,
	})
	if err != nil {
		fmt.Println("[ERROR] Setup2FA generate:", err)
		sendError(w, "Unable to generate 2FA secret", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{
		"secret":  key.Secret(),
		"otp_url": key.URL(),
	})
}

func Enable2FA(w http.ResponseWriter, r *http.Request) {
	idStr := strings.TrimPrefix(r.URL.Path, "/users/")
	idStr = strings.TrimSuffix(idStr, "/2fa/enable")

	if _, err := uuid.Parse(idStr); err != nil {
		sendError(w, "Invalid user ID", http.StatusBadRequest)
		return
	}

	var body struct {
		Secret string `json:"secret"`
		Code   string `json:"code"`
	}
	if err := json.NewDecoder(r.Body).Decode(&body); err != nil {
		sendError(w, "Invalid JSON", http.StatusBadRequest)
		return
	}
	if body.Secret == "" || body.Code == "" {
		sendError(w, "secret and code are required", http.StatusBadRequest)
		return
	}

	if !totp.Validate(body.Code, body.Secret) {
		sendError(w, "Invalid OTP code", http.StatusUnauthorized)
		return
	}

	if err := db.Enable2FAInDB(idStr, body.Secret); err != nil {
		fmt.Println("[ERROR] Enable2FA DB:", err)
		sendError(w, "Unable to enable 2FA", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]bool{"success": true})
}

func Disable2FA(w http.ResponseWriter, r *http.Request) {
	idStr := strings.TrimPrefix(r.URL.Path, "/users/")
	idStr = strings.TrimSuffix(idStr, "/2fa/disable")

	if _, err := uuid.Parse(idStr); err != nil {
		sendError(w, "Invalid user ID", http.StatusBadRequest)
		return
	}

	if err := db.Disable2FAInDB(idStr); err != nil {
		fmt.Println("[ERROR] Disable2FA DB:", err)
		sendError(w, "Unable to disable 2FA", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]bool{"success": true})
}

func Verify2FA(w http.ResponseWriter, r *http.Request) {
	var body struct {
		TempToken string `json:"temp_token"`
		Code      string `json:"code"`
	}
	if err := json.NewDecoder(r.Body).Decode(&body); err != nil {
		sendError(w, "Invalid JSON", http.StatusBadRequest)
		return
	}
	if body.TempToken == "" || body.Code == "" {
		sendError(w, "temp_token and code are required", http.StatusBadRequest)
		return
	}

	jwtSecret := os.Getenv("JWT_SECRET")
	if jwtSecret == "" {
		jwtSecret = "changeme_secret"
	}

	token, err := jwt.Parse(body.TempToken, func(token *jwt.Token) (interface{}, error) {
		if _, ok := token.Method.(*jwt.SigningMethodHMAC); !ok {
			return nil, fmt.Errorf("unexpected signing method: %v", token.Header["alg"])
		}
		return []byte(jwtSecret), nil
	})
	if err != nil || !token.Valid {
		sendError(w, "Invalid or expired temporary token", http.StatusUnauthorized)
		return
	}

	claims, ok := token.Claims.(jwt.MapClaims)
	if !ok || claims["type"] != "mfa_pending" {
		sendError(w, "Invalid token", http.StatusUnauthorized)
		return
	}

	userIDStr, ok := claims["user_id"].(string)
	if !ok || userIDStr == "" {
		sendError(w, "Invalid token payload", http.StatusUnauthorized)
		return
	}

	userID, err := uuid.Parse(userIDStr)
	if err != nil {
		sendError(w, "Invalid user ID in token", http.StatusUnauthorized)
		return
	}

	_, secret, err := db.Get2FAInfoFromDB(userIDStr)
	if err != nil || secret == "" {
		sendError(w, "2FA not configured for this user", http.StatusBadRequest)
		return
	}

	if !totp.Validate(body.Code, secret) {
		sendError(w, "Invalid OTP code", http.StatusUnauthorized)
		return
	}

	if err := db.UpdateLastLoginInDB(userID); err != nil {
		fmt.Println("[WARN] Verify2FA update last_login:", err)
	}

	user, err := db.GetUserByIDFromDB(userID)
	if err != nil {
		sendError(w, "User not found", http.StatusNotFound)
		return
	}

	fullToken := jwt.NewWithClaims(jwt.SigningMethodHS256, jwt.MapClaims{
		"user_id": user.ID.String(),
		"email":   user.Email,
		"exp":     time.Now().Add(time.Hour * 24).Unix(),
	})
	tokenString, err := fullToken.SignedString([]byte(jwtSecret))
	if err != nil {
		fmt.Println("[ERROR] Verify2FA JWT sign:", err)
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
