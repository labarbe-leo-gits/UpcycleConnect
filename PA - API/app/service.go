package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"
	"strconv"

	"github.com/google/uuid"
)

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
	serviceDto.CreatedBy = existing.CreatedBy

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
