package app

import (
	"bytes"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"net/http"
	"os"
	"sync"
	"time"
)

type zoomMeetingRequest struct {
	Topic     string `json:"topic"`
	Type      int    `json:"type"`
	StartTime string `json:"start_time,omitempty"`
}

type zoomMeetingResponse struct {
	JoinURL string `json:"join_url"`
}

type zoomTokenResponse struct {
	AccessToken string `json:"access_token"`
	ExpiresIn   int    `json:"expires_in"` // seconds
}

var (
	cachedZoomToken string
	tokenExpiry     time.Time
	tokenMutex      sync.Mutex
)

func getZoomAccessToken() (string, error) {
	tokenMutex.Lock()
	defer tokenMutex.Unlock()

	if cachedZoomToken != "" && time.Now().Before(tokenExpiry) {
		return cachedZoomToken, nil
	}

	clientID := os.Getenv("ZOOM_CLIENT_ID")
	clientSecret := os.Getenv("ZOOM_CLIENT_SECRET")
	accountID := os.Getenv("ZOOM_ACCOUNT_ID")
	if clientID == "" || clientSecret == "" || accountID == "" {
		return "", fmt.Errorf("zoom credentials not configured")
	}

	url := fmt.Sprintf("https://zoom.us/oauth/token?grant_type=account_credentials&account_id=%s", accountID)
	req, err := http.NewRequest("POST", url, nil)
	if err != nil {
		return "", err
	}
	creds := base64.StdEncoding.EncodeToString([]byte(clientID + ":" + clientSecret))
	req.Header.Set("Authorization", "Basic "+creds)

	client := &http.Client{Timeout: 10 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		return "", err
	}
	defer resp.Body.Close()

	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		buf := new(bytes.Buffer)
		buf.ReadFrom(resp.Body)
		return "", fmt.Errorf("zoom token request failed %d: %s", resp.StatusCode, buf.String())
	}

	var zr zoomTokenResponse
	if err := json.NewDecoder(resp.Body).Decode(&zr); err != nil {
		return "", err
	}

	cachedZoomToken = zr.AccessToken
	tokenExpiry = time.Now().Add(time.Duration(zr.ExpiresIn-30) * time.Second)
	return cachedZoomToken, nil
}

func createZoomMeeting(topic, startDate string) (string, error) {
	token, err := getZoomAccessToken()
	if err != nil {
		return "", err
	}

	userID := "me"
	if u := os.Getenv("ZOOM_USER_ID"); u != "" {
		userID = u
	}

	url := fmt.Sprintf("https://api.zoom.us/v2/users/%s/meetings", userID)

	reqBody := zoomMeetingRequest{
		Topic: topic,
	}
	if startDate != "" {
		reqBody.Type = 2
		if t, err := time.Parse("2006-01-02", startDate); err == nil {
			reqBody.StartTime = t.UTC().Format(time.RFC3339)
		}
	} else {
		reqBody.Type = 1
	}

	buf, _ := json.Marshal(reqBody)
	req, err := http.NewRequest("POST", url, bytes.NewReader(buf))
	if err != nil {
		return "", err
	}
	req.Header.Set("Authorization", "Bearer "+token)
	req.Header.Set("Content-Type", "application/json")

	client := &http.Client{Timeout: 10 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		return "", err
	}
	defer resp.Body.Close()

	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		buf := new(bytes.Buffer)
		buf.ReadFrom(resp.Body)
		bodyStr := buf.String()
		return "", fmt.Errorf("zoom API returned %d: %s", resp.StatusCode, bodyStr)
	}

	var zr zoomMeetingResponse
	if err := json.NewDecoder(resp.Body).Decode(&zr); err != nil {
		return "", err
	}
	return zr.JoinURL, nil
}
