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

func GetAnnonceImages(w http.ResponseWriter, r *http.Request) {

	idStr := r.URL.Path[len("/annonces/") : len(r.URL.Path)-len("/images")]
	_, err := uuid.Parse(idStr)

	images, err := db.GetAnnonceImagesFromDB(uuid.MustParse(idStr))
	if err != nil {
		fmt.Println("[ERROR] GetAnnonceImages:", err)
		sendError(w, "Unable to fetch images for annonce", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	jsonResponse, err := json.Marshal(images)
	if err != nil {
		fmt.Println("[ERROR] GetAnnonceImages marshal:", err)
		sendError(w, "Unable to process response", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "%s", jsonResponse)
}

func ValidateImageDto(imageDto models.Image) []string {

	var validationErrors []string

	if imageDto.FileName == "" {
		validationErrors = append(validationErrors, "FileName is required")
	}

	if imageDto.AnnonceID == "" {
		validationErrors = append(validationErrors, "AnnonceID is required")
	}

	return validationErrors
}

func UploadAnnonceImage(w http.ResponseWriter, r *http.Request) {

	var imageDto models.Image
	err := json.NewDecoder(r.Body).Decode(&imageDto)

	if err != nil {
		fmt.Println("[ERROR] UploadAnnonceImage decode:", err)
		sendError(w, "Invalid request payload", http.StatusBadRequest)
		return
	}

	if imageDto.AnnonceID == "" {
		pathSegments := strings.Split(strings.TrimPrefix(r.URL.Path, "/"), "/")
		if len(pathSegments) >= 2 {
			imageDto.AnnonceID = pathSegments[1]
		}
	}

	validationErrors := ValidateImageDto(imageDto)

	if len(validationErrors) > 0 {
		fmt.Println("[ERROR] UploadAnnonceImage validation:", validationErrors)
		sendError(w, "Validation errors: "+fmt.Sprint(validationErrors), http.StatusBadRequest)
		return
	}

	err = db.CreateAnnonceImageInDB(imageDto)

	if err != nil {
		fmt.Println("[ERROR] UploadAnnonceImage DB:", err)
		sendError(w, "Unable to upload image for annonce", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(imageDto)

}
