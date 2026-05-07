// Connection to the database for the API

package db

import (
	"database/sql"
	"fmt"
	"os"

	_ "github.com/go-sql-driver/mysql"
	"github.com/joho/godotenv"
)

const driver = "mysql"

var (
	host     string
	port     string
	user     string
	password string
	name     string
)

func init() {
	err := godotenv.Load()
	if err != nil {
		fmt.Printf("Error loading .env file: %s\n", err.Error())
		fmt.Println("Continuing with system environment variables")
	} else {
		fmt.Println("Environment variables loaded from .env")
	}

	host = os.Getenv("DB_HOST")
	port = os.Getenv("DB_PORT")
	user = os.Getenv("DB_USER")
	password = os.Getenv("DB_PASSWORD")
	name = os.Getenv("DB_NAME")

	if host == "" || port == "" || user == "" || password == "" || name == "" {
		fmt.Println("Warning: database env vars are missing or incomplete in environment")
	}
}

var Db *sql.DB

func NewDB() *sql.DB {

	sqlInfo := fmt.Sprintf("%s:%s@tcp(%s:%s)/%s", user, password, host, port, name)
	db, err := sql.Open(driver, sqlInfo)

	if err != nil {
		panic(err.Error())
	}

	if err := db.Ping(); err != nil {
		panic(fmt.Errorf("failed to ping database: %w", err))
	}

	if err := ensureFavoritesTable(db); err != nil {
		panic(fmt.Errorf("failed to ensure favorites table: %w", err))
	}

	fmt.Println("Database connection established")
	return db

}

// DEBUG AHHHHHHHHHHH
func ensureFavoritesTable(db *sql.DB) error {
	_, err := db.Exec(`CREATE TABLE IF NOT EXISTS favorites (
		id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
		user_id CHAR(36) NOT NULL,
		annonce_id CHAR(36) NOT NULL,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		UNIQUE INDEX idx_favorite (user_id, annonce_id),
		INDEX idx_favorites_user_id (user_id),
		INDEX idx_favorites_annonce_id (annonce_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;`)
	return err
}
