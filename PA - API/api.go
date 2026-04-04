package main

// @title           UpcycleConnect PA API
// @version         1.0
// @description     REST API pour UpcycleConnect (PA).
// @termsOfService  http://example.com/terms/
// @contact.name    API Support
// @contact.url     http://example.com/support
// @contact.email   support@upcycleconnect.cloud
// @license.name    None
// @license.url     https://opensource.org/licenses/MIT
// @host            localhost:9999
// @BasePath        /

import (
	"API/app"
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"
	"os"
	"reflect"
	"regexp"
	"runtime"
	"strings"

	"github.com/google/uuid"
	"github.com/joho/godotenv"
	httpSwagger "github.com/swaggo/http-swagger"
)

var registeredEndpoints []models.Endpoint

var pathParamRegex = regexp.MustCompile(`\{([^/}]+)\}`)

type moderationRequestBody struct {
	Content string `json:"content"`
}

type llmUsageRequestBody struct {
	UsageDelta int  `json:"usage_delta"`
	Quota      *int `json:"quota,omitempty"`
}

var requestBodyTypes = map[string]reflect.Type{
	"POST /login":                   reflect.TypeOf(app.LoginRequest{}),
	"POST /users":                   reflect.TypeOf(models.User{}),
	"POST /moderate":                reflect.TypeOf(moderationRequestBody{}),
	"POST /annonces":                reflect.TypeOf(models.Annonce{}),
	"PATCH /annonces/{id}":          reflect.TypeOf(models.Annonce{}),
	"POST /facteurs":                reflect.TypeOf(models.FacteurMateriaux{}),
	"PATCH /facteurs/{id}":          reflect.TypeOf(models.FacteurMateriaux{}),
	"POST /users/{id}/badges":       reflect.TypeOf(models.Badge{}),
	"POST /products/services":       reflect.TypeOf(models.Service{}),
	"PATCH /products/services/{id}": reflect.TypeOf(models.Service{}),
	"POST /orders":                  reflect.TypeOf(models.Order{}),
	"POST /annonces/{id}/images":    reflect.TypeOf(models.Image{}),
	"POST /notifications":           reflect.TypeOf(models.Notification{}),
	"POST /payment-requests":        reflect.TypeOf(models.PaymentRequest{}),
	"PATCH /payment-requests/{id}/status": reflect.TypeOf(struct {
		Status       *int   `json:"status"`
		ApproverID   string `json:"approver_id,omitempty"`
		AdminComment string `json:"admin_comment,omitempty"`
	}{}),
	"POST /payouts":                                   reflect.TypeOf(models.Payout{}),
	"POST /banking-details":                           reflect.TypeOf(models.BankingDetails{}),
	"POST /forums":                                    reflect.TypeOf(models.Forum{}),
	"POST /forums/{id}/posts":                         reflect.TypeOf(models.ForumPost{}),
	"POST /users/{id}/planning":                       reflect.TypeOf(models.Planning{}),
	"PATCH /users/{id}":                               reflect.TypeOf(models.User{}),
	"PATCH /forums/{id}/posts/{pID}":                  reflect.TypeOf(models.ForumPost{}),
	"POST /conteneurs":                                reflect.TypeOf(models.Conteneur{}),
	"POST /deposits":                                  reflect.TypeOf(models.Deposit{}),
	"POST /deposits/{id}/files":                       reflect.TypeOf(models.DepositFile{}),
	"POST /users/{id}/2fa/setup":                      reflect.TypeOf(struct{}{}),
	"POST /users/{id}/2fa/enable":                     reflect.TypeOf(struct{}{}),
	"POST /users/{id}/2fa/disable":                    reflect.TypeOf(struct{}{}),
	"POST /2fa/verify":                                reflect.TypeOf(struct{}{}),
	"POST /users/{id}/discussions":                    reflect.TypeOf(models.Discussion{}),
	"POST /tips":                                      reflect.TypeOf(models.Tip{}),
	"PATCH /tips/{id}":                                reflect.TypeOf(models.Tip{}),
	"PATCH /users/{id}/planning/{pID}":                reflect.TypeOf(models.Planning{}),
	"POST /ban":                                       reflect.TypeOf(models.Ban{}),
	"POST /refund-requests":                           reflect.TypeOf(models.RefundRequest{}),
	"POST /internal/subscription/activate":            reflect.TypeOf(struct{}{}),
	"POST /internal/subscription/revoke":              reflect.TypeOf(struct{}{}),
	"POST /internal/subscription/invoice":             reflect.TypeOf(struct{}{}),
	"POST /internal/promotion/complete":               reflect.TypeOf(struct{}{}),
	"POST /projects":                                  reflect.TypeOf(models.Project{}),
	"PATCH /projects/{id}":                            reflect.TypeOf(models.Project{}),
	"POST /projects/{id}/steps":                       reflect.TypeOf(models.ProjectStep{}),
	"PATCH /projects/{id}/steps/{sID}":                reflect.TypeOf(models.ProjectStep{}),
	"POST /projects/{id}/steps/{sID}/materials":       reflect.TypeOf(models.ProjectStepMaterial{}),
	"PATCH /users/{id}/llm":                           reflect.TypeOf(llmUsageRequestBody{}),
	"POST /projects/{id}/likes":                       reflect.TypeOf(struct{}{}),
	"POST /projects/{id}/comments":                    reflect.TypeOf(models.ProjectComment{}),
	"PATCH /projects/{id}/comments/{cID}":             reflect.TypeOf(models.ProjectComment{}),
	"PATCH /users/{id}/balance":                       reflect.TypeOf(struct{}{}),
	"POST /products/services/{id}/affected-employees": reflect.TypeOf(models.AffectedEmployee{}),
	"POST /typesPrestation":                           reflect.TypeOf(models.TypePrestation{}),
	"PATCH /typesPrestation/{id}":                     reflect.TypeOf(models.TypePrestation{}),
	"POST /polls":                                     reflect.TypeOf(models.Poll{}),
	"POST /polls/{id}/options":                        reflect.TypeOf(models.PollOption{}),
	"POST /polls/{id}/vote":                           reflect.TypeOf(models.PollVote{}),
	"PATCH /polls/{id}":                               reflect.TypeOf(models.Poll{}),
	"PATCH /polls/{id}/options/{oID}":                 reflect.TypeOf(models.PollOption{}),
	"POST /categories":                                reflect.TypeOf(models.Category{}),
	"PATCH /categories/{id}":                          reflect.TypeOf(models.Category{}),
}

func corsMiddleware(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Access-Control-Allow-Origin", "*")
		w.Header().Set("Access-Control-Allow-Methods", "GET, POST, PUT, PATCH, DELETE, OPTIONS")
		w.Header().Set("Access-Control-Allow-Headers", "Content-Type, Authorization")
		if r.Method == http.MethodOptions {
			w.WriteHeader(http.StatusOK)
			return
		}
		next(w, r)
	}
}

func registerRoute(method, path, description string, handler func(http.ResponseWriter, *http.Request), middlewares ...func(http.HandlerFunc) http.HandlerFunc) {
	pattern := method + " " + path
	finalHandler := handler
	finalHandler = corsMiddleware(finalHandler)

	requiresAuth := false
	for _, m := range middlewares {

		name := runtime.FuncForPC(reflect.ValueOf(m).Pointer()).Name()
		if strings.Contains(name, "JWTAuthMiddleware") || strings.Contains(name, "InternalKeyMiddleware") || strings.Contains(name, "RoleMiddleware") {
			requiresAuth = true
			break
		}
	}

	hasRequestBody := false
	if strings.EqualFold(method, "POST") || strings.EqualFold(method, "PUT") || strings.EqualFold(method, "PATCH") {
		hasRequestBody = true
	}

	for i := len(middlewares) - 1; i >= 0; i-- {
		finalHandler = middlewares[i](finalHandler)
	}
	http.HandleFunc(pattern, finalHandler)
	registeredEndpoints = append(registeredEndpoints, models.Endpoint{
		Method:         method,
		Path:           path,
		Description:    description,
		RequiresAuth:   requiresAuth,
		HasRequestBody: hasRequestBody,
	})
}

// @Summary      Health check
// @Description  Vérifie que l'API et la base de données répondent.
// @Tags         health
// @Accept       json
// @Produce      json
// @Success      200  {object} map[string]string
// @Failure      503  {object} map[string]string
// @Router       / [get]
func healthCheck(w http.ResponseWriter, r *http.Request) {

	err := db.Db.Ping()

	if err != nil {
		fmt.Println("[ERROR] Health check - DB ping failed:", err)
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusServiceUnavailable)
		json.NewEncoder(w).Encode(map[string]string{"error": "Service unavailable"})
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"status": "OK", "database": "connected"})

}

func notFoundHandler(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Access-Control-Allow-Origin", "*")
	w.Header().Set("Access-Control-Allow-Methods", "GET, POST, PUT, PATCH, DELETE, OPTIONS")
	w.Header().Set("Access-Control-Allow-Headers", "Content-Type, Authorization")

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusNotFound)

	response := map[string]interface{}{
		"error":               "Endpoint not found",
		"path":                r.URL.Path,
		"method":              r.Method,
		"available_endpoints": registeredEndpoints,
	}

	json.NewEncoder(w).Encode(response)
}

// @Summary      OpenAPI spec
// @Description  Retourne le document OpenAPI (swagger) pour l'API.
// @Tags         docs
// @Produce      json
// @Success      200  {object} map[string]interface{}
// @Router       /openapi.json [get]
func openapiSpecHandler(w http.ResponseWriter, r *http.Request) {
	serverURL := "http://localhost:9999"
	if host := os.Getenv("API_HOST"); host != "" {
		port := os.Getenv("API_PORT")
		if port == "" {
			port = "8080"
		}
		serverURL = fmt.Sprintf("http://%s:%s", host, port)
	}

	spec := buildOpenAPISpec(serverURL)

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(spec)
}

func generateJSONSchema(t reflect.Type) map[string]interface{} {
	if t == nil {
		return map[string]interface{}{"type": "object"}
	}
	for t.Kind() == reflect.Ptr {
		t = t.Elem()
	}

	switch t.Kind() {
	case reflect.Struct:
		props := map[string]interface{}{}
		required := []string{}
		for i := 0; i < t.NumField(); i++ {
			f := t.Field(i)
			if f.PkgPath != "" {
				continue
			}
			jsonTag := f.Tag.Get("json")
			if jsonTag == "-" {
				continue
			}
			name := strings.Split(jsonTag, ",")[0]
			if name == "" {
				name = f.Name
			}
			if name == "-" {
				continue
			}
			fieldSchema := generateJSONSchema(f.Type)
			props[name] = fieldSchema
			if !strings.Contains(jsonTag, "omitempty") {
				required = append(required, name)
			}
		}
		schema := map[string]interface{}{
			"type":       "object",
			"properties": props,
		}
		if len(required) > 0 {
			schema["required"] = required
		}
		return schema
	case reflect.Slice, reflect.Array:
		return map[string]interface{}{
			"type":  "array",
			"items": generateJSONSchema(t.Elem()),
		}
	case reflect.Map:
		return map[string]interface{}{
			"type":                 "object",
			"additionalProperties": generateJSONSchema(t.Elem()),
		}
	case reflect.String:
		if t.PkgPath() == "github.com/google/uuid" && t.Name() == "UUID" {
			return map[string]interface{}{"type": "string", "format": "uuid"}
		}
		return map[string]interface{}{"type": "string"}
	case reflect.Int, reflect.Int8, reflect.Int16, reflect.Int32, reflect.Int64:
		return map[string]interface{}{"type": "integer"}
	case reflect.Uint, reflect.Uint8, reflect.Uint16, reflect.Uint32, reflect.Uint64:
		return map[string]interface{}{"type": "integer"}
	case reflect.Float32, reflect.Float64:
		return map[string]interface{}{"type": "number"}
	case reflect.Bool:
		return map[string]interface{}{"type": "boolean"}
	default:
		return map[string]interface{}{"type": "string"}
	}
}

func buildOpenAPISpec(serverURL string) map[string]interface{} {
	paths := map[string]interface{}{}

	for _, e := range registeredEndpoints {
		path := e.Path
		if path == "/{$}" {
			path = "/"
		}
		if !strings.HasPrefix(path, "/") {
			path = "/" + path
		}

		method := strings.ToLower(e.Method)

		params := []map[string]interface{}{}
		for _, match := range pathParamRegex.FindAllStringSubmatch(path, -1) {
			if len(match) < 2 {
				continue
			}
			params = append(params, map[string]interface{}{
				"name":     match[1],
				"in":       "path",
				"required": true,
				"schema": map[string]interface{}{
					"type": "string",
				},
			})
		}

		operation := map[string]interface{}{
			"summary": e.Description,
			"responses": map[string]interface{}{
				"200": map[string]interface{}{
					"description": "Success",
					"content": map[string]interface{}{
						"application/json": map[string]interface{}{
							"schema": map[string]interface{}{"type": "object"},
						},
					},
				},
			},
		}

		if e.RequiresAuth {
			operation["security"] = []map[string][]string{{"bearerAuth": {}}}
		}

		if e.HasRequestBody {
			schema := map[string]interface{}{"type": "object"}
			if t, ok := requestBodyTypes[strings.ToUpper(e.Method)+" "+e.Path]; ok {
				schema = generateJSONSchema(t)
			}
			operation["requestBody"] = map[string]interface{}{
				"required":    true,
				"description": "JSON request body (see API implementation for expected fields)",
				"content": map[string]interface{}{
					"application/json": map[string]interface{}{
						"schema": schema,
					},
				},
			}
		}

		if len(params) > 0 {
			operation["parameters"] = params
		}

		if paths[path] == nil {
			paths[path] = map[string]interface{}{}
		}
		paths[path].(map[string]interface{})[method] = operation
	}

	return map[string]interface{}{
		"openapi": "3.0.3",
		"info": map[string]interface{}{
			"title":       "UpcycleConnect PA API",
			"version":     "1.0",
			"description": "Document d'API généré automatiquement.",
		},
		"servers": []map[string]interface{}{{"url": serverURL}},
		"paths":   paths,
		"components": map[string]interface{}{
			"securitySchemes": map[string]interface{}{
				"bearerAuth": map[string]interface{}{
					"type":         "http",
					"scheme":       "bearer",
					"bearerFormat": "JWT",
				},
			},
		},
	}
}

func checkRoleIntValue(targetRole int, userID uuid.UUID) (bool, error) {

	role, err := db.GetUserRoleByIDFromDB(userID.String())

	if err != nil {
		return false, err
	}

	return role == targetRole, nil

}

func InternalKeyMiddleware(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		key := r.Header.Get("X-Internal-Key")
		expected := os.Getenv("APP_API_KEY")
		if expected == "" || key != expected {
			w.Header().Set("Content-Type", "application/json")
			w.WriteHeader(http.StatusUnauthorized)
			json.NewEncoder(w).Encode(map[string]string{"error": "unauthorized"})
			return
		}
		next(w, r)
	}
}

func RoleMiddleware(requiredRole int) func(http.HandlerFunc) http.HandlerFunc {
	return func(next http.HandlerFunc) http.HandlerFunc {
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
			uidRaw := r.Context().Value("user_id")
			uidStr, ok := uidRaw.(string)
			fmt.Println("[RoleMiddleware] context user_id raw=", uidRaw, "ok=", ok)
			if !ok || uidStr == "" {
				w.Header().Set("Content-Type", "application/json")
				w.WriteHeader(http.StatusUnauthorized)
				json.NewEncoder(w).Encode(map[string]string{"error": "missing user identity"})
				return
			}

			userID, err := uuid.Parse(uidStr)
			if err != nil {
				w.Header().Set("Content-Type", "application/json")
				w.WriteHeader(http.StatusUnauthorized)
				json.NewEncoder(w).Encode(map[string]string{"error": "invalid user id"})
				return
			}

			hasRole, err := checkRoleIntValue(requiredRole, userID)
			if err != nil {
				fmt.Println("[ERROR] RoleMiddleware:", err)
				w.Header().Set("Content-Type", "application/json")
				w.WriteHeader(http.StatusInternalServerError)
				json.NewEncoder(w).Encode(map[string]string{"error": "could not verify user role"})
				return
			}

			if !hasRole {
				w.Header().Set("Content-Type", "application/json")
				w.WriteHeader(http.StatusForbidden)
				json.NewEncoder(w).Encode(map[string]string{"error": "insufficient privileges"})
				return
			}

			next(w, r)
		}
	}
}

func main() {

	if err := godotenv.Load(); err != nil {
		fmt.Println("No .env file loaded:", err)
	}

	port := os.Getenv("API_PORT")
	if port == "" {
		port = "8080"
	}

	host := os.Getenv("API_HOST")
	if host == "" {
		host = "0.0.0.0"
	}

	db.Db = db.NewDB()

	registerRoute("GET", "/{$}", "Health check - verify API and database connection", healthCheck)
	registerRoute("POST", "/login", "User login - authenticate and return user data", app.LoginUser)
	registerRoute("POST", "/oauth/login", "OAuth login - generate JWT for an OAuth-authenticated user", app.OAuthLogin)
	registerRoute("POST", "/moderate", "Moderate arbitrary text using bad‑word list and Gemini AI", app.ModerateContent, app.JWTAuthMiddleware)
	registerRoute("POST", "/users", "Create a new user", app.CreateUser)
	registerRoute("POST", "/users/email", "Get user by email - for OAuth lookup", app.GetUserByEmail)
	registerRoute("GET", "/docs", "Show the API documentation", notFoundHandler)
	registerRoute("GET", "/openapi.json", "OpenAPI (Swagger) spec for the API", openapiSpecHandler)

	registerRoute("GET", "/users", "Get all users", app.GetAllUsers)
	registerRoute("GET", "/dashboard-metrics", "Get aggregated dashboard metrics (admin only)", app.GetDashboardMetrics, app.JWTAuthMiddleware, RoleMiddleware(3))
	registerRoute("GET", "/profile/{username}", "Get public profile by username", app.GetPublicUser, app.JWTAuthMiddleware)
	registerRoute("GET", "/users/{id}", "Get a specific user by his UUID", app.GetUserByID, app.JWTAuthMiddleware)
	registerRoute("GET", "/users/{id}/contracts", "Get contracts for a specific user by their UUID", app.GetContractsByUserID, app.JWTAuthMiddleware)
	registerRoute("GET", "/users/{id}/invoices", "Get invoices for a specific user by their UUID", app.GetInvoicesByUserID, app.JWTAuthMiddleware)
	registerRoute("GET", "/users/{id}/orders", "Get all orders for a specific user by their UUID", app.GetOrdersByUserID, app.JWTAuthMiddleware)
	registerRoute("POST", "/users/{id}/badges", "Award a badge to a user", app.AddBadgeToUser, app.JWTAuthMiddleware)
	registerRoute("GET", "/products/services", "Services listing - for the catalog", app.GetServices, app.JWTAuthMiddleware)
	registerRoute("POST", "/products/services", "Create a new service", app.CreateService, app.JWTAuthMiddleware)
	registerRoute("GET", "/products/services/{id}", "Get a specific service by its UUID", app.GetServiceByID, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/products/services/{id}", "Update a service/event by its UUID", app.UpdateService, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/products/services/{id}", "Delete a service/event by its UUID", app.DeleteService, app.JWTAuthMiddleware)
	registerRoute("GET", "/orders", "List all orders", app.GetOrders, app.JWTAuthMiddleware)
	registerRoute("POST", "/orders", "Create a new order", app.CreateOrder, app.JWTAuthMiddleware)
	registerRoute("GET", "/annonces", "List all annonces", app.GetAnnonces)
	registerRoute("GET", "/facteurs", "List all available material factors", app.GetFacteurs)
	registerRoute("POST", "/facteurs", "Create a new material factor", app.CreateFacteur, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/facteurs/{id}", "Update a material factor by its UUID", app.UpdateFacteur, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/facteurs/{id}", "Delete a material factor by its UUID", app.DeleteFacteur, app.JWTAuthMiddleware)
	registerRoute("GET", "/upcycling-score", "Calculate upcycling score from weight and material", app.CalculateScore)
	registerRoute("GET", "/annonces/{id}/images", "List all images associated with an annonce", app.GetAnnonceImages)
	registerRoute("POST", "/annonces", "Create a new annonce", app.CreateAnnonce, app.JWTAuthMiddleware)
	registerRoute("GET", "/annonces/{id}", "Get a specific annonce by its UUID", app.GetAnnonceByID)
	registerRoute("POST", "/annonces/{id}/images", "Upload an image for a specific annonce", app.UploadAnnonceImage, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/annonces/{id}", "Update an existing annonce", app.UpdateAnnonce, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/annonces/{id}", "Delete an annonce by UUID (admin)", app.DeleteAnnonce, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/annonces/{id}/status", "Admin: set annonce status to pending/approved/rejected", app.AdminUpdateAnnonceStatus, app.JWTAuthMiddleware)
	registerRoute("GET", "/notifications", "List all notifications in the system", app.GetNotifications, app.JWTAuthMiddleware)
	registerRoute("POST", "/notifications", "Create a new notification", app.CreateNotification, app.JWTAuthMiddleware)
	registerRoute("GET", "/users/{id}/notifications", "List all notifications for a specific user by their UUID", app.GetNotificationsByUserID, app.JWTAuthMiddleware)
	registerRoute("GET", "/payment-requests", "List all payment requests in the system", app.GetPaymentRequests, app.JWTAuthMiddleware)
	registerRoute("POST", "/payment-requests", "Create a new payment request", app.CreatePaymentRequest, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/payment-requests/{id}/status", "Update a payment request status", app.UpdatePaymentRequestStatus, app.JWTAuthMiddleware)
	registerRoute("GET", "/payouts", "List all payouts in the system", app.GetPayouts, app.JWTAuthMiddleware)
	registerRoute("POST", "/payouts", "Create a new payout", app.CreatePayout, app.JWTAuthMiddleware)
	registerRoute("GET", "/users/{id}/payouts", "List use's payout", app.GetPayoutsByUserID, app.JWTAuthMiddleware)
	registerRoute("GET", "/banking-details", "List all banking details in the system", app.GetBankingDetails, app.JWTAuthMiddleware)
	registerRoute("GET", "/banking-details/{id}", "Get banking details by UUID", app.GetBankingDetailsByID, app.JWTAuthMiddleware)
	registerRoute("GET", "/users/{id}/banking-details", "Get banking details for a specific user by their UUID", app.GetBankingDetailsByUserID, app.JWTAuthMiddleware)
	registerRoute("POST", "/banking-details", "Create banking details for a user", app.CreateBankingDetails, app.JWTAuthMiddleware)
	registerRoute("GET", "/users/{id}/annonces", "List all annonces for a specific user by their UUID", app.GetAnnoncesByUserID, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/notifications/{id}/read", "Mark a notification as read by its UUID", app.MarkNotificationAsRead, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/users/{id}/notifications/read", "Mark all users notification as read", app.MarkAllNotificationAsRead, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/users/{id}/planning/{pID}", "Delete a planning entry for a user", app.DeletePlanning, app.JWTAuthMiddleware)
	registerRoute("GET", "/forums", "List all forums", app.GetForums)
	registerRoute("GET", "/forums/{id}/posts", "List all posts in a specific forum by its UUID", app.GetForumPosts)
	registerRoute("POST", "/forums", "Create a new forum", app.CreateForum, app.JWTAuthMiddleware)
	registerRoute("POST", "/forums/{id}/posts", "Create a new post in a specific forum by its UUID", app.CreateForumPost, app.JWTAuthMiddleware)
	registerRoute("GET", "/users/{id}/planning", "Get the planning data for the next 7 days", app.GetPlanning, app.JWTAuthMiddleware)
	registerRoute("POST", "/users/{id}/planning", "Create a new planning entry for a user", app.CreatePlanning, app.JWTAuthMiddleware)
	registerRoute("GET", "/planning", "Get all planning entries in the system (admin only)", app.GetAllPlanning)
	registerRoute("PATCH", "/users/{id}", "Update a user's profile", app.UpdateUser, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/users/{id}", "Delete a user by UUID", app.DeleteUser, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/annonces/{id}/views", "Increment the view count for an annonce", app.IncrementAnnonceViewCount, app.JWTAuthMiddleware)
	registerRoute("GET", "/forums/{id}", "Get details of a specific forum by its UUID", app.GetForumByID)
	registerRoute("PATCH", "/forums/{id}/posts/{pID}", "Update a specific post in a forum by its UUID", app.UpdatePost, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/forums/{id}/posts/{pID}", "Delete a post from the forum", app.DeletePost, app.JWTAuthMiddleware)
	registerRoute("GET", "/conteneurs", "List all conteneurs in the system", app.GetConteneurs, app.JWTAuthMiddleware)
	registerRoute("POST", "/conteneurs", "Create a new conteneur", app.CreateConteneur, app.JWTAuthMiddleware, RoleMiddleware(3))
	registerRoute("GET", "/deposits", "List all deposits in the system", app.GetDeposits, app.JWTAuthMiddleware)
	registerRoute("GET", "/deposits/{id}", "Get details of a deposit request", app.GetDepositByID, app.JWTAuthMiddleware)
	registerRoute("POST", "/deposits", "Create a new deposit request", app.CreateDeposit, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/deposits/{id}", "Update deposit details for pending request", app.UpdateDeposit, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/deposits/{id}/status", "Update the status of a deposit request", app.UpdateDepositStatus, app.JWTAuthMiddleware)
	registerRoute("GET", "/deposits/{id}/files", "List all files attached to a deposit request", app.GetDepositFiles, app.JWTAuthMiddleware)
	registerRoute("POST", "/deposits/{id}/files", "Attach file records to a deposit request", app.CreateDepositFiles, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/deposits/{id}/files/{fileId}", "Delete a deposit file", app.DeleteDepositFile, app.JWTAuthMiddleware)
	registerRoute("GET", "/users/{id}/deposits", "List all deposits for a specific user by their UUID", app.GetDepositsByUserID, app.JWTAuthMiddleware)
	registerRoute("GET", "/conteneurs/{id}", "Get details of a specific conteneur by its UUID", app.GetConteneurByID, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/conteneurs/{id}", "Update details of a specific conteneur by its UUID", app.UpdateConteneur, app.JWTAuthMiddleware)
	registerRoute("GET", "/conteneurs/{id}/items", "List all accepted items inside a conteneur", app.GetConteneurItems, app.JWTAuthMiddleware)
	registerRoute("GET", "/users/{id}/deposits/{depid}", "Get a specific user's deposit", app.GetDepositByID, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/users/{id}/password", "Change a user's password", app.ChangePassword, app.JWTAuthMiddleware)
	registerRoute("GET", "/users/{id}/2fa-info", "Check if 2FA is enabled for a user", app.Get2FAInfo, app.JWTAuthMiddleware)
	registerRoute("POST", "/users/{id}/2fa/setup", "Generate a TOTP secret and QR provisioning URL", app.Setup2FA, app.JWTAuthMiddleware)
	registerRoute("POST", "/users/{id}/2fa/enable", "Verify OTP then enable 2FA for the user", app.Enable2FA, app.JWTAuthMiddleware)
	registerRoute("POST", "/users/{id}/2fa/disable", "Disable 2FA for the user", app.Disable2FA, app.JWTAuthMiddleware)
	registerRoute("POST", "/2fa/verify", "Complete MFA login: verify temp token + OTP code, return full JWT", app.Verify2FA)

	registerRoute("GET", "/users/{id}/discussions", "List all discussions for a specific user by their UUID", app.GetUserDiscussions, app.JWTAuthMiddleware)
	registerRoute("POST", "/users/{id}/discussions", "Create a new discussion for a specific user by their UUID", app.CreateDiscussion, app.JWTAuthMiddleware)

	registerRoute("GET", "/ws", "WebSocket connection for messaging", app.ServeWs, app.JWTAuthMiddleware)
	registerRoute("GET", "/global/messages", "Get messages for global chat", app.GetGlobalDiscussionMessages, app.JWTAuthMiddleware)
	registerRoute("GET", "/discussions/{id}/messages", "Get messages for a 1-to-1 discussion", app.GetDiscussionMessages, app.JWTAuthMiddleware)
	registerRoute("GET", "/groups/{id}/messages", "Get messages for a group discussion", app.GetGroupDiscussionMessages, app.JWTAuthMiddleware)
	registerRoute("POST", "/groups", "Create a group discussion", app.CreateGroupDiscussion, app.JWTAuthMiddleware)
	registerRoute("POST", "/groups/{id}/members", "Add user to group discussion", app.AddMemberToGroup, app.JWTAuthMiddleware)
	registerRoute("GET", "/groups", "Get groups for user", app.GetUserGroups, app.JWTAuthMiddleware)

	/* registerRoute("GET", "/discussions/{id}/messages", "List all messages in a specific discussion by its UUID", app.GetDiscussionMessages, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/discussions/{id}/messages/{mID}", "Edit a specific message in a discussion by their UUIDs", app.UpdateMessage, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/discussions/{id}/messages/{mID}", "Delete a specific message from a discussion by their UUIDs", app.DeleteMessage, app.JWTAuthMiddleware)
	registerRoute("POST", "/discussions/{id}/messages", "Create a new message in a specific discussion by its UUID", app.CreateMessage, app.JWTAuthMiddleware)
	registerRoute("GET", "/groups", "List all groups", app.GetGroups, app.JWTAuthMiddleware)
	registerRoute("POST", "/groups", "Create a new group", app.CreateGroup, app.JWTAuthMiddleware)
	registerRoute("GET", "/groups/{id}", "Get details of a specific group by its UUID", app.GetGroupByID, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/groups/{id}", "Update a specific group by its UUID", app.UpdateGroup, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/groups/{id}", "Delete a specific group by its UUID", app.DeleteGroup, app.JWTAuthMiddleware)
	registerRoute("POST", "/groups/{id}/messages", "Create a new message in a specific group by its UUID", app.CreateGroupMessage, app.JWTAuthMiddleware)
	registerRoute("GET", "/groups/{id}/messages", "List all messages in a specific group by its UUID", app.GetGroupMessages, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/groups/{id}/messages/{mID}", "Edit a specific message in a group by their UUIDs", app.UpdateGroupMessage, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/groups/{id}/messages/{mID}", "Delete a specific message from a group by their UUIDs", app.DeleteGroupMessage, app.JWTAuthMiddleware)
	registerRoute("GET", "/groups/{id}/members", "List all members of a specific group by its UUID", app.GetGroupMembers, app.JWTAuthMiddleware)
	registerRoute("POST", "/groups/{id}/members", "Add a member to a specific group by its UUID", app.AddGroupMember, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/groups/{id}/members/{uid}", "Remove a member from a specific group by their UUIDs", app.RemoveGroupMember, app.JWTAuthMiddleware)
	registerRoute("POST", "/discussions/{id}/messages/{mID}/reactions", "Add a reaction to a specific message by their UUIDs", app.AddReaction, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/discussions/{id}/messages/{mID}/reactions", "Remove a reaction from a specific message by their UUIDs", app.RemoveReaction, app.JWTAuthMiddleware)
	registerRoute("GET", "/discussions/{id}/messages/{mID}/reactions", "List all reactions for a specific message by their UUIDs", app.GetReactions, app.JWTAuthMiddleware)
	registerRoute("POST", "/groups/{id}/messages/{mID}/reactions", "Add a reaction to a specific group message by their UUIDs", app.AddGroupMessageReaction, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/groups/{id}/messages/{mID}/reactions", "Remove a reaction from a specific group message by their UUIDs", app.RemoveGroupMessageReaction, app.JWTAuthMiddleware)
	registerRoute("GET", "/groups/{id}/messages/{mID}/reactions", "List all reactions for a specific group message by their UUIDs", app.GetGroupMessageReactions, app.JWTAuthMiddleware)
	*/
	registerRoute("GET", "/tips", "Get the tips from the Database", app.GetTips, app.JWTAuthMiddleware)
	registerRoute("GET", "/tips/{id}", "Get a specific tip by its UUID", app.GetTipByID, app.JWTAuthMiddleware)
	registerRoute("GET", "/tips/{id}/comments", "Get comments for a tip", app.GetTipComments, app.JWTAuthMiddleware)
	registerRoute("POST", "/tips/{id}/comments", "Post a comment on a tip", app.CreateTipComment, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/tips/{id}/comments/{cID}", "Update a tip comment", app.UpdateTipComment, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/tips/{id}/comments/{cID}", "Delete a tip comment", app.DeleteTipComment, app.JWTAuthMiddleware)
	registerRoute("GET", "/tips/{id}/reactions", "Get reactions for a tip", app.GetTipReactions, app.JWTAuthMiddleware)
	registerRoute("POST", "/tips/{id}/reactions", "Set reaction for a tip", app.SetTipReaction, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/tips/{id}/reactions", "Remove reaction for a tip", app.RemoveTipReaction, app.JWTAuthMiddleware)
	registerRoute("POST", "/tips", "Create a tip in the Database", app.CreateTip, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/tips/{id}", "Update a tip in the database", app.UpdateTip, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/tips/{id}", "Delete a tip from the database", app.DeleteTip, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/users/{id}/planning/{pID}", "Update an existing planning entry for a user", app.UpdatePlanning, app.JWTAuthMiddleware)
	registerRoute("GET", "/users/{id}/role", "Get the role of a specific user by their UUID", app.GetRoleByUserID)
	registerRoute("POST", "/ban", "Create a ban record for a user", app.CreateBan, RoleMiddleware(3), app.JWTAuthMiddleware)
	registerRoute("GET", "/users/{id}/bans", "List all bans for a specific user by their UUID", app.GetBansByUserID, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/ban/{id}", "Delete a specific ban by its UUID", app.DeleteBan, RoleMiddleware(3), app.JWTAuthMiddleware)
	registerRoute("GET", "/bans/{id}", "Get details of a specific ban by its UUID", app.GetBanByID, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/forums/{id}", "Delete a forum by its UUID", app.DeleteForum, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/forums/{id}", "Update a forum's title or description by its UUID", app.UpdateForum, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/conteneurs/{id}", "Delete a conteneur by its UUID", app.DeleteConteneur, app.JWTAuthMiddleware)
	registerRoute("GET", "/orders/{id}", "Get details of a specific order by its UUID", app.GetOrderByID, app.JWTAuthMiddleware)
	registerRoute("GET", "/refund-requests", "List all refund requests", app.GetRefundRequests, app.JWTAuthMiddleware)
	registerRoute("GET", "/refund-requests/{id}", "Get a specific refund request by its UUID", app.GetRefundRequestByID, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/refund-requests/{id}/status", "Update a refund request status", app.UpdateRefundRequestStatus, app.JWTAuthMiddleware)
	registerRoute("POST", "/refund-requests", "Create a refund request for an order", app.CreateRefundRequest, app.JWTAuthMiddleware)
	registerRoute("GET", "/users/{id}/refund-requests", "List all refund requests for a specific user by their UUID", app.GetRefundRequestsByUserID, app.JWTAuthMiddleware)
	registerRoute("GET", "/orders/{id}/refund-requests", "List all refund requests for a specific order by its UUID", app.GetRefundRequestsByOrderID, app.JWTAuthMiddleware)
	registerRoute("POST", "/internal/subscription/activate", "Activate premium for a user after successful Stripe subscription", app.ActivateSubscription, InternalKeyMiddleware)
	registerRoute("POST", "/internal/subscription/revoke", "Revoke premium for a user on subscription cancellation or payment failure", app.RevokeSubscription, InternalKeyMiddleware)
	registerRoute("POST", "/internal/subscription/invoice", "Create or update an invoice record", app.CreateInvoice, InternalKeyMiddleware)
	registerRoute("POST", "/internal/promotion/complete", "Complete a paid promotion by creating a contract, invoice and ad campaign", app.CompletePromotion, InternalKeyMiddleware)
	registerRoute("GET", "/users/{id}/subscription", "Get subscription details for a user", app.GetSubscriptionByUserID, app.JWTAuthMiddleware)
	registerRoute("GET", "/users/{id}/profile-picture", "Get the profile picture URL for a user", app.GetProfilePicture, app.JWTAuthMiddleware)
	registerRoute("GET", "/projects", "List all published projects", app.GetProjects, app.JWTAuthMiddleware)
	registerRoute("GET", "/projects/{id}", "Get a specific project with steps, images and materials", app.GetProjectByID, app.JWTAuthMiddleware)
	registerRoute("POST", "/projects", "Create a new project", app.CreateProject, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/projects/{id}", "Update a project", app.UpdateProject, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/projects/{id}", "Delete a project", app.DeleteProject, app.JWTAuthMiddleware)
	registerRoute("GET", "/users/{id}/projects", "List all projects by a specific user", app.GetProjectsByUserID, app.JWTAuthMiddleware)
	registerRoute("GET", "/projects/{id}/steps", "List all steps for a project", app.GetProjectSteps, app.JWTAuthMiddleware)
	registerRoute("POST", "/projects/{id}/steps", "Add a step to a project", app.CreateProjectStep, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/projects/{id}/steps/{sID}", "Update a project step", app.UpdateProjectStep, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/projects/{id}/steps/{sID}", "Delete a project step", app.DeleteProjectStep, app.JWTAuthMiddleware)
	registerRoute("POST", "/projects/{id}/steps/{sID}/images", "Upload image(s) for a step", app.UploadStepImage, app.JWTAuthMiddleware)
	registerRoute("GET", "/projects/{id}/steps/{sID}/images", "List images for a step", app.GetStepImages, app.JWTAuthMiddleware)
	registerRoute("POST", "/projects/{id}/steps/{sID}/materials", "Add a material to a step", app.AddStepMaterial, app.JWTAuthMiddleware)
	registerRoute("GET", "/projects/{id}/steps/{sID}/materials", "List materials for a step", app.GetStepMaterials, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/projects/{id}/steps/{sID}/materials/{fID}", "Remove a material from a step", app.DeleteStepMaterial, app.JWTAuthMiddleware)
	registerRoute("GET", "/users/{id}/llm", "Get the LLM usage for today and quota per day", app.GetLLMUsage, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/users/{id}/llm", "Update the LLM usage and/or quota for a user", app.UpdateLLMUsage, app.JWTAuthMiddleware)
	registerRoute("GET", "/projects/{id}/likes", "Get like count and liked state", app.GetProjectLikes, app.JWTAuthMiddleware)
	registerRoute("POST", "/projects/{id}/likes", "Like a project", app.LikeProject, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/projects/{id}/likes", "Unlike a project", app.UnlikeProject, app.JWTAuthMiddleware)
	registerRoute("GET", "/projects/{id}/comments", "List comments on a project", app.GetProjectComments, app.JWTAuthMiddleware)
	registerRoute("POST", "/projects/{id}/comments", "Post a comment on a project", app.CreateProjectComment, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/projects/{id}/comments/{cID}", "Edit a comment", app.UpdateProjectComment, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/projects/{id}/comments/{cID}", "Delete a comment", app.DeleteProjectComment, app.JWTAuthMiddleware)
	registerRoute("GET", "/users/{id}/balance", "Get the current balance for a user", app.GetUserBalance, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/users/{id}/balance", "Update a user's balance", app.UpdateUserBalance, app.JWTAuthMiddleware)

	registerRoute("GET", "/products/services/{id}/affected-employees", "List all employees affected by a specific service/event by its UUID", app.GetAffectedEmployeesByServiceID, app.JWTAuthMiddleware)
	registerRoute("POST", "/products/services/{id}/affected-employees", "Add an affected employee to a specific service/event by its UUID", app.AddAffectedEmployee, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/products/services/{id}/affected-employees/{aeID}", "Remove an affected employee from a specific service/event by their UUIDs", app.RemoveAffectedEmployee, app.JWTAuthMiddleware)

	registerRoute("GET", "/typesPrestation", "List all service types", app.GetTypePrestations, app.JWTAuthMiddleware)
	registerRoute("POST", "/typesPrestation", "Create a new service type", app.CreateTypePrestation, app.JWTAuthMiddleware)
	registerRoute("GET", "/typesPrestation/{id}", "Get a specific service type by its UUID", app.GetTypePrestationByID, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/typesPrestation/{id}", "Update a service type by its UUID", app.UpdateTypePrestation, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/typesPrestation/{id}", "Delete a service type by its UUID", app.DeleteTypePrestation, app.JWTAuthMiddleware)

	registerRoute("GET", "/polls/{id}", "Get details of a specific poll by its UUID", app.GetPollByID, app.JWTAuthMiddleware)
	registerRoute("POST", "/polls", "Create a new poll", app.CreatePoll, app.JWTAuthMiddleware)
	registerRoute("GET", "/polls/{id}/options", "List all options for a specific poll by its UUID", app.GetPollOptions, app.JWTAuthMiddleware)
	registerRoute("POST", "/polls/{id}/options", "Create a new option for a specific poll by its UUID", app.CreatePollOption, app.JWTAuthMiddleware)
	registerRoute("GET", "/polls/{id}/votes", "List all votes for a specific poll by its UUID", app.GetPollVotes, app.JWTAuthMiddleware)
	registerRoute("POST", "/polls/{id}/vote", "Cast a vote for a specific poll by its UUID", app.CastPollVote, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/polls/{id}/user/{uid}/vote", "Remove a user's vote from a specific poll by its UUID", app.RemovePollVote, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/polls/{id}", "Update a poll's question or expiration by its UUID", app.UpdatePoll, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/polls/{id}/options/{oID}", "Delete a specific option from a poll by their UUIDs", app.DeletePollOptions, app.JWTAuthMiddleware)
	registerRoute("PATCH", "/polls/{id}/options/{oID}", "Update a specific option's text in a poll by their UUIDs", app.UpdatePollOption, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/polls/{id}", "Delete a poll by its UUID", app.DeletePoll, app.JWTAuthMiddleware)

	registerRoute("GET", "/categories", "List all categories", app.GetCategories)
	registerRoute("POST", "/categories", "Create a new category", app.CreateCategory, app.JWTAuthMiddleware, RoleMiddleware(3))
	registerRoute("PATCH", "/categories/{id}", "Update a category by its UUID", app.UpdateCategory, app.JWTAuthMiddleware, RoleMiddleware(3))
	registerRoute("DELETE", "/categories/{id}", "Delete a category by its UUID", app.DeleteCategory, app.JWTAuthMiddleware, RoleMiddleware(3))
	// TODO: Implement item listing by category if needed
	// registerRoute("GET", "/categories/{id}/items", "List all items in a specific category by its UUID", app.GetItemsByCategoryID, app.JWTAuthMiddleware)
	registerRoute("GET", "/categories/{id}", "Get details of a specific category by its UUID", app.GetCategoryByID, app.JWTAuthMiddleware)

	registerRoute("GET", "/friends", "Get user friends and pending requests", app.GetUserFriends, app.JWTAuthMiddleware)
	registerRoute("POST", "/friends", "Send friend request by username", app.AddFriend, app.JWTAuthMiddleware)
	registerRoute("PUT", "/friends/{id}/accept", "Accept a friend request", app.AcceptFriendRequest, app.JWTAuthMiddleware)
	registerRoute("DELETE", "/friends/{id}", "Remove a friend or decline request", app.RemoveFriendOrRequest, app.JWTAuthMiddleware)
	http.Handle("/swagger/", httpSwagger.Handler(httpSwagger.URL("/openapi.json")))
	http.HandleFunc("/swagger/doc.json", openapiSpecHandler)
	http.HandleFunc("/", notFoundHandler)

	fmt.Println("Listening at : " + host + ":" + port)
	root := http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		http.DefaultServeMux.ServeHTTP(w, r)
	})
	rootWithCors := http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		corsMiddleware(root)(w, r)
	})

	go app.WsHub.Run()

	if err := http.ListenAndServe(host+":"+port, rootWithCors); err != nil {
		fmt.Println("[FATAL] ListenAndServe:", err)
	}

}
