package db

import(
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

func GetForumPostsFromDB(forumIDStr string) ([]models.ForumPost, error) {

	posts := []models.ForumPost{}
	rows, err := Db.Query("SELECT id, forum_id, content, author_id, created_at FROM forum_post WHERE forum_id = ?", forumIDStr)

	if err != nil {
		return nil, fmt.Errorf("getForumPosts package db : %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {
		var post models.ForumPost
		var idStr string
		var forumIDStr string
		var createdByStr string
		var createdAt sql.NullString
		err := rows.Scan(&idStr, &forumIDStr, &post.Content, &createdByStr, &createdAt)
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

		if createdAt.Valid {
			post.CreatedAt = createdAt.String
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

	_, err := Db.Exec("INSERT INTO forum_post (id, forum_id, content, author_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)", newID.String(), post.ForumID.String(), post.Content, post.AuthorID.String(), currentTime, currentTime)
	if err != nil {
		return fmt.Errorf("createForumPost package db : %s", err.Error())
	}

	return nil

}
