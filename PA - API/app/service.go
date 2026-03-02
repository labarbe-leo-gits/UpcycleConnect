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
	availableOnly := availableParam == "1" || availableParam == "true"

	if pageParam == "" && limitParam == "" {
		services, err := db.GetServicesFromDB()

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

	total, err := db.CountServicesFromDB(availableOnly)
	if err != nil {
		fmt.Println("[ERROR] GetServices count:", err)
		sendError(w, "Unable to fetch services", http.StatusInternalServerError)
		return
	}

	services, err := db.GetServicesPageFromDB(limit, offset, availableOnly)
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

	if serviceDto.Type < 0 || serviceDto.Type > 2 {
		validationErrors = append(validationErrors, "Type must be between 0 and 2")
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

	err = db.CreateServiceInDB(serviceDto)

	if err != nil {
		fmt.Println("[ERROR] CreateService DB insert:", err)
		sendError(w, "Unable to create service", http.StatusInternalServerError)
		return
	}

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
	if serviceDto.Type == 0 {
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
