package app

import (
	"API/db"
	"encoding/json"
	"fmt"
	"net/http"

	"github.com/google/uuid"
)

func GetRevenueReports(w http.ResponseWriter, r *http.Request) {
	startDate := r.URL.Query().Get("start_date")
	endDate := r.URL.Query().Get("end_date")

	var reports interface{}
	var err error

	if startDate != "" && endDate != "" {
		reports, err = db.GetRevenueReportsByDateRangeFromDB(startDate, endDate)
	} else {
		reports, err = db.GetAllRevenueReportsFromDB()
	}

	if err != nil {
		fmt.Println("[ERROR] GetRevenueReports:", err)
		sendError(w, "Unable to fetch revenue reports", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(reports)
}

func GetRevenueReportByID(w http.ResponseWriter, r *http.Request) {
	reportIDStr := r.URL.Query().Get("id")
	if reportIDStr == "" {
		sendError(w, "Report ID is required", http.StatusBadRequest)
		return
	}

	reportID, err := uuid.Parse(reportIDStr)
	if err != nil {
		sendError(w, "Invalid report ID", http.StatusBadRequest)
		return
	}

	report, err := db.GetRevenueReportByIDFromDB(reportID)
	if err != nil {
		fmt.Println("[ERROR] GetRevenueReportByID:", err)
		sendError(w, "Report not found", http.StatusNotFound)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(report)
}

func GenerateRevenueReport(w http.ResponseWriter, r *http.Request) {
	var payload struct {
		ReportPeriodStart string `json:"report_period_start"`
		ReportPeriodEnd   string `json:"report_period_end"`
	}

	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		sendError(w, "Invalid request body", http.StatusBadRequest)
		return
	}

	if payload.ReportPeriodStart == "" || payload.ReportPeriodEnd == "" {
		sendError(w, "Report period start and end are required", http.StatusBadRequest)
		return
	}

	report, err := db.GenerateRevenueReportInDB(payload.ReportPeriodStart, payload.ReportPeriodEnd)
	if err != nil {
		fmt.Println("[ERROR] GenerateRevenueReport:", err)
		sendError(w, "Failed to generate revenue report", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(report)
}

func GetCurrentMonthRevenue(w http.ResponseWriter, r *http.Request) {
	revenue, err := db.GetCurrentMonthRevenueFromDB()
	if err != nil {
		fmt.Println("[ERROR] GetCurrentMonthRevenue:", err)
		sendError(w, "Unable to fetch current month revenue", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(revenue)
}

func GetRevenueBreakdown(w http.ResponseWriter, r *http.Request) {
	startDate := r.URL.Query().Get("start_date")
	endDate := r.URL.Query().Get("end_date")

	if startDate == "" || endDate == "" {
		sendError(w, "Start and end dates are required", http.StatusBadRequest)
		return
	}

	breakdown, err := db.GetRevenueBreakdownFromDB(startDate, endDate)
	if err != nil {
		fmt.Println("[ERROR] GetRevenueBreakdown:", err)
		sendError(w, "Unable to fetch revenue breakdown", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(breakdown)
}
