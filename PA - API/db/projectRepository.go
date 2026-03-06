package db

import (
	"API/models"
	"database/sql"
	"fmt"

	"github.com/google/uuid"
)

// ── Projects ──────────────────────────────────────────────────────────────────

func GetProjectsFromDB(offset, limit int, search string) ([]models.Project, int, error) {
	if offset < 0 {
		offset = 0
	}
	if limit < 1 {
		limit = 20
	}

	baseQuery := "SELECT id, user_id, annonce_id, title, description, status, created_at, updated_at FROM projects WHERE status = 1"
	countQuery := "SELECT COUNT(*) FROM projects WHERE status = 1"
	args := []interface{}{}
	countArgs := []interface{}{}

	if search != "" {
		like := fmt.Sprintf("%%%s%%", search)
		baseQuery += " AND (title LIKE ? OR description LIKE ?)"
		countQuery += " AND (title LIKE ? OR description LIKE ?)"
		args = append(args, like, like)
		countArgs = append(countArgs, like, like)
	}

	baseQuery += " ORDER BY created_at DESC LIMIT ? OFFSET ?"
	args = append(args, limit, offset)

	rows, err := Db.Query(baseQuery, args...)
	if err != nil {
		return nil, 0, fmt.Errorf("querying projects: %w", err)
	}
	defer rows.Close()

	projects := []models.Project{}
	for rows.Next() {
		var p models.Project
		var idStr, userIDStr string
		var annonceIDStr sql.NullString
		var createdAt, updatedAt sql.NullString

		if err := rows.Scan(&idStr, &userIDStr, &annonceIDStr, &p.Title, &p.Description, &p.Status, &createdAt, &updatedAt); err != nil {
			return nil, 0, fmt.Errorf("scanning project row: %w", err)
		}
		p.ID, _ = uuid.Parse(idStr)
		p.UserID, _ = uuid.Parse(userIDStr)
		if annonceIDStr.Valid {
			uid, _ := uuid.Parse(annonceIDStr.String)
			p.AnnonceID = &uid
		}
		if createdAt.Valid {
			p.CreatedAt = createdAt.String
		}
		if updatedAt.Valid {
			p.UpdatedAt = updatedAt.String
		}
		projects = append(projects, p)
	}
	if err := rows.Err(); err != nil {
		return nil, 0, fmt.Errorf("iterating project rows: %w", err)
	}

	total := 0
	Db.QueryRow(countQuery, countArgs...).Scan(&total)

	return projects, total, nil
}

func GetProjectByIDFromDB(id string) (*models.Project, error) {
	var p models.Project
	var idStr, userIDStr string
	var annonceIDStr sql.NullString
	var createdAt, updatedAt sql.NullString

	err := Db.QueryRow(
		"SELECT id, user_id, annonce_id, title, description, status, created_at, updated_at FROM projects WHERE id = ?",
		id,
	).Scan(&idStr, &userIDStr, &annonceIDStr, &p.Title, &p.Description, &p.Status, &createdAt, &updatedAt)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, fmt.Errorf("GetProjectByIDFromDB: %w", err)
	}

	p.ID, _ = uuid.Parse(idStr)
	p.UserID, _ = uuid.Parse(userIDStr)
	if annonceIDStr.Valid {
		uid, _ := uuid.Parse(annonceIDStr.String)
		p.AnnonceID = &uid
	}
	if createdAt.Valid {
		p.CreatedAt = createdAt.String
	}
	if updatedAt.Valid {
		p.UpdatedAt = updatedAt.String
	}
	return &p, nil
}

func CreateProjectInDB(p models.Project) (*models.Project, error) {
	id := uuid.New()
	_, err := Db.Exec(
		"INSERT INTO projects (id, user_id, annonce_id, title, description, status) VALUES (?, ?, ?, ?, ?, ?)",
		id.String(), p.UserID.String(), nullableUUID(p.AnnonceID), p.Title, p.Description, p.Status,
	)
	if err != nil {
		return nil, fmt.Errorf("CreateProjectInDB: %w", err)
	}
	p.ID = id
	return &p, nil
}

func UpdateProjectInDB(id string, fields map[string]interface{}) error {
	if len(fields) == 0 {
		return nil
	}
	query := "UPDATE projects SET "
	args := []interface{}{}
	i := 0
	for k, v := range fields {
		if i > 0 {
			query += ", "
		}
		query += k + " = ?"
		args = append(args, v)
		i++
	}
	query += " WHERE id = ?"
	args = append(args, id)
	_, err := Db.Exec(query, args...)
	return err
}

func DeleteProjectFromDB(id string) error {
	_, err := Db.Exec("DELETE FROM projects WHERE id = ?", id)
	return err
}

func GetProjectsByUserIDFromDB(userID string) ([]models.Project, error) {
	rows, err := Db.Query(
		"SELECT id, user_id, annonce_id, title, description, status, created_at, updated_at FROM projects WHERE user_id = ? ORDER BY created_at DESC",
		userID,
	)
	if err != nil {
		return nil, fmt.Errorf("GetProjectsByUserIDFromDB: %w", err)
	}
	defer rows.Close()

	projects := []models.Project{}
	for rows.Next() {
		var p models.Project
		var idStr, userIDStr string
		var annonceIDStr sql.NullString
		var createdAt, updatedAt sql.NullString
		if err := rows.Scan(&idStr, &userIDStr, &annonceIDStr, &p.Title, &p.Description, &p.Status, &createdAt, &updatedAt); err != nil {
			return nil, err
		}
		p.ID, _ = uuid.Parse(idStr)
		p.UserID, _ = uuid.Parse(userIDStr)
		if annonceIDStr.Valid {
			uid, _ := uuid.Parse(annonceIDStr.String)
			p.AnnonceID = &uid
		}
		if createdAt.Valid {
			p.CreatedAt = createdAt.String
		}
		if updatedAt.Valid {
			p.UpdatedAt = updatedAt.String
		}
		projects = append(projects, p)
	}
	return projects, rows.Err()
}

func GetProjectStepsFromDB(projectID string) ([]models.ProjectStep, error) {
	rows, err := Db.Query(
		"SELECT id, project_id, step_order, title, description, duration_minutes, created_at FROM project_steps WHERE project_id = ? ORDER BY step_order ASC",
		projectID,
	)
	if err != nil {
		return nil, fmt.Errorf("GetProjectStepsFromDB: %w", err)
	}
	defer rows.Close()

	steps := []models.ProjectStep{}
	for rows.Next() {
		var s models.ProjectStep
		var idStr, projectIDStr string
		var duration sql.NullInt64
		var createdAt sql.NullString
		if err := rows.Scan(&idStr, &projectIDStr, &s.StepOrder, &s.Title, &s.Description, &duration, &createdAt); err != nil {
			return nil, err
		}
		s.ID, _ = uuid.Parse(idStr)
		s.ProjectID, _ = uuid.Parse(projectIDStr)
		if duration.Valid {
			v := int(duration.Int64)
			s.DurationMinutes = &v
		}
		if createdAt.Valid {
			s.CreatedAt = createdAt.String
		}
		steps = append(steps, s)
	}
	return steps, rows.Err()
}

func CreateProjectStepInDB(s models.ProjectStep) (*models.ProjectStep, error) {
	id := uuid.New()
	_, err := Db.Exec(
		"INSERT INTO project_steps (id, project_id, step_order, title, description, duration_minutes) VALUES (?, ?, ?, ?, ?, ?)",
		id.String(), s.ProjectID.String(), s.StepOrder, s.Title, s.Description, s.DurationMinutes,
	)
	if err != nil {
		return nil, fmt.Errorf("CreateProjectStepInDB: %w", err)
	}
	s.ID = id
	return &s, nil
}

func UpdateProjectStepInDB(id string, fields map[string]interface{}) error {
	if len(fields) == 0 {
		return nil
	}
	query := "UPDATE project_steps SET "
	args := []interface{}{}
	i := 0
	for k, v := range fields {
		if i > 0 {
			query += ", "
		}
		query += k + " = ?"
		args = append(args, v)
		i++
	}
	query += " WHERE id = ?"
	args = append(args, id)
	_, err := Db.Exec(query, args...)
	return err
}

func DeleteProjectStepFromDB(id string) error {
	_, err := Db.Exec("DELETE FROM project_steps WHERE id = ?", id)
	return err
}

func GetStepMaterialsFromDB(stepID string) ([]models.ProjectStepMaterial, error) {
	rows, err := Db.Query(
		"SELECT psm.step_id, psm.facteur_id, psm.quantity, f.nom FROM project_step_materials psm LEFT JOIN facteurs_materiaux f ON psm.facteur_id = f.id WHERE psm.step_id = ?",
		stepID,
	)
	if err != nil {
		return nil, fmt.Errorf("GetStepMaterialsFromDB: %w", err)
	}
	defer rows.Close()

	materials := []models.ProjectStepMaterial{}
	for rows.Next() {
		var m models.ProjectStepMaterial
		var stepIDStr, facteurIDStr string
		var qty sql.NullFloat64
		var nom sql.NullString
		if err := rows.Scan(&stepIDStr, &facteurIDStr, &qty, &nom); err != nil {
			return nil, err
		}
		m.StepID, _ = uuid.Parse(stepIDStr)
		m.FacteurID, _ = uuid.Parse(facteurIDStr)
		if qty.Valid {
			m.Quantity = &qty.Float64
		}
		if nom.Valid {
			m.Nom = nom.String
		}
		materials = append(materials, m)
	}
	return materials, rows.Err()
}

func AddStepMaterialInDB(m models.ProjectStepMaterial) error {
	_, err := Db.Exec(
		"INSERT INTO project_step_materials (step_id, facteur_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)",
		m.StepID.String(), m.FacteurID.String(), m.Quantity,
	)
	return err
}

func DeleteStepMaterialFromDB(stepID, facteurID string) error {
	_, err := Db.Exec(
		"DELETE FROM project_step_materials WHERE step_id = ? AND facteur_id = ?",
		stepID, facteurID,
	)
	return err
}

func LikeProjectInDB(projectID, userID string) error {
	_, err := Db.Exec(
		"INSERT IGNORE INTO project_likes (id, project_id, user_id) VALUES (UUID(), ?, ?)",
		projectID, userID,
	)
	return err
}

func UnlikeProjectFromDB(projectID, userID string) error {
	_, err := Db.Exec(
		"DELETE FROM project_likes WHERE project_id = ? AND user_id = ?",
		projectID, userID,
	)
	return err
}

func GetProjectLikeCountFromDB(projectID string) (int, error) {
	var count int
	err := Db.QueryRow("SELECT COUNT(*) FROM project_likes WHERE project_id = ?", projectID).Scan(&count)
	return count, err
}

func HasUserLikedProjectInDB(projectID, userID string) (bool, error) {
	var count int
	err := Db.QueryRow("SELECT COUNT(*) FROM project_likes WHERE project_id = ? AND user_id = ?", projectID, userID).Scan(&count)
	return count > 0, err
}

func GetProjectCommentsFromDB(projectID string) ([]models.ProjectComment, error) {
	rows, err := Db.Query(
		`SELECT pc.id, pc.project_id, pc.user_id, COALESCE(u.username, '') AS username,
		        pc.parent_id, pc.content, pc.created_at, pc.updated_at
		 FROM project_comments pc
		 LEFT JOIN users u ON u.id = pc.user_id
		 WHERE pc.project_id = ? ORDER BY pc.created_at ASC`,
		projectID,
	)
	if err != nil {
		return nil, fmt.Errorf("GetProjectCommentsFromDB: %w", err)
	}
	defer rows.Close()

	comments := []models.ProjectComment{}
	for rows.Next() {
		var c models.ProjectComment
		var idStr, projectIDStr, userIDStr string
		var parentIDStr sql.NullString
		var createdAt, updatedAt sql.NullString
		if err := rows.Scan(&idStr, &projectIDStr, &userIDStr, &c.Username, &parentIDStr, &c.Content, &createdAt, &updatedAt); err != nil {
			return nil, err
		}
		c.ID, _ = uuid.Parse(idStr)
		c.ProjectID, _ = uuid.Parse(projectIDStr)
		c.UserID, _ = uuid.Parse(userIDStr)
		if parentIDStr.Valid {
			uid, _ := uuid.Parse(parentIDStr.String)
			c.ParentID = &uid
		}
		if createdAt.Valid {
			c.CreatedAt = createdAt.String
		}
		if updatedAt.Valid {
			c.UpdatedAt = updatedAt.String
		}
		comments = append(comments, c)
	}
	return comments, rows.Err()
}

func CreateProjectCommentInDB(c models.ProjectComment) (*models.ProjectComment, error) {
	id := uuid.New()
	_, err := Db.Exec(
		"INSERT INTO project_comments (id, project_id, user_id, parent_id, content) VALUES (?, ?, ?, ?, ?)",
		id.String(), c.ProjectID.String(), c.UserID.String(), nullableUUID(c.ParentID), c.Content,
	)
	if err != nil {
		return nil, fmt.Errorf("CreateProjectCommentInDB: %w", err)
	}
	c.ID = id
	_ = Db.QueryRow("SELECT COALESCE(username,'') FROM users WHERE id = ?", c.UserID.String()).Scan(&c.Username)
	_ = Db.QueryRow("SELECT created_at FROM project_comments WHERE id = ?", id.String()).Scan(&c.CreatedAt)
	return &c, nil
}

func UpdateProjectCommentInDB(id, content string) error {
	_, err := Db.Exec("UPDATE project_comments SET content = ? WHERE id = ?", content, id)
	return err
}

func DeleteProjectCommentFromDB(id string) error {
	_, err := Db.Exec("DELETE FROM project_comments WHERE id = ?", id)
	return err
}

func GetProjectCommentByIDFromDB(id string) (*models.ProjectComment, error) {
	var c models.ProjectComment
	var idStr, projectIDStr, userIDStr string
	var parentIDStr sql.NullString
	var createdAt, updatedAt sql.NullString
	err := Db.QueryRow(
		`SELECT pc.id, pc.project_id, pc.user_id, COALESCE(u.username,'') AS username,
		        pc.parent_id, pc.content, pc.created_at, pc.updated_at
		 FROM project_comments pc LEFT JOIN users u ON u.id = pc.user_id WHERE pc.id = ?`,
		id,
	).Scan(&idStr, &projectIDStr, &userIDStr, &c.Username, &parentIDStr, &c.Content, &createdAt, &updatedAt)
	if err == sql.ErrNoRows {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}
	c.ID, _ = uuid.Parse(idStr)
	c.ProjectID, _ = uuid.Parse(projectIDStr)
	c.UserID, _ = uuid.Parse(userIDStr)
	if parentIDStr.Valid {
		uid, _ := uuid.Parse(parentIDStr.String)
		c.ParentID = &uid
	}
	if createdAt.Valid {
		c.CreatedAt = createdAt.String
	}
	if updatedAt.Valid {
		c.UpdatedAt = updatedAt.String
	}
	return &c, nil
}
