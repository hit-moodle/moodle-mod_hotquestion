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
require_once(dirname(__FILE__).'/mod_form.php');

$id = required_param('id', PARAM_INT); // course_module ID
$ajax = optional_param('ajax', 0, PARAM_BOOL); // asychronous form request
$action  = optional_param('action', '', PARAM_ACTION);  //action(vote,newround)
$roundid = optional_param('round', -1, PARAM_INT);  //round id

// Construct hotquestion instance
$hq = new mod_hotquestion($id, $roundid);

// Confirm login
require_login($hq->course, true, $hq->cm);
add_to_log($hq->course->id, 'hotquestion', 'view', "view.php?id={$hq->cm->id}", $hq->instance->name, $hq->cm->id);
$context = get_context_instance(CONTEXT_MODULE, $hq->cm->id);

// Set page
if (!$ajax) {
    $PAGE->set_url('/mod/hotquestion/view.php', array('id' => $hq->cm->id));
    $PAGE->set_title($hq->instance->name);
    $PAGE->set_heading($hq->course->shortname);
    $PAGE->set_button(update_module_button($hq->cm->id, $hq->course->id, get_string('modulename', 'hotquestion')));
    $PAGE->set_context($context);
    $PAGE->set_cm($hq->cm);
    $PAGE->add_body_class('hotquestion');
    $jsmodule = array(
        'name'     => 'mod_hotquestion',
        'fullpath' => '/mod/hotquestion/module.js',
        'requires' => array('base', 'io', 'node', 'event-valuechange'),
        'strings'  => array(
            array('invalidquestion', 'hotquestion'),
            array('connectionerror', 'hotquestion')
        )
    );
    $PAGE->requires->js_init_call('M.mod_hotquestion.init', null, false, $jsmodule);
}

require_capability('mod/hotquestion:view', $context);

// Get local renderer
$output = $PAGE->get_renderer('mod_hotquestion');
$output->init($hq);

// Process submited question
if (has_capability('mod/hotquestion:ask', $context)) {
    $mform = new hotquestion_form(null, array($hq->instance->anonymouspost, $hq->cm));
    if ($fromform=$mform->get_data()) {
        if (!$hq->add_new_question($fromform)) {
            redirect('view.php?id='.$hq->cm->id, get_string('invalidquestion', 'hotquestion'));
        }
        if (!$ajax) {
            redirect('view.php?id='.$hq->cm->id, get_string('questionsubmitted', 'hotquestion'));
        }
    }
}

// Handle vote and newround
if (!empty($action)) {
    switch ($action) {
        case 'vote':
            if (has_capability('mod/hotquestion:vote', $context)) {
                $q = required_param('q',  PARAM_INT);  //question id to vote
                $hq->vote_on($q);
            }
            break;
        case 'newround':
            if (has_capability('mod/hotquestion:manage', $context)) {
                $hq->add_new_round();
            }
            break;
    }
}

// Start print page
if (!$ajax){
    echo $output->header();
    // Print hotquestion description
    echo $output->introduction();
    // Print ask form
    if (has_capability('mod/hotquestion:ask', $context)) {
        $mform->display();
    }
}

echo $output->container_start(null, 'questions_list');
// Print toolbar
echo $output->container_start("toolbar");
echo $output->toolbar(has_capability('mod/hotquestion:manage', $context));
echo $output->container_end();

// Print questions list
echo $output->questions(has_capability('mod/hotquestion:vote', $context));
echo $output->container_end();

// Finish the page
if (!$ajax){
    echo $output->footer();
}

