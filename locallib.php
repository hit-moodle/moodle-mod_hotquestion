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

/**
 * Return whether the user has voted on specified question
 *
 * @param int $question question id
 * @param int $user user id. -1 means current user
 * @return boolean
 */
function has_voted($question, $user = -1) {
    global $USER, $DB;

    if ($user == -1)
        $user = $USER->id;

    return $DB->record_exists('hotquestion_votes', array('question'=>$question, 'voter'=>$user));
}
