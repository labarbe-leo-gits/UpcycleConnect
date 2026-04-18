package models

type BroadcastMessage struct {
	Action      string       `json:"action"`
	TargetType  string       `json:"target_type"`
	TargetID    string       `json:"target_id"`
	Content     string       `json:"content"`
	SenderID    string       `json:"sender_id,omitempty"`
	CreatedAt   string       `json:"created_at,omitempty"`
	Attachments []Attachment `json:"attachments,omitempty"`
}

type Attachment struct {
	FileName string `json:"file_name"`
	FileSize int    `json:"file_size"`
	FileType string `json:"file_type"`
	FilePath string `json:"file_path"`
}
