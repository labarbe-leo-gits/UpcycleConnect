package main

import (
	"encoding/json"
	"fmt"
	"net/http"
	"os"
	"path/filepath"
	"regexp"
	"strings"
)

type LanguageMeta struct {
	Code string `json:"code"`
	Name string `json:"name"`
}

type createLanguageRequest struct {
	Code string `json:"code"`
	Name string `json:"name"`
}

type updateTranslationRequest struct {
	Key   string `json:"key"`
	Value string `json:"value"`
}

var languageCodePattern = regexp.MustCompile(`^[a-z]{2,10}$`)

func translationsDirectory() string {
	return filepath.Join("data", "translations")
}

func languagesMetaPath() string {
	return filepath.Join(translationsDirectory(), "languages.json")
}

func translationFilePath(code string) string {
	return filepath.Join(translationsDirectory(), fmt.Sprintf("%s.json", code))
}

func ensureTranslationsDirectory() error {
	return os.MkdirAll(translationsDirectory(), 0755)
}

func loadLanguagesMetadata() ([]LanguageMeta, error) {
	if err := ensureTranslationsDirectory(); err != nil {
		return nil, err
	}
	path := languagesMetaPath()
	if _, err := os.Stat(path); os.IsNotExist(err) {
		if err := saveLanguagesMetadata([]LanguageMeta{}); err != nil {
			return nil, err
		}
		return []LanguageMeta{}, nil
	}
	data, err := os.ReadFile(path)
	if err != nil {
		return nil, err
	}
	var meta []LanguageMeta
	if err := json.Unmarshal(data, &meta); err != nil {
		return nil, err
	}
	return meta, nil
}

func saveLanguagesMetadata(meta []LanguageMeta) error {
	if err := ensureTranslationsDirectory(); err != nil {
		return err
	}
	data, err := json.MarshalIndent(meta, "", "  ")
	if err != nil {
		return err
	}
	return os.WriteFile(languagesMetaPath(), data, 0644)
}

func loadTranslationMap(code string) (map[string]string, error) {
	if err := ensureTranslationsDirectory(); err != nil {
		return nil, err
	}
	path := translationFilePath(code)
	data, err := os.ReadFile(path)
	if err != nil {
		return nil, err
	}
	var translations map[string]string
	if err := json.Unmarshal(data, &translations); err != nil {
		return nil, err
	}
	return translations, nil
}

func saveTranslationMap(code string, translations map[string]string) error {
	if err := ensureTranslationsDirectory(); err != nil {
		return err
	}
	path := translationFilePath(code)
	data, err := json.MarshalIndent(translations, "", "  ")
	if err != nil {
		return err
	}
	return os.WriteFile(path, data, 0644)
}

func listAllTranslationKeys() (map[string]struct{}, error) {
	if err := ensureTranslationsDirectory(); err != nil {
		return nil, err
	}
	files, err := os.ReadDir(translationsDirectory())
	if err != nil {
		return nil, err
	}
	keys := map[string]struct{}{}
	for _, file := range files {
		if file.IsDir() {
			continue
		}
		if file.Name() == "languages.json" || !strings.HasSuffix(file.Name(), ".json") {
			continue
		}
		data, err := os.ReadFile(filepath.Join(translationsDirectory(), file.Name()))
		if err != nil {
			return nil, err
		}
		var translations map[string]string
		if err := json.Unmarshal(data, &translations); err != nil {
			continue
		}
		for k := range translations {
			keys[k] = struct{}{}
		}
	}
	return keys, nil
}

func writeBlankTranslationFile(code string) error {
	keys, err := listAllTranslationKeys()
	if err != nil {
		return err
	}
	values := map[string]string{}
	for key := range keys {
		values[key] = ""
	}
	return saveTranslationMap(code, values)
}

func findLanguageMeta(code string, meta []LanguageMeta) (*LanguageMeta, int) {
	for index, item := range meta {
		if strings.EqualFold(item.Code, code) {
			return &item, index
		}
	}
	return nil, -1
}

func listLanguagesHandler(w http.ResponseWriter, r *http.Request) {
	meta, err := loadLanguagesMetadata()
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(meta)
}

func getTranslationHandler(w http.ResponseWriter, r *http.Request) {
	code := strings.TrimPrefix(r.URL.Path, "/translations/")
	code = strings.TrimSpace(code)
	if code == "" {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]string{"error": "missing language code"})
		return
	}
	translations, err := loadTranslationMap(code)
	if err != nil {
		w.WriteHeader(http.StatusNotFound)
		json.NewEncoder(w).Encode(map[string]string{"error": "language not found"})
		return
	}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(translations)
}

func createLanguageHandler(w http.ResponseWriter, r *http.Request) {
	var req createLanguageRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]string{"error": "invalid request body"})
		return
	}
	req.Code = strings.TrimSpace(strings.ToLower(req.Code))
	req.Name = strings.TrimSpace(req.Name)
	if req.Code == "" || req.Name == "" || !languageCodePattern.MatchString(req.Code) {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]string{"error": "invalid language code or name"})
		return
	}
	meta, err := loadLanguagesMetadata()
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}
	if existing, _ := findLanguageMeta(req.Code, meta); existing != nil {
		w.WriteHeader(http.StatusConflict)
		json.NewEncoder(w).Encode(map[string]string{"error": "language already exists"})
		return
	}
	meta = append(meta, LanguageMeta{Code: req.Code, Name: req.Name})
	if err := saveLanguagesMetadata(meta); err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}
	if err := writeBlankTranslationFile(req.Code); err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]string{"code": req.Code, "name": req.Name})
}

func updateTranslationHandler(w http.ResponseWriter, r *http.Request) {
	code := strings.TrimPrefix(r.URL.Path, "/translations/")
	code = strings.TrimSpace(code)
	if code == "" {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]string{"error": "missing language code"})
		return
	}
	var req updateTranslationRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]string{"error": "invalid request body"})
		return
	}
	req.Key = strings.TrimSpace(req.Key)
	if req.Key == "" {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]string{"error": "missing translation key"})
		return
	}
	translations, err := loadTranslationMap(code)
	if err != nil {
		w.WriteHeader(http.StatusNotFound)
		json.NewEncoder(w).Encode(map[string]string{"error": "language not found"})
		return
	}
	translations[req.Key] = req.Value
	if err := saveTranslationMap(code, translations); err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"status": "ok"})
}

func translationsHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method == http.MethodGet {
		if r.URL.Path == "/translations" {
			listLanguagesHandler(w, r)
			return
		}
		getTranslationHandler(w, r)
		return
	}
	if r.Method == http.MethodPost && r.URL.Path == "/translations" {
		createLanguageHandler(w, r)
		return
	}
	if r.Method == http.MethodPatch && strings.HasPrefix(r.URL.Path, "/translations/") {
		updateTranslationHandler(w, r)
		return
	}
	w.WriteHeader(http.StatusMethodNotAllowed)
	json.NewEncoder(w).Encode(map[string]string{"error": "method not allowed"})
}
