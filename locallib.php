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

    public function __construct($cmid) {
        global $DB;
        $this->cm        = get_coursemodule_from_id('hotquestion', $cmid, 0, false, MUST_EXIST);
        $this->course    = $DB->get_record('course', array('id' => $this->cm->course), '*', MUST_EXIST);
        $this->instance  = $DB->get_record('hotquestion', array('id' => $this->cm->instance), '*', MUST_EXIST);
    }

    /**
     * Return whether the user has voted on specified question
     *
     * @param int $question question id
     * @param int $user user id. -1 means current user
     * @return boolean
     */
    function has_voted($question, $user = -1) {
        global $USER, $DB;
        if ($user == -1) {
            $user = $USER->id;
        }
        return $DB->record_exists('hotquestion_votes', array('question'=>$question, 'voter'=>$user));
    }

    /**
     * Handle question submitted by user
     *
     * @global object
     * @global object
     * @global object
     * @param object $fromform from ask form
     * @param object $hotquestion
     * @param object $course
     * @param object $cm
     */
    function add_question($fromform) {
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
     * Handle new vote on question, insert it into database
     *
     * @global object
     * @global object
     * @param object $course
     * @param object $cm
     * @param int $q which is the question id
     */
    function add_vote($q) {
        global $DB, $USER;
        $question = $DB->get_record('hotquestion_questions', array('id'=>$q));
        if ($question && $USER->id != $question->userid) {
            add_to_log($this->course->id, 'hotquestion', 'update vote', "view.php?id={$this->cm->id}", $q, $this->cm->id);
            if (!$this->has_voted($q)) {
                $votes->question = $q;
                $votes->voter = $USER->id;
                if (!$DB->insert_record('hotquestion_votes', $votes)) {
                    error("error in inserting the votes!");
                }
            } else { 
                $DB->delete_records('hotquestion_votes', array('question'=> $q, 'voter'=>$USER->id));
            } 
        }
    }

    /**
     * Open a new round and close the old one
     *
     * @global object
     */
    function add_round() {
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
     * Select exist rounds from database and set $current_round, $pre_round, $next_round
     *
     * @global object
     * @param object $hotquestion
     * @param int $roundid
     * @param ref &$current_round, which is the reference of $current_round
     * @param ref &$prev_round
     * @param ref $next_round
    */
    function search_rounds($roundid, &$current_round, &$prev_round, &$next_round) {
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
        $ids = array_keys($rounds);
        if ($roundid != -1 && array_key_exists($roundid, $rounds)) {
            // Search by $roundid;
            $current_round = $rounds[$roundid];
            $current_key = array_search($roundid, $ids);
            if (array_key_exists($current_key-1, $ids)) {
                $prev_round = $rounds[$ids[$current_key-1]];
            }
            if (array_key_exists($current_key+1, $ids)) {
                $next_round = $rounds[$ids[$current_key+1]];
            }
            $roundnum = $current_key+1;
        } else {
            // Use the last round
            $current_round = array_pop($rounds);
            $prev_round = array_pop($rounds);
            $roundnum = array_search($current_round->id, $ids) + 1;
        }
    }

    /**
     * Search questions from database according to $current_round and $hotquestion
     *
     * @global object
     * @global object
     * @param object $hotquestion
     * @param int $current_round
     * @param ref &$questions,which is the reference of $questions  
     */
    function search_questions($current_round, &$questions) {
        global $DB, $CFG;
        $questions = $DB->get_records_sql("SELECT q.*, count(v.voter) as votecount
	      FROM {$CFG->prefix}hotquestion_questions q
	      LEFT JOIN {$CFG->prefix}hotquestion_votes v
	      ON v.question = q.id
	      WHERE q.hotquestion = {$this->instance->id}
		    AND q.time >= {$current_round->starttime}
		    AND q.time <= {$current_round->endtime}
	      GROUP BY q.id
	      ORDER BY votecount DESC, q.time DESC");
    }
}
