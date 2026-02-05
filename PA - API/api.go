package main

import (
	"API/db"
	"fmt"
	"net/http"
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

	db.Db = db.NewDB()

	http.HandleFunc("GET /{$}", healthCheck)
	fmt.Println("Listening at : http://localhost:9999")
	http.ListenAndServe(":9999", nil)

}
