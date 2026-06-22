// User-related handlers for the API

package app

import (
	"API/db"
	"API/models"
	"context"
	"encoding/json"
	"fmt"
	"html"
	"io"
	"net/http"
	"net/smtp"
	"path/filepath"
	"strconv"
	"strings"

	"os"
	"time"

	"github.com/golang-jwt/jwt/v4"

	"github.com/google/uuid"
	"github.com/pquerna/otp/totp"
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
	sort := q.Get("sort")
	if sort != "oldest" {
		sort = "newest"
	}

	bannedOnly := false
	if q.Get("banned") == "1" {
		bannedOnly = true
	}

	users, total, err := db.GetUsersFromDB(offset, limit, search, sort, userTypes...)
	if err != nil {
		fmt.Println("[ERROR] GetAllUsers:", err)
		sendError(w, "Unable to fetch users", http.StatusInternalServerError)
		return
	}

	if bannedOnly {
		var filtered []models.User
		for _, u := range users {
			bans, err := db.GetUserBansFromDB(u.ID)
			fmt.Printf("User: %s, Bans found: %d, Error: %v\n", u.ID.String(), len(bans), err)
			if err == nil && len(bans) > 0 {
				filtered = append(filtered, u)
			}
		}
		total = len(filtered)
		users = filtered
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

func GetPublicUser(w http.ResponseWriter, r *http.Request) {
	username := strings.TrimPrefix(r.URL.Path, "/profile/")

	if username == "" {
		sendError(w, "Username is required", http.StatusBadRequest)
		return
	}

	user, err := db.GetUserByIdentifierFromDB(username)
	if err != nil {
		fmt.Println("[ERROR] GetPublicUser DB query:", err)
		sendError(w, "User not found", http.StatusNotFound)
		return
	}

	user.Password = ""
	user.Email = ""
	user.Balance = 0

	w.Header().Set("Content-Type", "application/json")
	jsonResponse, err := json.Marshal(user)
	if err != nil {
		fmt.Println("[ERROR] GetPublicUser marshal:", err)
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

func getUserIDFromURLPath(path string) (uuid.UUID, error) {
	trimmed := strings.TrimPrefix(path, "/users/")
	segments := strings.SplitN(trimmed, "/", 2)
	if len(segments) == 0 || segments[0] == "" {
		return uuid.Nil, fmt.Errorf("invalid user ID path")
	}
	return uuid.Parse(segments[0])
}

func GetPersonalDataExport(w http.ResponseWriter, r *http.Request) {
	userID, err := getUserIDFromURLPath(r.URL.Path)
	if err != nil {
		fmt.Println("[ERROR] GetPersonalDataExport parse UUID:", err)
		sendError(w, "Invalid user ID format", http.StatusBadRequest)
		return
	}

	tokenUID, ok := r.Context().Value("user_id").(string)
	if !ok || tokenUID == "" {
		sendError(w, "Missing or invalid user token", http.StatusUnauthorized)
		return
	}
	if tokenUID != userID.String() {
		sendError(w, "Forbidden: access denied", http.StatusForbidden)
		return
	}

	user, err := db.GetUserByIDFromDB(userID)
	if err != nil {
		fmt.Println("[ERROR] GetPersonalDataExport DB query:", err)
		sendError(w, "User not found", http.StatusNotFound)
		return
	}
	user.Password = ""

	bankingDetails, bankingErr := db.GetBankingDetailsByUserIDFromDB(userID)
	annonces, annoncesErr := db.GetAnnoncesByUserIDFromDB(userID.String())
	orders, ordersErr := db.GetOrdersByUserIDFromDB(userID)
	deposits, depositsErr := db.GetDepositsByUserIDFromDB(userID)
	projects, projectsErr := db.GetProjectsByUserIDFromDB(userID.String())
	projectsWithCounts := make([]map[string]interface{}, 0, len(projects))
	for _, project := range projects {
		annonceID := interface{}(nil)
		if project.AnnonceID != nil {
			annonceID = project.AnnonceID.String()
		}

		likes, likeErr := db.GetProjectLikeCountFromDB(project.ID.String())
		commentCount := 0
		if comments, commentErr := db.GetProjectCommentsFromDB(project.ID.String()); commentErr == nil {
			commentCount = len(comments)
		} else {
			fmt.Println("[ERROR] GetProjectCommentsFromDB:", commentErr)
		}
		if likeErr != nil {
			fmt.Println("[ERROR] GetProjectLikeCountFromDB:", likeErr)
		}

		projectsWithCounts = append(projectsWithCounts, map[string]interface{}{
			"id":           project.ID.String(),
			"user_id":      project.UserID.String(),
			"author_name":  project.AuthorName,
			"annonce_id":   annonceID,
			"title":        project.Title,
			"description":  project.Description,
			"status":       project.Status,
			"ai_generated": project.AIGenerated,
			"created_at":   project.CreatedAt,
			"updated_at":   project.UpdatedAt,
			"likes":        likes,
			"comments":     commentCount,
		})
	}
	favorites, favoritesErr := db.GetFavoritesByUserID(userID)
	notifications, notificationsErr := db.GetNotificationsByUserIDFromDB(userID)
	contracts, contractsErr := db.GetContractsByUserID(userID)
	invoices, invoicesErr := db.GetInvoicesByUserID(userID)
	refundRequests, refundsErr := db.GetRefundRequestsByUserIDFromDB(userID.String())
	payouts, payoutsErr := db.GetPayoutsByUserIDFromDB(userID)
	bans, bansErr := db.GetBansByUserIDFromDB(userID.String())
	subscription, subscriptionErr := db.GetSubscriptionByUserIDFromDB(userID.String())
	llmUsage, llmQuota, llmErr := db.GetLLMUsageByUserIDFromDB(userID.String())

	result := map[string]interface{}{
		"user":            user,
		"banking_details": bankingDetails,
		"annonces":        annonces,
		"orders":          orders,
		"deposits":        deposits,
		"projects":        projectsWithCounts,
		"favorites":       favorites,
		"notifications":   notifications,
		"contracts":       contracts,
		"invoices":        invoices,
		"refund_requests": refundRequests,
		"payouts":         payouts,
		"bans":            bans,
		"subscription":    subscription,
		"llm_usage":       llmUsage,
		"llm_quota":       llmQuota,
	}

	errors := map[string]string{}
	if bankingErr != nil {
		errors["banking_details"] = bankingErr.Error()
	}
	if annoncesErr != nil {
		errors["annonces"] = annoncesErr.Error()
	}
	if ordersErr != nil {
		errors["orders"] = ordersErr.Error()
	}
	if depositsErr != nil {
		errors["deposits"] = depositsErr.Error()
	}
	if projectsErr != nil {
		errors["projects"] = projectsErr.Error()
	}
	if favoritesErr != nil {
		errors["favorites"] = favoritesErr.Error()
	}
	if notificationsErr != nil {
		errors["notifications"] = notificationsErr.Error()
	}
	if contractsErr != nil {
		errors["contracts"] = contractsErr.Error()
	}
	if invoicesErr != nil {
		errors["invoices"] = invoicesErr.Error()
	}
	if refundsErr != nil {
		errors["refund_requests"] = refundsErr.Error()
	}
	if payoutsErr != nil {
		errors["payouts"] = payoutsErr.Error()
	}
	if bansErr != nil {
		errors["bans"] = bansErr.Error()
	}
	if subscriptionErr != nil {
		errors["subscription"] = subscriptionErr.Error()
	}
	if llmErr != nil {
		errors["llm_usage"] = llmErr.Error()
	}
	if len(errors) > 0 {
		result["errors"] = errors
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(result)
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

func defaultLLMQuota(userType int, isPremium bool) int {
	switch userType {
	case 1:
		return 10
	case 2:
		if isPremium {
			return 25
		}
		return 15
	case 3:
		return 50
	case 4:
		return 0
	default:
		return 10
	}
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

	if user.LLMQuota < 0 {
		errs = append(errs, "LLM quota must be a non-negative integer.")
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

	if userDto.LLMQuota <= 0 {
		userDto.LLMQuota = defaultLLMQuota(userDto.UserType, userDto.IsPremium == 1)
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

func AddBadgeToUser(w http.ResponseWriter, r *http.Request) {

	idStr := strings.TrimPrefix(r.URL.Path, "/users/")
	idStr = strings.TrimSuffix(idStr, "/badges")
	userID, err := uuid.Parse(idStr)
	if err != nil {
		sendError(w, "Invalid user ID", http.StatusBadRequest)
		return
	}

	var payload struct {
		BadgeName string `json:"badge_name"`
	}
	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		sendError(w, "Invalid request body", http.StatusBadRequest)
		return
	}
	if payload.BadgeName == "" {
		sendError(w, "badge_name is required", http.StatusBadRequest)
		return
	}

	err = db.CreateUserBadgeInDB(userID, payload.BadgeName)
	if err != nil {
		fmt.Println("[ERROR] AddBadgeToUser db:", err)
		sendError(w, "Unable to award badge", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]bool{"success": true})
}

func JWTAuthMiddleware(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		expected := os.Getenv("APP_API_KEY")
		if expected == "" {
			next(w, r)
			return
		}
		if key := r.Header.Get("X-Internal-Key"); key != "" {
			if key == expected {
				next(w, r)
				return
			}
		}

		authHeader := r.Header.Get("Authorization")

		var tokenString string
		if authHeader != "" && strings.HasPrefix(authHeader, "Bearer ") {
			tokenString = strings.TrimPrefix(authHeader, "Bearer ")
		} else if q := r.URL.Query().Get("token"); q != "" {
			tokenString = q
		} else if c, err := r.Cookie("token"); err == nil {
			tokenString = c.Value
		}

		if tokenString == "" {

			fmt.Println("[JWTAuthMiddleware] no token found; Authorization=", authHeader, "cookies=", r.Cookies())
			sendError(w, "Missing or invalid Authorization token", http.StatusUnauthorized)
			return
		}
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

			if uidRaw, found := claims["user_id"]; found {
				if uidStr, ok2 := uidRaw.(string); ok2 && uidStr != "" {
					r = r.WithContext(context.WithValue(r.Context(), "user_id", uidStr))
				} else if uidNum, ok2 := uidRaw.(float64); ok2 {
					r = r.WithContext(context.WithValue(r.Context(), "user_id", fmt.Sprint(uidNum)))
				}
			} else if subRaw, found := claims["sub"]; found {
				if subStr, ok2 := subRaw.(string); ok2 && subStr != "" {
					r = r.WithContext(context.WithValue(r.Context(), "user_id", subStr))
				}
			}
			if r.Context().Value("user_id") == nil {
				fmt.Println("[JWTAuthMiddleware] token contains no user_id/sub claim", claims)
			}
		}
		next(w, r)
	}
}

type ForgotPasswordRequest struct {
	Action          string `json:"action"`
	Email           string `json:"email"`
	Code            string `json:"code,omitempty"`
	NewPassword     string `json:"new_password,omitempty"`
	ConfirmPassword string `json:"confirm_password,omitempty"`
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

func ForgotPassword(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		sendError(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	var req ForgotPasswordRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		fmt.Println("[ERROR] ForgotPassword decode:", err)
		sendError(w, "Invalid JSON format", http.StatusBadRequest)
		return
	}

	req.Action = strings.TrimSpace(req.Action)
	req.Email = strings.TrimSpace(req.Email)

	switch req.Action {
	case "send_code":
		handleSendPasswordCode(w, &req)
	case "verify_code":
		handleVerifyPasswordCode(w, &req)
	case "reset_password":
		handleResetPassword(w, &req)
	default:
		sendError(w, "Invalid action specified", http.StatusBadRequest)
	}
}

func handleSendPasswordCode(w http.ResponseWriter, req *ForgotPasswordRequest) {
	if req.Email == "" || !strings.Contains(req.Email, "@") {
		sendError(w, "Please provide a valid email address.", http.StatusBadRequest)
		return
	}

	user, err := db.GetUserByEmailFromDB(req.Email)
	if err != nil {
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]interface{}{"success": true, "message": "If that email is registered, a reset code has been sent."})
		return
	}

	code := fmt.Sprintf("%06d", time.Now().UnixNano()%1000000)
	expiresAt := time.Now().Add(15 * time.Minute)

	if err := db.CreateOrUpdatePasswordReset(user.Email, user.ID, code, expiresAt); err != nil {
		fmt.Println("[ERROR] CreateOrUpdatePasswordReset:", err)
		sendError(w, "Unable to process the password reset request.", http.StatusInternalServerError)
		return
	}

	if err := sendPasswordResetEmail(user.Email, user.FirstName, code); err != nil {
		fmt.Println("[ERROR] sendPasswordResetEmail:", err)
		sendError(w, "Unable to send the reset code email. Please try again later.", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]interface{}{"success": true, "message": "If that email is registered, a reset code has been sent."})
}

func handleVerifyPasswordCode(w http.ResponseWriter, req *ForgotPasswordRequest) {
	if req.Email == "" || req.Code == "" {
		sendError(w, "Email and verification code are required.", http.StatusBadRequest)
		return
	}

	reset, err := db.GetPasswordResetByEmailAndCode(req.Email, req.Code)
	if err != nil {
		sendError(w, "The verification code is invalid or has expired.", http.StatusBadRequest)
		return
	}
	if reset.UsedAt.Valid || reset.ExpiresAt.Before(time.Now()) {
		sendError(w, "The verification code is invalid or has expired.", http.StatusBadRequest)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]interface{}{"success": true, "message": "Your code is valid. Please enter a new password."})
}

func handleResetPassword(w http.ResponseWriter, req *ForgotPasswordRequest) {
	if req.Email == "" || req.Code == "" {
		sendError(w, "Email and verification code are required.", http.StatusBadRequest)
		return
	}
	if req.NewPassword == "" || req.ConfirmPassword == "" {
		sendError(w, "Please enter and confirm a new password.", http.StatusBadRequest)
		return
	}
	if req.NewPassword != req.ConfirmPassword {
		sendError(w, "Passwords do not match.", http.StatusBadRequest)
		return
	}
	if len(req.NewPassword) < 6 {
		sendError(w, "Password must be at least 6 characters long.", http.StatusBadRequest)
		return
	}

	reset, err := db.GetPasswordResetByEmailAndCode(req.Email, req.Code)
	if err != nil {
		sendError(w, "The verification code is invalid or has expired.", http.StatusBadRequest)
		return
	}
	if reset.UsedAt.Valid || reset.ExpiresAt.Before(time.Now()) {
		sendError(w, "The verification code is invalid or has expired.", http.StatusBadRequest)
		return
	}

	if err := db.ChangeUserPasswordInDB(reset.UserID, req.NewPassword); err != nil {
		fmt.Println("[ERROR] ChangeUserPasswordInDB:", err)
		sendError(w, "Unable to reset your password. Please try again later.", http.StatusInternalServerError)
		return
	}

	if err := db.MarkPasswordResetUsed(reset.ID); err != nil {
		fmt.Println("[ERROR] MarkPasswordResetUsed:", err)
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]interface{}{"success": true, "message": "Your password has been updated successfully. You can now log in with your new password."})
}

func sendPasswordResetEmail(email, name, code string) error {
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
	subject := "Password reset code"

	htmlBody := fmt.Sprintf(`
		<!DOCTYPE html>
		<html lang="en">
		<head>
		  <meta charset="UTF-8" />
		  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
		  <title>Password reset code</title>
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
		              <p style="margin:0 0 16px;font-size:16px;line-height:1.7;color:#334155;">Hello <strong>%s</strong>,</p>
		              <p style="margin:0 0 28px;font-size:16px;line-height:1.75;color:#475569;">We received a request to reset your UpcycleConnect password. Use the code below to continue.</p>
		              <div style="background:#f7f9fb;border:2px dashed #94a3b8;border-radius:16px;padding:26px 0;text-align:center;margin:0 0 28px;">
		                <span style="display:inline-block;font-size:40px;font-weight:700;letter-spacing:6px;color:#1f2937;">%s</span>
		              </div>
		              <p style="margin:0 0 24px;font-size:14px;line-height:1.7;color:#64748b;">This code expires in 15 minutes.</p>
		              <p style="margin:0;font-size:14px;line-height:1.7;color:#64748b;">If you did not request this reset, you can safely ignore this email.</p>
		            </td>
		          </tr>
		          <tr>
		            <td style="padding:24px 40px 32px;font-size:14px;line-height:1.7;color:#64748b;background:#f8fafc;">
		              <p style="margin:0;">Thanks,<br />UpcycleConnect</p>
		            </td>
		          </tr>
		        </table>
		      </td>
		    </tr>
		  </table>
		</body>
		</html>`, html.EscapeString(name), html.EscapeString(code))

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

	addrFields := []string{"user_road_number", "user_road", "user_zip_code", "user_city"}
	count := 0
	for _, f := range addrFields {
		if v, ok := updates[f]; ok {
			switch t := v.(type) {
			case string:
				if strings.TrimSpace(t) != "" {
					count++
				}
			default:
				count++
			}
		}
	}
	if count > 0 && count < len(addrFields) {
		sendError(w, "All address fields must be provided together", http.StatusBadRequest)
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

func UploadProfilePicture(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		sendError(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	userID := r.PathValue("id")
	if _, err := uuid.Parse(userID); err != nil {
		sendError(w, "Invalid user ID format", http.StatusBadRequest)
		return
	}

	callerID, _ := r.Context().Value("user_id").(string)
	if callerID == "" || callerID != userID {
		sendError(w, "Unauthorized", http.StatusForbidden)
		return
	}

	if err := r.ParseMultipartForm(10 << 20); err != nil {
		sendError(w, "Invalid form data or file too large", http.StatusBadRequest)
		return
	}

	file, header, err := r.FormFile("profile_picture")
	if err != nil {
		sendError(w, "Profile picture is required", http.StatusBadRequest)
		return
	}
	defer file.Close()

	ext := strings.ToLower(filepath.Ext(header.Filename))
	allowed := map[string]bool{".jpg": true, ".jpeg": true, ".png": true, ".webp": true, ".gif": true}
	if !allowed[ext] {
		sendError(w, "Unsupported file type. Allowed types: jpg, jpeg, png, webp, gif.", http.StatusBadRequest)
		return
	}

	user, err := db.GetUserByIDFromDB(uuid.MustParse(userID))
	if err != nil {
		fmt.Println("[ERROR] UploadProfilePicture get user:", err)
		sendError(w, "Unable to find user", http.StatusInternalServerError)
		return
	}

	storageDir := filepath.Join("..", "files", "uploads", "user")
	if err := os.MkdirAll(storageDir, 0755); err != nil {
		fmt.Println("[ERROR] UploadProfilePicture mkdir:", err)
		sendError(w, "Unable to store profile picture", http.StatusInternalServerError)
		return
	}

	newFilename := uuid.New().String() + ext
	newFilePath := filepath.Join(storageDir, newFilename)
	out, err := os.Create(newFilePath)
	if err != nil {
		fmt.Println("[ERROR] UploadProfilePicture create file:", err)
		sendError(w, "Unable to save profile picture", http.StatusInternalServerError)
		return
	}
	defer out.Close()

	if _, err := io.Copy(out, file); err != nil {
		fmt.Println("[ERROR] UploadProfilePicture save file:", err)
		sendError(w, "Unable to save profile picture", http.StatusInternalServerError)
		return
	}

	currentPicture := strings.TrimSpace(user.ProfilePicture)
	if currentPicture != "" {
		if err := db.CreateUserProfilePictureHistoryInDB(user.ID, currentPicture); err != nil {
			fmt.Println("[WARN] UploadProfilePicture history insert:", err)
		}
		removed, err := db.PruneUserProfilePictureHistoryFromDB(user.ID.String(), 5)
		if err != nil {
			fmt.Println("[WARN] UploadProfilePicture history prune:", err)
		} else {
			for _, pic := range removed {
				if pic == "" || strings.HasPrefix(pic, "http") || strings.HasPrefix(pic, "/") {
					continue
				}
				oldPath := filepath.Join(storageDir, pic)
				if err := os.Remove(oldPath); err != nil && !os.IsNotExist(err) {
					fmt.Println("[WARN] UploadProfilePicture remove old file:", err)
				}
			}
		}
	}

	if err := db.UpdateUserProfilePictureInDB(user.ID, newFilename); err != nil {
		fmt.Println("[ERROR] UploadProfilePicture update user:", err)
		sendError(w, "Unable to update user profile picture", http.StatusInternalServerError)
		return
	}

	historyItems, err := db.GetUserProfilePictureHistoryFromDB(user.ID.String(), 5)
	if err != nil {
		fmt.Println("[WARN] UploadProfilePicture history fetch:", err)
	}

	historyResponse := make([]map[string]string, 0, len(historyItems))
	for _, item := range historyItems {
		itemURL := "/PA/files/uploads/user/" + item.Picture
		historyResponse = append(historyResponse, map[string]string{
			"id":          item.ID.String(),
			"user_id":     item.UserID.String(),
			"picture":     item.Picture,
			"picture_url": itemURL,
			"created_at":  item.CreatedAt,
		})
	}

	profilePictureURL := "/PA/files/uploads/user/" + newFilename
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]interface{}{
		"success":             true,
		"profile_picture":     newFilename,
		"profile_picture_url": profilePictureURL,
		"history":             historyResponse,
	})
}

func GetProfilePictureHistory(w http.ResponseWriter, r *http.Request) {
	userID := strings.TrimSpace(r.PathValue("id"))
	if _, err := uuid.Parse(userID); err != nil {
		sendError(w, "Invalid user ID format", http.StatusBadRequest)
		return
	}

	history, err := db.GetUserProfilePictureHistoryFromDB(userID, 5)
	if err != nil {
		fmt.Println("[ERROR] GetProfilePictureHistory:", err)
		sendError(w, "Unable to fetch profile picture history", http.StatusInternalServerError)
		return
	}

	historyResponse := make([]map[string]string, 0, len(history))
	for _, item := range history {
		itemURL := "/PA/files/uploads/user/" + item.Picture
		historyResponse = append(historyResponse, map[string]string{
			"id":          item.ID.String(),
			"user_id":     item.UserID.String(),
			"picture":     item.Picture,
			"picture_url": itemURL,
			"created_at":  item.CreatedAt,
		})
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]interface{}{"history": historyResponse})
}

func RestoreProfilePictureFromHistory(w http.ResponseWriter, r *http.Request) {
	userID := strings.TrimSpace(r.PathValue("id"))
	historyID := strings.TrimSpace(r.PathValue("historyID"))
	if _, err := uuid.Parse(userID); err != nil {
		sendError(w, "Invalid user ID format", http.StatusBadRequest)
		return
	}
	if _, err := uuid.Parse(historyID); err != nil {
		sendError(w, "Invalid history item ID format", http.StatusBadRequest)
		return
	}

	callerID, _ := r.Context().Value("user_id").(string)
	if callerID == "" || callerID != userID {
		sendError(w, "Unauthorized", http.StatusForbidden)
		return
	}

	historyItem, err := db.GetUserProfilePictureHistoryItemByID(userID, historyID)
	if err != nil {
		fmt.Println("[ERROR] RestoreProfilePictureFromHistory:", err)
		sendError(w, "Unable to find profile picture history item", http.StatusNotFound)
		return
	}

	user, err := db.GetUserByIDFromDB(uuid.MustParse(userID))
	if err != nil {
		fmt.Println("[ERROR] RestoreProfilePictureFromHistory get user:", err)
		sendError(w, "Unable to fetch user", http.StatusInternalServerError)
		return
	}

	if user.ProfilePicture != "" && user.ProfilePicture != historyItem.Picture {
		if err := db.CreateUserProfilePictureHistoryInDB(user.ID, user.ProfilePicture); err != nil {
			fmt.Println("[WARN] RestoreProfilePictureFromHistory history insert:", err)
		}
	}

	if err := db.UpdateUserProfilePictureInDB(user.ID, historyItem.Picture); err != nil {
		fmt.Println("[ERROR] RestoreProfilePictureFromHistory update user:", err)
		sendError(w, "Unable to restore profile picture", http.StatusInternalServerError)
		return
	}

	history, err := db.GetUserProfilePictureHistoryFromDB(userID, 5)
	if err != nil {
		fmt.Println("[WARN] RestoreProfilePictureFromHistory history fetch:", err)
	}

	historyResponse := make([]map[string]string, 0, len(history))
	for _, item := range history {
		itemURL := "/PA/files/uploads/user/" + item.Picture
		historyResponse = append(historyResponse, map[string]string{
			"id":          item.ID.String(),
			"user_id":     item.UserID.String(),
			"picture":     item.Picture,
			"picture_url": itemURL,
			"created_at":  item.CreatedAt,
		})
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]interface{}{
		"success":             true,
		"profile_picture":     historyItem.Picture,
		"profile_picture_url": "/PA/files/uploads/user/" + historyItem.Picture,
		"history":             historyResponse,
	})
}

func DeleteProfilePictureHistoryItem(w http.ResponseWriter, r *http.Request) {
	userID := strings.TrimSpace(r.PathValue("id"))
	historyID := strings.TrimSpace(r.PathValue("historyID"))
	if _, err := uuid.Parse(userID); err != nil {
		sendError(w, "Invalid user ID format", http.StatusBadRequest)
		return
	}
	if _, err := uuid.Parse(historyID); err != nil {
		sendError(w, "Invalid history item ID format", http.StatusBadRequest)
		return
	}

	callerID, _ := r.Context().Value("user_id").(string)
	if callerID == "" || callerID != userID {
		sendError(w, "Unauthorized", http.StatusForbidden)
		return
	}

	historyItem, err := db.GetUserProfilePictureHistoryItemByID(userID, historyID)
	if err != nil {
		fmt.Println("[ERROR] DeleteProfilePictureHistoryItem:", err)
		sendError(w, "Unable to find profile picture history item", http.StatusNotFound)
		return
	}

	if err := db.DeleteUserProfilePictureHistoryItemFromDB(userID, historyID); err != nil {
		fmt.Println("[ERROR] DeleteProfilePictureHistoryItem delete:", err)
		sendError(w, "Unable to delete history item", http.StatusInternalServerError)
		return
	}

	user, _ := db.GetUserByIDFromDB(uuid.MustParse(userID))
	if user.ProfilePicture != "" && user.ProfilePicture != historyItem.Picture {
		storageDir := filepath.Join("..", "files", "uploads", "user")
		oldPath := filepath.Join(storageDir, filepath.Base(historyItem.Picture))
		if err := os.Remove(oldPath); err != nil && !os.IsNotExist(err) {
			fmt.Println("[WARN] DeleteProfilePictureHistoryItem remove old file:", err)
		}
	}

	history, err := db.GetUserProfilePictureHistoryFromDB(userID, 5)
	if err != nil {
		fmt.Println("[WARN] DeleteProfilePictureHistoryItem history fetch:", err)
	}

	historyResponse := make([]map[string]string, 0, len(history))
	for _, item := range history {
		itemURL := "/PA/files/uploads/user/" + item.Picture
		historyResponse = append(historyResponse, map[string]string{
			"id":          item.ID.String(),
			"user_id":     item.UserID.String(),
			"picture":     item.Picture,
			"picture_url": itemURL,
			"created_at":  item.CreatedAt,
		})
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]interface{}{"success": true, "history": historyResponse})
}

func DeleteAllProfilePictureHistory(w http.ResponseWriter, r *http.Request) {
	userID := strings.TrimSpace(r.PathValue("id"))
	if _, err := uuid.Parse(userID); err != nil {
		sendError(w, "Invalid user ID format", http.StatusBadRequest)
		return
	}

	callerID, _ := r.Context().Value("user_id").(string)
	if callerID == "" || callerID != userID {
		sendError(w, "Unauthorized", http.StatusForbidden)
		return
	}

	pictures, err := db.DeleteAllUserProfilePictureHistoryFromDB(userID)
	if err != nil {
		fmt.Println("[ERROR] DeleteAllProfilePictureHistory:", err)
		sendError(w, "Unable to clear history", http.StatusInternalServerError)
		return
	}

	user, _ := db.GetUserByIDFromDB(uuid.MustParse(userID))
	storageDir := filepath.Join("..", "files", "uploads", "user")
	for _, pic := range pictures {
		if pic == "" || pic == user.ProfilePicture {
			continue
		}
		oldPath := filepath.Join(storageDir, filepath.Base(pic))
		if err := os.Remove(oldPath); err != nil && !os.IsNotExist(err) {
			fmt.Println("[WARN] DeleteAllProfilePictureHistory remove old file:", err)
		}
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]interface{}{"success": true, "history": []interface{}{}})
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

func DeleteUserAdmin(w http.ResponseWriter, r *http.Request) {
	userID := strings.TrimPrefix(r.URL.Path, "/users/")
	userID = strings.TrimSuffix(userID, "/delete")

	if _, err := uuid.Parse(userID); err != nil {
		fmt.Println("[WARN] DeleteUserAdmin: invalid UUID", userID)
		sendError(w, "Invalid user ID format", http.StatusBadRequest)
		return
	}

	err := db.DeleteUserFromDB(uuid.MustParse(userID))
	if err != nil {
		fmt.Println("[ERROR] DeleteUserAdmin:", err)
		sendError(w, "Unable to delete user", http.StatusInternalServerError)
		return
	}
	
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]bool{"success": true})

}

func DeleteUser(w http.ResponseWriter, r *http.Request) {
	var deleteReq struct {
		Password string `json:"password"`
		MFACode  string `json:"mfa_code,omitempty"`
	}

	err := json.NewDecoder(r.Body).Decode(&deleteReq)
	if err != nil {
		fmt.Println("[ERROR] DeleteUser decode:", err)
		sendError(w, "Invalid JSON format", http.StatusBadRequest)
		return
	}

	if deleteReq.Password == "" {
		sendError(w, "Password is required to delete account", http.StatusBadRequest)
		return
	}

	idStr := strings.TrimPrefix(r.URL.Path, "/users/")
	userID, err := uuid.Parse(idStr)
	if err != nil {
		fmt.Println("[ERROR] DeleteUser parse UUID:", err)
		sendError(w, "Invalid user ID format", http.StatusBadRequest)
		return
	}

	user, err := db.GetUserByIDFromDB(userID)
	if err != nil {
		fmt.Println("[ERROR] DeleteUser get user:", err)
		sendError(w, "User not found", http.StatusNotFound)
		return
	}

	err = bcrypt.CompareHashAndPassword([]byte(user.Password), []byte(deleteReq.Password))
	if err != nil {
		fmt.Println("[ERROR] DeleteUser password verification failed")
		sendError(w, "Password is incorrect", http.StatusUnauthorized)
		return
	}

	twoFAEnabled, secret, tfaErr := db.Get2FAInfoFromDB(userID.String())
	if tfaErr != nil {
		fmt.Println("[WARN] DeleteUser get2FAInfo:", tfaErr)
		twoFAEnabled = false
	}

	if twoFAEnabled {
		if deleteReq.MFACode == "" {
			sendError(w, "MFA code is required to delete account", http.StatusBadRequest)
			return
		}

		if !verifyTOTPCode(deleteReq.MFACode, secret) {
			fmt.Println("[ERROR] DeleteUser MFA verification failed")
			sendError(w, "Invalid MFA code", http.StatusUnauthorized)
			return
		}
	}

	if err := db.DeleteUserFromDB(userID); err != nil {
		fmt.Println("[ERROR] DeleteUser DB deletion:", err)
		sendError(w, "Unable to delete account", http.StatusInternalServerError)
		return
	}

	err = sendAccountDeletedEmail(user.Email, user.FirstName)
	if err != nil {
		fmt.Println("[WARN] DeleteUser send email:", err)
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]interface{}{
		"success": true,
		"message": "Account deleted successfully",
	})
}

func verifyTOTPCode(code string, secret string) bool {
	if secret == "" || code == "" {
		return false
	}
	return totp.Validate(code, secret)
}

func sendAccountDeletedEmail(email, name string) error {
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
	subject := "Your account has been deleted"

	displayName := name
	if displayName == "" {
		displayName = "there"
	}

	htmlBody := fmt.Sprintf(`
		<!DOCTYPE html>
		<html lang="en">
		<head>
		  <meta charset="UTF-8" />
		  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
		  <title>Your Account Has Been Deleted</title>
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
		              <p style="margin:0 0 16px;font-size:16px;line-height:1.7;color:#334155;">Hello <strong>%s</strong>,</p>
		              <p style="margin:0 0 28px;font-size:16px;line-height:1.75;color:#475569;">Your UpcycleConnect account has been successfully deleted.</p>
		              <p style="margin:0 0 24px;font-size:14px;line-height:1.7;color:#64748b;">
		                If you did not request this action, please contact our support team immediately at <strong>support@upcycleconnect.com</strong>.
		              </p>
		              <p style="margin:0;font-size:14px;line-height:1.7;color:#64748b;">
		                We hope to see you again in the future. If you have any feedback about your experience with UpcycleConnect, please let us know.
		              </p>
		            </td>
		          </tr>
		          <tr>
		            <td style="padding:24px 40px 32px;font-size:14px;line-height:1.7;color:#64748b;background:#f8fafc;">
		              <p style="margin:0;">Thanks,<br />UpcycleConnect Team</p>
		            </td>
		          </tr>
		        </table>
		      </td>
		    </tr>
		  </table>
		</body>
		</html>`, html.EscapeString(displayName))

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
	if profilePictureURL != "" && !strings.HasPrefix(profilePictureURL, "http") && !strings.HasPrefix(profilePictureURL, "/") {
		profilePictureURL = "/PA/files/uploads/user/" + profilePictureURL
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

func GetUserBalance(w http.ResponseWriter, r *http.Request) {

	userID := strings.TrimPrefix(r.URL.Path, "/users/")
	userID = strings.TrimSuffix(userID, "/balance")

	if _, err := uuid.Parse(userID); err != nil {
		fmt.Println("[WARN] GetUserBalance: invalid UUID", userID)
		sendError(w, "Invalid user ID format", http.StatusBadRequest)
		return
	}

	balance, err := db.GetUserBalanceFromDB(userID)
	if err != nil {
		fmt.Println("[ERROR] GetUserBalance:", err)
		sendError(w, "Unable to fetch balance for user", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]float64{"balance": balance})

}

func UpdateUserBalance(w http.ResponseWriter, r *http.Request) {

	userID := strings.TrimPrefix(r.URL.Path, "/users/")
	userID = strings.TrimSuffix(userID, "/balance")

	if _, err := uuid.Parse(userID); err != nil {
		fmt.Println("[WARN] UpdateUserBalance: invalid UUID", userID)
		sendError(w, "Invalid user ID format", http.StatusBadRequest)
		return
	}

	var req struct {
		Amount    float64 `json:"amount"`
		Operation int     `json:"operation"` // 1 for add, 2 for subtract
	}

	err := json.NewDecoder(r.Body).Decode(&req)
	if err != nil {
		fmt.Println("[ERROR] UpdateUserBalance decode:", err)
		sendError(w, "Invalid JSON format", http.StatusBadRequest)
		return
	}

	if req.Operation != 1 && req.Operation != 2 {
		sendError(w, "Invalid operation type", http.StatusBadRequest)
		return
	}

	err = db.UpdateUserBalanceInDB(userID, req.Amount, req.Operation)
	if err != nil {
		fmt.Println("[ERROR] UpdateUserBalance DB:", err)
		sendError(w, "Unable to update balance for user", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]bool{"success": true})

}

func GetUserDiscussions(w http.ResponseWriter, r *http.Request) {

	userID := strings.TrimPrefix(r.URL.Path, "/users/")
	userID = strings.TrimSuffix(userID, "/discussions")

	if _, err := uuid.Parse(userID); err != nil {
		fmt.Println("[ERROR] GetUserDiscussions: invalid UUID", userID)
		sendError(w, "Invalid user ID format", http.StatusBadRequest)
		return
	}

	discussions, err := db.GetUserDiscussionsFromDB(userID)
	if err != nil {
		fmt.Println("[ERROR] GetUserDiscussions:", err)
		sendError(w, "Unable to fetch discussions for user", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(discussions)

}

func ValidateDiscussionDTO(discussion models.Discussion) []string {

	var errs []string

	if discussion.User1ID == uuid.Nil {
		errs = append(errs, "user1_id is required and must be a valid UUID")
	}

	if discussion.User2ID == uuid.Nil {
		errs = append(errs, "user2_id is required and must be a valid UUID")
	}

	return errs

}

func CreateDiscussion(w http.ResponseWriter, r *http.Request) {

	userID := strings.TrimPrefix(r.URL.Path, "/users/")
	userID = strings.TrimSuffix(userID, "/discussions")

	if _, err := uuid.Parse(userID); err != nil {
		fmt.Println("[ERROR] CreateDiscussion: invalid UUID", userID)
		sendError(w, "Invalid user ID format", http.StatusBadRequest)
		return
	}

	var discussionDTO models.Discussion
	err := json.NewDecoder(r.Body).Decode(&discussionDTO)

	if err != nil {
		fmt.Println("[ERROR] CreateDiscussion decode:", err)
		if err.Error() == "EOF" {
			sendError(w, "Request body is empty", http.StatusBadRequest)
		}

		sendError(w, "Invalid JSON format", http.StatusBadRequest)
		return
	}

	errs := ValidateDiscussionDTO(discussionDTO)
	if len(errs) > 0 {
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]interface{}{
			"errors": errs,
		})
		return
	}

	_, err = db.CreateDiscussionInDB(discussionDTO)
	if err != nil {
		fmt.Println("[ERROR] CreateDiscussion DB:", err)
		sendError(w, "Unable to create discussion", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]string{"message": "Discussion created successfully"})

}
