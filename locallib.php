<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.


/**
 * Internal library of functions for module hotquestion
 *
 * All the hotquestion specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package   mod_hotquestion
 * @copyright 2011 Sun Zhigang
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class mod_hotquestion {
    public $instance;
    public $cm;
    public $course;

    protected $current_round;
    protected $prev_round;
    protected $next_round;

    public function __construct($cmid, $roundid = -1) {
        global $DB;
        $this->cm        = get_coursemodule_from_id('hotquestion', $cmid, 0, false, MUST_EXIST);
        $this->course    = $DB->get_record('course', array('id' => $this->cm->course), '*', MUST_EXIST);
        $this->instance  = $DB->get_record('hotquestion', array('id' => $this->cm->instance), '*', MUST_EXIST);
        $this->set_current_round($roundid);
    }

    /**
     * Return whether the user has voted on specified question
     *
     * @param int $question question id
     * @param int $user user id. -1 means current user
     * @return boolean
     */
    public function has_voted($question, $user = -1) {
        global $USER, $DB;
        if ($user == -1) {
            $user = $USER->id;
        }
        return $DB->record_exists('hotquestion_votes', array('question'=>$question, 'voter'=>$user));
    }

    /**
     * Add a new question to current round
     *
     * @global object
     * @global object
     * @global object
     * @param object $fromform from ask form
     */
    public function add_new_question($fromform) {
        global $USER, $CFG, $DB;
        $data->hotquestion = $this->instance->id;
        $data->content = trim($fromform->question);
        $data->userid = $USER->id;
        $data->time = time();
        if (isset($fromform->anonymous) && $fromform->anonymous && $this->instance->anonymouspost) {
            $data->anonymous = $fromform->anonymous;
            // Assume this user is guest
            $data->userid = $CFG->siteguest;
        }
        if (!empty($data->content)) {
            $DB->insert_record('hotquestion_questions', $data);
            add_to_log($this->course->id, "hotquestion", "add question", "view.php?id={$this->cm->id}", $data->content, $this->cm->id);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Vote on question
     *
     * @global object
     * @global object
     * @param int $question the question id
     */
    public function vote_on($question) {
        global $DB, $USER;
        $question = $DB->get_record('hotquestion_questions', array('id'=>$question));
        if ($question && $this->can_vote_on($question)) {
            add_to_log($this->course->id, 'hotquestion', 'update vote', "view.php?id={$this->cm->id}", $question->id, $this->cm->id);
            if (!$this->has_voted($question->id)) {
                $votes->question = $question->id;
                $votes->voter = $USER->id;
                $DB->insert_record('hotquestion_votes', $votes);
            } else { 
                $DB->delete_records('hotquestion_votes', array('question'=> $question->id, 'voter'=>$USER->id));
            }
        }
    }

    /**
     * Whether can vote on the question
     *
     * @param object or int $question
     * @param object $user null means current user
     */
    public function can_vote_on($question, $user = null) {
        global $USER, $DB;

        if (is_int($question)) {
            $question = $DB->get_record('hotquestion_questions', array('id'=>$question));
        }
        if (empty($user)) {
            $user = $USER;
        }

        // Is this question in last round?
        $rounds = $DB->get_records('hotquestion_rounds', array('hotquestion' => $this->instance->id), 'id DESC', '*', 0, 1);
        $lastround = reset($rounds);
        $in_last_round = $question->time >= $lastround->starttime;

        return $question->userid != $user->id && $in_last_round;
    }

    /**
     * Open a new round and close the old one
     *
     * @global object
     */
    public function add_new_round() {
        global $DB;
        // Close the latest round
        $old = array_pop($DB->get_records('hotquestion_rounds', array('hotquestion'=>$this->instance->id), 'id DESC', '*', 0, 1));
        $old->endtime = time();
        $DB->update_record('hotquestion_rounds', $old);
        // Open a new round
        $new->hotquestion = $this->instance->id;
        $new->starttime = time();
        $new->endtime = 0;
        $rid = $DB->insert_record('hotquestion_rounds', $new);
        add_to_log($this->course->id, 'hotquestion', 'add round', "view.php?id={$this->cm->id}&round=$rid", $rid, $this->cm->id);
    }

    /**
     * Set current round to show
     *
     * @global object
     * @param int $roundid
    */
    public function set_current_round($roundid = -1) {
        global $DB;

        $rounds = $DB->get_records('hotquestion_rounds', array('hotquestion' => $this->instance->id), 'id ASC');
        if (empty($rounds)) {
            // Create the first round
            $round->starttime = time();
            $round->endtime = 0;
            $round->hotquestion = $this->instance->id;
            $round->id = $DB->insert_record('hotquestion_rounds', $round);
            $rounds[] = $round;
        }

        if ($roundid != -1 && array_key_exists($roundid, $rounds)) {
            $this->current_round = $rounds[$roundid];

            $ids = array_keys($rounds);
            // Search previous round
            $current_key = array_search($roundid, $ids);
            if (array_key_exists($current_key - 1, $ids)) {
                $this->prev_round = $rounds[$ids[$current_key - 1]];
            } else {
                $this->prev_round = null;
            }
            // Search next round
            if (array_key_exists($current_key + 1, $ids)) {
                $this->next_round = $rounds[$ids[$current_key + 1]];
            } else {
                $this->next_round = null;
            }
        } else {
            // Use the last round
            $this->current_round = array_pop($rounds);
            $this->prev_round = array_pop($rounds);
            $this->next_round = null;
        }
    }

    /**
     * Return current round
     *
     * @return object
     */
    public function get_current_round() {
        return $this->current_round;
    }

    /**
     * Return previous round
     *
     * @return object
     */
    public function get_prev_round() {
        return $this->prev_round;
    }

    /**
     * Return next round
     *
     * @return object
     */
    public function get_next_round() {
        return $this->next_round;
    }

    /**
     * Return questions according to $current_round
     *
     * @global object
     * @return all questions with vote count in current round
     */
    public function get_questions() {
        global $DB;
        if ($this->current_round->endtime == 0) {
            $this->current_round->endtime = 0xFFFFFFFF;  //Hack
        }
        $params = array($this->instance->id, $this->current_round->starttime, $this->current_round->endtime);
        return $DB->get_records_sql('SELECT q.*, count(v.voter) as votecount
                                     FROM {hotquestion_questions} q
                                         LEFT JOIN {hotquestion_votes} v
                                         ON v.question = q.id
                                     WHERE q.hotquestion = ?
                                        AND q.time >= ?
                                        AND q.time <= ?
                                     GROUP BY q.id
                                     ORDER BY votecount DESC, q.time DESC', $params);
    }
}
