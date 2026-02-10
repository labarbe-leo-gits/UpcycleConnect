package db

import (
	"API/models"
	"database/sql"
	"fmt"
	"github.com/google/uuid"
)

func GetOrdersFromDB() ([]models.Order, error) {

	orders := []models.Order{}
	rows, err := Db.Query("SELECT id, user_id, event_id, product_id, transaction_id, amount, status, created_at, updated_at FROM orders")

	if err != nil {
		return nil, fmt.Errorf("getOrders package db : %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {
		var order models.Order
		var idStr, userIDStr, eventIDStr, productIDStr sql.NullString
		err := rows.Scan(&idStr, &userIDStr, &eventIDStr, &productIDStr, &order.TransactionID, &order.Amount, &order.Status, &order.CreatedAt, &order.UpdatedAt)
		if err != nil {
			return nil, fmt.Errorf("getOrders package db scan : %s", err.Error())
		}
		if idStr.Valid {
			order.ID, err = uuid.Parse(idStr.String)
			if err != nil {
				return nil, fmt.Errorf("getOrders package db uuid parse id : %s", err.Error())
			}
		}
		if userIDStr.Valid {
			order.UserID, err = uuid.Parse(userIDStr.String)
			if err != nil {
				return nil, fmt.Errorf("getOrders package db uuid parse user_id : %s", err.Error())
			}
		}
		if eventIDStr.Valid {
			order.EventID, err = uuid.Parse(eventIDStr.String)
			if err != nil {
				return nil, fmt.Errorf("getOrders package db uuid parse event_id : %s", err.Error())
			}
		}
		if productIDStr.Valid {
			order.ProductID, err = uuid.Parse(productIDStr.String)
			if err != nil {
				return nil, fmt.Errorf("getOrders package db uuid parse product_id : %s", err.Error())
			}
		}
		orders = append(orders, order)
	}

	err = rows.Err()
	if err != nil {
		return nil, fmt.Errorf("getOrders package db rows : %s", err.Error())
	}

	return orders, nil

}

func CreateOrderInDB(order models.Order) error {

	newID := uuid.New()
	_, err := Db.Exec("INSERT INTO orders (id, user_id, event_id, product_id, transaction_id, amount, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())", newID, order.UserID, order.EventID, order.ProductID, order.TransactionID, order.Amount, order.Status)
	if err != nil {
		return fmt.Errorf("createOrder package db : %s", err.Error())
	}

	return nil

}