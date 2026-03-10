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

func GetPollByID(w http.ResponseWriter, r *http.Request) {

	idStr := strings.TrimPrefix(r.URL.Path, "/polls/")
	pollID, err := uuid.Parse(idStr)

	if err != nil {
		fmt.Println("[ERROR] GetPollByID:", err)
		sendError(w, "Invalid poll ID", http.StatusBadRequest)
		return
	}

	poll, err := db.GetPollByIDFromDB(pollID)
	if err != nil {
		fmt.Println("[ERROR] GetPollByID:", err)
		sendError(w, "Unable to fetch poll data", http.StatusInternalServerError)
		return
	}

	response := models.Poll{
		ID:        poll.ID,
		Question:  poll.Question,
		CreatedBy: poll.CreatedBy,
		CreatedAt: poll.CreatedAt,
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	jsonResponse, err := json.Marshal(response)

	if err != nil {
		fmt.Println("[ERROR] GetPollByID marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)
}

func ValidatePollDTO(dto models.Poll) error {

	var validationErrors []string

	if strings.TrimSpace(dto.Question) == "" {
		validationErrors = append(validationErrors, "Question is required")
	}

	if dto.CreatedBy == uuid.Nil {
		validationErrors = append(validationErrors, "CreatedBy is required")
	}

	if len(validationErrors) > 0 {
		return fmt.Errorf(strings.Join(validationErrors, "; "))
	}

	return nil

}

func CreatePoll(w http.ResponseWriter, r *http.Request) {

	var pollDTO models.Poll

	err := json.NewDecoder(r.Body).Decode(&pollDTO)
	if err != nil {
		fmt.Println("[ERROR] CreatePoll decode:", err)
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	validationErr := ValidatePollDTO(pollDTO)
	if validationErr != nil {
		fmt.Println("[ERROR] CreatePoll validation:", validationErr)
		sendError(w, fmt.Sprintf("Validation error: %s", validationErr.Error()), http.StatusBadRequest)
		return
	}

	err = db.CreatePollInDB(pollDTO)
	if err != nil {
		fmt.Println("[ERROR] CreatePoll:", err)
		sendError(w, "Unable to create poll", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	jsonResponse, err := json.Marshal(map[string]string{"message": "Poll created successfully"})

	if err != nil {
		fmt.Println("[ERROR] CreatePoll marshal:", err)
	}

	fmt.Fprintf(w, "%s", jsonResponse)

}

func GetPollOptions(w http.ResponseWriter, r *http.Request) {

	idStr := strings.TrimPrefix(r.URL.Path, "/polls/")
	idStr = strings.TrimSuffix(idStr, "/options")
	pollID, err := uuid.Parse(idStr)

	if err != nil {
		fmt.Println("[ERROR] GetPollOptions:", err)
		sendError(w, "Invalid poll ID", http.StatusBadRequest)
		return
	}

	options, err := db.GetPollOptionsFromDB(pollID)
	if err != nil {
		fmt.Println("[ERROR] GetPollOptions:", err)
		sendError(w, "Unable to fetch poll options", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	jsonResponse, err := json.Marshal(options)

	if err != nil {
		fmt.Println("[ERROR] GetPollOptions marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)
}

func ValidatePollOptionDTO(dto models.PollOption) error {

	var validationErrors []string

	if strings.TrimSpace(dto.Text) == "" {
		validationErrors = append(validationErrors, "Text is required")
	}

	if dto.PollID == uuid.Nil {
		validationErrors = append(validationErrors, "PollID is required")
	}

	if len(validationErrors) > 0 {
		return fmt.Errorf(strings.Join(validationErrors, "; "))
	}

	return nil

}

func CreatePollOption(w http.ResponseWriter, r *http.Request) {

	idStr := strings.TrimPrefix(r.URL.Path, "/polls/")
	idStr = strings.TrimSuffix(idStr, "/options")
	pollID, err := uuid.Parse(idStr)

	if err != nil {
		fmt.Println("[ERROR] CreatePollOption:", err)
		sendError(w, "Invalid poll ID", http.StatusBadRequest)
		return
	}

	var optionDTO models.PollOption

	err = json.NewDecoder(r.Body).Decode(&optionDTO)

	if err != nil {
		fmt.Println("[ERROR] CreatePollOption decode:", err)
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	if strings.TrimSpace(optionDTO.Text) == "" {
		fmt.Println("[ERROR] CreatePollOption validation: Text is required")
		sendError(w, "Text is required", http.StatusBadRequest)
		return
	}

	optionDTO.PollID = pollID

	var validationErrors = ValidatePollOptionDTO(optionDTO)
	if validationErrors != nil {
		fmt.Println("[ERROR] CreatePollOption validation:", validationErrors)
		sendError(w, fmt.Sprintf("Validation error: %s", validationErrors.Error()), http.StatusBadRequest)
		return
	}

	err = db.CreatePollOptionInDB(optionDTO)
	if err != nil {
		fmt.Println("[ERROR] CreatePollOption:", err)
		sendError(w, "Unable to create poll option", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	jsonResponse, err := json.Marshal(map[string]string{"message": "Poll option created successfully"})

	if err != nil {
		fmt.Println("[ERROR] CreatePollOption marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)

}

func GetPollVotes(w http.ResponseWriter, r *http.Request) {

	idStr := strings.TrimPrefix(r.URL.Path, "/polls/")
	idStr = strings.TrimSuffix(idStr, "/votes")
	pollID, err := uuid.Parse(idStr)

	if err != nil {
		fmt.Println("[ERROR] GetPollVotes:", err)
		sendError(w, "Invalid poll ID", http.StatusBadRequest)
		return
	}

	votes, err := db.GetPollVotesFromDB(pollID)
	if err != nil {
		fmt.Println("[ERROR] GetPollVotes:", err)
		sendError(w, "Unable to fetch poll votes", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	jsonResponse, err := json.Marshal(votes)

	if err != nil {
		fmt.Println("[ERROR] GetPollVotes marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)
}

func ValidatePollVoteDTO(dto models.PollVote) error {

	var validationErrors []string

	if dto.PollID == uuid.Nil {
		validationErrors = append(validationErrors, "PollID is required")
	}

	if dto.OptionID == uuid.Nil {
		validationErrors = append(validationErrors, "OptionID is required")
	}

	if dto.UserID == uuid.Nil {
		validationErrors = append(validationErrors, "UserID is required")
	}

	if len(validationErrors) > 0 {
		return fmt.Errorf(strings.Join(validationErrors, "; "))
	}

	return nil

}

func CastPollVote(w http.ResponseWriter, r *http.Request) {

	idStr := strings.TrimPrefix(r.URL.Path, "/polls/")
	idStr = strings.TrimSuffix(idStr, "/votes")
	pollID, err := uuid.Parse(idStr)

	if err != nil {
		fmt.Println("[ERROR] CastPollVote:", err)
		sendError(w, "Invalid poll ID", http.StatusBadRequest)
		return
	}

	var voteDTO models.PollVote

	err = json.NewDecoder(r.Body).Decode(&voteDTO)

	if err != nil {
		fmt.Println("[ERROR] CastPollVote decode:", err)
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	voteDTO.PollID = pollID

	var validationErrors = ValidatePollVoteDTO(voteDTO)

	if validationErrors != nil {
		fmt.Println("[ERROR] CastPollVote validation:", validationErrors)
		sendError(w, fmt.Sprintf("Validation error: %s", validationErrors.Error()), http.StatusBadRequest)
		return
	}

	err = db.CastPollVoteInDB(voteDTO)
	if err != nil {
		fmt.Println("[ERROR] CastPollVote:", err)
		sendError(w, "Unable to cast vote", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	jsonResponse, err := json.Marshal(map[string]string{"message": "Vote cast successfully"})

	if err != nil {
		fmt.Println("[ERROR] CastPollVote marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)

}

func RemovePollVote(w http.ResponseWriter, r *http.Request) {

	pathParts := strings.Split(r.URL.Path, "/")

	if len(pathParts) != 6 {
		sendError(w, "Invalid URL format", http.StatusBadRequest)
		return
	}

	pollID, err := uuid.Parse(pathParts[2])
	if err != nil {
		fmt.Println("[ERROR] RemovePollVote:", err)
		sendError(w, "Invalid poll ID", http.StatusBadRequest)
		return
	}

	userID, err := uuid.Parse(pathParts[4])
	if err != nil {
		fmt.Println("[ERROR] RemovePollVote:", err)
		sendError(w, "Invalid user ID", http.StatusBadRequest)
		return
	}

	err = db.RemovePollVoteFromDB(pollID, userID)

	if err != nil {
		fmt.Println("[ERROR] RemovePollVote:", err)
		sendError(w, "Unable to remove vote", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	jsonResponse, err := json.Marshal(map[string]string{"message": "Vote removed successfully"})

	if err != nil {
		fmt.Println("[ERROR] RemovePollVote marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)

}

func UpdatePoll(w http.ResponseWriter, r *http.Request) {

	idStr := strings.TrimPrefix(r.URL.Path, "/polls/")
	pollID, err := uuid.Parse(idStr)

	if err != nil {
		fmt.Println("[ERROR] UpdatePoll:", err)
		sendError(w, "Invalid poll ID", http.StatusBadRequest)
		return
	}

	var pollDTO models.Poll

	err = json.NewDecoder(r.Body).Decode(&pollDTO)

	if err != nil {
		fmt.Println("[ERROR] UpdatePoll decode:", err)
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	existingPoll, err := db.GetPollByIDFromDB(pollID)

	if err != nil {
		fmt.Println("[ERROR] UpdatePoll fetch existing:", err)
		sendError(w, "Unable to fetch existing poll data", http.StatusInternalServerError)
		return
	}

	if strings.TrimSpace(pollDTO.Question) == "" {
		pollDTO.Question = existingPoll.Question
	}

	if pollDTO.CreatedBy == uuid.Nil {
		pollDTO.CreatedBy = existingPoll.CreatedBy
	}

	pollDTO.ID = pollID

	var validationErrors = ValidatePollDTO(pollDTO)

	if validationErrors != nil {
		fmt.Println("[ERROR] UpdatePoll validation:", validationErrors)
		sendError(w, fmt.Sprintf("Validation error: %s", validationErrors.Error()), http.StatusBadRequest)
		return
	}

	err = db.UpdatePollInDB(pollDTO)

	if err != nil {
		fmt.Println("[ERROR] UpdatePoll:", err)
		sendError(w, "Unable to update poll", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	jsonResponse, err := json.Marshal(map[string]string{"message": "Poll updated successfully"})

	if err != nil {
		fmt.Println("[ERROR] UpdatePoll marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)

}

func DeletePollOptions(w http.ResponseWriter, r *http.Request) {

	pathParts := strings.Split(r.URL.Path, "/")

	if len(pathParts) != 6 {
		sendError(w, "Invalid URL format", http.StatusBadRequest)
		return
	}

	pollID, err := uuid.Parse(pathParts[2])
	if err != nil {
		fmt.Println("[ERROR] DeletePollOptions:", err)
		sendError(w, "Invalid poll ID", http.StatusBadRequest)
		return
	}

	optionID, err := uuid.Parse(pathParts[4])
	if err != nil {
		fmt.Println("[ERROR] DeletePollOptions:", err)
		sendError(w, "Invalid option ID", http.StatusBadRequest)
		return
	}

	err = db.DeletePollVotesByOptionIDFromDB(pollID, optionID)
	if err != nil {
		fmt.Println("[ERROR] DeletePollOptions (delete votes):", err)
		sendError(w, "Unable to delete poll votes for the option", http.StatusInternalServerError)
		return
	}

	err = db.DeletePollOptionFromDB(pollID, optionID)
	if err != nil {
		fmt.Println("[ERROR] DeletePollOptions:", err)
		sendError(w, "Unable to delete poll option", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	jsonResponse, err := json.Marshal(map[string]string{"message": "Poll option deleted successfully"})

	if err != nil {
		fmt.Println("[ERROR] DeletePollOptions marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)

}

func UpdatePollOption(w http.ResponseWriter, r *http.Request) {

	pathParts := strings.Split(r.URL.Path, "/")

	if len(pathParts) != 6 {
		sendError(w, "Invalid URL format", http.StatusBadRequest)
		return
	}

	pollID, err := uuid.Parse(pathParts[2])
	if err != nil {
		fmt.Println("[ERROR] UpdatePollOption:", err)
		sendError(w, "Invalid poll ID", http.StatusBadRequest)
		return
	}

	optionID, err := uuid.Parse(pathParts[4])
	if err != nil {
		fmt.Println("[ERROR] UpdatePollOption:", err)
		sendError(w, "Invalid option ID", http.StatusBadRequest)
		return
	}

	var optionDTO models.PollOption

	err = json.NewDecoder(r.Body).Decode(&optionDTO)

	if err != nil {
		fmt.Println("[ERROR] UpdatePollOption decode:", err)
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	existingOptions, err := db.GetPollOptionsFromDB(pollID)

	if err != nil {
		fmt.Println("[ERROR] UpdatePollOption fetch existing:", err)
		sendError(w, "Unable to fetch existing poll options", http.StatusInternalServerError)
		return
	}

	var existingOption *models.PollOption

	for _, opt := range existingOptions {
		if opt.ID == optionID {
			existingOption = &opt
			break
		}
	}

	if existingOption == nil {
		sendError(w, "Poll option not found", http.StatusNotFound)
		return
	}

	if strings.TrimSpace(optionDTO.Text) == "" {
		optionDTO.Text = existingOption.Text
	}

	optionDTO.PollID = pollID
	optionDTO.ID = optionID

	var validationErrors = ValidatePollOptionDTO(optionDTO)

	if validationErrors != nil {
		fmt.Println("[ERROR] UpdatePollOption validation:", validationErrors)
		sendError(w, fmt.Sprintf("Validation error: %s", validationErrors.Error()), http.StatusBadRequest)
		return
	}

	err = db.UpdatePollOptionInDB(optionDTO)

	if err != nil {
		fmt.Println("[ERROR] UpdatePollOption:", err)
		sendError(w, "Unable to update poll option", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	jsonResponse, err := json.Marshal(map[string]string{"message": "Poll option updated successfully"})

	if err != nil {
		fmt.Println("[ERROR] UpdatePollOption marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)

}

func DeletePoll(w http.ResponseWriter, r *http.Request) {

	idStr := strings.TrimPrefix(r.URL.Path, "/polls/")
	pollID, err := uuid.Parse(idStr)

	if err != nil {
		fmt.Println("[ERROR] DeletePoll:", err)
		sendError(w, "Invalid poll ID", http.StatusBadRequest)
		return
	}

	err = db.DeletePollVotesByPollIDFromDB(pollID)
	if err != nil {
		fmt.Println("[ERROR] DeletePoll (delete votes):", err)
		sendError(w, "Unable to delete poll votes", http.StatusInternalServerError)
		return
	}

	err = db.DeletePollOptionsByPollIDFromDB(pollID)
	if err != nil {
		fmt.Println("[ERROR] DeletePoll (delete options):", err)
		sendError(w, "Unable to delete poll options", http.StatusInternalServerError)
		return
	}

	err = db.DeletePollFromDB(pollID)
	if err != nil {
		fmt.Println("[ERROR] DeletePoll:", err)
		sendError(w, "Unable to delete poll", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	jsonResponse, err := json.Marshal(map[string]string{"message": "Poll deleted successfully"})

	if err != nil {
		fmt.Println("[ERROR] DeletePoll marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	jsonResponse, err = json.Marshal(map[string]string{"message": "Poll deleted successfully"})

	if err != nil {
		fmt.Println("[ERROR] DeletePoll marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)

}
