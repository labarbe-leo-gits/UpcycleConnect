package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"
	"strconv"
	"strings"

	"github.com/google/uuid"
)

func projectIDFromPath(r *http.Request) (string, bool) {
	id := r.PathValue("id")
	if id == "" {
		return "", false
	}
	if _, err := uuid.Parse(id); err != nil {
		return "", false
	}
	return id, true
}

func GetProjects(w http.ResponseWriter, r *http.Request) {
	q := r.URL.Query()
	page := 1
	limit := 20

	if p, err := strconv.Atoi(q.Get("page")); err == nil && p > 0 {
		page = p
	}
	if l, err := strconv.Atoi(q.Get("limit")); err == nil && l > 0 {
		limit = l
	}
	if limit > 100 {
		limit = 100
	}
	offset := (page - 1) * limit

	search := strings.TrimSpace(q.Get("search"))
	if search == "undefined" || search == "null" {
		search = ""
	}

	sort := strings.TrimSpace(q.Get("sort"))
	if sort == "" {
		sort = "newest"
	}

	authorID := strings.TrimSpace(q.Get("author_id"))

	var aiGenerated *int
	if v := strings.TrimSpace(q.Get("ai_generated")); v != "" {
		if iv, err := strconv.Atoi(v); err == nil {
			aiGenerated = &iv
		}
	}

	projects, total, err := db.GetProjectsFromDB(offset, limit, search, sort, authorID, aiGenerated)
	if err != nil {
		fmt.Println("[ERROR] GetProjects:", err)
		sendError(w, "Unable to fetch projects", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]interface{}{
		"items":  projects,
		"total":  total,
		"page":   page,
		"limit":  limit,
		"offset": offset,
	})
}

func GetProjectByID(w http.ResponseWriter, r *http.Request) {
	idStr, ok := projectIDFromPath(r)
	if !ok {
		sendError(w, "Invalid project ID", http.StatusBadRequest)
		return
	}

	project, err := db.GetProjectByIDFromDB(idStr)
	if err != nil {
		fmt.Println("[ERROR] GetProjectByID:", err)
		sendError(w, "Unable to fetch project", http.StatusInternalServerError)
		return
	}
	if project == nil {
		sendError(w, "Project not found", http.StatusNotFound)
		return
	}
	if err := db.IncrementProjectViewsInDB(idStr); err != nil {
		fmt.Println("[WARN] IncrementProjectViewsInDB:", err)
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(project)
}

func CountProjectsByUserIDFromDB(userID string) (int, error) {
	number, err := db.CountProjectsByUserIDFromDB(userID)
	if err != nil {
		return 0, err
	}

	return number, nil
}

func CreateProject(w http.ResponseWriter, r *http.Request) {
	var input models.Project
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	if input.Title == "" || input.Description == "" {
		sendError(w, "title and description are required", http.StatusBadRequest)
		return
	}
	if input.UserID == uuid.Nil {
		sendError(w, "user_id is required", http.StatusBadRequest)
		return
	}

	user, err := db.GetUserByIDFromDB(input.UserID)
	if err != nil {
		fmt.Println("[ERROR] CreateProject get user:", err)
		sendError(w, "Unable to fetch user", http.StatusInternalServerError)
		return
	}

	if user.UserType == 2 {

		count, err := db.CountProjectsByUserIDFromDB(input.UserID.String())
		if err != nil {
			fmt.Println("[ERROR] CreateProject count projects:", err)
			sendError(w, "Unable to count user's projects", http.StatusInternalServerError)
			return
		}

		if count >= user.UpdocQuota && user.UpdocQuota != 0 {
			sendError(w, fmt.Sprintf("Project quota reached. You can only have %d projects. Upgrade to the premium tier for an unlimited project amount !", user.UpdocQuota), http.StatusForbidden)
			return
		}

	}

	project, err := db.CreateProjectInDB(input)
	if err != nil {
		fmt.Println("[ERROR] CreateProject:", err)
		sendError(w, "Unable to create project", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(project)
}

func UpdateProject(w http.ResponseWriter, r *http.Request) {
	idStr, ok := projectIDFromPath(r)
	if !ok {
		sendError(w, "Invalid project ID", http.StatusBadRequest)
		return
	}

	existing, err := db.GetProjectByIDFromDB(idStr)
	if err != nil {
		fmt.Println("[ERROR] UpdateProject fetch:", err)
		sendError(w, "Unable to fetch project", http.StatusInternalServerError)
		return
	}
	if existing == nil {
		sendError(w, "Project not found", http.StatusNotFound)
		return
	}

	var input map[string]interface{}
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	allowed := map[string]bool{"title": true, "description": true, "status": true, "annonce_id": true, "ai_generated": true}
	fields := map[string]interface{}{}
	for k, v := range input {
		if allowed[k] {
			fields[k] = v
		}
	}

	if err := db.UpdateProjectInDB(idStr, fields); err != nil {
		fmt.Println("[ERROR] UpdateProject:", err)
		sendError(w, "Unable to update project", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"message": "Project updated successfully"})
}

func DeleteProject(w http.ResponseWriter, r *http.Request) {
	idStr, ok := projectIDFromPath(r)
	if !ok {
		sendError(w, "Invalid project ID", http.StatusBadRequest)
		return
	}

	if err := db.DeleteProjectFromDB(idStr); err != nil {
		fmt.Println("[ERROR] DeleteProject:", err)
		sendError(w, "Unable to delete project", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"message": "Project deleted successfully"})
}

func GetProjectsByUserID(w http.ResponseWriter, r *http.Request) {
	userID := strings.TrimSpace(r.PathValue("id"))
	if _, err := uuid.Parse(userID); err != nil {
		sendError(w, "Invalid user ID", http.StatusBadRequest)
		return
	}

	projects, err := db.GetProjectsByUserIDFromDB(userID)
	if err != nil {
		fmt.Println("[ERROR] GetProjectsByUserID:", err)
		sendError(w, "Unable to fetch projects", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(projects)
}

func GetProjectSteps(w http.ResponseWriter, r *http.Request) {
	idStr, ok := projectIDFromPath(r)
	if !ok {
		sendError(w, "Invalid project ID", http.StatusBadRequest)
		return
	}

	steps, err := db.GetProjectStepsFromDB(idStr)
	if err != nil {
		fmt.Println("[ERROR] GetProjectSteps:", err)
		sendError(w, "Unable to fetch steps", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(steps)
}

func CreateProjectStep(w http.ResponseWriter, r *http.Request) {
	idStr, ok := projectIDFromPath(r)
	if !ok {
		sendError(w, "Invalid project ID", http.StatusBadRequest)
		return
	}

	projectID, _ := uuid.Parse(idStr)

	var input models.ProjectStep
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}
	if input.Title == "" || input.Description == "" {
		sendError(w, "title and description are required", http.StatusBadRequest)
		return
	}

	input.ProjectID = projectID

	step, err := db.CreateProjectStepInDB(input)
	if err != nil {
		fmt.Println("[ERROR] CreateProjectStep:", err)
		sendError(w, "Unable to create step", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(step)
}

func UpdateProjectStep(w http.ResponseWriter, r *http.Request) {
	sID := r.PathValue("sID")
	if _, err := uuid.Parse(sID); err != nil {
		sendError(w, "Invalid step ID", http.StatusBadRequest)
		return
	}

	var input map[string]interface{}
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	allowed := map[string]bool{"title": true, "description": true, "step_order": true, "duration_minutes": true}
	fields := map[string]interface{}{}
	for k, v := range input {
		if allowed[k] {
			fields[k] = v
		}
	}

	if err := db.UpdateProjectStepInDB(sID, fields); err != nil {
		fmt.Println("[ERROR] UpdateProjectStep:", err)
		sendError(w, "Unable to update step", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"message": "Step updated successfully"})
}

func DeleteProjectStep(w http.ResponseWriter, r *http.Request) {
	sID := r.PathValue("sID")
	if _, err := uuid.Parse(sID); err != nil {
		sendError(w, "Invalid step ID", http.StatusBadRequest)
		return
	}

	if err := db.DeleteProjectStepFromDB(sID); err != nil {
		fmt.Println("[ERROR] DeleteProjectStep:", err)
		sendError(w, "Unable to delete step", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"message": "Step deleted successfully"})
}

func UploadStepImage(w http.ResponseWriter, r *http.Request) {
	sID := r.PathValue("sID")
	if _, err := uuid.Parse(sID); err != nil {
		sendError(w, "Invalid step ID", http.StatusBadRequest)
		return
	}

	var input models.Image
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}
	if input.FileName == "" {
		sendError(w, "file_name is required", http.StatusBadRequest)
		return
	}

	input.StepID = sID

	img, err := db.CreateStepImageInDB(input)
	if err != nil {
		fmt.Println("[ERROR] UploadStepImage:", err)
		sendError(w, "Unable to save image", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(img)
}

func GetStepImages(w http.ResponseWriter, r *http.Request) {
	sID := r.PathValue("sID")
	if _, err := uuid.Parse(sID); err != nil {
		sendError(w, "Invalid step ID", http.StatusBadRequest)
		return
	}

	images, err := db.GetStepImagesFromDB(sID)
	if err != nil {
		fmt.Println("[ERROR] GetStepImages:", err)
		sendError(w, "Unable to fetch images", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(images)
}

func GetStepMaterials(w http.ResponseWriter, r *http.Request) {
	sID := r.PathValue("sID")
	if _, err := uuid.Parse(sID); err != nil {
		sendError(w, "Invalid step ID", http.StatusBadRequest)
		return
	}

	materials, err := db.GetStepMaterialsFromDB(sID)
	if err != nil {
		fmt.Println("[ERROR] GetStepMaterials:", err)
		sendError(w, "Unable to fetch materials", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(materials)
}

func AddStepMaterial(w http.ResponseWriter, r *http.Request) {
	sID := r.PathValue("sID")
	if _, err := uuid.Parse(sID); err != nil {
		sendError(w, "Invalid step ID", http.StatusBadRequest)
		return
	}

	var input models.ProjectStepMaterial
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}
	if input.FacteurID == uuid.Nil {
		sendError(w, "facteur_id is required", http.StatusBadRequest)
		return
	}

	stepID, _ := uuid.Parse(sID)
	input.StepID = stepID

	if err := db.AddStepMaterialInDB(input); err != nil {
		fmt.Println("[ERROR] AddStepMaterial:", err)
		sendError(w, "Unable to add material", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]string{"message": "Material added successfully"})
}

func DeleteStepMaterial(w http.ResponseWriter, r *http.Request) {
	sID := r.PathValue("sID")
	fID := r.PathValue("fID")

	if _, err := uuid.Parse(sID); err != nil {
		sendError(w, "Invalid step ID", http.StatusBadRequest)
		return
	}
	if _, err := uuid.Parse(fID); err != nil {
		sendError(w, "Invalid facteur ID", http.StatusBadRequest)
		return
	}

	if err := db.DeleteStepMaterialFromDB(sID, fID); err != nil {
		fmt.Println("[ERROR] DeleteStepMaterial:", err)
		sendError(w, "Unable to remove material", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"message": "Material removed successfully"})
}

func GetProjectLikes(w http.ResponseWriter, r *http.Request) {
	idStr, ok := projectIDFromPath(r)
	if !ok {
		sendError(w, "Invalid project ID", http.StatusBadRequest)
		return
	}

	userIDRaw := r.Context().Value("user_id")
	userID, _ := userIDRaw.(string)

	count, err := db.GetProjectLikeCountFromDB(idStr)
	if err != nil {
		sendError(w, "Unable to get like count", http.StatusInternalServerError)
		return
	}

	liked := false
	if userID != "" {
		liked, _ = db.HasUserLikedProjectInDB(idStr, userID)
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]interface{}{"count": count, "liked": liked})
}

func LikeProject(w http.ResponseWriter, r *http.Request) {
	idStr, ok := projectIDFromPath(r)
	if !ok {
		sendError(w, "Invalid project ID", http.StatusBadRequest)
		return
	}

	userIDRaw := r.Context().Value("user_id")
	userID, ok := userIDRaw.(string)
	if !ok || userID == "" {
		sendError(w, "Unauthorized", http.StatusUnauthorized)
		return
	}

	if err := db.LikeProjectInDB(idStr, userID); err != nil {
		fmt.Println("[ERROR] LikeProject:", err)
		sendError(w, "Unable to like project", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"message": "Project liked"})
}

func UnlikeProject(w http.ResponseWriter, r *http.Request) {
	idStr, ok := projectIDFromPath(r)
	if !ok {
		sendError(w, "Invalid project ID", http.StatusBadRequest)
		return
	}

	userIDRaw := r.Context().Value("user_id")
	userID, ok := userIDRaw.(string)
	if !ok || userID == "" {
		sendError(w, "Unauthorized", http.StatusUnauthorized)
		return
	}

	if err := db.UnlikeProjectFromDB(idStr, userID); err != nil {
		fmt.Println("[ERROR] UnlikeProject:", err)
		sendError(w, "Unable to unlike project", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"message": "Project unliked"})
}

func GetProjectComments(w http.ResponseWriter, r *http.Request) {
	idStr, ok := projectIDFromPath(r)
	if !ok {
		sendError(w, "Invalid project ID", http.StatusBadRequest)
		return
	}

	comments, err := db.GetProjectCommentsFromDB(idStr)
	if err != nil {
		fmt.Println("[ERROR] GetProjectComments:", err)
		sendError(w, "Unable to fetch comments", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(comments)
}

func CreateProjectComment(w http.ResponseWriter, r *http.Request) {
	idStr, ok := projectIDFromPath(r)
	if !ok {
		sendError(w, "Invalid project ID", http.StatusBadRequest)
		return
	}

	projectID, _ := uuid.Parse(idStr)

	var input models.ProjectComment
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

	input.ProjectID = projectID

	comment, err := db.CreateProjectCommentInDB(input)
	if err != nil {
		fmt.Println("[ERROR] CreateProjectComment:", err)
		sendError(w, "Unable to create comment", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(comment)
}

func UpdateProjectComment(w http.ResponseWriter, r *http.Request) {
	cID := r.PathValue("cID")
	if _, err := uuid.Parse(cID); err != nil {
		sendError(w, "Invalid comment ID", http.StatusBadRequest)
		return
	}

	existing, err := db.GetProjectCommentByIDFromDB(cID)
	if err != nil {
		fmt.Println("[ERROR] UpdateProjectComment fetch:", err)
		sendError(w, "Unable to fetch comment", http.StatusInternalServerError)
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

	if err := db.UpdateProjectCommentInDB(cID, input.Content); err != nil {
		fmt.Println("[ERROR] UpdateProjectComment:", err)
		sendError(w, "Unable to update comment", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"message": "Comment updated successfully"})
}

func DeleteProjectComment(w http.ResponseWriter, r *http.Request) {
	cID := r.PathValue("cID")
	if _, err := uuid.Parse(cID); err != nil {
		sendError(w, "Invalid comment ID", http.StatusBadRequest)
		return
	}

	if err := db.DeleteProjectCommentFromDB(cID); err != nil {
		fmt.Println("[ERROR] DeleteProjectComment:", err)
		sendError(w, "Unable to delete comment", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"message": "Comment deleted successfully"})
}
