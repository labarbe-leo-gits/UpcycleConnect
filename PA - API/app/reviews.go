package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"
	"strings"

	"github.com/google/uuid"
)

type reviewPayload struct {
	Rating  int    `json:"rating"`
	Comment string `json:"comment,omitempty"`
}

type reviewUpdatePayload struct {
	Rating  *int    `json:"rating,omitempty"`
	Comment *string `json:"comment,omitempty"`
}

func extractReviewedUserID(path string) (uuid.UUID, error) {
	trimmed := strings.TrimPrefix(path, "/users/")
	trimmed = strings.TrimSuffix(trimmed, "/reviews")
	if trimmed == "" {
		return uuid.Nil, fmt.Errorf("reviewed user ID is required")
	}
	return uuid.Parse(trimmed)
}

func extractUserAndReviewIDs(path string) (uuid.UUID, uuid.UUID, error) {
	trimmed := strings.TrimPrefix(path, "/users/")
	parts := strings.SplitN(trimmed, "/reviews/", 2)
	if len(parts) != 2 || parts[0] == "" || parts[1] == "" {
		return uuid.Nil, uuid.Nil, fmt.Errorf("invalid review path")
	}

	reviewedUserID, err := uuid.Parse(parts[0])
	if err != nil {
		return uuid.Nil, uuid.Nil, fmt.Errorf("invalid reviewed user ID")
	}

	reviewID, err := uuid.Parse(parts[1])
	if err != nil {
		return uuid.Nil, uuid.Nil, fmt.Errorf("invalid review ID")
	}

	return reviewedUserID, reviewID, nil
}

func getAuthUserID(r *http.Request) (uuid.UUID, error) {
	raw := r.Context().Value("user_id")
	uidStr, ok := raw.(string)
	if !ok || uidStr == "" {
		return uuid.Nil, fmt.Errorf("missing user identity")
	}
	return uuid.Parse(uidStr)
}

func GetUserReviews(w http.ResponseWriter, r *http.Request) {
	reviewedUserID, err := extractReviewedUserID(r.URL.Path)
	if err != nil {
		fmt.Println("[ERROR] GetUserReviews path parse:", err)
		sendError(w, "Invalid user ID format", http.StatusBadRequest)
		return
	}

	reviews, err := db.GetUserReviewsFromDB(reviewedUserID)
	if err != nil {
		fmt.Println("[ERROR] GetUserReviews:", err)
		sendError(w, "Unable to fetch reviews", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(reviews)
}

func CreateUserReview(w http.ResponseWriter, r *http.Request) {
	reviewedUserID, err := extractReviewedUserID(r.URL.Path)
	if err != nil {
		fmt.Println("[ERROR] CreateUserReview path parse:", err)
		sendError(w, "Invalid user ID format", http.StatusBadRequest)
		return
	}

	var payload reviewPayload
	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		fmt.Println("[ERROR] CreateUserReview decode:", err)
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	if payload.Rating < 1 || payload.Rating > 5 {
		sendError(w, "Rating must be between 1 and 5", http.StatusBadRequest)
		return
	}

	reviewerID, err := getAuthUserID(r)
	if err != nil {
		fmt.Println("[ERROR] CreateUserReview auth:", err)
		sendError(w, "Missing user identity", http.StatusUnauthorized)
		return
	}

	if reviewerID == reviewedUserID {
		sendError(w, "You cannot review yourself", http.StatusBadRequest)
		return
	}

	review := models.Review{
		ID:             uuid.New(),
		ReviewerID:     reviewerID,
		ReviewedUserID: reviewedUserID,
		Rating:         payload.Rating,
		Comment:        strings.TrimSpace(payload.Comment),
	}

	if err := db.CreateUserReviewInDB(review); err != nil {
		fmt.Println("[ERROR] CreateUserReview DB insert:", err)
		sendError(w, "Unable to create review", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(review)
}

func UpdateUserReview(w http.ResponseWriter, r *http.Request) {
	reviewedUserID, reviewID, err := extractUserAndReviewIDs(r.URL.Path)
	if err != nil {
		fmt.Println("[ERROR] UpdateUserReview path parse:", err)
		sendError(w, "Invalid review path", http.StatusBadRequest)
		return
	}

	var payload reviewUpdatePayload
	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		fmt.Println("[ERROR] UpdateUserReview decode:", err)
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	if payload.Rating != nil && (*payload.Rating < 1 || *payload.Rating > 5) {
		sendError(w, "Rating must be between 1 and 5", http.StatusBadRequest)
		return
	}

	if payload.Rating == nil && payload.Comment == nil {
		sendError(w, "At least one field is required to update", http.StatusBadRequest)
		return
	}

	reviewerID, err := getAuthUserID(r)
	if err != nil {
		fmt.Println("[ERROR] UpdateUserReview auth:", err)
		sendError(w, "Missing user identity", http.StatusUnauthorized)
		return
	}

	existingReview, err := db.GetUserReviewByIDFromDB(reviewID)
	if err != nil {
		fmt.Println("[ERROR] UpdateUserReview fetch:", err)
		sendError(w, "Review not found", http.StatusNotFound)
		return
	}

	if existingReview.ReviewerID != reviewerID {
		sendError(w, "Insufficient privileges to update review", http.StatusForbidden)
		return
	}

	if existingReview.ReviewedUserID != reviewedUserID {
		sendError(w, "Review user mismatch", http.StatusBadRequest)
		return
	}

	if payload.Rating != nil {
		existingReview.Rating = *payload.Rating
	}
	if payload.Comment != nil {
		existingReview.Comment = strings.TrimSpace(*payload.Comment)
	}

	if err := db.UpdateUserReviewInDB(existingReview); err != nil {
		fmt.Println("[ERROR] UpdateUserReview DB update:", err)
		sendError(w, "Unable to update review", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(existingReview)
}

func DeleteUserReview(w http.ResponseWriter, r *http.Request) {
	_, reviewID, err := extractUserAndReviewIDs(r.URL.Path)
	if err != nil {
		fmt.Println("[ERROR] DeleteUserReview path parse:", err)
		sendError(w, "Invalid review path", http.StatusBadRequest)
		return
	}

	reviewerID, err := getAuthUserID(r)
	if err != nil {
		fmt.Println("[ERROR] DeleteUserReview auth:", err)
		sendError(w, "Missing user identity", http.StatusUnauthorized)
		return
	}

	existingReview, err := db.GetUserReviewByIDFromDB(reviewID)
	if err != nil {
		fmt.Println("[ERROR] DeleteUserReview fetch:", err)
		sendError(w, "Review not found", http.StatusNotFound)
		return
	}

	if existingReview.ReviewerID != reviewerID {
		sendError(w, "Insufficient privileges to delete review", http.StatusForbidden)
		return
	}

	if err := db.DeleteUserReviewFromDB(reviewID); err != nil {
		fmt.Println("[ERROR] DeleteUserReview DB delete:", err)
		sendError(w, "Unable to delete review", http.StatusInternalServerError)
		return
	}

	w.WriteHeader(http.StatusNoContent)
}
