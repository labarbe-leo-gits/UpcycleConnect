package app

import (
	"encoding/json"
	"log"
	"net/http"
	"sync"
	"time"

	"API/db"
	"API/models"

	"github.com/google/uuid"
	"github.com/gorilla/websocket"
)

var upgrader = websocket.Upgrader{
	ReadBufferSize:  1024,
	WriteBufferSize: 1024,
	// Temporarily allow all origins since its an API with a separate frontend
	CheckOrigin: func(r *http.Request) bool { return true },
}

type Client struct {
	ID   string
	Conn *websocket.Conn
	Send chan []byte
	Hub  *Hub
}

type Hub struct {
	Clients    map[string]map[*Client]bool
	Broadcast  chan models.BroadcastMessage
	Register   chan *Client
	Unregister chan *Client
	mu         sync.RWMutex
}

func NewHub() *Hub {
	return &Hub{
		Clients:    make(map[string]map[*Client]bool),
		Broadcast:  make(chan models.BroadcastMessage),
		Register:   make(chan *Client),
		Unregister: make(chan *Client),
	}
}

var WsHub = NewHub()

func (h *Hub) Run() {
	for {
		select {
		case client := <-h.Register:
			h.mu.Lock()
			if _, ok := h.Clients[client.ID]; !ok {
				h.Clients[client.ID] = make(map[*Client]bool)
			}
			h.Clients[client.ID][client] = true
			h.mu.Unlock()
			log.Printf("Client registered: %s\n", client.ID)

		case client := <-h.Unregister:
			h.mu.Lock()
			if clients, ok := h.Clients[client.ID]; ok {
				if _, ok := clients[client]; ok {
					delete(clients, client)
					close(client.Send)
					if len(clients) == 0 {
						delete(h.Clients, client.ID)
					}
					log.Printf("Client unregistered: %s\n", client.ID)
				}
			}
			h.mu.Unlock()

		case message := <-h.Broadcast:

			var recipients []string

			if message.TargetType == "user" {

				members, err := db.GetDiscussionMembersFromDB(message.TargetID)
				if err == nil {
					recipients = append(recipients, members...)
				}
			} else if message.TargetType == "group" {
				members, err := db.GetGroupMembers(message.TargetID)
				if err == nil {
					for _, m := range members {
						recipients = append(recipients, m.UserID.String())
					}
				}
			} else if message.TargetType == "global" {
				for uid := range h.Clients {
					recipients = append(recipients, uid)
				}
			}

			messageBytes, _ := json.Marshal(message)

			h.mu.RLock()
			for _, userID := range recipients {
				if clients, ok := h.Clients[userID]; ok {
					for client := range clients {
						select {
						case client.Send <- messageBytes:
						default:
							close(client.Send)
							delete(clients, client)
						}
					}
				}
			}
			h.mu.RUnlock()
		}
	}
}

func (c *Client) readPump() {
	defer func() {
		c.Hub.Unregister <- c
		c.Conn.Close()
	}()

	c.Conn.SetReadLimit(512 * 1024)
	c.Conn.SetReadDeadline(time.Now().Add(60 * time.Second))
	c.Conn.SetPongHandler(func(string) error { c.Conn.SetReadDeadline(time.Now().Add(60 * time.Second)); return nil })

	for {
		_, payload, err := c.Conn.ReadMessage()
		if err != nil {
			if websocket.IsUnexpectedCloseError(err, websocket.CloseGoingAway, websocket.CloseAbnormalClosure) {
				log.Printf("error: %v", err)
			}
			break
		}

		var msg models.BroadcastMessage
		if err := json.Unmarshal(payload, &msg); err != nil {
			continue
		}

		if msg.Action == "send_message" {
			msg.SenderID = c.ID
			msg.CreatedAt = time.Now().Format(time.RFC3339)

			var dbMessage models.Message
			dbMessage.SenderID = uuid.MustParse(c.ID)
			dbMessage.Content = msg.Content

			if msg.TargetType == "user" {
				authorized, _ := db.CheckIfUserInDiscussion(c.ID, msg.TargetID)
				if !authorized {
					continue
				}
				dbMessage.DiscussionID = uuid.MustParse(msg.TargetID)
			} else if msg.TargetType == "group" {
				authorized, _ := db.CheckIfUserInGroup(c.ID, msg.TargetID)
				if !authorized {
					continue
				}
				dbMessage.GroupDiscussionID = uuid.MustParse(msg.TargetID)
			} else if msg.TargetType == "global" {
				dbMessage.DiscussionID = uuid.Nil
				dbMessage.GroupDiscussionID = uuid.Nil
			}

			msgID, err := db.InsertMessage(dbMessage)
			if err != nil {
				log.Printf("Failed to insert message: %v\n", err)
				continue
			}

			log.Printf("Inserted message %s from %s", msgID, c.ID)

			c.Hub.Broadcast <- msg
		}
	}
}

func (c *Client) writePump() {
	ticker := time.NewTicker(54 * time.Second)
	defer func() {
		ticker.Stop()
		c.Conn.Close()
	}()

	for {
		select {
		case message, ok := <-c.Send:
			c.Conn.SetWriteDeadline(time.Now().Add(10 * time.Second))
			if !ok {
				c.Conn.WriteMessage(websocket.CloseMessage, []byte{})
				return
			}

			w, err := c.Conn.NextWriter(websocket.TextMessage)
			if err != nil {
				return
			}
			w.Write(message)

			n := len(c.Send)
			for i := 0; i < n; i++ {
				w.Write([]byte{'\n'})
				w.Write(<-c.Send)
			}

			if err := w.Close(); err != nil {
				return
			}
		case <-ticker.C:
			c.Conn.SetWriteDeadline(time.Now().Add(10 * time.Second))
			if err := c.Conn.WriteMessage(websocket.PingMessage, nil); err != nil {
				return
			}
		}
	}
}

func ServeWs(w http.ResponseWriter, r *http.Request) {

	userIDRaw := r.Context().Value("user_id")
	if userIDRaw == nil {
		http.Error(w, "Unauthorized", http.StatusUnauthorized)
		return
	}
	userID := userIDRaw.(string)

	conn, err := upgrader.Upgrade(w, r, nil)
	if err != nil {
		log.Println("Upgrade Error:", err)
		return
	}

	client := &Client{
		ID:   userID,
		Conn: conn,
		Send: make(chan []byte, 256),
		Hub:  WsHub,
	}

	client.Hub.Register <- client

	go client.writePump()
	go client.readPump()
}
