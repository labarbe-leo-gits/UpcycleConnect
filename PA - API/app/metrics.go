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
	"runtime"
	"sort"
	"time"

	"github.com/shirou/gopsutil/v3/disk"
	"github.com/shirou/gopsutil/v3/mem"
)

var serverStartTime = time.Now()

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

	var annonceCount, annonceCountYesterday, pendingDeposits, pendingDepositsYesterday, eventCount, eventCountYesterday, upcomingEvents, pendingRegistrations, pendingRegistrationsYesterday, adviceCount, badgeCount, categoryCount, orderCount int
	var categoryLabels []string
	var categoryCounts []int

	db.Db.QueryRow("SELECT COUNT(id) FROM annonces").Scan(&annonceCount)
	db.Db.QueryRow("SELECT COUNT(id) FROM annonces WHERE DATE(created_at) = ?", yesterday).Scan(&annonceCountYesterday)
	db.Db.QueryRow("SELECT COUNT(id) FROM demandes_depot WHERE status = 0").Scan(&pendingDeposits)
	db.Db.QueryRow("SELECT COUNT(id) FROM demandes_depot WHERE status = 0 AND DATE(created_at) = ?", yesterday).Scan(&pendingDepositsYesterday)
	db.Db.QueryRow("SELECT COUNT(id) FROM evenements").Scan(&eventCount)
	db.Db.QueryRow("SELECT COUNT(id) FROM evenements WHERE DATE(created_at) = ?", yesterday).Scan(&eventCountYesterday)
	db.Db.QueryRow("SELECT COUNT(id) FROM evenements WHERE event_date >= ?", today).Scan(&upcomingEvents)
	db.Db.QueryRow("SELECT COUNT(id) FROM pending_registrations").Scan(&pendingRegistrations)
	db.Db.QueryRow("SELECT COUNT(id) FROM pending_registrations WHERE DATE(created_at) = ?", yesterday).Scan(&pendingRegistrationsYesterday)
	db.Db.QueryRow("SELECT COUNT(id) FROM conseils").Scan(&adviceCount)
	db.Db.QueryRow("SELECT COUNT(id) FROM badges").Scan(&badgeCount)
	db.Db.QueryRow("SELECT COUNT(id) FROM categories").Scan(&categoryCount)
	db.Db.QueryRow("SELECT COUNT(id) FROM orders").Scan(&orderCount)

	topCategoryRows, err := db.Db.Query("SELECT COALESCE(c.name,'Unknown'), COUNT(a.id) FROM annonces a LEFT JOIN categories c ON a.category_id = c.id GROUP BY COALESCE(c.name,'Unknown') ORDER BY COUNT(a.id) DESC LIMIT 10")
	if err == nil {
		defer topCategoryRows.Close()
		var categoryName string
		var categoryTotal int
		for topCategoryRows.Next() {
			if err := topCategoryRows.Scan(&categoryName, &categoryTotal); err == nil {
				categoryLabels = append(categoryLabels, categoryName)
				categoryCounts = append(categoryCounts, categoryTotal)
			}
		}
	} else {
		fmt.Println("[WARN] unable to load top categories:", err)
	}

	var serverMem runtime.MemStats
	runtime.ReadMemStats(&serverMem)

	var vmStat *mem.VirtualMemoryStat
	var memErr error
	vmStat, memErr = mem.VirtualMemory()

	cwd, _ := os.Getwd()
	diskStat, _ := disk.Usage(cwd)

	serverInfo := map[string]interface{}{
		"os":               runtime.GOOS,
		"arch":             runtime.GOARCH,
		"goVersion":        runtime.Version(),
		"numCpu":           runtime.NumCPU(),
		"numGoroutine":     runtime.NumGoroutine(),
		"uptimeSeconds":    time.Since(serverStartTime).Seconds(),
		"memoryAlloc":      serverMem.Alloc,
		"memoryTotalAlloc": serverMem.TotalAlloc,
		"memorySys":        serverMem.Sys,
		"heapAlloc":        serverMem.HeapAlloc,
		"heapSys":          serverMem.HeapSys,
		"mallocs":          serverMem.Mallocs,
		"frees":            serverMem.Frees,
		"numGC":            serverMem.NumGC,
		"ramTotal":         0,
		"ramUsed":          0,
		"ramUsedPct":       0.0,
		"diskTotal":        0,
		"diskUsed":         0,
		"diskUsedPct":      0.0,
		"diskPath":         cwd,
	}

	if memErr == nil && vmStat != nil {
		serverInfo["ramTotal"] = vmStat.Total
		serverInfo["ramUsed"] = vmStat.Used
		serverInfo["ramUsedPct"] = vmStat.UsedPercent
	}
	if diskStat != nil {
		serverInfo["diskTotal"] = diskStat.Total
		serverInfo["diskUsed"] = diskStat.Used
		serverInfo["diskUsedPct"] = diskStat.UsedPercent
	}

	containerDelta := containerCount - containerCountYesterday
	containerPct := 0.0
	if containerCountYesterday > 0 {
		containerPct = float64(containerDelta) * 100.0 / float64(containerCountYesterday)
	} else if containerCount > 0 {
		containerPct = 100.0
	}

	annonceDelta := annonceCount - annonceCountYesterday
	annoncePct := 0.0
	if annonceCountYesterday > 0 {
		annoncePct = float64(annonceDelta) * 100.0 / float64(annonceCountYesterday)
	} else if annonceCount > 0 {
		annoncePct = 100.0
	}

	pendingDepositsDelta := pendingDeposits - pendingDepositsYesterday
	pendingDepositsPct := 0.0
	if pendingDepositsYesterday > 0 {
		pendingDepositsPct = float64(pendingDepositsDelta) * 100.0 / float64(pendingDepositsYesterday)
	} else if pendingDeposits > 0 {
		pendingDepositsPct = 100.0
	}

	eventDelta := eventCount - eventCountYesterday
	eventPct := 0.0
	if eventCountYesterday > 0 {
		eventPct = float64(eventDelta) * 100.0 / float64(eventCountYesterday)
	} else if eventCount > 0 {
		eventPct = 100.0
	}

	pendingRegistrationsDelta := pendingRegistrations - pendingRegistrationsYesterday
	pendingRegistrationsPct := 0.0
	if pendingRegistrationsYesterday > 0 {
		pendingRegistrationsPct = float64(pendingRegistrationsDelta) * 100.0 / float64(pendingRegistrationsYesterday)
	} else if pendingRegistrations > 0 {
		pendingRegistrationsPct = 100.0
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

		"annonceCount":              annonceCount,
		"pendingDeposits":           pendingDeposits,
		"eventCount":                eventCount,
		"eventDelta":                eventDelta,
		"eventPct":                  eventPct,
		"upcomingEvents":            upcomingEvents,
		"pendingRegistrations":      pendingRegistrations,
		"pendingRegistrationsDelta": pendingRegistrationsDelta,
		"pendingRegistrationsPct":   pendingRegistrationsPct,
		"adviceCount":               adviceCount,
		"badgeCount":                badgeCount,
		"categoryCount":             categoryCount,
		"annonceDelta":              annonceDelta,
		"annoncePct":                annoncePct,
		"pendingDepositsDelta":      pendingDepositsDelta,
		"pendingDepositsPct":        pendingDepositsPct,
		"orderCount":                orderCount,
		"dbTableLabels":             []string{"Users", "Annonces", "Conteneurs", "Pending deposits", "Projects", "Orders", "Events", "Pending regs", "Advice", "Badges", "Categories"},
		"dbTableCounts":             []int{userCount, annonceCount, containerCount, pendingDeposits, projectCount, orderCount, eventCount, pendingRegistrations, adviceCount, badgeCount, categoryCount},
		"categoryLabels":            categoryLabels,
		"categoryCounts":            categoryCounts,
		"serverInfo":                serverInfo,

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
