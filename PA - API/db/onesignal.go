package db

import (
	"bytes"
	"encoding/json"
	"fmt"
	"net/http"
	"os"
	"strings"
	"time"
)

const oneSignalAPIURL = "https://api.onesignal.com/notifications"
const oneSignalMaxPlayerIDs = 2000

type oneSignalNotificationRequest struct {
	AppID            string                   `json:"app_id"`
	IncludedSegments []string                 `json:"included_segments,omitempty"`
	IncludePlayerIDs []string                 `json:"include_player_ids,omitempty"`
	Filters          []map[string]interface{} `json:"filters,omitempty"`
	Headings         map[string]string        `json:"headings,omitempty"`
	Contents         map[string]string        `json:"contents,omitempty"`
	Data             map[string]string        `json:"data,omitempty"`
}

func isOneSignalConfigured() bool {
	return strings.TrimSpace(os.Getenv("ONESIGNAL_APP_ID")) != "" &&
		strings.TrimSpace(os.Getenv("ONESIGNAL_API_KEY")) != ""
}

func sendOneSignalNotification(title, message string, playerIDs []string, targetUserType int) error {
	if !isOneSignalConfigured() {
		return nil
	}

	appID := strings.TrimSpace(os.Getenv("ONESIGNAL_APP_ID"))
	apiKey := strings.TrimSpace(os.Getenv("ONESIGNAL_API_KEY"))

	request := oneSignalNotificationRequest{
		AppID:    appID,
		Headings: map[string]string{"en": title},
		Contents: map[string]string{"en": message},
		Data: map[string]string{
			"notification_campaign": "true",
		},
	}

	if len(playerIDs) > 0 {
		for start := 0; start < len(playerIDs); start += oneSignalMaxPlayerIDs {
			end := start + oneSignalMaxPlayerIDs
			if end > len(playerIDs) {
				end = len(playerIDs)
			}
			request.IncludePlayerIDs = playerIDs[start:end]
			if err := postOneSignalNotification(request, apiKey); err != nil {
				return err
			}
		}
		return nil
	}

	if targetUserType == 0 {
		request.IncludedSegments = []string{"Subscribed Users"}
	} else {
		request.Filters = []map[string]interface{}{
			{"field": "tag", "key": "user_type", "relation": "=", "value": fmt.Sprintf("%d", targetUserType)},
		}
	}

	return postOneSignalNotification(request, apiKey)
}

func postOneSignalNotification(body oneSignalNotificationRequest, apiKey string) error {
	jsonBody, err := json.Marshal(body)
	if err != nil {
		return fmt.Errorf("prepare OneSignal request: %w", err)
	}

	req, err := http.NewRequest("POST", oneSignalAPIURL, bytes.NewBuffer(jsonBody))
	if err != nil {
		return fmt.Errorf("build OneSignal request: %w", err)
	}
	req.Header.Set("Authorization", "Key "+apiKey)
	req.Header.Set("Content-Type", "application/json")

	client := &http.Client{Timeout: 15 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		return fmt.Errorf("send OneSignal request: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode >= 400 {
		var apiErr map[string]interface{}
		_ = json.NewDecoder(resp.Body).Decode(&apiErr)
		return fmt.Errorf("OneSignal request failed (%d): %v", resp.StatusCode, apiErr)
	}

	return nil
}
