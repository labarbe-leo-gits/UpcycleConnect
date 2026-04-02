package models

type FriendshipWithUser struct {
	ID        string `json:"id"`
	UserID    string `json:"user_id"`
	FriendID  string `json:"friend_id"`
	Status    int    `json:"status"`
	Message   string `json:"message"`
	Username  string `json:"username"`
	FirstName string `json:"first_name"`
	LastName  string `json:"last_name"`
}
