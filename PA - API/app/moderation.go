package app

import (
	"API/utils"
	"encoding/json"
	"net/http"
	"os"
	"strings"
	"sync"
)

var (
	badWords     map[string]struct{}
	badWordsMux  sync.RWMutex
	badWordsOnce sync.Once
)

func loadBadWords() error {
	remote, err := utils.FetchRemoteList("en")
	if err != nil {
		return err
	}

	path := os.Getenv("BADWORDS_FILE")
	if path == "" {
		path = "data/badwords.json"
	}
	local, err := utils.LoadLocalWords(path)
	if err != nil && !os.IsNotExist(err) {
		return err
	}

	merged := utils.MergeWordLists(remote, local)
	badWordsMux.Lock()
	badWords = merged
	badWordsMux.Unlock()
	return nil
}

func getBadWordSet() (map[string]struct{}, error) {
	var err error
	badWordsOnce.Do(func() {
		err = loadBadWords()
	})
	if err != nil {
		return nil, err
	}
	badWordsMux.RLock()
	defer badWordsMux.RUnlock()
	return badWords, nil
}

type moderationRequest struct {
	Content string `json:"content"`
}

type moderationResponse struct {
	Flagged      bool     `json:"flagged"`
	FlaggedWords []string `json:"flaggedWords"`
}

func ModerateContent(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)
		return
	}

	var req moderationRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "invalid request body", http.StatusBadRequest)
		return
	}

	text := strings.ToLower(req.Content)

	set, err := getBadWordSet()
	if err != nil {
		http.Error(w, "failed to load bad word list", http.StatusInternalServerError)
		return
	}

	found := make(map[string]struct{})
	for w := range set {
		if strings.Contains(text, w) {
			found[w] = struct{}{}
		}
	}

	words := make([]string, 0, len(found))
	for w := range found {
		words = append(words, w)
	}

	resp := moderationResponse{
		Flagged:      len(words) > 0,
		FlaggedWords: words,
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(resp)
}
