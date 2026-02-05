package db

import(
	"API/models"
	"fmt"
)

func GetAllUsersFromDB() ([]models.User, error) {

	users := []models.User{}
	rows, err := Db.Query("SELECT id, username, email, created_at, last_login FROM users")

	if err != nil {
		return nil, fmt.Errorf("getUsers package db : %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {
		var user models.User
		err := rows.Scan(&user.ID, &user.Username, &user.Password, &user.LastLogin)
		if err != nil {
			return nil, fmt.Errorf("getUsers package db scan : %s", err.Error())
		}
		users = append(users, user)
	}

	err = rows.Err()
	if err != nil {
		return nil, fmt.Errorf("getUsers package db rows : %s", err.Error())
	}

	return users, nil

}