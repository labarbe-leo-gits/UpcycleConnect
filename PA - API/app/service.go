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

func normalizeFormationDurations(service *models.Service) {
	if service.DurationDays <= 0 {
		service.DurationDays = 1
	}
	if service.EstimatedTimeMinutes <= 0 {
		service.EstimatedTimeMinutes = 60
	}
}

func createFormationPlanningEntries(service models.Service) error {
	creator, err := db.GetUserByIDFromDB(service.CreatedBy)
	if err != nil {
		return fmt.Errorf("get creator: %w", err)
	}

	if creator.UserType != 4 || service.Status != "published" || len(service.Schedules) == 0 {
		return nil
	}

	serviceDate, err := time.ParseInLocation("2006-01-02", service.ServiceDate, time.Local)
	if err != nil {
		return fmt.Errorf("parse service date: %w", err)
	}

	description := strings.TrimSpace(service.Description)
	if description == "" {
		description = fmt.Sprintf("Formation scheduled for %d day(s).", service.DurationDays)
	} else {
		description = fmt.Sprintf("%s\n\nFormation scheduled for %d day(s). Estimated time per slot: %d minute(s).", description, service.DurationDays, service.EstimatedTimeMinutes)
	}

	for dayOffset := 0; dayOffset < service.DurationDays; dayOffset++ {
		currentDay := serviceDate.AddDate(0, 0, dayOffset)
		for _, sched := range service.Schedules {
			startTime := time.Date(currentDay.Year(), currentDay.Month(), currentDay.Day(), sched.Hour, 0, 0, 0, time.Local)
			endTime := startTime.Add(time.Duration(service.EstimatedTimeMinutes) * time.Minute)

			planning := models.Planning{
				ID:          uuid.New(),
				Title:       fmt.Sprintf("Formation: %s", service.Name),
				Description: description,
				StartTime:   startTime.Format("2006-01-02 15:04:05"),
				EndTime:     endTime.Format("2006-01-02 15:04:05"),
				Date:        currentDay.Format("2006-01-02"),
				UserID:      service.CreatedBy,
			}

			if validationErrors := ValidatePlanningDto(planning); len(validationErrors) > 0 {
				return fmt.Errorf("invalid planning entry: %s", strings.Join(validationErrors, "; "))
			}

			if err := db.CreatePlanningInDB(planning); err != nil {
				return err
			}
		}
	}

	return nil
}

func GetServices(w http.ResponseWriter, r *http.Request) {
	query := r.URL.Query()
	pageParam := query.Get("page")
	limitParam := query.Get("limit")
	availableParam := query.Get("available")
	searchParam := query.Get("search")
	typeParam := query.Get("type")
	employeeParam := query.Get("employee_id")
	availableOnly := availableParam == "1" || availableParam == "true"

	if pageParam == "" && limitParam == "" {
		services, err := db.GetServicesFromDB(searchParam, typeParam, availableOnly, employeeParam)

		if err != nil {
			fmt.Println("[ERROR] GetServices:", err)
			sendError(w, "Unable to fetch services", http.StatusInternalServerError)
			return
		}

		w.Header().Set("Content-Type", "application/json")
		jsonResponse, err := json.Marshal(services)

		if err != nil {
			fmt.Println("[ERROR] GetServices marshal:", err)
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

	total, err := db.CountServicesFromDB(availableOnly, searchParam, typeParam, employeeParam)
	if err != nil {
		fmt.Println("[ERROR] GetServices count:", err)
		sendError(w, "Unable to fetch services", http.StatusInternalServerError)
		return
	}

	services, err := db.GetServicesPageFromDB(limit, offset, availableOnly, searchParam, typeParam, employeeParam)
	if err != nil {
		fmt.Println("[ERROR] GetServices page:", err)
		sendError(w, "Unable to fetch services", http.StatusInternalServerError)
		return
	}

	response := map[string]interface{}{
		"items": services,
		"total": total,
		"page":  page,
		"limit": limit,
	}

	w.Header().Set("Content-Type", "application/json")
	jsonResponse, err := json.Marshal(response)

	if err != nil {
		fmt.Println("[ERROR] GetServices marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)
}

func ValidateServiceDto(serviceDto models.Service) []string {

	var validationErrors []string
	if serviceDto.Name == "" {
		validationErrors = append(validationErrors, "Name is required")
	}

	if serviceDto.Type == uuid.Nil {
		validationErrors = append(validationErrors, "Type is required and must be a valid UUID")
	} else {

		tp, err := db.GetTypePrestationByIDFromDB(serviceDto.Type)
		if err != nil || tp == nil {
			validationErrors = append(validationErrors, "Type does not exist")
		}
	}

	if serviceDto.ServiceDate != "" {
		if len(serviceDto.ServiceDate) != 10 || serviceDto.ServiceDate[4] != '-' || serviceDto.ServiceDate[7] != '-' {
			validationErrors = append(validationErrors, "ServiceDate must be in YYYY-MM-DD format")
		}

		if serviceDto.ServiceDate < fmt.Sprintf("%d-%02d-%02d", 2024, 6, 1) {
			validationErrors = append(validationErrors, "ServiceDate cannot be in the past")
		}
	}

	if serviceDto.CreatedBy == uuid.Nil {
		validationErrors = append(validationErrors, "CreatedBy is required and must be a valid UUID")
	}

	if serviceDto.MaximumParticipants != nil && *serviceDto.MaximumParticipants < 0 {
		validationErrors = append(validationErrors, "MaximumParticipants must be 0 or greater")
	}

	if serviceDto.DurationDays <= 0 {
		validationErrors = append(validationErrors, "DurationDays must be greater than 0")
	}

	if serviceDto.EstimatedTimeMinutes <= 0 {
		validationErrors = append(validationErrors, "EstimatedTimeMinutes must be greater than 0")
	}

	if serviceDto.MeetingType != "" && serviceDto.MeetingType != "none" && serviceDto.MeetingType != "zoom" && serviceDto.MeetingType != "other" {
		validationErrors = append(validationErrors, "MeetingType is invalid")
	}

	if serviceDto.MeetingType == "other" {
		if serviceDto.OnlineMeetingLink == "" {
			validationErrors = append(validationErrors, "Meeting URL is required when type is other")
		}
	} else if serviceDto.MeetingType == "none" || serviceDto.MeetingType == "" {

		serviceDto.OnlineMeetingLink = ""
	}

	if len(serviceDto.Schedules) == 0 {
		validationErrors = append(validationErrors, "At least one schedule slot is required")
	}

	for idx, schedule := range serviceDto.Schedules {
		if schedule.Hour < 0 || schedule.Hour > 23 {
			validationErrors = append(validationErrors, fmt.Sprintf("Schedule[%d].hour must be between 0 and 23", idx))
		}
	}

	return validationErrors
}

func CreateService(w http.ResponseWriter, r *http.Request) {

	var serviceDto models.Service
	err := json.NewDecoder(r.Body).Decode(&serviceDto)

	if err != nil {
		fmt.Println("[ERROR] CreateService decode:", err)
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	normalizeFormationDurations(&serviceDto)

	validationErrors := ValidateServiceDto(serviceDto)

	if len(validationErrors) > 0 {
		fmt.Println("[ERROR] CreateService validation:", validationErrors)
		sendError(w, fmt.Sprintf("Validation errors: %s", validationErrors), http.StatusBadRequest)
		return
	}

	if serviceDto.MeetingType == "zoom" && serviceDto.OnlineMeetingLink == "" {
		if url, err := createZoomMeeting(serviceDto.Name, serviceDto.ServiceDate); err != nil {
			fmt.Println("[WARN] could not create zoom meeting:", err)
		} else {
			serviceDto.OnlineMeetingLink = url
		}
	}

	newID, err := db.CreateServiceInDB(serviceDto)

	if err != nil {
		fmt.Println("[ERROR] CreateService DB insert:", err)
		sendError(w, "Unable to create service", http.StatusInternalServerError)
		return
	}

	if len(serviceDto.Schedules) > 0 {
		if err := db.SaveServiceSchedulesInDB(newID, serviceDto.Schedules); err != nil {
			fmt.Println("[ERROR] CreateService save schedules:", err)
			sendError(w, "Unable to save service schedules", http.StatusInternalServerError)
			return
		}
	}

	if serviceDto.Status == "published" {
		serviceDto.ID = newID
		if err := createFormationPlanningEntries(serviceDto); err != nil {
			fmt.Println("[WARN] CreateService planning sync:", err)
		}
	}

	serviceDto.ID = newID

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(serviceDto)

}

func GetServiceByID(w http.ResponseWriter, r *http.Request) {

	idStr := r.URL.Path[len("/products/services/"):]
	serviceID, err := uuid.Parse(idStr)
	if err != nil {
		fmt.Println("[ERROR] GetServiceByID parse UUID:", err)
		sendError(w, "Invalid service ID format", http.StatusBadRequest)
		return
	}

	service, err := db.GetServiceByIDFromDB(serviceID)

	if err != nil {
		fmt.Println("[ERROR] GetServiceByID DB query:", err)
		sendError(w, "Unable to fetch service", http.StatusInternalServerError)
		return
	}

	if service.ID == uuid.Nil {
		sendError(w, "Service not found", http.StatusNotFound)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	jsonResponse, err := json.Marshal(service)

	if err != nil {
		fmt.Println("[ERROR] GetServiceByID marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)
}

func UpdateService(w http.ResponseWriter, r *http.Request) {
	idStr := r.URL.Path[len("/products/services/"):]
	serviceID, err := uuid.Parse(idStr)
	if err != nil {
		fmt.Println("[ERROR] UpdateService parse UUID:", err)
		sendError(w, "Invalid service ID format", http.StatusBadRequest)
		return
	}

	existing, err := db.GetServiceByIDFromDB(serviceID)
	if err != nil || existing.ID == uuid.Nil {
		sendError(w, "Service not found", http.StatusNotFound)
		return
	}

	var serviceDto models.Service
	if decodeErr := json.NewDecoder(r.Body).Decode(&serviceDto); decodeErr != nil {
		fmt.Println("[ERROR] UpdateService decode:", decodeErr)
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	if serviceDto.Name == "" {
		serviceDto.Name = existing.Name
	}
	if serviceDto.Description == "" {
		serviceDto.Description = existing.Description
	}
	if serviceDto.Price == 0 {
		serviceDto.Price = existing.Price
	}
	if serviceDto.Type == uuid.Nil {
		serviceDto.Type = existing.Type
	}
	if serviceDto.ServiceDate == "" {
		serviceDto.ServiceDate = existing.ServiceDate
	}
	if serviceDto.ServiceRoad == "" {
		serviceDto.ServiceRoad = existing.ServiceRoad
	}
	if serviceDto.ServiceCity == "" {
		serviceDto.ServiceCity = existing.ServiceCity
	}
	if serviceDto.ServiceZip == "" {
		serviceDto.ServiceZip = existing.ServiceZip
	}
	if serviceDto.MaximumParticipants == nil {
		serviceDto.MaximumParticipants = existing.MaximumParticipants
	}
	if serviceDto.DurationDays == 0 {
		serviceDto.DurationDays = existing.DurationDays
	}
	if serviceDto.EstimatedTimeMinutes == 0 {
		serviceDto.EstimatedTimeMinutes = existing.EstimatedTimeMinutes
	}
	serviceDto.CreatedBy = existing.CreatedBy
	normalizeFormationDurations(&serviceDto)

	if serviceDto.MeetingType == "zoom" && serviceDto.OnlineMeetingLink == "" {
		if url, err := createZoomMeeting(serviceDto.Name, serviceDto.ServiceDate); err != nil {
			fmt.Println("[WARN] could not create zoom meeting:", err)
		} else {
			serviceDto.OnlineMeetingLink = url
		}
	}

	validationErrors := ValidateServiceDto(serviceDto)
	if len(validationErrors) > 0 {
		fmt.Println("[ERROR] UpdateService validation:", validationErrors)
		sendError(w, fmt.Sprintf("Validation errors: %s", validationErrors), http.StatusBadRequest)
		return
	}

	if updateErr := db.UpdateServiceInDB(serviceID, serviceDto); updateErr != nil {
		fmt.Println("[ERROR] UpdateService DB:", updateErr)
		sendError(w, "Unable to update service", http.StatusInternalServerError)
		return
	}

	if err := db.SaveServiceSchedulesInDB(serviceID, serviceDto.Schedules); err != nil {
		fmt.Println("[ERROR] UpdateService save schedules:", err)
		sendError(w, "Unable to update service schedules", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusNoContent)
}

func DeleteService(w http.ResponseWriter, r *http.Request) {
	idStr := r.URL.Path[len("/products/services/"):]
	serviceID, err := uuid.Parse(idStr)
	if err != nil {
		fmt.Println("[ERROR] DeleteService parse UUID:", err)
		sendError(w, "Invalid service ID format", http.StatusBadRequest)
		return
	}

	existing, err := db.GetServiceByIDFromDB(serviceID)
	if err != nil || existing.ID == uuid.Nil {
		sendError(w, "Service not found", http.StatusNotFound)
		return
	}

	if cancelErr := db.CancelAndRefundServiceOrdersFromDB(serviceID, existing.Name); cancelErr != nil {
		fmt.Println("[ERROR] DeleteService cancel orders:", cancelErr)
		sendError(w, "Unable to cancel service orders", http.StatusInternalServerError)
		return
	}

	if deleteErr := db.DeleteServiceFromDB(serviceID); deleteErr != nil {
		fmt.Println("[ERROR] DeleteService DB:", deleteErr)
		sendError(w, "Unable to delete service", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusNoContent)
}

func GetAffectedEmployeesByServiceID(w http.ResponseWriter, r *http.Request) {
	idStr := r.URL.Path[len("/products/services/"):]
	idStr = idStr[:len(idStr)-len("/affected-employees")]
	serviceID, err := uuid.Parse(idStr)

	if err != nil {
		fmt.Println("[ERROR] GetAffectedEmployeesByServiceID parse UUID:", err)
		sendError(w, "Invalid service ID format", http.StatusBadRequest)
		return
	}

	affectedEmployees, err := db.GetAffectedEmployeesByServiceIDFromDB(serviceID)
	if err != nil {
		fmt.Println("[ERROR] GetAffectedEmployeesByServiceID DB query:", err)
		sendError(w, "Unable to fetch affected employees", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(affectedEmployees)
}

func ValidateAffectedEmployeeDto(aeDto models.AffectedEmployee) []string {

	var validationErrors []string

	if aeDto.UserID == uuid.Nil {
		validationErrors = append(validationErrors, "UserID is required and must be a valid UUID")
	}

	if aeDto.EventID == uuid.Nil {
		validationErrors = append(validationErrors, "EventID is required and must be a valid UUID")
	}

	return validationErrors
}

func AddAffectedEmployee(w http.ResponseWriter, r *http.Request) {
	idStr := r.URL.Path[len("/products/services/"):]
	idStr = idStr[:len(idStr)-len("/affected-employees")]
	serviceID, err := uuid.Parse(idStr)

	if err != nil {
		fmt.Println("[ERROR] AddAffectedEmployee parse UUID:", err)
		sendError(w, "Invalid service ID format", http.StatusBadRequest)
		return
	}

	var aeDto models.AffectedEmployee
	err = json.NewDecoder(r.Body).Decode(&aeDto)

	if err != nil {
		fmt.Println("[ERROR] AddAffectedEmployee decode:", err)
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	aeDto.EventID = serviceID

	validationErrors := ValidateAffectedEmployeeDto(aeDto)

	if len(validationErrors) > 0 {
		fmt.Println("[ERROR] AddAffectedEmployee validation:", validationErrors)
		sendError(w, fmt.Sprintf("Validation errors: %s", validationErrors), http.StatusBadRequest)
		return
	}

	newID, err := db.AddAffectedEmployeeInDB(aeDto)

	if err != nil {
		fmt.Println("[ERROR] AddAffectedEmployee DB insert:", err)
		sendError(w, "Unable to add affected employee", http.StatusInternalServerError)
		return
	}
	aeDto.ID = newID

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(aeDto)
}

func RemoveAffectedEmployee(w http.ResponseWriter, r *http.Request) {
	idStr := r.URL.Path[len("/products/services/"):]
	parts := len("/affected-employees/")
	idStr = idStr[:len(idStr)-parts]
	serviceID, err := uuid.Parse(idStr)

	if err != nil {
		fmt.Println("[ERROR] RemoveAffectedEmployee parse service UUID:", err)
		sendError(w, "Invalid service ID format", http.StatusBadRequest)
		return
	}

	aeIDStr := r.URL.Path[len("/products/")+len(serviceID.String())+len("/affected-employees/"):]
	aeID, err := uuid.Parse(aeIDStr)

	if err != nil {
		fmt.Println("[ERROR] RemoveAffectedEmployee parse affected employee UUID:", err)
		sendError(w, "Invalid affected employee ID format", http.StatusBadRequest)
		return
	}

	err = db.RemoveAffectedEmployeeFromDB(aeID)

	if err != nil {
		fmt.Println("[ERROR] RemoveAffectedEmployee DB delete:", err)
		sendError(w, "Unable to remove affected employee", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusNoContent)
}

func GetFormationsByCreator(w http.ResponseWriter, r *http.Request) {
	query := r.URL.Query()
	creatorIDStr := query.Get("creator_id")
	pageParam := query.Get("page")
	limitParam := query.Get("limit")
	searchParam := query.Get("search")

	if creatorIDStr == "" {
		sendError(w, "creator_id parameter is required", http.StatusBadRequest)
		return
	}

	creatorID, err := uuid.Parse(creatorIDStr)
	if err != nil {
		fmt.Println("[ERROR] GetFormationsByCreator parse UUID:", err)
		sendError(w, "Invalid creator ID format", http.StatusBadRequest)
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

	total, err := db.CountFormationsByCreatorFromDB(creatorID)
	if err != nil {
		fmt.Println("[ERROR] GetFormationsByCreator count:", err)
		sendError(w, "Unable to fetch formations", http.StatusInternalServerError)
		return
	}

	formations, err := db.GetFormationsByCreatorFromDB(creatorID, searchParam, limit, offset)
	if err != nil {
		fmt.Println("[ERROR] GetFormationsByCreator:", err)
		sendError(w, "Unable to fetch formations", http.StatusInternalServerError)
		return
	}

	response := map[string]interface{}{
		"items": formations,
		"total": total,
		"page":  page,
		"limit": limit,
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(response)
}

func GetPendingFormationsForManager(w http.ResponseWriter, r *http.Request) {
	userIDRaw := r.Context().Value("user_id")
	if userIDRaw == nil {
		sendError(w, "Unauthorized", http.StatusUnauthorized)
		return
	}

	managerID, err := uuid.Parse(fmt.Sprint(userIDRaw))
	if err != nil {
		fmt.Println("[ERROR] GetPendingFormationsForManager parse UUID:", err)
		sendError(w, "Invalid manager ID", http.StatusBadRequest)
		return
	}

	query := r.URL.Query()
	pageParam := query.Get("page")
	limitParam := query.Get("limit")
	searchParam := query.Get("search")

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

	total, err := db.CountPendingFormationsForManagerFromDB(managerID, searchParam)
	if err != nil {
		fmt.Println("[ERROR] CountPendingFormationsForManager:", err)
		sendError(w, "Unable to load pending formations", http.StatusInternalServerError)
		return
	}

	formations, err := db.GetPendingFormationsForManagerFromDB(managerID, searchParam, limit, offset)
	if err != nil {
		fmt.Println("[ERROR] GetPendingFormationsForManager:", err)
		sendError(w, "Unable to load pending formations", http.StatusInternalServerError)
		return
	}

	response := map[string]interface{}{
		"items": formations,
		"total": total,
		"page":  page,
		"limit": limit,
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(response)
}

func UpdateFormationStatus(w http.ResponseWriter, r *http.Request) {
	if !strings.HasSuffix(r.URL.Path, "/status") {
		sendError(w, "Invalid status update path", http.StatusBadRequest)
		return
	}

	idStr := strings.TrimSuffix(strings.TrimPrefix(r.URL.Path, "/formations/"), "/status")
	serviceID, err := uuid.Parse(idStr)
	if err != nil {
		fmt.Println("[ERROR] UpdateFormationStatus parse UUID:", err)
		sendError(w, "Invalid formation ID format", http.StatusBadRequest)
		return
	}

	userIDRaw := r.Context().Value("user_id")
	if userIDRaw == nil {
		sendError(w, "Unauthorized", http.StatusUnauthorized)
		return
	}
	managerID, err := uuid.Parse(fmt.Sprint(userIDRaw))
	if err != nil {
		fmt.Println("[ERROR] UpdateFormationStatus parse manager UUID:", err)
		sendError(w, "Invalid manager ID", http.StatusBadRequest)
		return
	}

	existing, err := db.GetServiceByIDFromDB(serviceID)
	if err != nil || existing.ID == uuid.Nil {
		sendError(w, "Formation not found", http.StatusNotFound)
		return
	}

	creator, err := db.GetUserByIDFromDB(existing.CreatedBy)
	if err != nil {
		fmt.Println("[ERROR] UpdateFormationStatus get creator:", err)
		sendError(w, "Unable to verify permissions", http.StatusInternalServerError)
		return
	}

	if creator.ManagerID == nil || *creator.ManagerID != managerID.String() {
		sendError(w, "Forbidden", http.StatusForbidden)
		return
	}

	var payload struct {
		Status string `json:"status"`
	}
	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		fmt.Println("[ERROR] UpdateFormationStatus decode:", err)
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	if payload.Status == "" {
		sendError(w, "Status is required", http.StatusBadRequest)
		return
	}

	if payload.Status != "published" && payload.Status != "rejected" {
		sendError(w, "Invalid status", http.StatusBadRequest)
		return
	}

	if err := db.UpdateServiceStatusInDB(serviceID, payload.Status); err != nil {
		fmt.Println("[ERROR] UpdateFormationStatus DB:", err)
		sendError(w, "Unable to update formation status", http.StatusInternalServerError)
		return
	}

	if payload.Status == "published" && existing.Status != "published" {
		updatedService := existing
		updatedService.Status = payload.Status
		if err := createFormationPlanningEntries(updatedService); err != nil {
			fmt.Println("[WARN] UpdateFormationStatus planning sync:", err)
		}
	}

	if payload.Status == "published" || payload.Status == "rejected" {
		notif := models.Notification{
			UserID: existing.CreatedBy,
		}
		if payload.Status == "published" {
			notif.Message = fmt.Sprintf("Your formation '%s' has been approved.", existing.Name)
		} else {
			notif.Message = fmt.Sprintf("Your formation '%s' has been rejected. Please edit and resubmit.", existing.Name)
		}
		if err := db.CreateNotificationInDB(notif); err != nil {
			fmt.Println("[WARN] UpdateFormationStatus notification:", err)
		}
	}

	w.WriteHeader(http.StatusNoContent)
}

func CreateFormation(w http.ResponseWriter, r *http.Request) {
	var serviceDto models.Service
	err := json.NewDecoder(r.Body).Decode(&serviceDto)

	if err != nil {
		fmt.Println("[ERROR] CreateFormation decode:", err)
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	normalizeFormationDurations(&serviceDto)

	validationErrors := ValidateServiceDto(serviceDto)

	if len(validationErrors) > 0 {
		fmt.Println("[ERROR] CreateFormation validation:", validationErrors)
		sendError(w, fmt.Sprintf("Validation errors: %s", validationErrors), http.StatusBadRequest)
		return
	}

	if serviceDto.MeetingType == "zoom" && serviceDto.OnlineMeetingLink == "" {
		if url, err := createZoomMeeting(serviceDto.Name, serviceDto.ServiceDate); err != nil {
			fmt.Println("[WARN] could not create zoom meeting:", err)
		} else {
			serviceDto.OnlineMeetingLink = url
		}
	}

	newID, err := db.CreateServiceInDB(serviceDto)

	if err != nil {
		fmt.Println("[ERROR] CreateFormation DB insert:", err)
		sendError(w, "Unable to create formation", http.StatusInternalServerError)
		return
	}

	if len(serviceDto.Schedules) > 0 {
		if err := db.SaveServiceSchedulesInDB(newID, serviceDto.Schedules); err != nil {
			fmt.Println("[ERROR] CreateFormation save schedules:", err)
			sendError(w, "Unable to save formation schedules", http.StatusInternalServerError)
			return
		}
	}

	if serviceDto.Status == "published" {
		serviceDto.ID = newID
		if err := createFormationPlanningEntries(serviceDto); err != nil {
			fmt.Println("[WARN] CreateFormation planning sync:", err)
		}
	}

	if serviceDto.Status == "draft" {
		user, err := db.GetUserByIDFromDB(serviceDto.CreatedBy)
		if err == nil && user.ManagerID != nil && *user.ManagerID != "" {
			managerID, parseErr := uuid.Parse(*user.ManagerID)
			if parseErr == nil {
				notif := models.Notification{
					UserID:  managerID,
					Message: fmt.Sprintf("New formation draft to review: %s", serviceDto.Name),
				}
				if err := db.CreateNotificationInDB(notif); err != nil {
					fmt.Println("[WARN] Could not create notification for manager:", err)
				}
			}
		}
	}

	serviceDto.ID = newID

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(serviceDto)
}
