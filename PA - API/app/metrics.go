package app

import (
	"API/db"
	"encoding/json"
	"fmt"
	"net/http"
	"sort"
	"time"
)

func GetDashboardMetrics(w http.ResponseWriter, r *http.Request) {

	var (
		userCount, userCountYesterday int
		newUsersToday                 int

		containerCount, containerCountYesterday int
		newDepositsToday                        int

		projectCount, projectCountYesterday int
		aiSum                               int

		totalIncome, todayIncome, yesterdayIncome float64
	)

	today := time.Now().Format("2006-01-02")
	yesterday := time.Now().Add(-24 * time.Hour).Format("2006-01-02")

	db.Db.QueryRow("SELECT COUNT(id) FROM users").Scan(&userCount)
	db.Db.QueryRow("SELECT COUNT(id) FROM users WHERE DATE(created_at) < ?", today).Scan(&userCountYesterday)
	db.Db.QueryRow("SELECT COUNT(id) FROM users WHERE DATE(created_at) = ?", today).Scan(&newUsersToday)

	db.Db.QueryRow("SELECT COUNT(id) FROM conteneurs").Scan(&containerCount)
	db.Db.QueryRow("SELECT COUNT(id) FROM conteneurs WHERE DATE(created_at) < ?", today).Scan(&containerCountYesterday)
	db.Db.QueryRow("SELECT COUNT(id) FROM demandes_depot WHERE DATE(created_at) = ?", today).Scan(&newDepositsToday)

	db.Db.QueryRow("SELECT COUNT(id) FROM projects").Scan(&projectCount)
	db.Db.QueryRow("SELECT COUNT(id) FROM projects WHERE DATE(created_at) < ?", today).Scan(&projectCountYesterday)
	db.Db.QueryRow("SELECT COALESCE(SUM(ai_generated),0) FROM projects").Scan(&aiSum)

	db.Db.QueryRow("SELECT COALESCE(SUM(amount),0) FROM orders").Scan(&totalIncome)
	db.Db.QueryRow("SELECT COALESCE(SUM(amount),0) FROM orders WHERE DATE(created_at) = ?", today).Scan(&todayIncome)
	db.Db.QueryRow("SELECT COALESCE(SUM(amount),0) FROM orders WHERE DATE(created_at) = ?", yesterday).Scan(&yesterdayIncome)

	userDelta := userCount - userCountYesterday
	userPct := 0.0
	if userCountYesterday > 0 {
		userPct = float64(userDelta) * 100.0 / float64(userCountYesterday)
	}

	loginRows, err := db.Db.Query("SELECT DATE(last_login), COUNT(id) FROM users WHERE last_login IS NOT NULL GROUP BY DATE(last_login)")
	if err != nil {
		fmt.Println("[ERROR] login query:", err)
	} else {
		defer loginRows.Close()
	}
	loginCounts := make(map[string]int)
	var loginDate string
	var loginCnt int
	for loginRows != nil && loginRows.Next() {
		if err := loginRows.Scan(&loginDate, &loginCnt); err == nil {
			loginCounts[loginDate] = loginCnt
		}
	}

	loginDates := []string{}
	for d := range loginCounts {
		loginDates = append(loginDates, d)
	}
	sort.Strings(loginDates)
	loginSeries := []int{}
	for _, d := range loginDates {
		loginSeries = append(loginSeries, loginCounts[d])
	}

	containerDelta := containerCount - containerCountYesterday
	containerPct := 0.0
	if containerCountYesterday > 0 {
		containerPct = float64(containerDelta) * 100.0 / float64(containerCountYesterday)
	}

	incomeDelta := todayIncome - yesterdayIncome
	incomePct := 0.0
	if yesterdayIncome > 0 {
		incomePct = incomeDelta * 100.0 / yesterdayIncome
	}

	projectDelta := projectCount - projectCountYesterday
	projectPct := 0.0
	if projectCountYesterday > 0 {
		projectPct = float64(projectDelta) * 100.0 / float64(projectCountYesterday)
	}

	aiPct := 0.0
	if projectCount > 0 {
		aiPct = float64(aiSum) * 100.0 / float64(projectCount)
	}

	resp := map[string]interface{}{
		"userCount":     userCount,
		"newUsersToday": newUsersToday,
		"userDelta":     userDelta,
		"userPct":       userPct,

		"containerCount":   containerCount,
		"newDepositsToday": newDepositsToday,
		"containerDelta":   containerDelta,
		"containerPct":     containerPct,

		"totalIncome": totalIncome,
		"todayIncome": todayIncome,
		"incomeDelta": incomeDelta,
		"incomePct":   incomePct,

		"projectCount": projectCount,
		"aiPct":        aiPct,
		"projectDelta": projectDelta,
		"projectPct":   projectPct,

		"loginDates":  loginDates,
		"loginSeries": loginSeries,
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(resp)
}
