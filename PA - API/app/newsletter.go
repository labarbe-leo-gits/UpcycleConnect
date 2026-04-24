package app

// Hello future developper
// If you ever have to touch this file, please just don't,
// This is a warning, not to be a threat, but to care for your mental health,
// See ya,
// Hours wasted : 5h

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"
	"regexp"
	"strconv"
	"strings"
)

func GetNewsletters(w http.ResponseWriter, r *http.Request) {
	page := 1
	limit := 10

	if pageStr := r.URL.Query().Get("page"); pageStr != "" {
		if p, err := strconv.Atoi(pageStr); err == nil && p > 0 {
			page = p
		}
	}
	if limitStr := r.URL.Query().Get("limit"); limitStr != "" {
		if l, err := strconv.Atoi(limitStr); err == nil && l > 0 && l <= 100 {
			limit = l
		}
	}

	search := r.URL.Query().Get("search")
	var status *int
	if statusStr := r.URL.Query().Get("status"); statusStr != "" {
		if s, err := strconv.Atoi(statusStr); err == nil {
			status = &s
		}
	}

	newsletters, total, err := db.GetNewslettersFromDB(page, limit, search, status)
	if err != nil {
		fmt.Println("[ERROR] GetNewsletters:", err)
		sendError(w, "Unable to fetch newsletters", http.StatusInternalServerError)
		return
	}

	if newsletters == nil {
		newsletters = []models.Newsletter{}
	}

	totalPages := (total + limit - 1) / limit

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	response := map[string]interface{}{
		"success":     true,
		"newsletters": newsletters,
		"pagination": map[string]interface{}{
			"page":        page,
			"limit":       limit,
			"total_count": total,
			"total_pages": totalPages,
		},
	}
	json.NewEncoder(w).Encode(response)
}

func GetNewsletter(w http.ResponseWriter, r *http.Request) {

	id := r.URL.Query().Get("id")

	if id == "" {
		parts := strings.Split(strings.Trim(r.URL.Path, "/"), "/")
		if len(parts) > 1 {
			id = parts[len(parts)-1]
		}
	}

	if id == "" || id == "get" {
		sendError(w, "Newsletter ID is required", http.StatusBadRequest)
		return
	}

	newsletter, err := db.GetNewsletterByIDFromDB(id)
	if err != nil {
		fmt.Println("[ERROR] GetNewsletter:", err)
		if strings.Contains(err.Error(), "not found") {
			sendError(w, "Newsletter not found", http.StatusNotFound)
		} else {
			sendError(w, "Unable to fetch newsletter", http.StatusInternalServerError)
		}
		return
	}

	count, _ := db.GetNewsletterRecipientsCountFromDB(id)

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	response := map[string]interface{}{
		"success": true,
		"newsletter": map[string]interface{}{
			"id":              newsletter.ID,
			"title":           newsletter.Title,
			"content":         newsletter.Content,
			"status":          newsletter.Status,
			"recipient_count": count,
			"created_at":      newsletter.CreatedAt,
			"updated_at":      newsletter.UpdatedAt,
		},
	}
	json.NewEncoder(w).Encode(response)
}

func GetNewsletterSubscribers(w http.ResponseWriter, r *http.Request) {
	users, err := db.GetSubscribedUsersFromDB()
	if err != nil {
		fmt.Println("[ERROR] GetNewsletterSubscribers:", err)
		sendError(w, "Unable to fetch subscribers", http.StatusInternalServerError)
		return
	}

	subscribers := make([]map[string]string, 0, len(users))
	for _, u := range users {
		subscribers = append(subscribers, map[string]string{
			"id":         u.ID.String(),
			"first_name": u.FirstName,
			"email":      u.Email,
		})
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	response := map[string]interface{}{
		"success":     true,
		"subscribers": subscribers,
	}
	json.NewEncoder(w).Encode(response)
}

func CreateNewsletter(w http.ResponseWriter, r *http.Request) {
	var req map[string]string
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	title := strings.TrimSpace(req["title"])
	content := strings.TrimSpace(req["content"])

	if title == "" || content == "" {
		sendError(w, "Title and content are required", http.StatusBadRequest)
		return
	}

	id, err := db.CreateNewsletterInDB(title, content)
	if err != nil {
		fmt.Println("[ERROR] CreateNewsletter:", err)
		sendError(w, "Unable to create newsletter", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	response := map[string]interface{}{
		"success": true,
		"message": "Newsletter created",
		"id":      id,
	}
	json.NewEncoder(w).Encode(response)
}

func UpdateNewsletterStatus(w http.ResponseWriter, r *http.Request){
	pathParts := strings.Split(strings.Trim(r.URL.Path, "/"), "/")
	if len(pathParts) < 2{
		sendError(w, "Invalid URL format", http.StatusBadRequest)
		return
	}
	id := pathParts[1]

	var req struct {
		Status int `json:"status"`
	}

	if err := json.NewDecoder(r.Body).Decode(&req); err != nil{
		sendError(w, "Invalid JSON body", http.StatusBadRequest)
		return
	}

	if err := db.UpdateNewsletterStatusFromDB(id, req.Status); err != nil{
		sendError(w, "Failed to update status: " + err.Error(), http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]interface{}{
		"success": true,
		"new_status": req.Status,
	})
}

func UpdateNewsletter(w http.ResponseWriter, r *http.Request) {
	var req map[string]string
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	id := strings.TrimSpace(req["id"])
	title := strings.TrimSpace(req["title"])
	content := strings.TrimSpace(req["content"])

	if id == "" || title == "" || content == "" {
		sendError(w, "ID, title, and content are required", http.StatusBadRequest)
		return
	}

	// Check if newsletter exists and is not sent
	newsletter, err := db.GetNewsletterByIDFromDB(id)
	if err != nil {
		fmt.Println("[ERROR] UpdateNewsletter: Get:", err)
		sendError(w, "Newsletter not found", http.StatusNotFound)
		return
	}

	if newsletter.Status == 2 { // Sent status
		sendError(w, "Cannot modify a sent newsletter", http.StatusForbidden)
		return
	}

	if err := db.UpdateNewsletterInDB(id, title, content); err != nil {
		fmt.Println("[ERROR] UpdateNewsletter: Update:", err)
		sendError(w, "Unable to update newsletter", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	response := map[string]interface{}{
		"success": true,
		"message": "Newsletter updated",
	}
	json.NewEncoder(w).Encode(response)
}

// DeleteNewsletter godoc
// @Summary Delete newsletter
// @Description Delete a newsletter
// @Tags newsletter
// @Produce json
// @Param id query string true "Newsletter ID"
// @Success 200 {object} map[string]interface{} "Success"
// @Failure 400 {object} map[string]string "Bad request"
// @Failure 500 {object} map[string]string "Error"
// @Router /newsletter/delete [post]
func DeleteNewsletter(w http.ResponseWriter, r *http.Request) {
	var req map[string]string
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	id := strings.TrimSpace(req["id"])
	if id == "" {
		sendError(w, "Newsletter ID is required", http.StatusBadRequest)
		return
	}

	if err := db.DeleteNewsletterFromDB(id); err != nil {
		fmt.Println("[ERROR] DeleteNewsletter:", err)
		sendError(w, "Unable to delete newsletter", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	response := map[string]interface{}{
		"success": true,
		"message": "Newsletter deleted",
	}
	json.NewEncoder(w).Encode(response)
}

// SendNewsletter godoc
// @Summary Send newsletter to subscribers
// @Description Send newsletter to all active users subscribed to newsletter
// @Tags newsletter
// @Accept json
// @Produce json
// @Param request body map[string]string true "Newsletter data with id"
// @Success 200 {object} map[string]interface{} "Success"
// @Failure 400 {object} map[string]string "Bad request"
// @Failure 500 {object} map[string]string "Error"
// @Router /newsletter/send [post]
func SendNewsletter(w http.ResponseWriter, r *http.Request) {
	var req map[string]string
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	id := strings.TrimSpace(req["id"])
	fmt.Println("ID :", id)
	if id == "" {
		sendError(w, "Newsletter ID is required", http.StatusBadRequest)
		return
	}

	newsletter, err := db.GetNewsletterByIDFromDB(id)
	if err != nil {
		fmt.Println("[ERROR] SendNewsletter: Get:", err)
		sendError(w, "Newsletter not found", http.StatusNotFound)
		return
	}

	// Get all subscribed users
	users, err := db.GetSubscribedUsersFromDB()
	if err != nil {
		fmt.Println("[ERROR] SendNewsletter: Get users:", err)
		sendError(w, "Unable to fetch subscribers", http.StatusInternalServerError)
		return
	}

	// Convert markdown to HTML
	htmlContent := MarkdownToHTML(newsletter.Content)

	// Build email HTML
	emailHTML := BuildEmailHTML(newsletter.Title, htmlContent)

	sentCount := 0
	failedCount := 0

	// Send to each user
	for _, user := range users {
		if err := SendEmailNewsletter(user.Email, user.FirstName, newsletter.Title, emailHTML); err != nil {
			fmt.Printf("[ERROR] Failed to send to %s: %v\n", user.Email, err)
			failedCount++
		} else {
			sentCount++
			db.LogNewsletterRecipientFromDB(id, user.ID.String())
		}
	}

	// Update newsletter status to sent
	if sentCount > 0 {
		db.UpdateNewsletterStatusFromDB(id, 2)
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	response := map[string]interface{}{
		"success":      true,
		"sent_count":   sentCount,
		"failed_count": failedCount,
		"message":      fmt.Sprintf("Newsletter sent to %d subscribers", sentCount),
	}
	json.NewEncoder(w).Encode(response)
}

func MarkdownToHTML(md string) string {
	html := md

	html = regexp.MustCompile(`(?m)^### (.*?)$`).ReplaceAllString(html, "<h3>$1</h3>")
	html = regexp.MustCompile(`(?m)^## (.*?)$`).ReplaceAllString(html, "<h2>$1</h2>")
	html = regexp.MustCompile(`(?m)^# (.*?)$`).ReplaceAllString(html, "<h1>$1</h1>")

	html = regexp.MustCompile(`\*\*(.*?)\*\*`).ReplaceAllString(html, "<strong>$1</strong>")
	html = regexp.MustCompile(`__(.*?)__`).ReplaceAllString(html, "<strong>$1</strong>")

	html = regexp.MustCompile(`\*(.*?)\*`).ReplaceAllString(html, "<em>$1</em>")
	html = regexp.MustCompile(`_(.*?)_`).ReplaceAllString(html, "<em>$1</em>")

	html = regexp.MustCompile(`\[(.*?)\]\((.*?)\)`).ReplaceAllString(html, "<a href=\"$2\">$1</a>")

	html = regexp.MustCompile("```([\\s\\S]*?)```").ReplaceAllString(html, "<pre><code>$1</code></pre>")

	html = regexp.MustCompile("`([^`]+)`").ReplaceAllString(html, "<code>$1</code>")

	html = regexp.MustCompile(`\n\n+`).ReplaceAllString(html, "</p><p>")
	html = "<p>" + html + "</p>"

	html = regexp.MustCompile(`(?m)\n`).ReplaceAllString(html, "<br>")

	return html
}

func BuildEmailHTML(title, content string) string {
	return `<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f0f4f8; margin: 0; padding: 0; }
        .email-container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #065f46 0%, #10b981 100%); padding: 30px 20px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 24px; }
        .content { padding: 30px 20px; line-height: 1.6; color: #333; }
        .content p { margin: 15px 0; }
        .content a { color: #10b981; text-decoration: none; }
        .content a:hover { text-decoration: underline; }
        .footer { background-color: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #eee; }
        .footer p { margin: 5px 0; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>` + title + `</h1>
        </div>
        <div class="content">
            ` + content + `
        </div>
        <div class="footer">
            <p>This is a newsletter from UpcycleConnect</p>
            <p>You received this because you subscribed to our newsletter.</p>
        </div>
    </div>
</body>
</html>`
}

func SendEmailNewsletter(to, name, subject, htmlContent string) error {
	fmt.Printf("[INFO] Would send email to %s (%s): %s\n", to, name, subject)
	return nil
}
