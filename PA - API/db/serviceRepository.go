package db

import (
	"API/models"
	"database/sql"
	"fmt"
	"strings"

	"github.com/google/uuid"
)

func GetServicesFromDB(search string, typeUUID string, availableOnly bool, employeeUUID string) ([]models.Service, error) {

	services := []models.Service{}

	query := "SELECT id, title, description, price, event_type, event_date, duration_days, estimated_time_minutes, event_road, event_city, event_zip_code, maximum_participants, current_participants, meetingType, onlineMeetingLink, status, created_by, created_at, updated_at FROM evenements"
	args := []interface{}{}
	clauses := []string{}

	if employeeUUID != "" {

		query = "SELECT e.id, e.title, e.description, e.price, e.event_type, e.event_date, e.duration_days, e.estimated_time_minutes, e.event_road, e.event_city, e.event_zip_code, e.maximum_participants, e.current_participants, e.meetingType, e.onlineMeetingLink, e.status, e.created_by, e.created_at, e.updated_at FROM evenements e INNER JOIN affectedEmployees ae ON ae.event_id = e.id AND ae.user_id = ?"
		args = append(args, employeeUUID)
	}

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
		var createdAt, updatedAt, status sql.NullString
		var maxParticipants, currentParticipants sql.NullInt64
		var typeStr string
		var mt sql.NullString
		var link sql.NullString
		err := rows.Scan(&idStr, &service.Name, &service.Description, &service.Price, &typeStr, &service.ServiceDate, &service.DurationDays, &service.EstimatedTimeMinutes, &service.ServiceRoad, &service.ServiceCity, &service.ServiceZip, &maxParticipants, &currentParticipants, &mt, &link, &status, &createdByStr, &createdAt, &updatedAt)
		if err != nil {
			return nil, fmt.Errorf("getServices package db scan : %s", err.Error())
		}
		if mt.Valid {
			service.MeetingType = mt.String
		}
		if link.Valid {
			service.OnlineMeetingLink = link.String
		}
		if status.Valid {
			service.Status = status.String
		} else {
			service.Status = "published"
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

	if err = attachSchedulesToServices(services); err != nil {
		return nil, err
	}

	return services, nil

}

func GetServiceSchedulesFromDB(serviceID uuid.UUID) ([]models.ServiceSchedule, error) {
	schedules := []models.ServiceSchedule{}

	rows, err := Db.Query("SELECT id, hour, is_available FROM event_availability WHERE event_id = ? ORDER BY hour ASC", serviceID.String())
	if err != nil {
		return nil, fmt.Errorf("getServiceSchedules package db : %s", err.Error())
	}
	defer rows.Close()

	for rows.Next() {
		var sched models.ServiceSchedule
		var idStr string
		if err := rows.Scan(&idStr, &sched.Hour, &sched.IsAvailable); err != nil {
			return nil, fmt.Errorf("getServiceSchedules package db scan : %s", err.Error())
		}
		if parsedID, err := uuid.Parse(idStr); err == nil {
			sched.ID = parsedID
		}
		schedules = append(schedules, sched)
	}

	if err = rows.Err(); err != nil {
		return nil, fmt.Errorf("getServiceSchedules package db rows : %s", err.Error())
	}

	return schedules, nil
}

func SaveServiceSchedulesInDB(serviceID uuid.UUID, scheduleData []models.ServiceSchedule) error {
	_, err := Db.Exec("DELETE FROM event_availability WHERE event_id = ?", serviceID.String())
	if err != nil {
		return fmt.Errorf("saveServiceSchedules package db delete existing : %s", err.Error())
	}

	for _, sched := range scheduleData {
		id := sched.ID
		if id == uuid.Nil {
			id = uuid.New()
		}
		_, err = Db.Exec("INSERT INTO event_availability (id, event_id, hour, is_available) VALUES (?, ?, ?, ?)",
			id.String(), serviceID.String(), sched.Hour, sched.IsAvailable)
		if err != nil {
			return fmt.Errorf("saveServiceSchedules package db insert : %s", err.Error())
		}
	}

	return nil
}

func attachSchedulesToServices(services []models.Service) error {
	for i := range services {
		if schedules, err := GetServiceSchedulesFromDB(services[i].ID); err != nil {
			return err
		} else {
			services[i].Schedules = schedules
		}
	}
	return nil
}

func GetServicesPageFromDB(limit int, offset int, availableOnly bool, search string, typeUUID string, employeeUUID string) ([]models.Service, error) {

	services := []models.Service{}
	query := "SELECT id, title, description, price, event_type, event_date, duration_days, estimated_time_minutes, event_road, event_city, event_zip_code, maximum_participants, current_participants, meetingType, onlineMeetingLink, status, created_by, created_at, updated_at FROM evenements"
	args := []interface{}{}
	clauses := []string{}

	if employeeUUID != "" {

		query = "SELECT e.id, e.title, e.description, e.price, e.event_type, e.event_date, e.event_road, e.event_city, e.event_zip_code, e.maximum_participants, e.current_participants, e.meetingType, e.onlineMeetingLink, e.status, e.created_by, e.created_at, e.updated_at FROM evenements e INNER JOIN affectedEmployees ae ON ae.event_id = e.id AND ae.user_id = ?"
		args = append(args, employeeUUID)
	}

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
		var createdAt, updatedAt, status sql.NullString
		var maxParticipants, currentParticipants sql.NullInt64
		var typeStr string
		var mt sql.NullString
		var link sql.NullString
		err := rows.Scan(&idStr, &service.Name, &service.Description, &service.Price, &typeStr, &service.ServiceDate, &service.DurationDays, &service.EstimatedTimeMinutes, &service.ServiceRoad, &service.ServiceCity, &service.ServiceZip, &maxParticipants, &currentParticipants, &mt, &link, &status, &createdByStr, &createdAt, &updatedAt)
		if err != nil {
			return nil, fmt.Errorf("getServicesPage package db scan : %s", err.Error())
		}
		if mt.Valid {
			service.MeetingType = mt.String
		}
		if link.Valid {
			service.OnlineMeetingLink = link.String
		}
		if status.Valid {
			service.Status = status.String
		} else {
			service.Status = "published"
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

	if err = attachSchedulesToServices(services); err != nil {
		return nil, err
	}

	return services, nil
}

func CountFormationsByCreatorFromDB(creatorID uuid.UUID) (int, error) {
	var total int
	err := Db.QueryRow("SELECT COUNT(*) FROM evenements WHERE created_by = ?", creatorID.String()).Scan(&total)
	if err != nil {
		return 0, fmt.Errorf("countFormationsByCreator package db : %s", err.Error())
	}
	return total, nil
}

func GetFormationsByCreatorFromDB(creatorID uuid.UUID, search string, limit int, offset int) ([]models.Service, error) {
	services := []models.Service{}

	query := "SELECT id, title, description, price, event_type, event_date, duration_days, estimated_time_minutes, event_road, event_city, event_zip_code, maximum_participants, current_participants, meetingType, onlineMeetingLink, status, created_by, created_at, updated_at FROM evenements WHERE created_by = ?"
	args := []interface{}{creatorID.String()}

	if search != "" {
		query += " AND title LIKE ?"
		args = append(args, "%"+search+"%")
	}

	query += " ORDER BY created_at DESC LIMIT ? OFFSET ?"
	args = append(args, limit, offset)

	rows, err := Db.Query(query, args...)
	if err != nil {
		return nil, fmt.Errorf("getFormationsByCreator package db : %s", err.Error())
	}
	defer rows.Close()

	for rows.Next() {
		var service models.Service
		var idStr string
		var createdByStr string
		var createdAt, updatedAt, status sql.NullString
		var maxParticipants, currentParticipants sql.NullInt64
		var typeStr string
		var mt sql.NullString
		var link sql.NullString
		err := rows.Scan(&idStr, &service.Name, &service.Description, &service.Price, &typeStr, &service.ServiceDate, &service.DurationDays, &service.EstimatedTimeMinutes, &service.ServiceRoad, &service.ServiceCity, &service.ServiceZip, &maxParticipants, &currentParticipants, &mt, &link, &status, &createdByStr, &createdAt, &updatedAt)
		if err != nil {
			return nil, fmt.Errorf("getFormationsByCreator package db scan : %s", err.Error())
		}
		if mt.Valid {
			service.MeetingType = mt.String
		}
		if link.Valid {
			service.OnlineMeetingLink = link.String
		}
		if status.Valid {
			service.Status = status.String
		} else {
			service.Status = "published"
		}
		service.ID, err = uuid.Parse(idStr)
		if err != nil {
			return nil, fmt.Errorf("getFormationsByCreator package db uuid parse : %s", err.Error())
		}
		service.Type, err = uuid.Parse(typeStr)
		if err != nil {
			return nil, fmt.Errorf("getFormationsByCreator package db uuid parse event_type : %s", err.Error())
		}
		service.CreatedBy, err = uuid.Parse(createdByStr)
		if err != nil {
			return nil, fmt.Errorf("getFormationsByCreator package db uuid parse created_by : %s", err.Error())
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
		return nil, fmt.Errorf("getFormationsByCreator package db rows : %s", err.Error())
	}

	if err = attachSchedulesToServices(services); err != nil {
		return nil, err
	}

	return services, nil
}

func CountPendingFormationsForManagerFromDB(managerID uuid.UUID, search string) (int, error) {
	query := "SELECT COUNT(*) FROM evenements e INNER JOIN users u ON e.created_by = u.id WHERE e.status = 'draft' AND u.manager_id = ?"
	args := []interface{}{managerID.String()}

	if search != "" {
		query += " AND e.title LIKE ?"
		args = append(args, "%"+search+"%")
	}

	var total int
	err := Db.QueryRow(query, args...).Scan(&total)
	if err != nil {
		return 0, fmt.Errorf("countPendingFormationsForManager package db : %s", err.Error())
	}
	return total, nil
}

func GetPendingFormationsForManagerFromDB(managerID uuid.UUID, search string, limit int, offset int) ([]models.Service, error) {
	services := []models.Service{}

	query := "SELECT e.id, e.title, e.description, e.price, e.event_type, e.event_date, e.duration_days, e.estimated_time_minutes, e.event_road, e.event_city, e.event_zip_code, e.maximum_participants, e.current_participants, e.meetingType, e.onlineMeetingLink, e.status, e.created_by, e.created_at, e.updated_at, u.first_name, u.last_name, u.username FROM evenements e INNER JOIN users u ON e.created_by = u.id WHERE e.status = 'draft' AND u.manager_id = ?"
	args := []interface{}{managerID.String()}

	if search != "" {
		query += " AND e.title LIKE ?"
		args = append(args, "%"+search+"%")
	}

	query += " ORDER BY e.created_at DESC LIMIT ? OFFSET ?"
	args = append(args, limit, offset)

	rows, err := Db.Query(query, args...)
	if err != nil {
		return nil, fmt.Errorf("getPendingFormationsForManager package db : %s", err.Error())
	}
	defer rows.Close()

	for rows.Next() {
		var service models.Service
		var idStr string
		var createdByStr string
		var createdAt, updatedAt, status sql.NullString
		var maxParticipants, currentParticipants sql.NullInt64
		var typeStr string
		var mt sql.NullString
		var link sql.NullString
		err := rows.Scan(&idStr, &service.Name, &service.Description, &service.Price, &typeStr, &service.ServiceDate, &service.DurationDays, &service.EstimatedTimeMinutes, &service.ServiceRoad, &service.ServiceCity, &service.ServiceZip, &maxParticipants, &currentParticipants, &mt, &link, &status, &createdByStr, &createdAt, &updatedAt, &service.CreatorFirstName, &service.CreatorLastName, &service.CreatorUsername)
		if err != nil {
			return nil, fmt.Errorf("getPendingFormationsForManager package db scan : %s", err.Error())
		}
		if mt.Valid {
			service.MeetingType = mt.String
		}
		if link.Valid {
			service.OnlineMeetingLink = link.String
		}
		if status.Valid {
			service.Status = status.String
		} else {
			service.Status = "published"
		}
		service.ID, err = uuid.Parse(idStr)
		if err != nil {
			return nil, fmt.Errorf("getPendingFormationsForManager package db uuid parse : %s", err.Error())
		}
		service.Type, err = uuid.Parse(typeStr)
		if err != nil {
			return nil, fmt.Errorf("getPendingFormationsForManager package db uuid parse event_type : %s", err.Error())
		}
		service.CreatedBy, err = uuid.Parse(createdByStr)
		if err != nil {
			return nil, fmt.Errorf("getPendingFormationsForManager package db uuid parse created_by : %s", err.Error())
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
		return nil, fmt.Errorf("getPendingFormationsForManager package db rows : %s", err.Error())
	}

	if err = attachSchedulesToServices(services); err != nil {
		return nil, err
	}

	return services, nil
}

func UpdateServiceStatusInDB(serviceID uuid.UUID, status string) error {
	currentTime := getCurrentTime()
	_, err := Db.Exec("UPDATE evenements SET status = ?, updated_at = ? WHERE id = ?", status, currentTime, serviceID.String())
	if err != nil {
		return fmt.Errorf("updateServiceStatus package db : %s", err.Error())
	}
	return nil
}

func CountServicesFromDB(availableOnly bool, search string, typeUUID string, employeeUUID string) (int, error) {
	query := "SELECT COUNT(*) FROM evenements"
	args := []interface{}{}
	clauses := []string{}
	if employeeUUID != "" {
		query = "SELECT COUNT(*) FROM evenements e INNER JOIN affectedEmployees ae ON ae.event_id = e.id AND ae.user_id = ?"
		args = append(args, employeeUUID)
	}
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

func CreateServiceInDB(service models.Service) (uuid.UUID, error) {

	id := service.ID
	if id == uuid.Nil {
		id = uuid.New()
	}
	currentTime := getCurrentTime()
	status := service.Status
	if status == "" {
		status = "published"
	}
	_, err := Db.Exec("INSERT INTO evenements (id, title, description, price, event_type, event_date, duration_days, estimated_time_minutes, event_road, event_city, event_zip_code, maximum_participants, meetingType, onlineMeetingLink, status, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
		id, service.Name, service.Description, service.Price, service.Type, service.ServiceDate, service.DurationDays, service.EstimatedTimeMinutes, service.ServiceRoad, service.ServiceCity, service.ServiceZip, service.MaximumParticipants, service.MeetingType, service.OnlineMeetingLink, status, service.CreatedBy, currentTime, currentTime)

	if err != nil {
		return uuid.Nil, fmt.Errorf("createService package db : %s", err.Error())
	}

	return id, nil

}

func UpdateServiceInDB(serviceID uuid.UUID, service models.Service) error {
	currentTime := getCurrentTime()
	query := "UPDATE evenements SET title=?, description=?, price=?, event_type=?, event_date=?, duration_days=?, estimated_time_minutes=?, event_road=?, event_city=?, event_zip_code=?, maximum_participants=?, meetingType=?, onlineMeetingLink=?"
	args := []interface{}{service.Name, service.Description, service.Price, service.Type, service.ServiceDate, service.DurationDays, service.EstimatedTimeMinutes, service.ServiceRoad, service.ServiceCity, service.ServiceZip, service.MaximumParticipants, service.MeetingType, service.OnlineMeetingLink}

	if service.Status != "" {
		query += ", status=?"
		args = append(args, service.Status)
	}

	query += ", updated_at=? WHERE id=?"
	args = append(args, currentTime, serviceID)

	_, err := Db.Exec(query, args...)
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
	var createdAt, updatedAt, status sql.NullString

	var maxParticipants, currentParticipants sql.NullInt64
	var typeStr string

	var mt sql.NullString
	var link sql.NullString
	err := Db.QueryRow("SELECT id, title, description, price, event_type, event_date, duration_days, estimated_time_minutes, event_road, event_city, event_zip_code, maximum_participants, current_participants, meetingType, onlineMeetingLink, status, created_by, created_at, updated_at FROM evenements WHERE id = ?", serviceID).Scan(&idStr, &service.Name, &service.Description, &service.Price, &typeStr, &service.ServiceDate, &service.DurationDays, &service.EstimatedTimeMinutes, &service.ServiceRoad, &service.ServiceCity, &service.ServiceZip, &maxParticipants, &currentParticipants, &mt, &link, &status, &createdByStr, &createdAt, &updatedAt)
	if err != nil {
		if err == sql.ErrNoRows {
			return service, fmt.Errorf("service not found")
		}
		return service, fmt.Errorf("getServiceByID package db : %s", err.Error())
	}
	if mt.Valid {
		service.MeetingType = mt.String
	}
	if link.Valid {
		service.OnlineMeetingLink = link.String
	}
	if status.Valid {
		service.Status = status.String
	} else {
		service.Status = "published"
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

	if schedules, err := GetServiceSchedulesFromDB(service.ID); err != nil {
		return service, err
	} else {
		service.Schedules = schedules
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

func GetAffectedEmployeesByServiceIDFromDB(serviceID uuid.UUID) ([]models.AffectedEmployee, error) {

	affectedEmployees := []models.AffectedEmployee{}

	rows, err := Db.Query("SELECT id, user_id, event_id FROM affectedEmployees WHERE event_id = ?", serviceID.String())
	if err != nil {
		return nil, fmt.Errorf("getAffectedEmployeesByServiceID package db : %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {
		var ae models.AffectedEmployee
		var idStr, userIDStr, eventIDStr string
		err := rows.Scan(&idStr, &userIDStr, &eventIDStr)

		if err != nil {
			return nil, fmt.Errorf("getAffectedEmployeesByServiceID package db scan : %s", err.Error())
		}

		ae.ID, err = uuid.Parse(idStr)
		if err != nil {
			return nil, fmt.Errorf("getAffectedEmployeesByServiceID package db uuid parse id : %s", err.Error())
		}

		ae.UserID, err = uuid.Parse(userIDStr)
		if err != nil {
			return nil, fmt.Errorf("getAffectedEmployeesByServiceID package db uuid parse user_id : %s", err.Error())
		}

		ae.EventID, err = uuid.Parse(eventIDStr)
		if err != nil {
			return nil, fmt.Errorf("getAffectedEmployeesByServiceID package db uuid parse event_id : %s", err.Error())
		}

		affectedEmployees = append(affectedEmployees, ae)
	}

	err = rows.Err()
	if err != nil {
		return nil, fmt.Errorf("getAffectedEmployeesByServiceID package db rows : %s", err.Error())
	}

	return affectedEmployees, nil

}

func AddAffectedEmployeeInDB(ae models.AffectedEmployee) (uuid.UUID, error) {

	id := ae.ID
	if id == uuid.Nil {
		id = uuid.New()
	}

	_, err := Db.Exec("INSERT INTO affectedEmployees (id, user_id, event_id) VALUES (?, ?, ?)", id, ae.UserID, ae.EventID)

	if err != nil {
		return uuid.Nil, fmt.Errorf("addAffectedEmployee package db : %s", err.Error())
	}

	return id, nil

}

func RemoveAffectedEmployeeFromDB(aeID uuid.UUID) error {

	_, err := Db.Exec("DELETE FROM affectedEmployees WHERE id = ?", aeID)
	if err != nil {
		return fmt.Errorf("removeAffectedEmployee package db : %s", err.Error())
	}

	return nil

}
