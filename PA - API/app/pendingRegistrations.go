package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"html"
	"net/http"
	"net/mail"
	"net/smtp"
	"os"
	"strings"
	"time"

	"github.com/google/uuid"
	"golang.org/x/crypto/bcrypt"
)

type PendingRegistrationRequest struct {
	FirstName   string `json:"first_name"`
	LastName    string `json:"last_name"`
	CompanyName string `json:"company_name,omitempty"`
	Siret       string `json:"siret,omitempty"`
	UserType    int    `json:"user_type"`
	Username    string `json:"username"`
	Email       string `json:"email"`
	Password    string `json:"password"`
	LLMQuota    int    `json:"llm_quota"`
}

type PendingRegistrationVerifyRequest struct {
	ID   string `json:"id"`
	Code string `json:"code"`
}

func normalizeDigits(value string) string {
	var result strings.Builder
	for _, r := range value {
		if r >= '0' && r <= '9' {
			result.WriteRune(r)
		}
	}
	return result.String()
}

func isValidLuhn(number string) bool {
	sum := 0
	length := len(number)
	parity := length % 2
	for i, r := range number {
		digit := int(r - '0')
		if i%2 == parity {
			digit *= 2
			if digit > 9 {
				digit -= 9
			}
		}
		sum += digit
	}
	return sum%10 == 0
}

func isValidFrenchSiretOrSiren(value string) bool {
	cleaned := normalizeDigits(value)
	length := len(cleaned)
	if length != 9 && length != 14 {
		return false
	}
	return isValidLuhn(cleaned)
}

type PendingRegistrationResendRequest struct {
	ID         string `json:"id,omitempty"`
	Identifier string `json:"identifier,omitempty"`
}

func CreatePendingRegistration(w http.ResponseWriter, r *http.Request) {
	var req PendingRegistrationRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		fmt.Println("[ERROR] CreatePendingRegistration decode:", err)
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	req.Username = strings.TrimSpace(req.Username)
	req.Email = strings.TrimSpace(req.Email)
	req.FirstName = strings.TrimSpace(req.FirstName)
	req.LastName = strings.TrimSpace(req.LastName)
	req.CompanyName = strings.TrimSpace(req.CompanyName)
	req.Siret = normalizeDigits(req.Siret)

	if req.FirstName == "" || req.LastName == "" || req.Username == "" || req.Email == "" || req.Password == "" {
		sendError(w, "All required fields must be provided", http.StatusBadRequest)
		return
	}

	if _, err := mail.ParseAddress(req.Email); err != nil {
		fmt.Println("[ERROR] CreatePendingRegistration invalid email:", err)
		sendError(w, "Please provide a valid email address", http.StatusBadRequest)
		return
	}

	if req.UserType != 1 && req.UserType != 2 {
		sendError(w, "Please select a valid account type", http.StatusBadRequest)
		return
	}

	if req.UserType == 2 {
		if req.Siret == "" {
			sendError(w, "Professional accounts require a valid SIRET or SIREN", http.StatusBadRequest)
			return
		}
		if !isValidFrenchSiretOrSiren(req.Siret) {
			sendError(w, "Please provide a valid SIRET or SIREN number", http.StatusBadRequest)
			return
		}
	}

	if req.LLMQuota <= 0 {
		if req.UserType == 2 {
			req.LLMQuota = 15
		} else {
			req.LLMQuota = 10
		}
	}

	existingUser, err := CheckForExistingUsername(req.Username)
	if err != nil {
		fmt.Println("[ERROR] CreatePendingRegistration check username:", err)
		sendError(w, "Unable to verify username availability", http.StatusInternalServerError)
		return
	}
	if existingUser {
		sendError(w, "Username already exists", http.StatusConflict)
		return
	}

	_, err = db.GetUserByEmailFromDB(req.Email)
	if err == nil {
		sendError(w, "Email already exists", http.StatusConflict)
		return
	}
	if err != nil && !strings.Contains(err.Error(), "user not found") {
		fmt.Println("[ERROR] CreatePendingRegistration email lookup:", err)
		sendError(w, "Unable to verify email availability", http.StatusInternalServerError)
		return
	}

	if pending, err := db.GetPendingRegistrationByIdentifier(req.Username); err != nil {
		fmt.Println("[ERROR] CreatePendingRegistration pending username lookup:", err)
		sendError(w, "Unable to verify pending registration", http.StatusInternalServerError)
		return
	} else if pending != nil {
		sendError(w, "A registration is already pending for this username", http.StatusConflict)
		return
	}

	if pending, err := db.GetPendingRegistrationByIdentifier(req.Email); err != nil {
		fmt.Println("[ERROR] CreatePendingRegistration pending email lookup:", err)
		sendError(w, "Unable to verify pending registration", http.StatusInternalServerError)
		return
	} else if pending != nil {
		sendError(w, "A registration is already pending for this email", http.StatusConflict)
		return
	}

	hashedPassword, err := bcrypt.GenerateFromPassword([]byte(req.Password), bcrypt.DefaultCost)
	if err != nil {
		fmt.Println("[ERROR] CreatePendingRegistration hash password:", err)
		sendError(w, "Unable to process password", http.StatusInternalServerError)
		return
	}

	token := generateVerificationToken()
	expiresAt := time.Now().Add(30 * time.Minute).Format("2006-01-02 15:04:05")

	pending := models.PendingRegistration{
		ID:           uuid.New().String(),
		FirstName:    req.FirstName,
		LastName:     req.LastName,
		CompanyName:  req.CompanyName,
		UserType:     req.UserType,
		Username:     req.Username,
		Email:        req.Email,
		PasswordHash: string(hashedPassword),
		LLMQuota:     req.LLMQuota,
		Token:        token,
		ExpiresAt:    expiresAt,
	}

	pendingID, err := db.CreatePendingRegistration(pending)
	if err != nil {
		fmt.Println("[ERROR] CreatePendingRegistration DB insert:", err)
		if strings.Contains(err.Error(), "Duplicate entry") {
			sendError(w, "A registration already exists using this username or email", http.StatusConflict)
			return
		}
		sendError(w, "Unable to create pending registration", http.StatusInternalServerError)
		return
	}

	if err := sendPendingRegistrationEmail(pending.Email, pending.FirstName, pending.Token); err != nil {
		fmt.Println("[ERROR] CreatePendingRegistration email send:", err)
		_ = db.DeletePendingRegistration(pendingID)
		sendError(w, "Unable to send verification email", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]string{"pending_id": pendingID, "message": "Verification code sent to your email."})
}

func GetPendingRegistration(w http.ResponseWriter, r *http.Request) {
	identifier := r.URL.Query().Get("identifier")
	id := r.URL.Query().Get("id")

	if identifier == "" && id == "" {
		sendError(w, "identifier or id is required", http.StatusBadRequest)
		return
	}

	var pending *models.PendingRegistration
	var err error
	if id != "" {
		pending, err = db.GetPendingRegistrationByID(id)
	} else {
		pending, err = db.GetPendingRegistrationByIdentifier(identifier)
	}
	if err != nil {
		fmt.Println("[ERROR] GetPendingRegistration DB query:", err)
		sendError(w, "Unable to fetch pending registration", http.StatusInternalServerError)
		return
	}

	if pending == nil {
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]bool{"exists": false})
		return
	}

	response := map[string]interface{}{
		"exists":     true,
		"id":         pending.ID,
		"first_name": pending.FirstName,
		"last_name":  pending.LastName,
		"username":   pending.Username,
		"email":      pending.Email,
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(response)
}

func VerifyPendingRegistration(w http.ResponseWriter, r *http.Request) {
	var req PendingRegistrationVerifyRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		fmt.Println("[ERROR] VerifyPendingRegistration decode:", err)
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	req.ID = strings.TrimSpace(req.ID)
	req.Code = strings.TrimSpace(req.Code)

	if req.ID == "" || req.Code == "" {
		sendError(w, "Pending registration ID and code are required", http.StatusBadRequest)
		return
	}

	pending, err := db.GetPendingRegistrationByID(req.ID)
	if err != nil {
		fmt.Println("[ERROR] VerifyPendingRegistration DB query:", err)
		sendError(w, "Unable to verify registration", http.StatusInternalServerError)
		return
	}
	if pending == nil {
		sendError(w, "Pending registration not found", http.StatusNotFound)
		return
	}

	if pending.Token != req.Code {
		sendError(w, "The verification code is incorrect", http.StatusBadRequest)
		return
	}

	expiresAt, err := time.Parse("2006-01-02 15:04:05", pending.ExpiresAt)
	if err != nil {
		fmt.Println("[ERROR] VerifyPendingRegistration parse expires_at:", err)
		sendError(w, "Unable to verify registration", http.StatusInternalServerError)
		return
	}

	if time.Now().After(expiresAt) {
		sendError(w, "The verification code has expired. Please resend the code.", http.StatusBadRequest)
		return
	}

	_, err = db.CreateUserFromPendingRegistration(*pending)
	if err != nil {
		fmt.Println("[ERROR] VerifyPendingRegistration create user:", err)
		if strings.Contains(err.Error(), "Duplicate entry") {
			sendError(w, "A user with this email or username already exists", http.StatusConflict)
			return
		}
		sendError(w, "Unable to create user account", http.StatusInternalServerError)
		return
	}

	if err := db.DeletePendingRegistration(req.ID); err != nil {
		fmt.Println("[ERROR] VerifyPendingRegistration cleanup pending:", err)
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]interface{}{"success": true, "message": "Your account has been verified and created successfully."})
}

func ResendPendingRegistrationCode(w http.ResponseWriter, r *http.Request) {
	var req PendingRegistrationResendRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		fmt.Println("[ERROR] ResendPendingRegistrationCode decode:", err)
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	req.ID = strings.TrimSpace(req.ID)
	req.Identifier = strings.TrimSpace(req.Identifier)
	if req.ID == "" && req.Identifier == "" {
		sendError(w, "Pending registration ID or identifier is required", http.StatusBadRequest)
		return
	}

	var pending *models.PendingRegistration
	var err error
	if req.ID != "" {
		pending, err = db.GetPendingRegistrationByID(req.ID)
	} else {
		pending, err = db.GetPendingRegistrationByIdentifier(req.Identifier)
	}
	if err != nil {
		fmt.Println("[ERROR] ResendPendingRegistrationCode DB query:", err)
		sendError(w, "Unable to locate pending registration", http.StatusInternalServerError)
		return
	}
	if pending == nil {
		sendError(w, "Pending registration not found", http.StatusNotFound)
		return
	}

	token := generateVerificationToken()
	expiresAt := time.Now().Add(30 * time.Minute).Format("2006-01-02 15:04:05")
	if err := db.UpdatePendingRegistrationToken(pending.ID, token, expiresAt); err != nil {
		fmt.Println("[ERROR] ResendPendingRegistrationCode update token:", err)
		sendError(w, "Unable to resend verification code", http.StatusInternalServerError)
		return
	}

	if err := sendPendingRegistrationEmail(pending.Email, pending.FirstName, token); err != nil {
		fmt.Println("[ERROR] ResendPendingRegistrationCode send email:", err)
		sendError(w, "Unable to resend verification code", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]interface{}{"success": true, "message": "A new verification code has been sent to your email."})
}

func generateVerificationToken() string {
	return fmt.Sprintf("%06d", time.Now().UnixNano()%1000000)
}

func sendPendingRegistrationEmail(email, name, token string) error {
	host := os.Getenv("EMAIL_HOST")
	port := os.Getenv("EMAIL_PORT")
	username := os.Getenv("EMAIL_USERNAME")
	password := os.Getenv("EMAIL_PASSWORD")
	if host == "" || username == "" || password == "" {
		return fmt.Errorf("email settings are not configured")
	}

	from := os.Getenv("EMAIL_FROM")
	if from == "" {
		from = username
	}
	fromName := os.Getenv("EMAIL_FROM_NAME")
	if fromName == "" {
		fromName = "UpcycleConnect"
	}

	if port == "" {
		port = "587"
	}

	auth := smtp.PlainAuth("", username, password, host)
	addr := fmt.Sprintf("%s:%s", host, port)
	subject := "Confirm your UpcycleConnect registration"

	htmlBody := fmt.Sprintf(`
		<!DOCTYPE html>
		<html lang="en">
		<head>
		  <meta charset="UTF-8" />
		  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
		  <title>Confirm your registration</title>
		</head>
		<body style="margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;background:#f3f6f8;color:#334155;">
		  <table width="100%%" cellpadding="0" cellspacing="0" style="background:#f3f6f8;padding:24px 0;">
		    <tr>
		      <td align="center">
		        <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,.08);">
		          <tr>
		            <td style="background:#176f3a;padding:28px 32px;text-align:center;color:#ffffff;">
		              <h1 style="margin:0;font-size:28px;letter-spacing:0.5px;">UpcycleConnect</h1>
		            </td>
		          </tr>
		          <tr>
		            <td style="padding:32px 40px;">
		              <p style="margin:0 0 16px;font-size:16px;line-height:1.7;color:#334155;">Bonjour <strong>%s</strong>,</p>
		              <p style="margin:0 0 28px;font-size:16px;line-height:1.75;color:#475569;">Merci de vous être inscrit sur UpcycleConnect. Voici votre code de confirmation pour activer votre compte :</p>
		              <div style="background:#f7f9fb;border:2px dashed #94a3b8;border-radius:16px;padding:26px 0;text-align:center;margin:0 0 28px;">
		                <span style="display:inline-block;font-size:40px;font-weight:700;letter-spacing:6px;color:#1f2937;">%s</span>
		              </div>
		              <p style="margin:0 0 24px;font-size:14px;line-height:1.7;color:#64748b;">Entrez ce code sur la page de confirmation pour activer votre compte.</p>
		              <p style="margin:0;font-size:14px;line-height:1.7;color:#64748b;">Si vous n'avez pas créé de compte, ignorez simplement ce message.</p>
		            </td>
		          </tr>
		          <tr>
		            <td style="padding:24px 40px 32px;font-size:14px;line-height:1.7;color:#64748b;background:#f8fafc;">
		              <p style="margin:0;">Cordialement,<br />L'équipe UpcycleConnect</p>
		            </td>
		          </tr>
		        </table>
		      </td>
		    </tr>
		  </table>
		</body>
		</html>`,
		html.EscapeString(name), html.EscapeString(token))

	message := strings.Join([]string{
		fmt.Sprintf("From: %s <%s>", fromName, from),
		fmt.Sprintf("To: %s", email),
		fmt.Sprintf("Subject: %s", subject),
		"MIME-Version: 1.0",
		"Content-Type: text/html; charset=\"UTF-8\"",
		"",
		htmlBody,
	}, "\r\n")

	return smtp.SendMail(addr, auth, from, []string{email}, []byte(message))
}
