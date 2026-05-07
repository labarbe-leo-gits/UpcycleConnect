package app

import (
	"API/db"
	"encoding/json"
	"fmt"
	"net/http"
	"strings"

	"github.com/google/uuid"
)

type favoriteRequestBody struct {
	AnnonceID string `json:"annonce_id"`
}

func GetUserFavorites(w http.ResponseWriter, r *http.Request) {
	trimmed := strings.TrimPrefix(r.URL.Path, "/users/")
	trimmed = strings.TrimSuffix(trimmed, "/favorites")

	userID, err := uuid.Parse(trimmed)
	if err != nil {
		http.Error(w, "Invalid user ID", http.StatusBadRequest)
		return
	}

	authUserID, err := getAuthenticatedUserID(r)
	if err != nil {
		http.Error(w, "Unauthorized", http.StatusUnauthorized)
		return
	}

	if authUserID != userID {
		userID = authUserID
	}

	userType, err := db.GetUserRoleByIDFromDB(authUserID.String())
	if err != nil {
		if strings.Contains(err.Error(), "user not found") {
			http.Error(w, "Unauthorized user", http.StatusUnauthorized)
			return
		}
		fmt.Println("[GetUserFavorites] role lookup error:", err)
		http.Error(w, "Unable to verify user type", http.StatusInternalServerError)
		return
	}
	if userType != 1 && userType != 2 {
		http.Error(w, "Favorites are only available for this account type", http.StatusForbidden)
		return
	}

	favorites, err := db.GetFavoritesByUserID(userID)
	if err != nil {
		http.Error(w, "Failed to retrieve favorites", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(favorites)
}

func AddUserFavorite(w http.ResponseWriter, r *http.Request) {
	trimmed := strings.TrimPrefix(r.URL.Path, "/users/")
	trimmed = strings.TrimSuffix(trimmed, "/favorites")

	userID, err := uuid.Parse(trimmed)
	if err != nil {
		http.Error(w, "Invalid user ID", http.StatusBadRequest)
		return
	}

	authUserID, err := getAuthenticatedUserID(r)
	if err != nil {
		http.Error(w, "Unauthorized", http.StatusUnauthorized)
		return
	}

	if authUserID != userID {
		userID = authUserID
	}

	userType, err := db.GetUserRoleByIDFromDB(authUserID.String())
	if err != nil {
		if strings.Contains(err.Error(), "user not found") {
			http.Error(w, "Unauthorized user", http.StatusUnauthorized)
			return
		}
		fmt.Println("[AddUserFavorite] role lookup error:", err)
		http.Error(w, "Unable to verify user type", http.StatusInternalServerError)
		return
	}
	if userType != 1 && userType != 2 {
		http.Error(w, "Favorites are only available for this account type", http.StatusForbidden)
		return
	}

	var payload favoriteRequestBody
	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		http.Error(w, "Invalid request body", http.StatusBadRequest)
		return
	}
	if payload.AnnonceID == "" {
		http.Error(w, "annonce_id is required", http.StatusBadRequest)
		return
	}

	annonceID, err := uuid.Parse(payload.AnnonceID)
	if err != nil {
		http.Error(w, "Invalid annonce ID", http.StatusBadRequest)
		return
	}

	favorite, err := db.CreateFavorite(userID, annonceID)
	if err != nil {
		http.Error(w, "Unable to add favorite", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(favorite)
}

func RemoveUserFavorite(w http.ResponseWriter, r *http.Request) {
	trimmed := strings.TrimPrefix(r.URL.Path, "/users/")
	trimmed = strings.TrimSuffix(trimmed, "/favorites")

	parts := strings.Split(trimmed, "/")
	if len(parts) != 3 {
		http.Error(w, "Invalid path", http.StatusBadRequest)
		return
	}

	userID, err := uuid.Parse(parts[0])
	if err != nil {
		http.Error(w, "Invalid user ID", http.StatusBadRequest)
		return
	}

	favoriteID, err := uuid.Parse(parts[2])
	if err != nil {
		http.Error(w, "Invalid favorite ID", http.StatusBadRequest)
		return
	}

	authUserID, err := getAuthenticatedUserID(r)
	if err != nil {
		http.Error(w, "Unauthorized", http.StatusUnauthorized)
		return
	}

	if authUserID != userID {
		userID = authUserID
	}

	userType, err := db.GetUserRoleByIDFromDB(authUserID.String())
	if err != nil {
		if strings.Contains(err.Error(), "user not found") {
			http.Error(w, "Unauthorized user", http.StatusUnauthorized)
			return
		}
		fmt.Println("[RemoveUserFavorite] role lookup error:", err)
		http.Error(w, "Unable to verify user type", http.StatusInternalServerError)
		return
	}
	if userType != 1 && userType != 2 {
		http.Error(w, "Favorites are only available for this account type", http.StatusForbidden)
		return
	}

	deleted, err := db.DeleteFavoriteByID(userID, favoriteID)
	if err != nil {
		http.Error(w, "Unable to remove favorite", http.StatusInternalServerError)
		return
	}
	if !deleted {
		http.Error(w, "Favorite not found", http.StatusNotFound)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]bool{"success": true})
}

func getAuthenticatedUserID(r *http.Request) (uuid.UUID, error) {
	uidRaw := r.Context().Value("user_id")
	uidStr, ok := uidRaw.(string)
	if !ok || uidStr == "" {
		return uuid.Nil, fmt.Errorf("missing user identity")
	}

	userID, err := uuid.Parse(uidStr)
	if err != nil {
		return uuid.Nil, err
	}

	return userID, nil
}
