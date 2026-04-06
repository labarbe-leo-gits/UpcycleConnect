package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"
	"net/mail"
	"strconv"
	"strings"

	"github.com/google/uuid"
)

func GetUserContacts(w http.ResponseWriter, r *http.Request) {
	page := 1
	limit := 20

	q := r.URL.Query()
	if q.Get("page") != "" {
		parsed, err := strconv.Atoi(q.Get("page"))
		if err == nil && parsed > 0 {
			page = parsed
		}
	}

	if q.Get("limit") != "" {
		parsed, err := strconv.Atoi(q.Get("limit"))
		if err == nil && parsed > 0 {
			limit = parsed
		}
	}

	if limit > 100 {
		limit = 100
	}

	offset := (page - 1) * limit
	contacts, total, err := db.GetContactsFromDB(limit, offset)
	if err != nil {
		fmt.Println("[ERROR] GetUserContacts DB query:", err)
		sendError(w, "Unable to fetch contacts", http.StatusInternalServerError)
		return
	}

	response := map[string]interface{}{
		"items": contacts,
		"total": total,
		"page":  page,
		"limit": limit,
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(response)
}

func AddContact(w http.ResponseWriter, r *http.Request) {
	var contact models.Contact
	if err := json.NewDecoder(r.Body).Decode(&contact); err != nil {
		fmt.Println("[ERROR] AddContact decode:", err)
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	contact.Name = strings.TrimSpace(contact.Name)
	contact.Email = strings.TrimSpace(contact.Email)
	contact.Message = strings.TrimSpace(contact.Message)

	if contact.Name == "" || contact.Email == "" || contact.Message == "" {
		sendError(w, "Name, email and message are required", http.StatusBadRequest)
		return
	}

	if _, err := mail.ParseAddress(contact.Email); err != nil {
		fmt.Println("[ERROR] AddContact invalid email:", err)
		sendError(w, "Please provide a valid email address", http.StatusBadRequest)
		return
	}

	if contact.ID == uuid.Nil {
		contact.ID = uuid.New()
	}

	if err := db.CreateContactInDB(contact); err != nil {
		fmt.Println("[ERROR] AddContact DB insert:", err)
		sendError(w, "Unable to create contact message", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]string{"message": "Contact request saved successfully."})
}

func RemoveContactOrRequest(w http.ResponseWriter, r *http.Request) {
	sendError(w, "Not implemented yet", http.StatusNotImplemented)
}

func GetContactResponses(w http.ResponseWriter, r *http.Request) {
	sendError(w, "Not implemented yet", http.StatusNotImplemented)
}

func RespondToContactRequest(w http.ResponseWriter, r *http.Request) {
	sendError(w, "Not implemented yet", http.StatusNotImplemented)
}

func DeleteContactResponse(w http.ResponseWriter, r *http.Request) {
	sendError(w, "Not implemented yet", http.StatusNotImplemented)
}
