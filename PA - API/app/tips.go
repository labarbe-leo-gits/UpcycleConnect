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

func GetTips(w http.ResponseWriter, r *http.Request) {

	tips, err := db.GetTipsFromDB()
	if err != nil {
		fmt.Println("[ERROR] GetTips:", err)
		sendError(w, "Unable to fetch tips", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(tips)
}

type TipPollResponse struct {
	ID   string `json:"id"`
	Text string `json:"text"`
}

type TipWithPollResponse struct {
	models.Tip
	Poll *struct {
		ID       string            `json:"id"`
		Question string            `json:"question"`
		Options  []TipPollResponse `json:"options"`
	} `json:"poll,omitempty"`
}

func GetTipByID(w http.ResponseWriter, r *http.Request) {
	idStr := strings.TrimPrefix(r.URL.Path, "/tips/")
	tip, err := db.GetTipByIDFromDB(idStr)
	if err != nil {
		fmt.Println("[ERROR] GetTipByID:", err)
		sendError(w, "Tip not found", http.StatusNotFound)
		return
	}

	response := TipWithPollResponse{Tip: tip}

	if tip.PollID != nil {
		poll, pErr := db.GetPollByIDFromDB(*tip.PollID)
		if pErr == nil {
			options, oErr := db.GetPollOptionsFromDB(*tip.PollID)
			if oErr == nil {
				pollObj := &struct {
					ID       string            `json:"id"`
					Question string            `json:"question"`
					Options  []TipPollResponse `json:"options"`
				}{
					ID:       poll.ID.String(),
					Question: poll.Question,
				}
				for _, option := range options {
					pollObj.Options = append(pollObj.Options, TipPollResponse{ID: option.ID.String(), Text: option.Text})
				}
				response.Poll = pollObj
			}
		}
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(response)
}

func ValidateTipDto(tipDto models.Tip, isUpdate bool) []string {

	var validationErrors []string

	if tipDto.Title == "" {
		validationErrors = append(validationErrors, "Title is required")
	}

	if tipDto.Description == "" {
		validationErrors = append(validationErrors, "Description is required")
	}

	if !isUpdate {
		if tipDto.CreatedBy == uuid.Nil {
			validationErrors = append(validationErrors, "CreatedBy is required and must be a valid UUID")
		}
	} else {
		if tipDto.UpdatedBy == uuid.Nil {
			validationErrors = append(validationErrors, "UpdatedBy is required and must be a valid UUID")
		}
	}

	return validationErrors
}

func CreateTip(w http.ResponseWriter, r *http.Request) {

	var tipDto models.Tip

	err := json.NewDecoder(r.Body).Decode(&tipDto)

	if err != nil {
		http.Error(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	validationErrors := ValidateTipDto(tipDto, false)

	if len(validationErrors) > 0 {
		fmt.Println("[ERROR] CreateTip validation:", validationErrors)
		sendError(w, fmt.Sprintf("Validation errors: %s", validationErrors), http.StatusBadRequest)
		return
	}

	newID, err := db.CreateTipInDB(tipDto)

	if err != nil {
		fmt.Println("[ERROR] CreateTip DB insert:", err)
		sendError(w, "Unable to create tip", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]string{"id": newID.String()})

}

func UpdateTip(w http.ResponseWriter, r *http.Request) {

	idStr := r.URL.Path[len("/tips/"):]
	tipID, err := uuid.Parse(idStr)

	if err != nil {
		fmt.Println("[ERROR] UpdateTip parse UUID:", err)
		sendError(w, "Invalid tip ID format", http.StatusBadRequest)
		return
	}

	var tipDto models.Tip

	err = json.NewDecoder(r.Body).Decode(&tipDto)

	if err != nil {
		fmt.Println("[ERROR] UpdateTip decode:", err)
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	validationErrors := ValidateTipDto(tipDto, true)

	if len(validationErrors) > 0 {
		fmt.Println("[ERROR] UpdateTip validation:", validationErrors)
		sendError(w, fmt.Sprintf("Validation errors: %s", validationErrors), http.StatusBadRequest)
		return
	}

	err = db.UpdateTipInDB(tipID, tipDto)

	if err != nil {
		fmt.Println("[ERROR] UpdateTip DB update:", err)
		sendError(w, "Unable to update tip", http.StatusInternalServerError)
		return
	}

	w.WriteHeader(http.StatusNoContent)
}

func DeleteTip(w http.ResponseWriter, r *http.Request) {

	idStr := r.URL.Path[len("/tips/"):]
	tipID, err := uuid.Parse(idStr)

	if err != nil {
		fmt.Println("[ERROR] DeleteTip parse UUID:", err)
		sendError(w, "Invalid tip ID format", http.StatusBadRequest)
		return
	}

	err = db.DeleteTipFromDB(tipID)

	if err != nil {
		fmt.Println("[ERROR] DeleteTip DB delete:", err)
		sendError(w, "Unable to delete tip", http.StatusInternalServerError)
		return
	}

	w.WriteHeader(http.StatusNoContent)

}

func GetTipComments(w http.ResponseWriter, r *http.Request) {
	idStr, ok := projectIDFromPath(r)
	if !ok {
		sendError(w, "Invalid tip ID", http.StatusBadRequest)
		return
	}

	comments, err := db.GetTipCommentsFromDB(idStr)
	if err != nil {
		fmt.Println("[ERROR] GetTipComments:", err)
		sendError(w, "Unable to fetch tip comments", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(comments)
}

func CreateTipComment(w http.ResponseWriter, r *http.Request) {
	idStr, ok := projectIDFromPath(r)
	if !ok {
		sendError(w, "Invalid tip ID", http.StatusBadRequest)
		return
	}

	tipID, _ := uuid.Parse(idStr)

	var input models.TipComment
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}
	if input.Content == "" {
		sendError(w, "content is required", http.StatusBadRequest)
		return
	}
	if input.UserID == uuid.Nil {
		sendError(w, "user_id is required", http.StatusBadRequest)
		return
	}

	input.TipID = tipID

	comment, err := db.CreateTipCommentInDB(input)
	if err != nil {
		fmt.Println("[ERROR] CreateTipComment:", err)
		sendError(w, "Unable to create tip comment", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(comment)
}

func UpdateTipComment(w http.ResponseWriter, r *http.Request) {
	cID := r.PathValue("cID")
	if _, err := uuid.Parse(cID); err != nil {
		sendError(w, "Invalid comment ID", http.StatusBadRequest)
		return
	}

	existing, err := db.GetTipCommentByIDFromDB(cID)
	if err != nil {
		fmt.Println("[ERROR] UpdateTipComment fetch:", err)
		sendError(w, "Unable to fetch tip comment", http.StatusInternalServerError)
		return
	}
	if existing == nil {
		sendError(w, "Comment not found", http.StatusNotFound)
		return
	}

	var input struct {
		Content string `json:"content"`
	}
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil || input.Content == "" {
		sendError(w, "content is required", http.StatusBadRequest)
		return
	}

	if err := db.UpdateTipCommentInDB(cID, input.Content); err != nil {
		fmt.Println("[ERROR] UpdateTipComment:", err)
		sendError(w, "Unable to update tip comment", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"message": "Comment updated successfully"})
}

func DeleteTipComment(w http.ResponseWriter, r *http.Request) {
	cID := r.PathValue("cID")
	if _, err := uuid.Parse(cID); err != nil {
		sendError(w, "Invalid comment ID", http.StatusBadRequest)
		return
	}

	if err := db.DeleteTipCommentFromDB(cID); err != nil {
		fmt.Println("[ERROR] DeleteTipComment:", err)
		sendError(w, "Unable to delete tip comment", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"message": "Comment deleted successfully"})
}

func GetTipReactions(w http.ResponseWriter, r *http.Request) {
	idStr := strings.TrimPrefix(r.URL.Path, "/tips/")
	idStr = strings.TrimSuffix(idStr, "/reactions")
	if _, err := uuid.Parse(idStr); err != nil {
		sendError(w, "Invalid tip ID", http.StatusBadRequest)
		return
	}

	likes, dislikes, err := db.GetTipReactionsFromDB(idStr)
	if err != nil {
		fmt.Println("[ERROR] GetTipReactions:", err)
		sendError(w, "Unable to fetch reactions", http.StatusInternalServerError)
		return
	}

	var current int = -1
	if uidRaw := r.Context().Value("user_id"); uidRaw != nil {
		if uidStr, ok := uidRaw.(string); ok && uidStr != "" {
			ur, err := db.GetTipReactionByUserFromDB(idStr, uidStr)
			if err == nil {
				current = ur
			}
		}
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]interface{}{"likes": likes, "dislikes": dislikes, "current_user_reaction": current})
}

func SetTipReaction(w http.ResponseWriter, r *http.Request) {
	idStr := strings.TrimPrefix(r.URL.Path, "/tips/")
	idStr = strings.TrimSuffix(idStr, "/reactions")
	if _, err := uuid.Parse(idStr); err != nil {
		sendError(w, "Invalid tip ID", http.StatusBadRequest)
		return
	}

	userIDRaw := r.Context().Value("user_id")
	uid, ok := userIDRaw.(string)
	if !ok || uid == "" {
		sendError(w, "Unauthorized", http.StatusUnauthorized)
		return
	}

	var payload struct {
		ReactionType int `json:"reaction_type"`
	}
	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		sendError(w, "Invalid payload", http.StatusBadRequest)
		return
	}
	if payload.ReactionType != 0 && payload.ReactionType != 1 {
		sendError(w, "reaction_type must be 0 (dislike) or 1 (like)", http.StatusBadRequest)
		return
	}

	if err := db.SetTipReactionInDB(idStr, uid, payload.ReactionType); err != nil {
		fmt.Println("[ERROR] SetTipReaction:", err)
		sendError(w, "Unable to set reaction", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"message": "Reaction recorded"})
}

func RemoveTipReaction(w http.ResponseWriter, r *http.Request) {
	idStr := strings.TrimPrefix(r.URL.Path, "/tips/")
	idStr = strings.TrimSuffix(idStr, "/reactions")
	if _, err := uuid.Parse(idStr); err != nil {
		sendError(w, "Invalid tip ID", http.StatusBadRequest)
		return
	}

	userIDRaw := r.Context().Value("user_id")
	uid, ok := userIDRaw.(string)
	if !ok || uid == "" {
		sendError(w, "Unauthorized", http.StatusUnauthorized)
		return
	}

	if err := db.RemoveTipReactionFromDB(idStr, uid); err != nil {
		fmt.Println("[ERROR] RemoveTipReaction:", err)
		sendError(w, "Unable to remove reaction", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"message": "Reaction removed"})
}
