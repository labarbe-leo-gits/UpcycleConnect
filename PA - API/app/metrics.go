package app

import (
	"API/db"
	"bufio"
	"encoding/json"
	"fmt"
	"net/http"
	"os"
	"path/filepath"
	"regexp"
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
	} else if userCount > 0 {
		userPct = 100.0
	}

	loginCounts := make(map[string]int)
	registerCounts := make(map[string]int)

	loginLogCandidates := []string{
		"/files/logs/login.log",
		"../files/logs/login.log",
		"../../files/logs/login.log",
	}
	parsedFromLog := false
	for _, p := range loginLogCandidates {
		if counts, err := loadCountsFromLog(p, `^\[([0-9]{4}-[0-9]{2}-[0-9]{2}) [0-9]{2}:[0-9]{2}:[0-9]{2}\].*logged in successfully`); err == nil {
			loginCounts = counts
			parsedFromLog = true
			break
		} else {
			fmt.Println("[WARN] unable to read login log", p, err)
		}
	}

	registerLogCandidates := []string{
		"/files/logs/register.log",
		"../files/logs/register.log",
		"../../files/logs/register.log",
	}
	for _, p := range registerLogCandidates {
		if counts, err := loadCountsFromLog(p, `^\[([0-9]{4}-[0-9]{2}-[0-9]{2}) [0-9]{2}:[0-9]{2}:[0-9]{2}\].*registered successfully`); err == nil {
			registerCounts = counts
			break
		} else {
			fmt.Println("[WARN] unable to read register log", p, err)
		}
	}

	if !parsedFromLog {
		loginRows, err := db.Db.Query("SELECT DATE(last_login), COUNT(id) FROM users WHERE last_login IS NOT NULL GROUP BY DATE(last_login)")
		if err != nil {
			fmt.Println("[ERROR] login query:", err)
		} else {
			defer loginRows.Close()
		}
		var loginDate string
		var loginCnt int
		for loginRows != nil && loginRows.Next() {
			if err := loginRows.Scan(&loginDate, &loginCnt); err == nil {
				loginCounts[loginDate] = loginCnt
			}
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

	registerDates := []string{}
	for d := range registerCounts {
		registerDates = append(registerDates, d)
	}
	sort.Strings(registerDates)
	registerSeries := []int{}
	for _, d := range registerDates {
		registerSeries = append(registerSeries, registerCounts[d])
	}

	containerDelta := containerCount - containerCountYesterday
	containerPct := 0.0
	if containerCountYesterday > 0 {
		containerPct = float64(containerDelta) * 100.0 / float64(containerCountYesterday)
	} else if containerCount > 0 {
		containerPct = 100.0
	}

	incomeDelta := todayIncome - yesterdayIncome
	incomePct := 0.0
	if yesterdayIncome > 0 {
		incomePct = incomeDelta * 100.0 / yesterdayIncome
	} else if todayIncome > 0 {
		incomePct = 100.0
	}

	projectDelta := projectCount - projectCountYesterday
	projectPct := 0.0
	if projectCountYesterday > 0 {
		projectPct = float64(projectDelta) * 100.0 / float64(projectCountYesterday)
	} else if projectCount > 0 {
		projectPct = 100.0
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

		"loginDates":     loginDates,
		"loginSeries":    loginSeries,
		"registerDates":  registerDates,
		"registerSeries": registerSeries,
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(resp)
}

func loadCountsFromLog(path string, pattern string) (map[string]int, error) {
	absPath, err := filepath.Abs(path)
	if err != nil {
		absPath = path
	}
	f, err := os.Open(absPath)
	if err != nil {
		return nil, err
	}
	defer f.Close()

	counts := make(map[string]int)
	scanner := bufio.NewScanner(f)
	lineRegex := regexp.MustCompile(pattern)

	for scanner.Scan() {
		line := scanner.Text()
		matches := lineRegex.FindStringSubmatch(line)
		if len(matches) < 2 {
			continue
		}
		date := matches[1]
		counts[date]++
	}
	if err := scanner.Err(); err != nil {
		return nil, err
	}

	return counts, nil
}
