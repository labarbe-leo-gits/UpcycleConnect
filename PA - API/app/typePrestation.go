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

func GetTypePrestations(w http.ResponseWriter, r *http.Request) {

	typePrestations, err := db.GetAllTypePrestations()
	if err != nil {
		http.Error(w, "Failed to retrieve service types", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(typePrestations)

}

func ValidateTypePrestationDTO(typePrestation models.TypePrestation) []string {

	var errors []string

	if typePrestation.Name == "" {
		errors = append(errors, "Name is required")
	}

	if len(typePrestation.Name) > 100 {
		errors = append(errors, "Name must be less than 100 characters")
	}

	if len(typePrestation.Name) < 3 {
		errors = append(errors, "Name must be at least 3 characters")
	}

	return errors

}

func CreateTypePrestation(w http.ResponseWriter, r *http.Request) {

	var typePrestation models.TypePrestation
	err := json.NewDecoder(r.Body).Decode(&typePrestation)

	if err != nil {
		http.Error(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	validationErrors := ValidateTypePrestationDTO(typePrestation)

	if len(validationErrors) > 0 {
		validationErrorsJSON, _ := json.Marshal(validationErrors)
		http.Error(w, "Validation errors: "+string(validationErrorsJSON), http.StatusBadRequest)
		return
	}

	err = db.CreateTypePrestationInDB(typePrestation)

	if err != nil {
		msg := err.Error()
		if strings.Contains(msg, "1062") || strings.Contains(strings.ToLower(msg), "duplicate entry") {
			msg = "Duplicate entry"
		}
		sendError(w, fmt.Sprintf("Unable to create service type: %s", msg), http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(typePrestation)

}

func GetTypePrestationByID(w http.ResponseWriter, r *http.Request) {

	idStr := r.URL.Path[len("/typesPrestation/"):]
	id, err := uuid.Parse(idStr)

	if err != nil {
		http.Error(w, "Invalid service type ID format", http.StatusBadRequest)
		return
	}

	typePrestation, err := db.GetTypePrestationByIDFromDB(id)

	if err != nil {
		http.Error(w, "Service type not found", http.StatusNotFound)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(typePrestation)

}

func UpdateTypePrestation(w http.ResponseWriter, r *http.Request) {

	idStr := r.URL.Path[len("/typesPrestation/"):]
	id, err := uuid.Parse(idStr)

	if err != nil {
		http.Error(w, "Invalid service type ID format", http.StatusBadRequest)
		return
	}

	var typePrestation models.TypePrestation
	err = json.NewDecoder(r.Body).Decode(&typePrestation)

	if err != nil {
		http.Error(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	existingTypePrestation, err := db.GetTypePrestationByIDFromDB(id)
	if err != nil {
		http.Error(w, "Service type not found", http.StatusNotFound)
		return
	}

	if typePrestation.Name == "" {
		typePrestation.Name = existingTypePrestation.Name
	}

	typePrestation.ID = id
	typePrestation.CreatedAt = existingTypePrestation.CreatedAt

	validationErrors := ValidateTypePrestationDTO(typePrestation)

	if len(validationErrors) > 0 {
		validationErrorsJSON, _ := json.Marshal(validationErrors)
		http.Error(w, "Validation errors: "+string(validationErrorsJSON), http.StatusBadRequest)
		return
	}

	err = db.UpdateTypePrestationInDB(typePrestation)

	if err != nil {
		http.Error(w, "Unable to update service type", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(typePrestation)

}

func DeleteTypePrestation(w http.ResponseWriter, r *http.Request) {

	idStr := r.URL.Path[len("/typesPrestation/"):]
	id, err := uuid.Parse(idStr)

	if err != nil {
		http.Error(w, "Invalid service type ID format", http.StatusBadRequest)
		return
	}

	err = db.DeleteTypePrestationInDB(id)

	if err != nil {
		http.Error(w, "Unable to delete service type", http.StatusInternalServerError)
		return
	}

	w.WriteHeader(http.StatusNoContent)

}
