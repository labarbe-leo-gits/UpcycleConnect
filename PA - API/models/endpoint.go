// Endpoint auto-discovery model

package models

type Endpoint struct {
	Method         string `json:"method"`
	Path           string `json:"path"`
	Description    string `json:"description,omitempty"`
	RequiresAuth   bool   `json:"requires_auth,omitempty"`
	HasRequestBody bool   `json:"has_request_body,omitempty"`
}
