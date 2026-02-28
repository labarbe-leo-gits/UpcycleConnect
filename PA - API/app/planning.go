package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"
	"strconv"
	"strings"
	"time"

	"github.com/google/uuid"
)

func GetPlanning(w http.ResponseWriter, r *http.Request) {
	idStr := strings.TrimPrefix(r.URL.Path, "/users/")
	idStr = strings.TrimSuffix(idStr, "/planning")

	query := r.URL.Query()
	pageParam := query.Get("page")
	limitParam := query.Get("limit")
	startParam := query.Get("start")
	endParam := query.Get("end")

	if pageParam == "" && limitParam == "" && startParam == "" && endParam == "" {
		planningData, err := db.GetPlanningFromDB(idStr)
		if err != nil {
			fmt.Println("[ERROR] GetPlanning:", err)
			sendError(w, "Unable to fetch planning data", http.StatusInternalServerError)
			return
		}

		w.Header().Set("Content-Type", "application/json")
		jsonResponse, err := json.Marshal(planningData)
		if err != nil {
			fmt.Println("[ERROR] GetPlanning marshal:", err)
			sendError(w, "Unable to process response", http.StatusInternalServerError)
			return
		}

		fmt.Fprintf(w, "%s", jsonResponse)
		return
	}

	page := 1
	limit := 20
	if pageParam != "" {
		if parsed, err := strconv.Atoi(pageParam); err == nil && parsed > 0 {
			page = parsed
		}
	}
	if limitParam != "" {
		if parsed, err := strconv.Atoi(limitParam); err == nil && parsed > 0 {
			limit = parsed
		}
	}
	if limit > 100 {
		limit = 100
	}

	offset := (page - 1) * limit

	total, err := db.CountPlanningFromDB(idStr, startParam, endParam)
	if err != nil {
		fmt.Println("[ERROR] GetPlanning count:", err)
		sendError(w, "Unable to fetch planning data", http.StatusInternalServerError)
		return
	}

	planningPage, err := db.GetPlanningFromDBWithRange(idStr, limit, offset, startParam, endParam)
	if err != nil {
		fmt.Println("[ERROR] GetPlanning page:", err)
		sendError(w, "Unable to fetch planning data", http.StatusInternalServerError)
		return
	}

	response := map[string]interface{}{
		"items": planningPage,
		"total": total,
		"page":  page,
		"limit": limit,
	}

	w.Header().Set("Content-Type", "application/json")
	jsonResponse, err := json.Marshal(response)
	if err != nil {
		fmt.Println("[ERROR] GetPlanning marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)
}

func CreatePlanning(w http.ResponseWriter, r *http.Request) {
	idStr := strings.TrimPrefix(r.URL.Path, "/users/")
	idStr = strings.TrimSuffix(idStr, "/planning")

	var planning models.Planning

	err := json.NewDecoder(r.Body).Decode(&planning)
	if err != nil {
		fmt.Println("[ERROR] CreatePlanning decode:", err)
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	if planning.ID == uuid.Nil {
		planning.ID = uuid.New()
	}

	userUUID, err := uuid.Parse(idStr)
	if err != nil {
		sendError(w, "Invalid user id", http.StatusBadRequest)
		return
	}
	planning.UserID = userUUID

	if validationErrors := ValidatePlanningDto(planning); len(validationErrors) > 0 {
		fmt.Println("[ERROR] CreatePlanning validation:", validationErrors)
		sendError(w, fmt.Sprintf("Validation errors: %v", validationErrors), http.StatusBadRequest)
		return
	}

	err = db.CreatePlanningInDB(planning)
	if err != nil {
		fmt.Println("[ERROR] CreatePlanning:", err)
		sendError(w, "Unable to create planning entry", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	jsonResponse, err := json.Marshal(map[string]string{"message": "Planning entry created successfully"})
	if err != nil {
		fmt.Println("[ERROR] CreatePlanning marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)

}

func ValidatePlanningDto(p models.Planning) []string {
	var errs []string
	if strings.TrimSpace(p.StartTime) == "" {
		errs = append(errs, "StartTime is required")
	}
	if strings.TrimSpace(p.EndTime) == "" {
		errs = append(errs, "EndTime is required")
	}
	if strings.TrimSpace(p.Date) == "" {
		errs = append(errs, "Date is required")
	}

	if p.StartTime != "" && p.EndTime != "" {
		layout := "2006-01-02 15:04:05"
		s, err1 := time.Parse(layout, p.StartTime)
		e, err2 := time.Parse(layout, p.EndTime)
		if err1 != nil || err2 != nil {
			errs = append(errs, "StartTime and EndTime must be in format YYYY-MM-DD HH:MM:SS")
		} else if !e.After(s) {
			errs = append(errs, "EndTime must be greater than StartTime")
		}
	}
	if p.Title != "" && len(p.Title) > 255 {
		errs = append(errs, "Title must be 255 characters or less")
	}

	if p.UserID == uuid.Nil {
		errs = append(errs, "UserID is required and must be a valid UUID")
	}

	return errs
}

func GetAllPlanning(w http.ResponseWriter, r *http.Request) {
	planningData, err := db.GetAllPlanningFromDB()
	if err != nil {
		fmt.Println("[ERROR] GetAllPlanning:", err)
		sendError(w, "Unable to fetch planning data", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	jsonResponse, err := json.Marshal(planningData)
	if err != nil {
		fmt.Println("[ERROR] GetAllPlanning marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)

}

func DeletePlanning(w http.ResponseWriter, r *http.Request) {

	// Format: /users/{id}/planning/{pID}
	pathParts := strings.Split(r.URL.Path, "/")
	if len(pathParts) != 5 {
		sendError(w, "Invalid URL format", http.StatusBadRequest)
		return
	}

	userIDStr := pathParts[2]
	planningIDStr := pathParts[4]

	userUUID, err := uuid.Parse(userIDStr)
	if err != nil {
		sendError(w, "Invalid user id", http.StatusBadRequest)
		return
	}

	planningUUID, err := uuid.Parse(planningIDStr)

	if err != nil {
		sendError(w, "Invalid planning id", http.StatusBadRequest)
		return
	}

	err = db.DeletePlanningFromDB(planningUUID.String(), userUUID)
	if err != nil {
		fmt.Println("[ERROR] DeletePlanning:", err)
		sendError(w, "Unable to delete planning entry", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	jsonResponse, err := json.Marshal(map[string]string{"message": "Planning entry deleted successfully"})

	if err != nil {
		fmt.Println("[ERROR] DeletePlanning marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)

}

func UpdatePlanning(w http.ResponseWriter, r *http.Request) {
	
	pathParts := strings.Split(r.URL.Path, "/")
	if len(pathParts) != 5 {
		sendError(w, "Invalid URL format", http.StatusBadRequest)
		return
	}

	userIDStr := pathParts[2]
	planningIDStr := pathParts[4]

	var planning models.Planning

	err := json.NewDecoder(r.Body).Decode(&planning)

	if err != nil {
		fmt.Println("[ERROR] UpdatePlanning decode:", err)
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	if planning.ID == uuid.Nil {
		planning.ID = uuid.New()
	}

	userUUID, err := uuid.Parse(userIDStr)
	if err != nil {
		sendError(w, "Invalid user id", http.StatusBadRequest)
		return
	}

	planning.UserID = userUUID

	planningUUID, err := uuid.Parse(planningIDStr)

	if err != nil {
		sendError(w, "Invalid planning id", http.StatusBadRequest)
		return
	}

	planning.ID = planningUUID

	oldPlanning, err := db.GetPlanningEntryByID(planningIDStr)
	if err != nil {
		fmt.Println("[ERROR] UpdatePlanning fetch existing:", err)
		sendError(w, "Unable to fetch existing planning entry", http.StatusInternalServerError)
		return
	}

	if strings.TrimSpace(planning.StartTime) == "" {
		planning.StartTime = oldPlanning.StartTime
	}

	if strings.TrimSpace(planning.EndTime) == "" {
		planning.EndTime = oldPlanning.EndTime
	}

	if strings.TrimSpace(planning.Date) == "" {
		planning.Date = oldPlanning.Date
	}

	if strings.TrimSpace(planning.Title) == "" {
		planning.Title = oldPlanning.Title
	}

	if validationErrors := ValidatePlanningDto(planning); len(validationErrors) > 0 {
		fmt.Println("[ERROR] UpdatePlanning validation:", validationErrors)
		sendError(w, fmt.Sprintf("Validation errors: %v", validationErrors), http.StatusBadRequest)
		return
	}

	err = db.UpdatePlanningInDB(planning)

	if err != nil {
		fmt.Println("[ERROR] UpdatePlanning:", err)
		sendError(w, "Unable to update planning entry", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	jsonResponse, err := json.Marshal(map[string]string{"message": "Planning entry updated successfully"})

	if err != nil {
		fmt.Println("[ERROR] UpdatePlanning marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)

}

