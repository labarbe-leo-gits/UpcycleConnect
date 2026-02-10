package db

import (
	"API/models"
	"database/sql"
	"fmt"
	"github.com/google/uuid"
)

func GetServicesFromDB() ([]models.Service, error) {

	services := []models.Service{}
	rows, err := Db.Query("SELECT id, title, description, price, event_type, event_date, event_road, event_city, event_zip_code, created_by, created_at, updated_at FROM evenements")

	if err != nil {
		return nil, fmt.Errorf("getServices package db : %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {
		var service models.Service
		var idStr string
		var createdByStr string
		var createdAt, updatedAt sql.NullString
		err := rows.Scan(&idStr, &service.Name, &service.Description, &service.Price, &service.Type, &service.ServiceDate, &service.ServiceRoad, &service.ServiceCity, &service.ServiceZip, &createdByStr, &createdAt, &updatedAt)
		if err != nil {
			return nil, fmt.Errorf("getServices package db scan : %s", err.Error())
		}
		service.ID, err = uuid.Parse(idStr)
		if err != nil {
			return nil, fmt.Errorf("getServices package db uuid parse : %s", err.Error())
		}
		service.CreatedBy, err = uuid.Parse(createdByStr)
		if err != nil {
			return nil, fmt.Errorf("getServices package db uuid parse created_by : %s", err.Error())
		}
		if createdAt.Valid {
			service.CreatedAt = createdAt.String
		}
		if updatedAt.Valid {
			service.UpdatedAt = updatedAt.String
		}
		services = append(services, service)
	}

	err = rows.Err()
	if err != nil {
		return nil, fmt.Errorf("getServices package db rows : %s", err.Error())
	}

	return services, nil

}

func getCurrentTime() string {
	var currentTime string
	err := Db.QueryRow("SELECT NOW()").Scan(&currentTime)
	if err != nil {
		fmt.Println("[ERROR] getCurrentTime package db : ", err)
		return ""
	}
	return currentTime
}

func CreateServiceInDB(service models.Service) error {

	newID := uuid.New()
	currentTime := getCurrentTime()
	_, err := Db.Exec("INSERT INTO evenements (id, title, description, price, event_type, event_date, event_road, event_city, event_zip_code, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
		newID, service.Name, service.Description, service.Price, service.Type, service.ServiceDate, service.ServiceRoad, service.ServiceCity, service.ServiceZip, service.CreatedBy, currentTime, currentTime)
	
	if err != nil {
		return fmt.Errorf("createService package db : %s", err.Error())
	}

	return nil

}

func GetServiceByIDFromDB(serviceID uuid.UUID) (models.Service, error) {
	var service models.Service
	var idStr string
	var createdByStr string
	var createdAt, updatedAt sql.NullString

	err := Db.QueryRow("SELECT id, title, description, price, event_type, event_date, event_road, event_city, event_zip_code, created_by, created_at, updated_at FROM evenements WHERE id = ?", serviceID).Scan(&idStr, &service.Name, &service.Description, &service.Price, &service.Type, &service.ServiceDate, &service.ServiceRoad, &service.ServiceCity, &service.ServiceZip, &createdByStr, &createdAt, &updatedAt)

	if err != nil {
		if err == sql.ErrNoRows {
			return service, fmt.Errorf("service not found")
		}
		return service, fmt.Errorf("getServiceByID package db : %s", err.Error())
	}

	service.ID, err = uuid.Parse(idStr)
	if err != nil {
		return service, fmt.Errorf("getServiceByID package db uuid parse : %s", err.Error())
	}

	service.CreatedBy, err = uuid.Parse(createdByStr)
	if err != nil {
		return service, fmt.Errorf("getServiceByID package db uuid parse created_by : %s", err.Error())
	}

	if createdAt.Valid {
		service.CreatedAt = createdAt.String
	}

	if updatedAt.Valid {
		service.UpdatedAt = updatedAt.String
	}

	return service, nil

}
