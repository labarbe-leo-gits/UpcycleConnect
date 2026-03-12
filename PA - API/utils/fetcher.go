package utils

import (
	"bufio"
	"encoding/json"
	"errors"
	"net/http"
	"os"
	"strings"
)

func FetchRemoteList(lang string) ([]string, error) {
	if lang == "" || lang == "all" {
		return FetchAllRemoteLists()
	}

	url := "https://raw.githubusercontent.com/LDNOOBW/List-of-Dirty-Naughty-Obscene-and-Otherwise-Bad-Words/master/" + lang
	resp, err := http.Get(url)
	if err != nil {
		return nil, err
	}

	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return nil, errors.New("failed to fetch the list: " + resp.Status)
	}

	var words []string
	scanner := bufio.NewScanner(resp.Body)

	for scanner.Scan() {

		line := strings.TrimSpace(scanner.Text())
		if line != "" {
			words = append(words, line)
		}

	}
	if err := scanner.Err(); err != nil {
		return nil, err
	}

	return words, nil
}

func FetchAllRemoteLists() ([]string, error) {
	apiURL := "https://api.github.com/repos/LDNOOBW/List-of-Dirty-Naughty-Obscene-and-Otherwise-Bad-Words/contents"
	req, err := http.NewRequest("GET", apiURL, nil)
	if err != nil {
		return nil, err
	}
	req.Header.Set("User-Agent", "UpcycleConnect")

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return nil, errors.New("could not list repo contents: " + resp.Status)
	}

	var items []struct {
		Name string `json:"name"`
		Type string `json:"type"`
	}
	if err := json.NewDecoder(resp.Body).Decode(&items); err != nil {
		return nil, err
	}

	var all []string
	for _, item := range items {
		if item.Type != "file" {
			continue
		}
		name := item.Name
		if name == "LICENSE" || name == "README.md" || name == "USERS.md" {
			continue
		}
		url := "https://raw.githubusercontent.com/LDNOOBW/List-of-Dirty-Naughty-Obscene-and-Otherwise-Bad-Words/master/" + name
		r, err := http.Get(url)
		if err != nil {
			continue
		}
		if r.StatusCode != http.StatusOK {
			r.Body.Close()
			continue
		}
		scanner := bufio.NewScanner(r.Body)
		for scanner.Scan() {
			if t := strings.TrimSpace(scanner.Text()); t != "" {
				all = append(all, t)
			}
		}
		r.Body.Close()
	}

	set := make(map[string]struct{}, len(all))
	for _, w := range all {
		set[w] = struct{}{}
	}
	result := make([]string, 0, len(set))
	for w := range set {
		result = append(result, w)
	}
	return result, nil
}

func LoadLocalWords(path string) ([]string, error) {

	f, err := os.Open(path)
	if err != nil {
		return nil, err
	}

	defer f.Close()
	var words []string

	if err := json.NewDecoder(f).Decode(&words); err != nil {
		return nil, err
	}

	return words, nil

}

func MergeWordLists(remote []string, local []string) map[string]struct{} {
	set := make(map[string]struct{}, len(remote)+len(local))
	for _, w := range remote {
		set[w] = struct{}{}
	}
	for _, w := range local {
		set[w] = struct{}{}
	}
	return set
}
