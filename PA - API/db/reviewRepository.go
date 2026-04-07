package db

import (
	"API/models"
	"database/sql"
	"fmt"
	"strings"

	"github.com/google/uuid"
)

func GetUserReviewsFromDB(userID uuid.UUID) ([]models.Review, error) {
	rows, err := Db.Query(
		"SELECT id, reviewer_id, reviewed_user_id, rating, comment, created_at, updated_at FROM userReviews WHERE reviewed_user_id = ? ORDER BY created_at DESC",
		userID.String(),
	)
	if err != nil {
		return nil, fmt.Errorf("getUserReviews package db : %s", err.Error())
	}
	defer rows.Close()

	reviews := []models.Review{}
	for rows.Next() {
		var review models.Review
		var idStr, reviewerIDStr, reviewedUserIDStr string
		var comment sql.NullString
		var createdAt, updatedAt sql.NullString
		if err := rows.Scan(&idStr, &reviewerIDStr, &reviewedUserIDStr, &review.Rating, &comment, &createdAt, &updatedAt); err != nil {
			return nil, fmt.Errorf("getUserReviews package db scan : %s", err.Error())
		}
		review.ID, err = uuid.Parse(idStr)
		if err != nil {
			return nil, fmt.Errorf("getUserReviews package db uuid parse id : %s", err.Error())
		}
		review.ReviewerID, err = uuid.Parse(reviewerIDStr)
		if err != nil {
			return nil, fmt.Errorf("getUserReviews package db uuid parse reviewer_id : %s", err.Error())
		}
		review.ReviewedUserID, err = uuid.Parse(reviewedUserIDStr)
		if err != nil {
			return nil, fmt.Errorf("getUserReviews package db uuid parse reviewed_user_id : %s", err.Error())
		}
		review.Comment = comment.String
		if createdAt.Valid {
			review.CreatedAt = createdAt.String
		}
		if updatedAt.Valid {
			review.UpdatedAt = updatedAt.String
		}
		reviews = append(reviews, review)
	}

	if err := rows.Err(); err != nil {
		return nil, fmt.Errorf("getUserReviews package db rows : %s", err.Error())
	}

	return reviews, nil
}

func GetUserReviewByIDFromDB(reviewID uuid.UUID) (models.Review, error) {
	var review models.Review
	var idStr, reviewerIDStr, reviewedUserIDStr string
	var comment sql.NullString
	var createdAt, updatedAt sql.NullString

	err := Db.QueryRow(
		"SELECT id, reviewer_id, reviewed_user_id, rating, comment, created_at, updated_at FROM userReviews WHERE id = ?",
		reviewID.String(),
	).Scan(&idStr, &reviewerIDStr, &reviewedUserIDStr, &review.Rating, &comment, &createdAt, &updatedAt)
	if err != nil {
		if err == sql.ErrNoRows {
			return review, fmt.Errorf("review not found")
		}
		return review, fmt.Errorf("getUserReviewByID package db : %s", err.Error())
	}

	review.ID, err = uuid.Parse(idStr)
	if err != nil {
		return review, fmt.Errorf("getUserReviewByID package db uuid parse id : %s", err.Error())
	}
	review.ReviewerID, err = uuid.Parse(reviewerIDStr)
	if err != nil {
		return review, fmt.Errorf("getUserReviewByID package db uuid parse reviewer_id : %s", err.Error())
	}
	review.ReviewedUserID, err = uuid.Parse(reviewedUserIDStr)
	if err != nil {
		return review, fmt.Errorf("getUserReviewByID package db uuid parse reviewed_user_id : %s", err.Error())
	}
	review.Comment = comment.String
	if createdAt.Valid {
		review.CreatedAt = createdAt.String
	}
	if updatedAt.Valid {
		review.UpdatedAt = updatedAt.String
	}

	return review, nil
}

func CreateUserReviewInDB(review models.Review) error {
	comment := sql.NullString{String: strings.TrimSpace(review.Comment), Valid: strings.TrimSpace(review.Comment) != ""}

	_, err := Db.Exec(
		"INSERT INTO userReviews (id, reviewer_id, reviewed_user_id, rating, comment, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
		review.ID.String(), review.ReviewerID.String(), review.ReviewedUserID.String(), review.Rating, comment,
	)
	if err != nil {
		return fmt.Errorf("createUserReview package db : %s", err.Error())
	}

	return nil
}

func UpdateUserReviewInDB(review models.Review) error {
	result, err := Db.Exec(
		"UPDATE userReviews SET rating = ?, comment = ?, updated_at = NOW() WHERE id = ?",
		review.Rating, review.Comment, review.ID.String(),
	)
	if err != nil {
		return fmt.Errorf("updateUserReview package db : %s", err.Error())
	}

	affected, err := result.RowsAffected()
	if err != nil {
		return fmt.Errorf("updateUserReview package db rows affected : %s", err.Error())
	}
	if affected == 0 {
		return fmt.Errorf("review not found")
	}

	return nil
}

func DeleteUserReviewFromDB(reviewID uuid.UUID) error {
	result, err := Db.Exec("DELETE FROM userReviews WHERE id = ?", reviewID.String())
	if err != nil {
		return fmt.Errorf("deleteUserReview package db : %s", err.Error())
	}

	affected, err := result.RowsAffected()
	if err != nil {
		return fmt.Errorf("deleteUserReview package db rows affected : %s", err.Error())
	}
	if affected == 0 {
		return fmt.Errorf("review not found")
	}

	return nil
}
