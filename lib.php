<?php

/**
 * Library of functions and constants for module hotquestion
 * This file should have two well differenced parts:
 *   - All the core Moodle functions, neeeded to allow
 *     the module to work integrated in Moodle.
 *   - All the hotquestion specific functions, needed
 *     to implement all the module logic. Please, note
 *     that, if the module become complex and this lib
 *     grows a lot, it's HIGHLY recommended to move all
 *     these module specific functions to a new php file,
 *     called "locallib.php" (see forum, quiz...). This will
 *     help to save some memory when Moodle is performing
 *     actions across all modules.
 */


/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $hotquestion An object from the form in mod_form.php
 * @return int The id of the newly inserted hotquestion record
 */
function hotquestion_add_instance($hotquestion) {
    global $DB;

    $hotquestion->timecreated = time();

    # You may have to add extra stuff in here #

    $id = $DB->insert_record('hotquestion', $hotquestion);

    // Create the first round
    $round->starttime = time();
    $round->endtime = 0;
    $round->hotquestion = $id;

    if ($DB->insert_record('hotquestion_rounds', $round)) {
        return $id;
    }

    return $id;
}


/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $hotquestion An object from the form in mod_form.php
 * @return boolean Success/Fail
 */
function hotquestion_update_instance($hotquestion, $mform) {
    global $DB;

    $hotquestion->timemodified = time();
    $hotquestion->id = $hotquestion->instance;

    # You may have to add extra stuff in here #

    $DB->update_record('hotquestion', $hotquestion);
    return true;
}


/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function hotquestion_delete_instance($id) {
    global $DB;

    if (! $hotquestion = $DB->get_record('hotquestion', array('id' => $id))) {
        return false;
    }

    $result = true;

    $questions = $DB->get_records('hotquestion_questions', array('hotquestion'=>$hotquestion->id));
    foreach ($questions as $question) {
        if (! $DB->delete_records('hotquestion_votes', array('question'=>$question->id))) {
            $result = false;
        }
    }

    if (! $DB->delete_records('hotquestion_questions', array('hotquestion'=>$hotquestion->id))) {
        $result = false;
    }

    if (! $DB->delete_records('hotquestion_rounds', array('hotquestion'=>$hotquestion->id))) {
        $result = false;
    }

    if (! $DB->delete_records('hotquestion', array('id'=>$hotquestion->id))) {
        $result = false;
    }

    return $result;
}


/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return null
 * @todo Finish documenting this function
 */
function hotquestion_user_outline($course, $user, $mod, $hotquestion) {
    return $return;
}


/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @return boolean
 * @todo Finish documenting this function
 */
function hotquestion_user_complete($course, $user, $mod, $hotquestion) {
    return true;
}


/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in hotquestion activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @return boolean
 */
function hotquestion_print_recent_activity($course, $isteacher, $timestart) {
    return false;  //  True if anything was printed, otherwise false
}


/**
 * Must return an array of user records (all data) who are participants
 * for a given instance of hotquestion. Must include every user involved
 * in the instance, independient of his role (student, teacher, admin...)
 * See other modules as example.
 *
 * @param int $hotquestionid ID of an instance of this module
 * @return mixed boolean/array of students
 */
function hotquestion_get_participants($hotquestionid) {
    return false;
}


/**
 * This function returns if a scale is being used by one hotquestion
 * if it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 *
 * @param int $hotquestionid ID of an instance of this module
 * @return mixed
 */
function hotquestion_scale_used($hotquestionid, $scaleid) {
    $return = false;

    //$rec = get_record("hotquestion","id","$hotquestionid","scale","-$scaleid");
    //
    //if (!empty($rec) && !empty($scaleid)) {
    //    $return = true;
    //}

    return $return;
}


/**
 * Checks if scale is being used by any instance of hotquestion.
 * This function was added in 1.9
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any hotquestion
 */
function hotquestion_scale_used_anywhere($scaleid) {
    global $DB;
    if ($scaleid and $DB->record_exists('hotquestion', array('grade'=>-$scaleid))) {
        return true;
    } else {
        return false;
    }
}


//return whether the user has voted on question
function has_voted($question, $user = -1) {
    global $USER, $DB;

    if ($user == -1)
        $user = $USER->id;

    return $DB->record_exists('hotquestion_votes', array('question'=>$question, 'voter'=>$user));
}

/**
 * Indicates API features that the hotquestion supports.
 *
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_GROUPMEMBERSONLY
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_COMPLETION_HAS_RULES
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature
 * @return mixed True if yes (some features may use other values)
 */
function hotquestion_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return false;
        case FEATURE_GROUPINGS:               return false;
        case FEATURE_GROUPMEMBERSONLY:        return false;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return false;
        case FEATURE_COMPLETION_HAS_RULES:    return false;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_RATE:                    return false;
        case FEATURE_BACKUP_MOODLE2:          return false;

        default: return null;
    }
}

