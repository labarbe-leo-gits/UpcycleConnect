package db

import (
	"API/models"
	"database/sql"
	"fmt"
	"strings"
	"time"

	"github.com/google/uuid"
)

func CreateRefundRequestInDB(refundRequest models.RefundRequest) (err error) {
	newID := uuid.New()
	currentTime := time.Now().UTC().Format("2006-01-02 15:04:05")

	_, err = Db.Exec(
		"INSERT INTO refundsRequests (id, order_id, user_id, reason, status, admin_comment, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
		newID.String(), refundRequest.OrderID.String(), refundRequest.UserID.String(), refundRequest.Reason, 0, refundRequest.AdminComment, currentTime, currentTime,
	)

	if err != nil {
		return fmt.Errorf("createRefundRequestInDB: %s", err.Error())
	}

	return nil
}

func GetRefundRequestByIDFromDB(refundRequestID uuid.UUID) (*models.RefundRequest, error) {
	var refundRequest models.RefundRequest
	var approvedBy, adminComment, userName, orderTitle, approverName sql.NullString

	query := `SELECT rr.id, rr.order_id, rr.user_id, rr.reason, rr.status, rr.admin_comment, rr.created_at, rr.updated_at, rr.approved_by, u.username as user_name, a.title as order_title, ap.username as approver_name
		FROM refundsRequests rr
		LEFT JOIN users u ON rr.user_id = u.id
		LEFT JOIN users ap ON rr.approved_by = ap.id
		LEFT JOIN orders o ON rr.order_id = o.id
		LEFT JOIN annonces a ON o.product_id = a.id
		WHERE rr.id = ?`

	err := Db.QueryRow(query, refundRequestID.String()).Scan(
		&refundRequest.ID,
		&refundRequest.OrderID,
		&refundRequest.UserID,
		&refundRequest.Reason,
		&refundRequest.Status,
		&adminComment,
		&refundRequest.CreatedAt,
		&refundRequest.UpdatedAt,
		&approvedBy,
		&userName,
		&orderTitle,
		&approverName,
	)
	if err != nil {
		return nil, fmt.Errorf("getRefundRequestByIDFromDB: %s", err.Error())
	}

	if approvedBy.Valid {
		approvedUUID, err := uuid.Parse(approvedBy.String)
		if err != nil {
			return nil, fmt.Errorf("getRefundRequestByIDFromDB parse approver_id: %s", err.Error())
		}
		refundRequest.ApproverID = approvedUUID
	}

	if adminComment.Valid {
		refundRequest.AdminComment = adminComment.String
	}
	if userName.Valid {
		refundRequest.UserName = userName.String
	}
	if orderTitle.Valid {
		refundRequest.OrderTitle = orderTitle.String
	}
	if approverName.Valid {
		refundRequest.ApproverName = approverName.String
	}

	return &refundRequest, nil
}

func GetAllRefundRequestsFromDB() ([]models.RefundRequest, error) {
	query := `SELECT rr.id, rr.order_id, rr.user_id, rr.reason, rr.status, rr.admin_comment, rr.created_at, rr.updated_at, rr.approved_by, u.username as user_name, a.title as order_title, ap.username as approver_name
		FROM refundsRequests rr
		LEFT JOIN users u ON rr.user_id = u.id
		LEFT JOIN users ap ON rr.approved_by = ap.id
		LEFT JOIN orders o ON rr.order_id = o.id
		LEFT JOIN annonces a ON o.product_id = a.id`
	rows, err := Db.Query(query)
	if err != nil {
		return nil, fmt.Errorf("getAllRefundRequestsFromDB: %s", err.Error())
	}
	defer rows.Close()

	refundRequests := []models.RefundRequest{}

	for rows.Next() {
		var refundRequest models.RefundRequest
		var idStr, orderIDStr, userIDStr, approverIDStr, adminComment, userName, orderTitle, approverName sql.NullString
		var statusVal int
		if err := rows.Scan(&idStr, &orderIDStr, &userIDStr, &refundRequest.Reason, &statusVal, &adminComment, &refundRequest.CreatedAt, &refundRequest.UpdatedAt, &approverIDStr, &userName, &orderTitle, &approverName); err != nil {
			return nil, fmt.Errorf("getAllRefundRequestsFromDB scan: %s", err.Error())
		}

		if idStr.Valid {
			refundRequest.ID, err = uuid.Parse(idStr.String)
			if err != nil {
				return nil, fmt.Errorf("getAllRefundRequestsFromDB id parse: %s", err.Error())
			}
		}

		if orderIDStr.Valid {
			refundRequest.OrderID, err = uuid.Parse(orderIDStr.String)
			if err != nil {
				return nil, fmt.Errorf("getAllRefundRequestsFromDB order_id parse: %s", err.Error())
			}
		}

		if userIDStr.Valid {
			refundRequest.UserID, err = uuid.Parse(userIDStr.String)
			if err != nil {
				return nil, fmt.Errorf("getAllRefundRequestsFromDB user_id parse: %s", err.Error())
			}
		}

		refundRequest.Status = statusVal

		if adminComment.Valid {
			refundRequest.AdminComment = adminComment.String
		}
		if userName.Valid {
			refundRequest.UserName = userName.String
		}
		if orderTitle.Valid {
			refundRequest.OrderTitle = orderTitle.String
		}
		if approverName.Valid {
			refundRequest.ApproverName = approverName.String
		}

		if approverIDStr.Valid {
			approvedUUID, err := uuid.Parse(approverIDStr.String)
			if err == nil {
				refundRequest.ApproverID = approvedUUID
			}
		}

		refundRequests = append(refundRequests, refundRequest)
	}

	if rowsErr := rows.Err(); rowsErr != nil {
		return nil, fmt.Errorf("getAllRefundRequestsFromDB rows: %s", rowsErr.Error())
	}

	return refundRequests, nil
}

func UpdateRefundRequestStatusInDB(refundRequestID uuid.UUID, status int, approverID uuid.UUID, adminComment string) error {
	currentTime := time.Now().UTC().Format("2006-01-02 15:04:05")
	query := "UPDATE refundsRequests SET status = ?, approved_by = ?, admin_comment = ?, updated_at = ? WHERE id = ?"
	params := []interface{}{status, nil, adminComment, currentTime, refundRequestID.String()}

	if approverID != uuid.Nil {
		params[1] = approverID.String()
	}

	result, err := Db.Exec(query, params...)
	if err != nil {
		return fmt.Errorf("updateRefundRequestStatusInDB: %s", err.Error())
	}

	rowsAffected, err := result.RowsAffected()
	if err != nil {
		return fmt.Errorf("updateRefundRequestStatusInDB rows affected: %s", err.Error())
	}

	if rowsAffected == 0 {
		return fmt.Errorf("refund request not found")
	}

	return nil
}

func SearchRefundRequestsInDB(status *int, userID *uuid.UUID, orderID *uuid.UUID, searchTerm string) ([]models.RefundRequest, error) {
	filters := []string{}
	params := []interface{}{}

	if status != nil {
		filters = append(filters, "status = ?")
		params = append(params, *status)
	}
	if userID != nil {
		filters = append(filters, "user_id = ?")
		params = append(params, userID.String())
	}
	if orderID != nil {
		filters = append(filters, "order_id = ?")
		params = append(params, orderID.String())
	}
	if strings.TrimSpace(searchTerm) != "" {
		filters = append(filters, "(reason LIKE ?)")
		params = append(params, "%"+strings.TrimSpace(searchTerm)+"%")
	}

	query := `SELECT rr.id, rr.order_id, rr.user_id, rr.reason, rr.status, rr.admin_comment, rr.created_at, rr.updated_at, rr.approved_by, u.username as user_name, a.title as order_title, ap.username as approver_name
		FROM refundsRequests rr
		LEFT JOIN users u ON rr.user_id = u.id
		LEFT JOIN users ap ON rr.approved_by = ap.id
		LEFT JOIN orders o ON rr.order_id = o.id
		LEFT JOIN annonces a ON o.product_id = a.id`
	if len(filters) > 0 {
		query += " WHERE " + strings.Join(filters, " AND ")
	}

	rows, err := Db.Query(query, params...)
	if err != nil {
		return nil, fmt.Errorf("searchRefundRequestsInDB: %s", err.Error())
	}
	defer rows.Close()

	refundRequests := []models.RefundRequest{}

	for rows.Next() {
		var refundRequest models.RefundRequest
		var idStr, orderIDStr, userIDStr, approverIDStr, adminComment, userName, orderTitle, approverName sql.NullString
		var statusVal int
		if err := rows.Scan(&idStr, &orderIDStr, &userIDStr, &refundRequest.Reason, &statusVal, &adminComment, &refundRequest.CreatedAt, &refundRequest.UpdatedAt, &approverIDStr, &userName, &orderTitle, &approverName); err != nil {
			return nil, fmt.Errorf("searchRefundRequestsInDB scan: %s", err.Error())
		}

		if idStr.Valid {
			refundRequest.ID, err = uuid.Parse(idStr.String)
			if err != nil {
				return nil, fmt.Errorf("searchRefundRequestsInDB id parse: %s", err.Error())
			}
		}

		if orderIDStr.Valid {
			refundRequest.OrderID, err = uuid.Parse(orderIDStr.String)
			if err != nil {
				return nil, fmt.Errorf("searchRefundRequestsInDB order_id parse: %s", err.Error())
			}
		}

		if userIDStr.Valid {
			refundRequest.UserID, err = uuid.Parse(userIDStr.String)
			if err != nil {
				return nil, fmt.Errorf("searchRefundRequestsInDB user_id parse: %s", err.Error())
			}
		}

		refundRequest.Status = statusVal

		if adminComment.Valid {
			refundRequest.AdminComment = adminComment.String
		}
		if userName.Valid {
			refundRequest.UserName = userName.String
		}
		if orderTitle.Valid {
			refundRequest.OrderTitle = orderTitle.String
		}
		if approverName.Valid {
			refundRequest.ApproverName = approverName.String
		}

		if approverIDStr.Valid {
			approvedUUID, err := uuid.Parse(approverIDStr.String)
			if err == nil {
				refundRequest.ApproverID = approvedUUID
			}
		}

		refundRequests = append(refundRequests, refundRequest)
	}

	if rowsErr := rows.Err(); rowsErr != nil {
		return nil, fmt.Errorf("searchRefundRequestsInDB rows: %s", rowsErr.Error())
	}

	return refundRequests, nil
}
