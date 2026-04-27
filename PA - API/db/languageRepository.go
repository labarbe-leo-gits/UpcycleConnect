package db

import (
	"API/models"
	"database/sql"
	"fmt"
	"time"

	"github.com/google/uuid"
)

func GetLanguagesFromDB(limit, offset int) ([]models.Language, int, error) {
	baseQuery := "SELECT id, code, name, created_at FROM languages"
	countQuery := "SELECT COUNT(*) FROM languages"

	var total int
	if err := Db.QueryRow(countQuery).Scan(&total); err != nil {
		return nil, 0, fmt.Errorf("failed to count languages: %v", err)
	}

	query := baseQuery + " ORDER BY name ASC"
	if limit > 0 {
		query += fmt.Sprintf(" LIMIT %d OFFSET %d", limit, offset)
	}

	rows, err := Db.Query(query)
	if err != nil {
		return nil, 0, fmt.Errorf("failed to query languages: %v", err)
	}
	defer rows.Close()

	var languages []models.Language
	for rows.Next() {
		var lang models.Language
		var createdAt sql.NullTime
		if err := rows.Scan(&lang.ID, &lang.Code, &lang.Name, &createdAt); err != nil {
			return nil, 0, fmt.Errorf("failed to scan language: %v", err)
		}
		if createdAt.Valid {
			lang.CreatedAt = createdAt.Time
		}
		languages = append(languages, lang)
	}

	if err = rows.Err(); err != nil {
		return nil, 0, fmt.Errorf("error reading language rows: %v", err)
	}

	return languages, total, nil
}

func GetLanguageByIDFromDB(id uuid.UUID) (*models.Language, error) {
	query := "SELECT id, code, name, created_at FROM languages WHERE id = ?"
	var lang models.Language
	var createdAt sql.NullTime

	err := Db.QueryRow(query, id.String()).Scan(&lang.ID, &lang.Code, &lang.Name, &createdAt)
	if err != nil {
		if err == sql.ErrNoRows {
			return nil, fmt.Errorf("language not found")
		}
		return nil, fmt.Errorf("failed to get language: %v", err)
	}

	if createdAt.Valid {
		lang.CreatedAt = createdAt.Time
	}

	return &lang, nil
}

func CreateLanguageInDB(lang *models.Language) error {
	if lang.ID == uuid.Nil {
		lang.ID = uuid.New()
	}
	if lang.CreatedAt.IsZero() {
		lang.CreatedAt = time.Now()
	}

	query := "INSERT INTO languages (id, code, name, created_at) VALUES (?, ?, ?, ?)"
	_, err := Db.Exec(query, lang.ID.String(), lang.Code, lang.Name, lang.CreatedAt)
	if err != nil {
		return fmt.Errorf("failed to create language: %v", err)
	}

	return nil
}

func UpdateLanguageInDB(id uuid.UUID, lang *models.Language) error {
	query := "UPDATE languages SET code = ?, name = ? WHERE id = ?"
	result, err := Db.Exec(query, lang.Code, lang.Name, id.String())
	if err != nil {
		return fmt.Errorf("failed to update language: %v", err)
	}

	rowsAffected, err := result.RowsAffected()
	if err != nil {
		return fmt.Errorf("failed to get rows affected: %v", err)
	}

	if rowsAffected == 0 {
		return fmt.Errorf("language not found")
	}

	return nil
}

func DeleteLanguageFromDB(id uuid.UUID) error {
	query := "DELETE FROM languages WHERE id = ?"
	result, err := Db.Exec(query, id.String())
	if err != nil {
		return fmt.Errorf("failed to delete language: %v", err)
	}

	rowsAffected, err := result.RowsAffected()
	if err != nil {
		return fmt.Errorf("failed to get rows affected: %v", err)
	}

	if rowsAffected == 0 {
		return fmt.Errorf("language not found")
	}

	return nil
}

func GetLanguageTranslationsFromDB(languageID uuid.UUID) ([]models.LanguageTranslation, error) {
	query := `
		SELECT id, language_id, key_name, section, value, created_at 
		FROM language_translations 
		WHERE language_id = ?
		ORDER BY key_name ASC
	`

	rows, err := Db.Query(query, languageID.String())
	if err != nil {
		return nil, fmt.Errorf("failed to query language translations: %v", err)
	}
	defer rows.Close()

	var translations []models.LanguageTranslation
	for rows.Next() {
		var trans models.LanguageTranslation
		var createdAt sql.NullTime
		if err := rows.Scan(&trans.ID, &trans.LanguageID, &trans.KeyName, &trans.Section, &trans.Value, &createdAt); err != nil {
			return nil, fmt.Errorf("failed to scan translation: %v", err)
		}
		if createdAt.Valid {
			trans.CreatedAt = createdAt.Time
		}
		translations = append(translations, trans)
	}

	if err = rows.Err(); err != nil {
		return nil, fmt.Errorf("error reading translation rows: %v", err)
	}

	return translations, nil
}

func CreateLanguageTranslationInDB(trans *models.LanguageTranslation) error {
	if trans.ID == uuid.Nil {
		trans.ID = uuid.New()
	}
	if trans.CreatedAt.IsZero() {
		trans.CreatedAt = time.Now()
	}

	query := `
		INSERT INTO language_translations (id, language_id, key_name, section, value, created_at) 
		VALUES (?, ?, ?, ?, ?, ?)
	`
	_, err := Db.Exec(query, trans.ID.String(), trans.LanguageID.String(), trans.KeyName, trans.Section, trans.Value, trans.CreatedAt)
	if err != nil {
		return fmt.Errorf("failed to create language translation: %v", err)
	}

	return nil
}

func UpdateLanguageTranslationInDB(id uuid.UUID, trans *models.LanguageTranslation) error {
	query := "UPDATE language_translations SET key_name = ?, section = ?, value = ? WHERE id = ?"
	result, err := Db.Exec(query, trans.KeyName, trans.Section, trans.Value, id.String())
	if err != nil {
		return fmt.Errorf("failed to update language translation: %v", err)
	}

	rowsAffected, err := result.RowsAffected()
	if err != nil {
		return fmt.Errorf("failed to get rows affected: %v", err)
	}

	if rowsAffected == 0 {
		return fmt.Errorf("translation not found")
	}

	return nil
}

func DeleteLanguageTranslationFromDB(id uuid.UUID) error {
	query := "DELETE FROM language_translations WHERE id = ?"
	result, err := Db.Exec(query, id.String())
	if err != nil {
		return fmt.Errorf("failed to delete language translation: %v", err)
	}

	rowsAffected, err := result.RowsAffected()
	if err != nil {
		return fmt.Errorf("failed to get rows affected: %v", err)
	}

	if rowsAffected == 0 {
		return fmt.Errorf("translation not found")
	}

	return nil
}
