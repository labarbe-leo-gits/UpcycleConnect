package app

import (
	"encoding/json"
	"net/http"

	"API/db"
	"API/models"

	"strings"

	"github.com/google/uuid"
)

func jsonError(w http.ResponseWriter, msg string, code int) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(code)
	json.NewEncoder(w).Encode(map[string]string{"error": msg})
}

func GetDiscussionMessages(w http.ResponseWriter, r *http.Request) {
	parts := strings.Split(r.URL.Path, "/")
	if len(parts) < 3 {
		jsonError(w, "Invalid path", http.StatusBadRequest)
		return
	}
	discussionID := parts[2]

	messages, err := db.GetMessagesByDiscussionID(discussionID)
	if err != nil {
		jsonError(w, "Failed to get messages: "+err.Error(), http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(messages)
}

func GetGroupDiscussionMessages(w http.ResponseWriter, r *http.Request) {

	parts := strings.Split(r.URL.Path, "/")
	if len(parts) < 3 {
		jsonError(w, "Invalid path", http.StatusBadRequest)
		return
	}
	groupID := parts[2]

	messages, err := db.GetMessagesByGroupDiscussionID(groupID)
	if err != nil {
		jsonError(w, "Failed to get messages: "+err.Error(), http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(messages)
}

func CreateGroupDiscussion(w http.ResponseWriter, r *http.Request) {
	userIDRaw := r.Context().Value("user_id")
	if userIDRaw == nil {
		jsonError(w, "Unauthorized", http.StatusUnauthorized)
		return
	}
	userID := userIDRaw.(string)

	var req struct {
		Title    string `json:"title"`
		ImageUrl string `json:"image_url"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		jsonError(w, "Invalid payload", http.StatusBadRequest)
		return
	}

	newID, err := db.CreateGroupDiscussion(req.Title, req.ImageUrl, userID)
	if err != nil {
		jsonError(w, "Failed to create group", http.StatusInternalServerError)
		return
	}

	err = db.AddUserToGroupDiscussion(newID.String(), userID)

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]string{"id": newID.String(), "title": req.Title})
}

func AddMemberToGroup(w http.ResponseWriter, r *http.Request) {

	parts := strings.Split(r.URL.Path, "/")
	if len(parts) < 3 {
		jsonError(w, "Invalid path", http.StatusBadRequest)
		return
	}
	groupID := parts[2]

	var req struct {
		Username string `json:"username"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		jsonError(w, "Invalid payload", http.StatusBadRequest)
		return
	}

	userToAdd, err := db.GetUserObjectByUsername(req.Username)
	if err != nil || userToAdd.ID == uuid.Nil {
		jsonError(w, "User not found", http.StatusNotFound)
		return
	}

	err = db.AddUserToGroupDiscussion(groupID, userToAdd.ID.String())
	if err != nil {
		jsonError(w, "Failed to add user", http.StatusInternalServerError)
		return
	}

	WsHub.Broadcast <- models.BroadcastMessage{
		Action:     "member_added",
		TargetType: "group",
		TargetID:   groupID,
		SenderID:   userToAdd.ID.String(),
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]string{"status": "success"})
}

func GetUserGroups(w http.ResponseWriter, r *http.Request) {
	userIDRaw := r.Context().Value("user_id")
	if userIDRaw == nil {
		jsonError(w, "Unauthorized", http.StatusUnauthorized)
		return
	}
	userID := userIDRaw.(string)

	groups, err := db.GetUserGroupDiscussions(userID)
	if err != nil {
		jsonError(w, "Failed to get groups: "+err.Error(), http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	if groups == nil {
		groups = []models.GroupDiscussion{}
	}
	json.NewEncoder(w).Encode(groups)
}

func GetGlobalDiscussionMessages(w http.ResponseWriter, r *http.Request) {
	messages, err := db.GetGlobalMessages()
	if err != nil {
		jsonError(w, "Failed to get global messages: "+err.Error(), http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(messages)
}
