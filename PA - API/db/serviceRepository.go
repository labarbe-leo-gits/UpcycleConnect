package db

import (
	"API/models"
	"database/sql"
	"fmt"
	"strings"

	"github.com/google/uuid"
)

func GetServicesFromDB(search string, typeUUID string, availableOnly bool) ([]models.Service, error) {

	services := []models.Service{}
	query := "SELECT id, title, description, price, event_type, event_date, event_road, event_city, event_zip_code, maximum_participants, current_participants, created_by, created_at, updated_at FROM evenements"
	args := []interface{}{}
	clauses := []string{}

	if availableOnly {
		clauses = append(clauses, "(maximum_participants IS NULL OR current_participants < maximum_participants)")
	}
	if search != "" {
		clauses = append(clauses, "title LIKE ?")
		args = append(args, "%"+search+"%")
	}
	if typeUUID != "" {
		clauses = append(clauses, "event_type = ?")
		args = append(args, typeUUID)
	}
	if len(clauses) > 0 {
		query += " WHERE " + strings.Join(clauses, " AND ")
	}

	rows, err := Db.Query(query, args...)

	if err != nil {
		return nil, fmt.Errorf("getServices package db : %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {
		var service models.Service
		var idStr string
		var createdByStr string
		var createdAt, updatedAt sql.NullString
		var maxParticipants, currentParticipants sql.NullInt64
		var typeStr string
		err := rows.Scan(&idStr, &service.Name, &service.Description, &service.Price, &typeStr, &service.ServiceDate, &service.ServiceRoad, &service.ServiceCity, &service.ServiceZip, &maxParticipants, &currentParticipants, &createdByStr, &createdAt, &updatedAt)
		if err != nil {
			return nil, fmt.Errorf("getServices package db scan : %s", err.Error())
		}
		service.ID, err = uuid.Parse(idStr)
		if err != nil {
			return nil, fmt.Errorf("getServices package db uuid parse : %s", err.Error())
		}
		service.Type, err = uuid.Parse(typeStr)
		if err != nil {
			return nil, fmt.Errorf("getServices package db uuid parse event_type : %s", err.Error())
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
		if maxParticipants.Valid {
			value := int(maxParticipants.Int64)
			service.MaximumParticipants = &value
		}
		if currentParticipants.Valid {
			service.CurrentParticipants = int(currentParticipants.Int64)
		}
		services = append(services, service)
	}

	err = rows.Err()
	if err != nil {
		return nil, fmt.Errorf("getServices package db rows : %s", err.Error())
	}

	return services, nil

}

func GetServicesPageFromDB(limit int, offset int, availableOnly bool, search string, typeUUID string) ([]models.Service, error) {

	services := []models.Service{}
	query := "SELECT id, title, description, price, event_type, event_date, event_road, event_city, event_zip_code, maximum_participants, current_participants, created_by, created_at, updated_at FROM evenements"
	args := []interface{}{}
	clauses := []string{}

	if availableOnly {
		clauses = append(clauses, "(maximum_participants IS NULL OR current_participants < maximum_participants)")
	}
	if search != "" {
		clauses = append(clauses, "title LIKE ?")
		args = append(args, "%"+search+"%")
	}
	if typeUUID != "" {
		clauses = append(clauses, "event_type = ?")
		args = append(args, typeUUID)
	}
	if len(clauses) > 0 {
		query += " WHERE " + strings.Join(clauses, " AND ")
	}

	query += " ORDER BY created_at DESC LIMIT ? OFFSET ?"
	args = append(args, limit, offset)

	rows, err := Db.Query(query, args...)

	if err != nil {
		return nil, fmt.Errorf("getServicesPage package db : %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {
		var service models.Service
		var idStr string
		var createdByStr string
		var createdAt, updatedAt sql.NullString
		var maxParticipants, currentParticipants sql.NullInt64
		var typeStr string
		err := rows.Scan(&idStr, &service.Name, &service.Description, &service.Price, &typeStr, &service.ServiceDate, &service.ServiceRoad, &service.ServiceCity, &service.ServiceZip, &maxParticipants, &currentParticipants, &createdByStr, &createdAt, &updatedAt)
		if err != nil {
			return nil, fmt.Errorf("getServicesPage package db scan : %s", err.Error())
		}
		service.ID, err = uuid.Parse(idStr)
		if err != nil {
			return nil, fmt.Errorf("getServicesPage package db uuid parse : %s", err.Error())
		}
		service.Type, err = uuid.Parse(typeStr)
		if err != nil {
			return nil, fmt.Errorf("getServicesPage package db uuid parse event_type : %s", err.Error())
		}
		service.CreatedBy, err = uuid.Parse(createdByStr)
		if err != nil {
			return nil, fmt.Errorf("getServicesPage package db uuid parse created_by : %s", err.Error())
		}
		if createdAt.Valid {
			service.CreatedAt = createdAt.String
		}
		if updatedAt.Valid {
			service.UpdatedAt = updatedAt.String
		}
		if maxParticipants.Valid {
			value := int(maxParticipants.Int64)
			service.MaximumParticipants = &value
		}
		if currentParticipants.Valid {
			service.CurrentParticipants = int(currentParticipants.Int64)
		}
		services = append(services, service)
	}

	err = rows.Err()
	if err != nil {
		return nil, fmt.Errorf("getServicesPage package db rows : %s", err.Error())
	}

	return services, nil
}

func CountServicesFromDB(availableOnly bool, search string, typeUUID string) (int, error) {
	query := "SELECT COUNT(*) FROM evenements"
	args := []interface{}{}
	clauses := []string{}
	if availableOnly {
		clauses = append(clauses, "(maximum_participants IS NULL OR current_participants < maximum_participants)")
	}
	if search != "" {
		clauses = append(clauses, "title LIKE ?")
		args = append(args, "%"+search+"%")
	}
	if typeUUID != "" {
		clauses = append(clauses, "event_type = ?")
		args = append(args, typeUUID)
	}
	if len(clauses) > 0 {
		query += " WHERE " + strings.Join(clauses, " AND ")
	}

	var total int
	err := Db.QueryRow(query, args...).Scan(&total)
	if err != nil {
		return 0, fmt.Errorf("countServices package db : %s", err.Error())
	}
	return total, nil
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
	_, err := Db.Exec("INSERT INTO evenements (id, title, description, price, event_type, event_date, event_road, event_city, event_zip_code, maximum_participants, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
		newID, service.Name, service.Description, service.Price, service.Type, service.ServiceDate, service.ServiceRoad, service.ServiceCity, service.ServiceZip, service.MaximumParticipants, service.CreatedBy, currentTime, currentTime)

	if err != nil {
		return fmt.Errorf("createService package db : %s", err.Error())
	}

	return nil

}

func UpdateServiceInDB(serviceID uuid.UUID, service models.Service) error {
	currentTime := getCurrentTime()
	_, err := Db.Exec(
		"UPDATE evenements SET title=?, description=?, price=?, event_type=?, event_date=?, event_road=?, event_city=?, event_zip_code=?, maximum_participants=?, updated_at=? WHERE id=?",
		service.Name, service.Description, service.Price, service.Type, service.ServiceDate, service.ServiceRoad, service.ServiceCity, service.ServiceZip, service.MaximumParticipants, currentTime, serviceID,
	)
	if err != nil {
		return fmt.Errorf("updateService package db : %s", err.Error())
	}
	return nil
}

func DeleteServiceFromDB(serviceID uuid.UUID) error {
	_, err := Db.Exec("DELETE FROM evenements WHERE id = ?", serviceID)
	if err != nil {
		return fmt.Errorf("deleteService package db : %s", err.Error())
	}
	return nil
}

func GetServiceByIDFromDB(serviceID uuid.UUID) (models.Service, error) {
	var service models.Service
	var idStr string
	var createdByStr string
	var createdAt, updatedAt sql.NullString

	var maxParticipants, currentParticipants sql.NullInt64
	var typeStr string
	err := Db.QueryRow("SELECT id, title, description, price, event_type, event_date, event_road, event_city, event_zip_code, maximum_participants, current_participants, created_by, created_at, updated_at FROM evenements WHERE id = ?", serviceID).Scan(&idStr, &service.Name, &service.Description, &service.Price, &typeStr, &service.ServiceDate, &service.ServiceRoad, &service.ServiceCity, &service.ServiceZip, &maxParticipants, &currentParticipants, &createdByStr, &createdAt, &updatedAt)

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

	service.Type, err = uuid.Parse(typeStr)
	if err != nil {
		return service, fmt.Errorf("getServiceByID package db uuid parse event_type : %s", err.Error())
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
	if maxParticipants.Valid {
		value := int(maxParticipants.Int64)
		service.MaximumParticipants = &value
	}
	if currentParticipants.Valid {
		service.CurrentParticipants = int(currentParticipants.Int64)
	}

	return service, nil

}

func CancelAndRefundServiceOrdersFromDB(serviceID uuid.UUID, serviceName string) error {

	rows, err := Db.Query("SELECT id, user_id, amount FROM orders WHERE event_id = ?", serviceID.String())
	if err != nil {
		return fmt.Errorf("cancelServiceOrders query: %s", err.Error())
	}

	type orderRow struct {
		id     string
		userID string
		amount float64
	}

	var orders []orderRow

	for rows.Next() {
		var o orderRow
		if scanErr := rows.Scan(&o.id, &o.userID, &o.amount); scanErr != nil {
			rows.Close()
			return fmt.Errorf("cancelServiceOrders scan: %s", scanErr.Error())
		}

		orders = append(orders, o)
	}

	rows.Close()

	if err = rows.Err(); err != nil {
		return fmt.Errorf("cancelServiceOrders rows err: %s", err.Error())
	}

	for _, o := range orders {
		if o.amount > 0 {
			_, err = Db.Exec("UPDATE users SET balance = balance + ? WHERE id = ?", o.amount, o.userID)

			if err != nil {
				return fmt.Errorf("cancelServiceOrders refund: %s", err.Error())
			}

			var msg string

			msg = fmt.Sprintf("The service \"%s\" has been cancelled. €%.2f has been refunded to your balance.", serviceName, o.amount)

			notifID := uuid.New()

			_, err = Db.Exec("INSERT INTO notifications (id, user_id, annonce_id, message, created_at) VALUES (?, ?, NULL, ?, NOW())", notifID.String(), o.userID, msg)

			if err != nil {
				return fmt.Errorf("cancelServiceOrders notify: %s", err.Error())
			}

		} else {
			var msg string

			msg = fmt.Sprintf("The service \"%s\" has been cancelled.", serviceName)

			notifID := uuid.New()

			_, err = Db.Exec("INSERT INTO notifications (id, user_id, annonce_id, message, created_at) VALUES (?, ?, NULL, ?, NOW())", notifID.String(), o.userID, msg)

			if err != nil {
				return fmt.Errorf("cancelServiceOrders notify: %s", err.Error())
			}

		}
	}

	_, err = Db.Exec("DELETE FROM orders WHERE event_id = ?", serviceID.String())

	if err != nil {
		return fmt.Errorf("cancelServiceOrders delete orders: %s", err.Error())
	}

	return nil

}
