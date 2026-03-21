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

	fmt.Println("Database connection established")
	return db

}
