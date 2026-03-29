package db

import (
	"API/models"
	"context"
	"database/sql"
	"fmt"

	"github.com/google/uuid"
)

func GetPollByIDFromDB(pollID uuid.UUID) (models.Poll, error) {

	var poll models.Poll

	query := `SELECT id, question, created_at FROM polls WHERE id = ?`
	row := Db.QueryRowContext(context.Background(), query, pollID)

	err := row.Scan(&poll.ID, &poll.Question, &poll.CreatedAt)
	if err != nil {
		if err == sql.ErrNoRows {
			return poll, fmt.Errorf("poll not found")
		}
		return poll, err
	}

	return poll, nil

}

func CreatePollInDB(poll models.Poll) error {

	query := `INSERT INTO polls (id, question, created_by, created_at) VALUES (?, ?, ?, ?)`
	_, err := Db.ExecContext(context.Background(), query, poll.ID, poll.Question, poll.CreatedBy, poll.CreatedAt)

	if err != nil {
		return fmt.Errorf("createPollInDB: %s", err.Error())
	}

	return nil
}

func GetPollOptionsFromDB(pollID uuid.UUID) ([]models.PollOption, error) {

	var options []models.PollOption

	query := `SELECT id, poll_id, option_text FROM poll_options WHERE poll_id = ?`
	rows, err := Db.QueryContext(context.Background(), query, pollID)
	if err != nil {
		return nil, fmt.Errorf("getPollOptionsFromDB: %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {
		var option models.PollOption
		err := rows.Scan(&option.ID, &option.PollID, &option.Text)

		if err != nil {
			return nil, fmt.Errorf("getPollOptionsFromDB scan: %s", err.Error())
		}

		options = append(options, option)
	}

	return options, nil
}

func CreatePollOptionInDB(option models.PollOption) error {
	if option.ID == uuid.Nil {
		option.ID = uuid.New()
	}

	query := `INSERT INTO poll_options (id, poll_id, option_text) VALUES (?, ?, ?)`
	_, err := Db.ExecContext(context.Background(), query, option.ID, option.PollID, option.Text)

	if err != nil {
		return fmt.Errorf("createPollOptionInDB: %s", err.Error())
	}

	return nil
}

func GetPollVotesFromDB(pollID uuid.UUID) ([]models.PollVote, error) {

	var votes []models.PollVote

	query := `SELECT id, poll_id, option_id, user_id FROM poll_votes WHERE poll_id = ?`
	rows, err := Db.QueryContext(context.Background(), query, pollID)

	if err != nil {
		return nil, fmt.Errorf("getPollVotesFromDB: %s", err.Error())
	}

	defer rows.Close()

	for rows.Next() {
		var vote models.PollVote
		err := rows.Scan(&vote.ID, &vote.PollID, &vote.OptionID, &vote.UserID)

		if err != nil {
			return nil, fmt.Errorf("getPollVotesFromDB scan: %s", err.Error())
		}

		votes = append(votes, vote)
	}

	return votes, nil
}

func CastPollVoteInDB(vote models.PollVote) error {

	query := `INSERT INTO poll_votes (id, poll_id, option_id, user_id) VALUES (?, ?, ?, ?)`
	_, err := Db.ExecContext(context.Background(), query, vote.ID, vote.PollID, vote.OptionID, vote.UserID)

	if err != nil {
		return fmt.Errorf("castPollVoteInDB: %s", err.Error())
	}

	return nil
}

func RemovePollVoteFromDB(pollID, userID uuid.UUID) error {

	query := `DELETE FROM poll_votes WHERE poll_id = ? AND user_id = ?`
	_, err := Db.ExecContext(context.Background(), query, pollID, userID)

	if err != nil {
		return fmt.Errorf("removePollVoteFromDB: %s", err.Error())
	}

	return nil
}

func UpdatePollInDB(poll models.Poll) error {

	query := `UPDATE polls SET question = ?, created_at = ? WHERE id = ?`
	_, err := Db.ExecContext(context.Background(), query, poll.Question, poll.CreatedAt, poll.ID)

	if err != nil {
		return fmt.Errorf("updatePollInDB: %s", err.Error())
	}

	return nil
}

func DeletePollVotesByOptionIDFromDB(pollID, optionID uuid.UUID) error {

	query := `DELETE FROM poll_votes WHERE poll_id = ? AND option_id = ?`
	_, err := Db.ExecContext(context.Background(), query, pollID, optionID)

	if err != nil {
		return fmt.Errorf("deletePollVotesByOptionIDFromDB: %s", err.Error())
	}

	return nil
}

func DeletePollOptionFromDB(pollID, optionID uuid.UUID) error {

	query := `DELETE FROM poll_options WHERE poll_id = ? AND id = ?`
	_, err := Db.ExecContext(context.Background(), query, pollID, optionID)

	if err != nil {
		return fmt.Errorf("deletePollOptionFromDB: %s", err.Error())
	}

	return nil
}

func UpdatePollOptionInDB(option models.PollOption) error {

	query := `UPDATE poll_options SET option_text = ? WHERE id = ? AND poll_id = ?`
	_, err := Db.ExecContext(context.Background(), query, option.Text, option.ID, option.PollID)

	if err != nil {
		return fmt.Errorf("updatePollOptionInDB: %s", err.Error())
	}

	return nil
}

func DeletePollVotesByPollIDFromDB(pollID uuid.UUID) error {

	query := `DELETE FROM poll_votes WHERE poll_id = ?`
	_, err := Db.ExecContext(context.Background(), query, pollID)

	if err != nil {
		return fmt.Errorf("deletePollVotesByPollIDFromDB: %s", err.Error())
	}

	return nil
}

func DeletePollOptionsByPollIDFromDB(pollID uuid.UUID) error {

	query := `DELETE FROM poll_options WHERE poll_id = ?`
	_, err := Db.ExecContext(context.Background(), query, pollID)

	if err != nil {
		return fmt.Errorf("deletePollOptionsByPollIDFromDB: %s", err.Error())
	}

	return nil
}

func DeletePollFromDB(pollID uuid.UUID) error {

	query := `DELETE FROM polls WHERE id = ?`
	_, err := Db.ExecContext(context.Background(), query, pollID)

	if err != nil {
		return fmt.Errorf("deletePollFromDB: %s", err.Error())
	}

	return nil
}
