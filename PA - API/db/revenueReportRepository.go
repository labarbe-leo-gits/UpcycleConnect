package db

import (
	"API/models"
	"fmt"

	"github.com/google/uuid"
)

func GetAllRevenueReportsFromDB() ([]models.RevenueReport, error) {
	query := `
		SELECT id, report_period_start, report_period_end, subscription_revenue,
		       commission_revenue, partnership_revenue, training_revenue, total_revenue,
		       subscription_count, commission_count, partnership_count, training_participants,
		       generated_at
		FROM revenue_reports
		ORDER BY report_period_start DESC
	`

	rows, err := Db.Query(query)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var reports []models.RevenueReport
	for rows.Next() {
		var report models.RevenueReport
		if err := rows.Scan(
			&report.ID, &report.ReportPeriodStart, &report.ReportPeriodEnd,
			&report.SubscriptionRevenue, &report.CommissionRevenue, &report.PartnershipRevenue,
			&report.TrainingRevenue, &report.TotalRevenue, &report.SubscriptionCount,
			&report.CommissionCount, &report.PartnershipCount, &report.TrainingParticipants,
			&report.GeneratedAt,
		); err != nil {
			return nil, err
		}
		reports = append(reports, report)
	}

	return reports, rows.Err()
}

func GetRevenueReportByIDFromDB(reportID uuid.UUID) (*models.RevenueReport, error) {
	query := `
		SELECT id, report_period_start, report_period_end, subscription_revenue,
		       commission_revenue, partnership_revenue, training_revenue, total_revenue,
		       subscription_count, commission_count, partnership_count, training_participants,
		       generated_at
		FROM revenue_reports
		WHERE id = ?
	`

	var report models.RevenueReport
	err := Db.QueryRow(query, reportID.String()).Scan(
		&report.ID, &report.ReportPeriodStart, &report.ReportPeriodEnd,
		&report.SubscriptionRevenue, &report.CommissionRevenue, &report.PartnershipRevenue,
		&report.TrainingRevenue, &report.TotalRevenue, &report.SubscriptionCount,
		&report.CommissionCount, &report.PartnershipCount, &report.TrainingParticipants,
		&report.GeneratedAt,
	)

	if err != nil {
		return nil, err
	}

	return &report, nil
}

func GetRevenueReportsByDateRangeFromDB(startDate string, endDate string) ([]models.RevenueReport, error) {
	query := `
		SELECT id, report_period_start, report_period_end, subscription_revenue,
		       commission_revenue, partnership_revenue, training_revenue, total_revenue,
		       subscription_count, commission_count, partnership_count, training_participants,
		       generated_at
		FROM revenue_reports
		WHERE report_period_start >= ? AND report_period_end <= ?
		ORDER BY report_period_start DESC
	`

	rows, err := Db.Query(query, startDate, endDate)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var reports []models.RevenueReport
	for rows.Next() {
		var report models.RevenueReport
		if err := rows.Scan(
			&report.ID, &report.ReportPeriodStart, &report.ReportPeriodEnd,
			&report.SubscriptionRevenue, &report.CommissionRevenue, &report.PartnershipRevenue,
			&report.TrainingRevenue, &report.TotalRevenue, &report.SubscriptionCount,
			&report.CommissionCount, &report.PartnershipCount, &report.TrainingParticipants,
			&report.GeneratedAt,
		); err != nil {
			return nil, err
		}
		reports = append(reports, report)
	}

	return reports, rows.Err()
}

func GenerateRevenueReportInDB(startDate string, endDate string) (*models.RevenueReport, error) {
	subscriptionQuery := `
		SELECT COALESCE(SUM(c.amount), 0), COUNT(DISTINCT c.id)
		FROM contracts c
		WHERE c.created_at >= ? AND c.created_at <= ?
		AND c.contract_type = 1
	`

	var subscriptionRev float64
	var subscriptionCount int
	err := Db.QueryRow(subscriptionQuery, startDate, endDate).Scan(&subscriptionRev, &subscriptionCount)
	if err != nil {
		fmt.Println("[ERROR] GenerateRevenueReport subscription:", err)
	}

	commissionQuery := `
		SELECT COALESCE(SUM(ct.commission_amount), 0), COUNT(ct.id)
		FROM commission_transactions ct
		WHERE ct.created_at >= ? AND ct.created_at <= ?
		AND ct.status = 1
	`

	var commissionRev float64
	var commissionCount int
	err = Db.QueryRow(commissionQuery, startDate, endDate).Scan(&commissionRev, &commissionCount)
	if err != nil {
		fmt.Println("[ERROR] GenerateRevenueReport commission:", err)
	}

	partnershipQuery := `
		SELECT COALESCE(SUM(pc.monthly_price), 0), COUNT(pc.id)
		FROM partnership_campaigns pc
		WHERE pc.created_at >= ? AND pc.created_at <= ?
		AND pc.status = 1
	`

	var partnershipRev float64
	var partnershipCount int
	err = Db.QueryRow(partnershipQuery, startDate, endDate).Scan(&partnershipRev, &partnershipCount)
	if err != nil {
		fmt.Println("[ERROR] GenerateRevenueReport partnership:", err)
	}

	trainingQuery := `
		SELECT COALESCE(SUM(tsr.amount_paid), 0), COUNT(tsr.id)
		FROM training_session_registrations tsr
		WHERE tsr.created_at >= ? AND tsr.created_at <= ?
		AND tsr.status = 1
	`

	var trainingRev float64
	var trainingParticipants int
	err = Db.QueryRow(trainingQuery, startDate, endDate).Scan(&trainingRev, &trainingParticipants)
	if err != nil {
		fmt.Println("[ERROR] GenerateRevenueReport training:", err)
	}

	totalRevenue := subscriptionRev + commissionRev + partnershipRev + trainingRev

	report := models.RevenueReport{
		ID:                   uuid.New(),
		ReportPeriodStart:    startDate,
		ReportPeriodEnd:      endDate,
		SubscriptionRevenue:  subscriptionRev,
		CommissionRevenue:    commissionRev,
		PartnershipRevenue:   partnershipRev,
		TrainingRevenue:      trainingRev,
		TotalRevenue:         totalRevenue,
		SubscriptionCount:    subscriptionCount,
		CommissionCount:      commissionCount,
		PartnershipCount:     partnershipCount,
		TrainingParticipants: trainingParticipants,
	}

	insertQuery := `
		INSERT INTO revenue_reports
		(id, report_period_start, report_period_end, subscription_revenue,
		 commission_revenue, partnership_revenue, training_revenue, total_revenue,
		 subscription_count, commission_count, partnership_count, training_participants, generated_at)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
	`

	_, err = Db.Exec(insertQuery,
		report.ID.String(), report.ReportPeriodStart, report.ReportPeriodEnd,
		report.SubscriptionRevenue, report.CommissionRevenue, report.PartnershipRevenue,
		report.TrainingRevenue, report.TotalRevenue, report.SubscriptionCount,
		report.CommissionCount, report.PartnershipCount, report.TrainingParticipants,
	)

	if err != nil {
		fmt.Println("[ERROR] GenerateRevenueReport insert:", err)
		return nil, err
	}

	return &report, nil
}

func GetCurrentMonthRevenueFromDB() (map[string]interface{}, error) {
	query := `
		SELECT 
		  COALESCE(SUM(CASE WHEN contract_type = 1 THEN amount ELSE 0 END), 0) as subscription_revenue,
		  COALESCE((SELECT SUM(commission_amount) FROM commission_transactions WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())), 0) as commission_revenue,
		  COALESCE((SELECT SUM(monthly_price) FROM partnership_campaigns WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())), 0) as partnership_revenue,
		  COALESCE((SELECT SUM(amount_paid) FROM training_session_registrations WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())), 0) as training_revenue
		FROM contracts
		WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())
	`

	var subscriptionRev, commissionRev, partnershipRev, trainingRev float64
	err := Db.QueryRow(query).Scan(&subscriptionRev, &commissionRev, &partnershipRev, &trainingRev)

	if err != nil {
		return nil, err
	}

	return map[string]interface{}{
		"subscription_revenue": subscriptionRev,
		"commission_revenue":   commissionRev,
		"partnership_revenue":  partnershipRev,
		"training_revenue":     trainingRev,
		"total_revenue":        subscriptionRev + commissionRev + partnershipRev + trainingRev,
	}, nil
}

func GetRevenueBreakdownFromDB(startDate string, endDate string) (map[string]interface{}, error) {
	return map[string]interface{}{
		"status": "breakdown requested",
		"start":  startDate,
		"end":    endDate,
	}, nil
}
