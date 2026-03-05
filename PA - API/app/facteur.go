package app

import (
	"API/db"
	"API/models"
	"encoding/json"
	"fmt"
	"net/http"

	"github.com/google/uuid"
)

func GetFacteurs(w http.ResponseWriter, r *http.Request) {
	facteurs, err := db.GetAllFacteurs()
	if err != nil {
		fmt.Println("[ERROR] GetFacteurs DB:", err)
		sendError(w, "Unable to fetch materials", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	if err := json.NewEncoder(w).Encode(facteurs); err != nil {
		fmt.Println("[ERROR] GetFacteurs encode:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}
}

func ValidateFacteurDTO(dto models.FacteurMateriaux) error {

	var validationErrors []string

	if dto.Nom == "" {
		validationErrors = append(validationErrors, "Le nom du matériau est requis.")
	}

	if dto.FacteurCO2 <= 0 {
		validationErrors = append(validationErrors, "Le facteur CO2 doit être un nombre positif.")
	}

	if len(validationErrors) > 0 {
		return fmt.Errorf("Validation errors: %s", validationErrors)
	}

	return nil

}

func CreateFacteur(w http.ResponseWriter, r *http.Request) {

	var facteurDto models.FacteurMateriaux

	err := json.NewDecoder(r.Body).Decode(&facteurDto)

	if err != nil {
		fmt.Println("[ERROR] CreateFacteur decode:", err)
		sendError(w, "Unable to process request body", http.StatusBadRequest)
		return
	}

	if err := ValidateFacteurDTO(facteurDto); err != nil {
		sendError(w, err.Error(), http.StatusBadRequest)
		return
	}

	err = db.CreateFacteurInDB(facteurDto)

	if err != nil {
		fmt.Println("[ERROR] CreateFacteur DB:", err)
		sendError(w, "Unable to create material factor", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]string{"message": "Material factor created successfully"})

}

func DeleteFacteur(w http.ResponseWriter, r *http.Request) {
	idStr := r.URL.Path[len("/facteurs/"):]
	id, err := uuid.Parse(idStr)
	if err != nil {
		fmt.Println("[ERROR] DeleteFacteur parse UUID:", err)
		sendError(w, "Invalid UUID format", http.StatusBadRequest)
		return
	}

	existing, err := db.GetFacteurByID(id.String())
	if err != nil {
		fmt.Println("[ERROR] DeleteFacteur DB fetch:", err)
		sendError(w, "Unable to fetch material factor", http.StatusInternalServerError)
		return
	}
	if existing == nil {
		sendError(w, "Material factor not found", http.StatusNotFound)
		return
	}

	if err := db.DeleteFacteurFromDB(id); err != nil {
		fmt.Println("[ERROR] DeleteFacteur DB delete:", err)
		sendError(w, "Unable to delete material factor", http.StatusInternalServerError)
		return
	}

	w.WriteHeader(http.StatusNoContent)
}

func UpdateFacteur(w http.ResponseWriter, r *http.Request) {

	idStr := r.URL.Path[len("/facteurs/"):]
	id, err := uuid.Parse(idStr)
	if err != nil {
		fmt.Println("[ERROR] UpdateFacteur parse UUID:", err)
		sendError(w, "Invalid UUID format", http.StatusBadRequest)
		return
	}

	var facteurDto models.FacteurMateriaux

	err = json.NewDecoder(r.Body).Decode(&facteurDto)

	if err != nil {
		fmt.Println("[ERROR] UpdateFacteur decode:", err)
		sendError(w, "Unable to process request body", http.StatusBadRequest)
		return
	}

	existingFacteur, err := db.GetFacteurByID(id.String())
	if err != nil {
		fmt.Println("[ERROR] UpdateFacteur DB fetch:", err)
		sendError(w, "Unable to fetch existing material factor", http.StatusInternalServerError)
		return
	}

	if existingFacteur == nil {
		sendError(w, "Material factor not found", http.StatusNotFound)
		return
	}

	if facteurDto.Nom == "" {
		facteurDto.Nom = existingFacteur.Nom
	}

	if facteurDto.FacteurCO2 <= 0 {
		facteurDto.FacteurCO2 = existingFacteur.FacteurCO2
	}

	// Always preserve the existing energie value (field removed from UI)
	facteurDto.FacteurEnergie = existingFacteur.FacteurEnergie

	if err := ValidateFacteurDTO(facteurDto); err != nil {
		sendError(w, err.Error(), http.StatusBadRequest)
		return
	}

	err = db.UpdateFacteurInDB(id, facteurDto)

	if err != nil {
		fmt.Println("[ERROR] UpdateFacteur DB update:", err)
		sendError(w, "Unable to update material factor", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]string{"message": "Material factor updated successfully"})

}
