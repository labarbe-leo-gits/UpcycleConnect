package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"

	"github.com/google/uuid"
)

func sendError(w http.ResponseWriter, message string, statusCode int) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(statusCode)
	json.NewEncoder(w).Encode(map[string]string{"error": message})
}

func GetForums(w http.ResponseWriter, r *http.Request) {
	q := r.URL.Query()
	pageStr := q.Get("page")
	limitStr := q.Get("limit")
	sort := q.Get("sort")

	if pageStr != "" || limitStr != "" {
		page := 1
		limit := 100
		if pageStr != "" {
			fmt.Sscanf(pageStr, "%d", &page)
			if page < 1 {
				page = 1
			}
		}
		if limitStr != "" {
			fmt.Sscanf(limitStr, "%d", &limit)
			if limit < 1 {
				limit = 1
			}
			if limit > 200 {
				limit = 200
			}
		}

		offset := (page - 1) * limit
		forums, total, err := db.GetForumsPageFromDB(offset, limit, sort)
		if err != nil {
			fmt.Println("[ERROR] GetForums (paged):", err)
			sendError(w, "Unable to fetch forums", http.StatusInternalServerError)
			return
		}

		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]interface{}{
			"items": forums,
			"total": total,
			"page":  page,
			"limit": limit,
		})
		return
	}

	forums, err := db.GetForumsFromDB()
	if err != nil {
		fmt.Println("[ERROR] GetForums:", err)
		sendError(w, "Unable to fetch forums", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	jsonResponse, err := json.Marshal(forums)
	if err != nil {
		fmt.Println("[ERROR] GetForums marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)
}

func GetForumPosts(w http.ResponseWriter, r *http.Request) {

	idStr := r.URL.Path[len("/forums/") : len(r.URL.Path)-len("/posts")]
	posts, err := db.GetForumPostsFromDB(idStr)
	if err != nil {
		fmt.Println("[ERROR] GetForumPosts:", err)
		sendError(w, "Unable to fetch forum posts", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	jsonResponse, err := json.Marshal(posts)

	if err != nil {
		fmt.Println("[ERROR] GetForumPosts marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)
}

func ValidateForumDTO(dto models.Forum) []string {

	var validationErrors []string

	if dto.Title == "" {
		validationErrors = append(validationErrors, "Title is required")
	}

	if dto.Description == "" {
		validationErrors = append(validationErrors, "Description is required")
	}

	return validationErrors

}

func CreateForum(w http.ResponseWriter, r *http.Request) {

	var forumDto models.Forum

	err := json.NewDecoder(r.Body).Decode(&forumDto)

	if err != nil {
		fmt.Println("[ERROR] CreateForum decode:", err)
		sendError(w, "Unable to process request body", http.StatusBadRequest)
		return
	}

	validationErrors := ValidateForumDTO(forumDto)

	if len(validationErrors) > 0 {
		sendError(w, fmt.Sprintf("Validation errors: %s", validationErrors), http.StatusBadRequest)
		return
	}

	err = db.CreateForumInDB(forumDto)

	if err != nil {
		fmt.Println("[ERROR] CreateForum DB:", err)
		sendError(w, "Unable to create forum", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]string{"message": "Forum created successfully"})

}

func ValidatePostDTO(dto models.ForumPost) []string {

	var validationErrors []string

	if dto.ForumID == uuid.Nil {
		validationErrors = append(validationErrors, "ForumID is required")
	}

	if dto.AuthorID == uuid.Nil {
		validationErrors = append(validationErrors, "AuthorID is required")
	}

	if dto.Content == "" {
		validationErrors = append(validationErrors, "Content is required")
	}

	if len(dto.Content) > 300 || len(dto.Content) < 5 {
		validationErrors = append(validationErrors, "The content must be between 5 and 300 characters")
	}

	return validationErrors

}

func CreateForumPost(w http.ResponseWriter, r *http.Request) {

	var postDto models.ForumPost

	err := json.NewDecoder(r.Body).Decode(&postDto)

	if err != nil {
		fmt.Println("[ERROR] CreateForumPost decode:", err)
		sendError(w, "Unable to process request body", http.StatusBadRequest)
		return
	}

	if postDto.ForumID == uuid.Nil {
		idStr := r.URL.Path[len("/forums/") : len(r.URL.Path)-len("/posts")]
		if idStr != "" {
			if fid, err2 := uuid.Parse(idStr); err2 == nil {
				postDto.ForumID = fid
			}
		}
	}

	if postDto.AuthorID == uuid.Nil {
		if uid, ok := r.Context().Value("user_id").(string); ok && uid != "" {
			if parsed, err2 := uuid.Parse(uid); err2 == nil {
				postDto.AuthorID = parsed
			}
		}
	}

	validationErrors := ValidatePostDTO(postDto)
	if len(validationErrors) > 0 {
		sendError(w, fmt.Sprintf("Validation errors: %s", validationErrors), http.StatusBadRequest)
		return
	}

	err = db.CreateForumPostInDB(postDto)
	if err != nil {
		fmt.Println("[ERROR] CreateForumPost DB:", err)
		sendError(w, "Unable to create forum post", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]string{"message": "Forum post created successfully"})

}

func GetForumByID(w http.ResponseWriter, r *http.Request) {

	idStr := r.URL.Path[len("/forums/"):]
	forum, err := db.GetForumByIDFromDB(idStr)

	if err != nil {
		fmt.Println("[ERROR] GetForumByID:", err)
		sendError(w, "Unable to fetch forum details", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	jsonResponse, err := json.Marshal(forum)
	if err != nil {
		fmt.Println("[ERROR] GetForumByID marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)
}

func UpdatePost(w http.ResponseWriter, r *http.Request) {

	idStr := r.URL.Path[len("/forums/") : len(r.URL.Path)-len("/posts/{pID}")]
	pID := r.URL.Path[len("/forums/{id}/posts/"):]

	if idStr == "" || pID == "" {
		sendError(w, "Forum ID and Post ID are required", http.StatusBadRequest)
		return
	}

	var postDto models.ForumPost

	err := json.NewDecoder(r.Body).Decode(&postDto)

	if err != nil {
		fmt.Println("[ERROR] UpdatePost decode:", err)
		sendError(w, "Unable to process request body", http.StatusBadRequest)
		return
	}

	validationErrors := ValidatePostDTO(postDto)
	if len(validationErrors) > 0 {
		sendError(w, fmt.Sprintf("Validation errors: %s", validationErrors), http.StatusBadRequest)
		return
	}

	contentString := postDto.Content

	err = db.UpdateForumPostInDB(pID, contentString)
	if err != nil {
		fmt.Println("[ERROR] UpdatePost DB:", err)
		sendError(w, "Unable to update forum post", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]string{"message": "Forum post updated successfully"})

}

func DeletePost(w http.ResponseWriter, r *http.Request) {

	forumID := r.URL.Path[len("/forums/") : len(r.URL.Path)-len("/posts/{pID}")]
	pID := r.URL.Path[len("/forums/{id}/posts/"):]

	if forumID == "" || pID == "" {
		sendError(w, "Forum ID and Post ID are required", http.StatusBadRequest)
		return
	}

	err := db.DeleteForumPostFromDB(pID)
	if err != nil {
		fmt.Println("[ERROR] DeletePost DB:", err)
		sendError(w, "Unable to delete forum post", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusNoContent)
}

func DeleteForum(w http.ResponseWriter, r *http.Request) {

	idStr := r.URL.Path[len("/forums/"):]
	err := db.DeleteForumFromDB(idStr)

	if err != nil {
		fmt.Println("[ERROR] DeleteForum DB:", err)
		sendError(w, "Unable to delete forum", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusNoContent)
}

func UpdateForum(w http.ResponseWriter, r *http.Request) {

	idStr := r.URL.Path[len("/forums/"):]
	var forumDto models.Forum

	err := json.NewDecoder(r.Body).Decode(&forumDto)

	oldForum, err2 := db.GetForumByIDFromDB(idStr)
	if err2 != nil {
		fmt.Println("[ERROR] UpdateForum GetForumByID:", err2)
		sendError(w, "Unable to fetch existing forum details", http.StatusInternalServerError)
		return
	}

	if err != nil {
		fmt.Println("[ERROR] UpdateForum decode:", err)
		sendError(w, "Unable to process request body", http.StatusBadRequest)
		return
	}

	if forumDto.Title == "" {
		forumDto.Title = oldForum.Title
	}

	if forumDto.Description == "" {
		forumDto.Description = oldForum.Description
	}

	validationErrors := ValidateForumDTO(forumDto)

	if len(validationErrors) > 0 {
		sendError(w, fmt.Sprintf("Validation errors: %s", validationErrors), http.StatusBadRequest)
		return
	}

	err = db.UpdateForumInDB(idStr, forumDto)

	if err != nil {
		fmt.Println("[ERROR] UpdateForum DB:", err)
		sendError(w, "Unable to update forum", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]string{"message": "Forum updated successfully"})

}
