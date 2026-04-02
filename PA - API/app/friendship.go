package app

import (
	"API/db"
	"encoding/json"
	"net/http"
	"strings"
)

func AddFriend(w http.ResponseWriter, r *http.Request) {
	userIDRaw := r.Context().Value("user_id")
	if userIDRaw == nil {
		jsonError(w, "Unauthorized", http.StatusUnauthorized)
		return
	}
	userID := userIDRaw.(string)

	var req struct {
		Username string `json:"username"`
		Message  string `json:"message"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Username == "" {
		jsonError(w, "Username is required", http.StatusBadRequest)
		return
	}

	err := db.SendFriendRequest(userID, req.Username, req.Message)
	if err != nil {
		jsonError(w, err.Error(), http.StatusBadRequest)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]string{"message": "Friend request sent"})
}

func GetUserFriends(w http.ResponseWriter, r *http.Request) {
	userIDRaw := r.Context().Value("user_id")
	if userIDRaw == nil {
		jsonError(w, "Unauthorized", http.StatusUnauthorized)
		return
	}
	userID := userIDRaw.(string)

	friendships, err := db.GetUserFriends(userID)
	if err != nil {
		jsonError(w, "Failed to retrieve friendships", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(friendships)
}

func AcceptFriendRequest(w http.ResponseWriter, r *http.Request) {
	userIDRaw := r.Context().Value("user_id")
	if userIDRaw == nil {
		jsonError(w, "Unauthorized", http.StatusUnauthorized)
		return
	}
	userID := userIDRaw.(string)

	parts := strings.Split(r.URL.Path, "/")
	if len(parts) < 4 {
		jsonError(w, "Invalid path", http.StatusBadRequest)
		return
	}
	friendshipID := parts[2]

	err := db.AcceptFriendRequest(userID, friendshipID)
	if err != nil {
		jsonError(w, err.Error(), http.StatusBadRequest)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]string{"message": "Friend request accepted"})
}

func RemoveFriendOrRequest(w http.ResponseWriter, r *http.Request) {
	userIDRaw := r.Context().Value("user_id")
	if userIDRaw == nil {
		jsonError(w, "Unauthorized", http.StatusUnauthorized)
		return
	}
	userID := userIDRaw.(string)

	parts := strings.Split(r.URL.Path, "/")
	if len(parts) < 3 {
		jsonError(w, "Invalid path", http.StatusBadRequest)
		return
	}
	friendshipID := parts[2]

	err := db.RemoveFriendOrRequest(userID, friendshipID)
	if err != nil {
		jsonError(w, err.Error(), http.StatusBadRequest)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]string{"message": "Friend removed or request declined"})
}
