package db

// MySQL database connection

import (
	"database/sql"
	"fmt"
	"os"
	"github.com/joho/godotenv"
	_ "github.com/go-sql-driver/mysql"
)

const driver = "mysql"

var (
	host	 string
	port	 string
	user	 string
	password string
	name	 string
)

func init(){
	err := godotenv.Load()
	if err != nil {
		fmt.Printf("Error loading .env file: %s", err.Error())
		return
	}
	host = os.Getenv("DB_HOST")
	port = os.Getenv("DB_PORT")
	user = os.Getenv("DB_USER")
	password = os.Getenv("DB_PASSWORD")
	name = os.Getenv("DB_NAME")

	fmt.Println("Environment variables loaded")
}

var Db *sql.DB

func NewDB() (*sql.DB) {

	sqlInfo := fmt.Sprintf("%s:%s@tcp(%s:%s)/%s", user, password, host, port, name)
	db, err := sql.Open(driver, sqlInfo)

	if err != nil {
		panic(err.Error())
	}

	fmt.Println("Database connection established")
	return db

}