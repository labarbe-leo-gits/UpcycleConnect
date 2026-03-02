package db

import (
	"API/models"
	"database/sql"
	"fmt"

	"github.com/google/uuid"
)

func GetForumsFromDB() ([]models.Forum, error) {

	forums := []models.Forum{}
	rows, err := Db.Query("SELECT id, title, description, created_by, created_at, updated_at FROM forum")

	if err != nil {
		return nil, fmt.Errorf("getForums package db : %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {
		var forum models.Forum
		var idStr string
		var createdByStr string
		var createdAt sql.NullString
		var updatedAt sql.NullString
		err := rows.Scan(&idStr, &forum.Title, &forum.Description, &createdByStr, &createdAt, &updatedAt)
		if err != nil {
			return nil, fmt.Errorf("getForums package db scan : %s", err.Error())
		}

		forum.ID, err = uuid.Parse(idStr)
		if err != nil {
			return nil, fmt.Errorf("getForums package db uuid parse : %s", err.Error())
		}

		forum.CreatedBy, err = uuid.Parse(createdByStr)
		if err != nil {
			return nil, fmt.Errorf("getForums package db uuid parse created_by : %s", err.Error())
		}

		if createdAt.Valid {
			forum.CreatedAt = createdAt.String
		}

		if updatedAt.Valid {
			forum.UpdatedAt = updatedAt.String
		}

		forums = append(forums, forum)
	}

	err = rows.Err()
	if err != nil {
		return nil, fmt.Errorf("getForums package db rows : %s", err.Error())
	}

	return forums, nil
}

func GetForumsPageFromDB(offset int, limit int, sort string) ([]models.Forum, int, error) {
	forums := []models.Forum{}

	var total int
	err := Db.QueryRow("SELECT COUNT(id) FROM forum").Scan(&total)
	if err != nil {
		return nil, 0, fmt.Errorf("getForumsPage count: %v", err)
	}

	baseQuery := `SELECT f.id, f.title, f.description, f.created_by, f.created_at, f.updated_at, COALESCE(fp.post_count,0) as post_count
	FROM forum f
	LEFT JOIN (SELECT forum_id, COUNT(*) as post_count FROM forum_posts GROUP BY forum_id) fp ON fp.forum_id = f.id`

	orderClause := " ORDER BY f.updated_at DESC"
	if sort == "trending" {
		orderClause = " ORDER BY COALESCE(fp.post_count,0) DESC, f.updated_at DESC"
	}

	query := baseQuery + orderClause + " LIMIT ? OFFSET ?"

	rows, err := Db.Query(query, limit, offset)
	if err != nil {
		return nil, 0, fmt.Errorf("getForumsPage query: %v", err)
	}
	defer rows.Close()

	for rows.Next() {
		var forum models.Forum
		var idStr string
		var createdByStr string
		var createdAt sql.NullString
		var updatedAt sql.NullString
		var postCount sql.NullInt64

		err := rows.Scan(&idStr, &forum.Title, &forum.Description, &createdByStr, &createdAt, &updatedAt, &postCount)
		if err != nil {
			return nil, 0, fmt.Errorf("getForumsPage scan: %v", err)
		}

		forum.ID, err = uuid.Parse(idStr)
		if err != nil {
			return nil, 0, fmt.Errorf("getForumsPage uuid: %v", err)
		}

		forum.CreatedBy, err = uuid.Parse(createdByStr)
		if err != nil {
			return nil, 0, fmt.Errorf("getForumsPage created_by uuid: %v", err)
		}

		if createdAt.Valid {
			forum.CreatedAt = createdAt.String
		}
		if updatedAt.Valid {
			forum.UpdatedAt = updatedAt.String
		}

		if postCount.Valid {
			forum.PostCount = int(postCount.Int64)
		} else {
			forum.PostCount = 0
		}

		var latest sql.NullString
		row := Db.QueryRow("SELECT content FROM forum_posts WHERE forum_id = ? ORDER BY created_at DESC LIMIT 1", forum.ID.String())
		if err2 := row.Scan(&latest); err2 == nil && latest.Valid {
			forum.LatestPost = latest.String
		}

		forums = append(forums, forum)
	}

	if err := rows.Err(); err != nil {
		return nil, 0, fmt.Errorf("getForumsPage rows: %v", err)
	}

	return forums, total, nil
}

func GetForumPostsFromDB(forumIDStr string) ([]models.ForumPost, error) {

	posts := []models.ForumPost{}
	rows, err := Db.Query("SELECT id, forum_id, parent_id, content, user_id, created_at, updated_at FROM forum_posts WHERE forum_id = ? ORDER BY created_at DESC", forumIDStr)

	if err != nil {
		return nil, fmt.Errorf("getForumPosts package db : %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {
		var post models.ForumPost
		var idStr string
		var forumIDStr string
		var parentIDStr sql.NullString
		var createdByStr string
		var createdAt sql.NullString
		var updatedAt sql.NullString
		err := rows.Scan(&idStr, &forumIDStr, &parentIDStr, &post.Content, &createdByStr, &createdAt, &updatedAt)
		if err != nil {
			return nil, fmt.Errorf("getForumPosts package db scan : %s", err.Error())
		}

		post.ID, err = uuid.Parse(idStr)
		if err != nil {
			return nil, fmt.Errorf("getForumPosts package db uuid parse : %s", err.Error())
		}

		post.ForumID, err = uuid.Parse(forumIDStr)
		if err != nil {
			return nil, fmt.Errorf("getForumPosts package db uuid parse forum_id : %s", err.Error())
		}

		post.AuthorID, err = uuid.Parse(createdByStr)
		if err != nil {
			return nil, fmt.Errorf("getForumPosts package db uuid parse created_by : %s", err.Error())
		}
		if parentIDStr.Valid {
			post.ParentID, err = uuid.Parse(parentIDStr.String)
			if err != nil {
				return nil, fmt.Errorf("getForumPosts package db uuid parse parent_id : %s", err.Error())
			}
		}
		if createdAt.Valid {
			post.CreatedAt = createdAt.String
		}
		if updatedAt.Valid {
			post.UpdatedAt = updatedAt.String
		}

		posts = append(posts, post)
	}

	err = rows.Err()
	if err != nil {
		return nil, fmt.Errorf("getForumPosts package db rows : %s", err.Error())
	}

	return posts, nil
}

func CreateForumInDB(forum models.Forum) error {

	newID := uuid.New()
	userID := forum.CreatedBy
	currentTime := getCurrentTime()

	_, err := Db.Exec("INSERT INTO forum (id, title, description, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)", newID.String(), forum.Title, forum.Description, userID.String(), currentTime, currentTime)
	if err != nil {
		return fmt.Errorf("createForum package db : %s", err.Error())
	}

	return nil

}

func CreateForumPostInDB(post models.ForumPost) error {

	newID := uuid.New()
	currentTime := getCurrentTime()

	var parent sql.NullString
	if post.ParentID != uuid.Nil {
		parent.String = post.ParentID.String()
		parent.Valid = true
	}

	_, err := Db.Exec("INSERT INTO forum_posts (id, forum_id, parent_id, content, user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)",
		newID.String(),
		post.ForumID.String(),
		parent,
		post.Content,
		post.AuthorID.String(),
		currentTime,
		currentTime)
	if err != nil {
		return fmt.Errorf("createForumPost package db : %s", err.Error())
	}

	return nil

}

func GetForumByIDFromDB(forumIDStr string) (*models.Forum, error) {

	var forum models.Forum

	row := Db.QueryRow("SELECT id, title, description, created_by, created_at, updated_at FROM forum WHERE id = ?", forumIDStr)

	var idStr string
	var createdByStr string
	var createdAt sql.NullString
	var updatedAt sql.NullString

	err := row.Scan(&idStr, &forum.Title, &forum.Description, &createdByStr, &createdAt, &updatedAt)
	if err != nil {
		if err == sql.ErrNoRows {
			return nil, nil
		}
		return nil, fmt.Errorf("getForumByID package db scan : %s", err.Error())
	}

	forum.ID, err = uuid.Parse(idStr)
	if err != nil {
		return nil, fmt.Errorf("getForumByID package db uuid parse : %s", err.Error())
	}

	forum.CreatedBy, err = uuid.Parse(createdByStr)
	if err != nil {
		return nil, fmt.Errorf("getForumByID package db uuid parse created_by : %s", err.Error())
	}

	if createdAt.Valid {
		forum.CreatedAt = createdAt.String
	}

	if updatedAt.Valid {
		forum.UpdatedAt = updatedAt.String
	}

	return &forum, nil

}

func UpdateForumPostInDB(postIDStr string, content string) error {

	currentTime := getCurrentTime()

	_, err := Db.Exec("UPDATE forum_posts SET content = ?, updated_at = ? WHERE id = ?", content, currentTime, postIDStr)
	if err != nil {
		return fmt.Errorf("updateForumPost package db : %s", err.Error())
	}

	return nil

}

func DeleteForumPostFromDB(postIDStr string) error {

	_, err := Db.Exec("DELETE FROM forum_posts WHERE id = ?", postIDStr)

	if err != nil {
		return fmt.Errorf("deleteForumPost package db : %s", err.Error())
	}

	return nil
}

func DeleteForumFromDB(forumIDStr string) error {

	_, err := Db.Exec("DELETE FROM forums WHERE id = ?", forumIDStr)

	if err != nil {
		return fmt.Errorf("deleteForum package db : %s", err.Error())
	}

	return nil

}

func UpdateForumInDB(idForum string, forumDto models.Forum) error {

	currentTime := getCurrentTime()

	_, err := Db.Exec("UPDATE forum SET title = ?, description = ?, updated_at = ? WHERE id = ?", forumDto.Title, forumDto.Description, currentTime, idForum)
	if err != nil {
		return fmt.Errorf("updateForum package db : %s", err.Error())
	}

	return nil

}
