package app

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"net"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"time"

	"API/db"

	"github.com/google/uuid"
)

type responseLogger struct {
	http.ResponseWriter
	status  int
	written int
}

func (rl *responseLogger) WriteHeader(code int) {
	rl.status = code
	rl.ResponseWriter.WriteHeader(code)
}

func (rl *responseLogger) Write(b []byte) (int, error) {
	if rl.status == 0 {
		rl.status = http.StatusOK
	}
	n, err := rl.ResponseWriter.Write(b)
	rl.written += n
	return n, err
}

func getLogDir() string {
	if customDir := os.Getenv("API_LOG_DIR"); customDir != "" {
		return customDir
	}

	if info, err := os.Stat("/files"); err == nil && info.IsDir() {
		return filepath.Join("/files", "logs")
	}

	cwd, err := os.Getwd()
	if err == nil {
		return filepath.Join(cwd, "..", "files", "logs")
	}

	return filepath.Join("files", "logs")
}

func getLogFilePath(filename string) string {
	filename = strings.TrimSpace(filename)
	if filename == "" {
		filename = "api"
	}
	filename = strings.TrimSuffix(filename, ".log") + ".log"
	return filepath.Join(getLogDir(), filename)
}

func getClientIP(r *http.Request) string {
	if xff := r.Header.Get("X-Forwarded-For"); xff != "" {
		parts := strings.Split(xff, ",")
		return strings.TrimSpace(parts[0])
	}
	ip, _, err := net.SplitHostPort(r.RemoteAddr)
	if err != nil {
		return r.RemoteAddr
	}
	return ip
}

func WriteLog(filename, level, ipAddr, message string) {
	logDir := getLogDir()
	if err := os.MkdirAll(logDir, 0o755); err != nil {
		fmt.Println("[ERROR] WriteLog create log directory:", err)
		return
	}

	logFile := getLogFilePath(filename)
	if strings.TrimSpace(ipAddr) == "" {
		ipAddr = "-"
	}
	message = strings.TrimSpace(message)
	logEntry := fmt.Sprintf("[%s] [%s] [%s] %s\n", time.Now().Format("2006-01-02 15:04:05"), strings.ToUpper(strings.TrimSpace(level)), ipAddr, message)

	f, err := os.OpenFile(logFile, os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0o644)
	if err != nil {
		fmt.Println("[ERROR] WriteLog open file:", err)
		return
	}
	defer f.Close()

	if _, err := f.WriteString(logEntry); err != nil {
		fmt.Println("[ERROR] WriteLog write file:", err)
	}
}

func readRequestBody(r *http.Request) []byte {
	if r.Body == nil {
		return nil
	}
	bodyBytes, err := io.ReadAll(r.Body)
	if err != nil {
		return nil
	}
	r.Body = io.NopCloser(bytes.NewReader(bodyBytes))
	return bodyBytes
}

type requestUserFields struct {
	Identifier string `json:"identifier"`
	Username   string `json:"username"`
	Email      string `json:"email"`
}

type userIdentity struct {
	Username string
	ID       string
}

func extractUsernameFromBody(body []byte) string {
	if len(body) == 0 {
		return ""
	}
	var fields requestUserFields
	if err := json.Unmarshal(body, &fields); err != nil {
		return ""
	}
	if fields.Username != "" {
		return fields.Username
	}
	if fields.Identifier != "" {
		return fields.Identifier
	}
	if fields.Email != "" {
		return fields.Email
	}
	return ""
}

func getAuthenticatedUserIdentity(r *http.Request) userIdentity {
	uidRaw := r.Context().Value("user_id")
	if uidRaw == nil {
		return userIdentity{}
	}
	uidStr, ok := uidRaw.(string)
	if !ok || uidStr == "" {
		return userIdentity{}
	}
	uid, err := uuid.Parse(uidStr)
	if err != nil {
		return userIdentity{Username: "", ID: uidStr}
	}
	user, err := db.GetUserByIDFromDB(uid)
	if err != nil {
		return userIdentity{Username: "", ID: uidStr}
	}
	return userIdentity{Username: user.Username, ID: uidStr}
}

func userActionPrefix(body []byte, r *http.Request) string {
	identity := getAuthenticatedUserIdentity(r)
	username := identity.Username
	if username == "" {
		username = extractUsernameFromBody(body)
	}
	if username == "" {
		username = "anonymous"
	}
	if identity.ID != "" {
		return fmt.Sprintf("User %s (ID: %s)", username, identity.ID)
	}
	return fmt.Sprintf("User %s", username)
}

func routeActionMessage(method, path string, status int, body []byte, r *http.Request) string {
	name := userActionPrefix(body, r)
	success := status >= 200 && status < 300

	isWrite := strings.EqualFold(method, "POST") || strings.EqualFold(method, "PATCH") || strings.EqualFold(method, "PUT") || strings.EqualFold(method, "DELETE")

	if strings.EqualFold(method, "POST") && path == "/login" {
		if success {
			return fmt.Sprintf("%s logged in successfully", name)
		}
		return fmt.Sprintf("%s failed to login", name)
	}

	if strings.EqualFold(method, "POST") && path == "/users" {
		if status == http.StatusCreated {
			return fmt.Sprintf("%s registered successfully", name)
		}
		return fmt.Sprintf("%s failed to register", name)
	}

	if strings.EqualFold(method, "POST") && path == "/annonces" {
		if success {
			return fmt.Sprintf("%s posted a new annonce", name)
		}
		return fmt.Sprintf("%s failed to post a new annonce", name)
	}

	if strings.EqualFold(method, "PATCH") && strings.HasPrefix(path, "/users/") {
		if strings.HasSuffix(path, "/password") {
			if success {
				return fmt.Sprintf("%s changed their password", name)
			}
			return fmt.Sprintf("%s failed to change password", name)
		}
		if strings.HasSuffix(path, "/profile-picture") {
			if success {
				return fmt.Sprintf("%s updated profile picture", name)
			}
			return fmt.Sprintf("%s failed to update profile picture", name)
		}
		if strings.Count(path, "/") == 2 {
			if success {
				return fmt.Sprintf("%s updated profile", name)
			}
			return fmt.Sprintf("%s failed to update profile", name)
		}
	}

	if strings.EqualFold(method, "POST") && strings.HasSuffix(path, "/badges") {
		if success {
			return fmt.Sprintf("%s awarded a badge", name)
		}
		return fmt.Sprintf("%s failed to award a badge", name)
	}

	if strings.EqualFold(method, "POST") && strings.Contains(path, "/comments") {
		if success {
			return fmt.Sprintf("%s posted a comment", name)
		}
		return fmt.Sprintf("%s failed to post a comment", name)
	}

	if strings.EqualFold(method, "POST") && strings.Contains(path, "/images") {
		if success {
			return fmt.Sprintf("%s uploaded an image", name)
		}
		return fmt.Sprintf("%s failed to upload an image", name)
	}

	if strings.EqualFold(method, "DELETE") {
		if success {
			return fmt.Sprintf("%s deleted %s", name, path)
		}
		return fmt.Sprintf("%s failed to delete %s", name, path)
	}

	if isWrite {
		if success {
			return fmt.Sprintf("%s performed %s %s", name, strings.ToUpper(method), path)
		}
		return fmt.Sprintf("%s failed %s %s", name, strings.ToUpper(method), path)
	}

	return fmt.Sprintf("%s accessed %s %s", name, strings.ToUpper(method), path)
}

func LogMiddleware(filename, level string) func(http.HandlerFunc) http.HandlerFunc {
	return func(next http.HandlerFunc) http.HandlerFunc {
		return func(w http.ResponseWriter, r *http.Request) {
			ip := getClientIP(r)
			body := readRequestBody(r)
			logger := &responseLogger{ResponseWriter: w}

			next(logger, r)

			if logger.status == 0 {
				logger.status = http.StatusOK
			}

			message := routeActionMessage(r.Method, r.URL.Path, logger.status, body, r)
			WriteLog(filename, level, ip, message)
		}
	}
}
