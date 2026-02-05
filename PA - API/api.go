package main

import (
	"API/db"
	"fmt"
	"net/http"
	"os"

	"github.com/joho/godotenv"
)

func healthCheck(w http.ResponseWriter, r *http.Request) {

	err := db.Db.Ping()

	if err != nil {
		http.Error(w, "Database connection failed", http.StatusInternalServerError)
		return
	}

	fmt.Fprintf(w, "Ping OK")

}

func main(){

	err := godotenv.Load("../PA - Site Principal/.env")
	if err != nil {
		fmt.Printf("Error loading .env file: %s", err.Error())
		return
	}

	port := os.Getenv("API_PORT")
	host := os.Getenv("API_HOST")

	db.Db = db.NewDB()

	http.HandleFunc("GET /{$}", healthCheck)
	fmt.Println("Listening at : " + host + ":" + port)
	http.ListenAndServe(host + ":" + port, nil)

}
