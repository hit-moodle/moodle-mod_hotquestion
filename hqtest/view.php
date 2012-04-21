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
 * Prints a particular instance of hotquestion
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package   mod_hotquestion
 * @copyright 2011 Sun Zhigang
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once($CFG->dirroot . '/mod/hotquestion/mod_form.php');

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$h  = optional_param('h', 0, PARAM_INT);  // hotquestion instance ID
$ajax = optional_param('ajax', 0, PARAM_BOOL); // asychronous form request

if ($id) {
    $cm           = get_coursemodule_from_id('hotquestion', $id, 0, false, MUST_EXIST);
    $course       = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $hotquestion  = $DB->get_record('hotquestion', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($h) {
    $hotquestion  = $DB->get_record('hotquestion', array('id' => $h), '*', MUST_EXIST);
    $course       = $DB->get_record('course', array('id' => $hotquestion->course), '*', MUST_EXIST);
    $cm           = get_coursemodule_from_instance('hotquestion', $hotquestion->id, $course->id, false, MUST_EXIST);
} else {
    error('You must specify a course_module ID or an instance ID');
}

require_login($course, true, $cm);

add_to_log($course->id, 'hotquestion', 'view', "view.php?id=$cm->id", $hotquestion->name, $cm->id);




$context = get_context_instance(CONTEXT_MODULE, $cm->id);



/// Print the page header
if (!$ajax){
    $PAGE->set_url('/mod/hotquestion/view.php', array('id' => $cm->id));
    $PAGE->set_title($hotquestion->name);
    $PAGE->set_heading($course->shortname);
    $PAGE->set_button(update_module_button($cm->id, $course->id, get_string('modulename', 'hotquestion')));

    $PAGE->set_context($context);
    $PAGE->set_cm($cm);
    $PAGE->add_body_class('hotquestion');
    //$PAGE->set_focuscontrol('some-html-id');

    $jsmodule = array(
        'name'     => 'mod_hotquestion',
        'fullpath' => '/mod/hotquestion/module.js',
        'requires' => array('base', 'io', 'node', 'event-valuechange'),
        'strings' => array(
            array('invalidquestion', 'hotquestion'),
            array('connectionerror', 'hotquestion')
        )
    );

    $PAGE->requires->js_init_call('M.mod_hotquestion.init', null, false, $jsmodule);
}

require_capability('mod/hotquestion:view', $context);



$output = $PAGE->get_renderer('mod_hotqustion_render');




// Post question
if(has_capability('mod/hotquestion:ask', $context)){
    $mform = new hotquestion_form(null, $hotquestion->anonymouspost);

    if ($fromform=$mform->get_data()){

       $output->handle_question($fromform);

       // Redirect to show questions. So that the page can be refreshed
        if (!$ajax){
            redirect('view.php?id='.$cm->id, get_string('questionsubmitted', 'hotquestion'));
        }
    }
}

// Output starts here
if (!$ajax){
    echo $out->header();
}

// Handle the new votes
$action  = optional_param('action', '', PARAM_ACTION);  // Vote or unvote
if (!empty($action)) {

    switch ($action) {
        case 'vote':
	    output->handle_vote();
	    break;

        case 'newround':
	    output->new_round();
	    break;
    }

}

/// Print the main part of the page

if (!$ajax) {
    // Print hotquestion description 
    if (trim($hotquestion->intro)) {
        echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
        echo format_module_intro('hotquestion', $hotquestion, $cm->id);
        echo $OUTPUT->box_end();
    }

    // Ask form
    if(has_capability('mod/hotquestion:ask', $context)){
        $mform->display();
    }
}



echo $output->container_start(null, 'questions_list');





// Look for rounds
$rounds = $DB->get_records('hotquestion_rounds', array('hotquestion' => $hotquestion->id), 'id ASC');

$output->lookfor_rounds($rounds);


// Print round toolbar




echo $output->container_start("toolbar");
$output->toolbuttons();

echo $output->container_end();






// Questions list
if ($current_round->endtime == 0)
    $current_round->endtime = 0xFFFFFFFF;  //Hack

$questions = $DB->get_records_sql("SELECT q.*, count(v.voter) as votecount
                              FROM {$CFG->prefix}hotquestion_questions q
                              LEFT JOIN {$CFG->prefix}hotquestion_votes v
                              ON v.question = q.id
                              WHERE q.hotquestion = $hotquestion->id
                                    AND q.time >= {$current_round->starttime}
                                    AND q.time <= {$current_round->endtime}
                              GROUP BY q.id
                              ORDER BY votecount DESC, q.time DESC");

if ($questions) {
    $output->display_questionlist($questions);

}else{
    echo $output->box(get_string('noquestions', 'hotquestion'), 'center', '70%');
}



echo $output->container_end();







add_to_log($course->id, "hotquestion", "view", "view.php?id=$cm->id&round=$roundid", $roundid, $cm->id);

// Finish the page
if (!$ajax){
    echo $output->footer();
}
