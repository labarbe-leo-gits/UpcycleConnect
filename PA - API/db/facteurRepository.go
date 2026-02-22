package db

import (
	"API/models"
	"database/sql"
	"fmt"

	"github.com/google/uuid"
)

func GetFacteurByName(name string) (*models.FacteurMateriaux, error) {
	var f models.FacteurMateriaux
	// first try exact case-insensitive match
	row := Db.QueryRow("SELECT id, nom, facteur_co2, facteur_energie FROM facteurs_materiaux WHERE LOWER(nom) = LOWER(?)", name)
	var idStr string
	var factorCO2, factorEnergie sql.NullFloat64
	if err := row.Scan(&idStr, &f.Nom, &factorCO2, &factorEnergie); err != nil {
		if err == sql.ErrNoRows {
			subRow := Db.QueryRow("SELECT id, nom, facteur_co2, facteur_energie FROM facteurs_materiaux WHERE LOWER(nom) LIKE LOWER(?) LIMIT 1", "%"+name+"%")
			if err2 := subRow.Scan(&idStr, &f.Nom, &factorCO2, &factorEnergie); err2 != nil {
				if err2 == sql.ErrNoRows {
					return nil, nil
				}
				return nil, fmt.Errorf("getFacteurByName substring scan: %s", err2.Error())
			}
		} else {
			return nil, fmt.Errorf("getFacteurByName scan: %s", err.Error())
		}
	}
	uid, err := uuid.Parse(idStr)
	if err != nil {
		return nil, fmt.Errorf("getFacteurByName uuid parse: %s", err.Error())
	}
	f.ID = uid
	if factorCO2.Valid {
		f.FacteurCO2 = factorCO2.Float64
	}
	if factorEnergie.Valid {
		f.FacteurEnergie = factorEnergie.Float64
	}
	return &f, nil
}

func GetFacteurByID(id string) (*models.FacteurMateriaux, error) {
	var f models.FacteurMateriaux
	row := Db.QueryRow("SELECT id, nom, facteur_co2, facteur_energie FROM facteurs_materiaux WHERE id = ?", id)
	var idStr string
	var factorCO2, factorEnergie sql.NullFloat64
	if err := row.Scan(&idStr, &f.Nom, &factorCO2, &factorEnergie); err != nil {
		if err == sql.ErrNoRows {
			return nil, nil
		}
		return nil, fmt.Errorf("getFacteurByID scan: %s", err.Error())
	}
	uid, err := uuid.Parse(idStr)
	if err != nil {
		return nil, fmt.Errorf("getFacteurByID uuid parse: %s", err.Error())
	}
	f.ID = uid
	if factorCO2.Valid {
		f.FacteurCO2 = factorCO2.Float64
	}
	if factorEnergie.Valid {
		f.FacteurEnergie = factorEnergie.Float64
	}
	return &f, nil
}

func GetAllFacteurs() ([]models.FacteurMateriaux, error) {
	rows, err := Db.Query("SELECT id, nom, facteur_co2, facteur_energie FROM facteurs_materiaux")
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var list []models.FacteurMateriaux
	for rows.Next() {
		var f models.FacteurMateriaux
		var idStr string
		var co2, energie sql.NullFloat64
		if err := rows.Scan(&idStr, &f.Nom, &co2, &energie); err != nil {
			return nil, err
		}
		if idStr != "" {
			f.ID, _ = uuid.Parse(idStr)
		}
		if co2.Valid {
			f.FacteurCO2 = co2.Float64
		}
		if energie.Valid {
			f.FacteurEnergie = energie.Float64
		}
		list = append(list, f)
	}
	return list, nil
}

func CreateOrUpdateFacteur(f models.FacteurMateriaux) error {
	// simple upsert
	_, err := Db.Exec("INSERT INTO facteurs_materiaux (id, nom, facteur_co2, facteur_energie) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE facteur_co2 = VALUES(facteur_co2), facteur_energie = VALUES(facteur_energie)", f.ID.String(), f.Nom, f.FacteurCO2, f.FacteurEnergie)
	if err != nil {
		return fmt.Errorf("createOrUpdateFacteur: %s", err.Error())
	}
	return nil
}
