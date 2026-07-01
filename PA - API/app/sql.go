package app

import (
	"API/db"
	"context"
	"encoding/json"
	"fmt"
	"net/http"
	"regexp"
	"strings"
	"time"

	"github.com/google/uuid"
	"golang.org/x/crypto/bcrypt"
)

type SQLQueryRequest struct {
	Query    string `json:"query"`
	UserID   string `json:"user_id,omitempty"`
	Password string `json:"password,omitempty"`
	MFACode  string `json:"mfa_code,omitempty"`
}

func ExecuteReadOnlySQL(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		sendError(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	var payload SQLQueryRequest
	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		sendError(w, "Invalid SQL payload", http.StatusBadRequest)
		return
	}

	query := strings.TrimSpace(payload.Query)
	if query == "" {
		sendError(w, "SQL query is required", http.StatusBadRequest)
		return
	}

	normalized := strings.ToLower(query)
	if strings.Contains(normalized, ";") || strings.Contains(normalized, "--") || strings.Contains(normalized, "/*") || strings.Contains(normalized, "*/") || strings.Contains(normalized, "#") {
		sendError(w, "SQL query must be a single statement without comments or batch separators", http.StatusBadRequest)
		return
	}

	if containsDangerousTerm(normalized) {
		sendError(w, "SQL query contains forbidden operations", http.StatusBadRequest)
		return
	}

	if isElevatedSQL(query) {
		if err := validateElevatedSQL(payload.UserID, payload.Password, payload.MFACode); err != nil {
			sendError(w, err.Error(), http.StatusUnauthorized)
			return
		}
	} else {
		if !regexp.MustCompile(`(?i)^\s*(select|with)\b`).MatchString(query) {
			sendError(w, "Only read-only SELECT or WITH queries are allowed", http.StatusBadRequest)
			return
		}
	}

	ctx, cancel := context.WithTimeout(r.Context(), 15*time.Second)
	defer cancel()

	rows, err := db.Db.QueryContext(ctx, query)
	if err != nil {
		sendError(w, fmt.Sprintf("Query failed: %s", err.Error()), http.StatusBadRequest)
		return
	}
	defer rows.Close()

	columns, err := rows.Columns()
	if err != nil {
		sendError(w, "Unable to read query result columns", http.StatusInternalServerError)
		return
	}

	resultRows := []map[string]any{}
	count := 0
	for rows.Next() {
		values := make([]any, len(columns))
		pointers := make([]any, len(columns))
		for i := range values {
			pointers[i] = &values[i]
		}

		if err := rows.Scan(pointers...); err != nil {
			sendError(w, "Unable to scan query result", http.StatusInternalServerError)
			return
		}

		rowMap := map[string]any{}
		for i, col := range columns {
			value := values[i]
			switch v := value.(type) {
			case nil:
				rowMap[col] = nil
			case []byte:
				rowMap[col] = string(v)
			default:
				rowMap[col] = v
			}
		}
		resultRows = append(resultRows, rowMap)
		count++
		if count >= 1000 {
			break
		}
	}

	if err := rows.Err(); err != nil {
		sendError(w, "Error reading query results", http.StatusInternalServerError)
		return
	}

	response := map[string]any{
		"columns":   columns,
		"rows":      resultRows,
		"row_count": len(resultRows),
	}
	if count >= 1000 {
		response["truncated"] = true
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(response)
}

func containsDangerousTerm(normalizedQuery string) bool {
	for _, token := range []string{"outfile", "dumpfile", "load_file"} {
		if strings.Contains(normalizedQuery, token) {
			return true
		}
	}
	return false
}

func isElevatedSQL(query string) bool {
	return regexp.MustCompile(`(?i)^\s*(alter|update|delete|insert|create|drop|truncate|replace|rename|grant|revoke|set|use|lock|unlock|describe|desc|explain|show|call|prepare|execute|declare)\b`).MatchString(query)
}

func validateElevatedSQL(userIDStr, password, mfaCode string) error {
	if password == "" {
		return fmt.Errorf("Password is required for elevated SQL commands")
	}
	if mfaCode == "" {
		return fmt.Errorf("MFA code is required for elevated SQL commands")
	}

	if strings.TrimSpace(userIDStr) == "" {
		return fmt.Errorf("Unable to verify elevated SQL credentials")
	}

	userID, err := uuid.Parse(userIDStr)
	if err != nil {
		return fmt.Errorf("Unable to verify elevated SQL credentials")
	}

	user, err := db.GetUserByIDFromDB(userID)
	if err != nil {
		return fmt.Errorf("Unable to verify elevated SQL credentials")
	}

	if err := bcrypt.CompareHashAndPassword([]byte(user.Password), []byte(password)); err != nil {
		return fmt.Errorf("Password is incorrect")
	}

	twoFAEnabled, secret, err := db.Get2FAInfoFromDB(userID.String())
	if err != nil {
		return fmt.Errorf("Unable to verify elevated SQL credentials")
	}
	if !twoFAEnabled {
		return fmt.Errorf("Elevated SQL requires an account with two-factor authentication enabled")
	}
	if !verifyTOTPCode(mfaCode, secret) {
		return fmt.Errorf("Invalid MFA code")
	}

	if user.UserType < 3 {
		return fmt.Errorf("Insufficient privileges for elevated SQL commands")
	}

	return nil
}
