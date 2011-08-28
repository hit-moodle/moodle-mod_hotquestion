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

/// Print the page header
$context = get_context_instance(CONTEXT_MODULE, $cm->id);
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

// Post question
if(has_capability('mod/hotquestion:ask', $context)){
    $mform = new hotquestion_form(null, $hotquestion->anonymouspost);

    if ($fromform=$mform->get_data()){

        $data->hotquestion = $hotquestion->id;
        $data->content = trim($fromform->question);
        $data->userid = $USER->id;
        $data->time = time();
        if (isset($fromform->anonymous) && $fromform->anonymous && $hotquestion->anonymouspost) {
            $data->anonymous = $fromform->anonymous;
            // Assume this user is guest
            $data->userid = $CFG->siteguest;
        }

        if (!empty($data->content)) {
            $DB->insert_record('hotquestion_questions', $data);
        } else {
            redirect('view.php?id='.$cm->id, get_string('invalidquestion', 'hotquestion'));
        }

        add_to_log($course->id, 'hotquestion', 'add question', "view.php?id=$cm->id", $data->content, $cm->id);

        // Redirect to show questions. So that the page can be refreshed
        if (!$ajax){
            redirect('view.php?id='.$cm->id, get_string('questionsubmitted', 'hotquestion'));
        }
    }
}

// Output starts here
if (!$ajax){
    echo $OUTPUT->header();
}

// Handle the new votes
$action  = optional_param('action', '', PARAM_ACTION);  // Vote or unvote
if (!empty($action)) {
    switch ($action) {

    case 'vote':
    case 'unvote':
        require_capability('mod/hotquestion:vote', $context);
        $q  = required_param('q', PARAM_INT);  // question ID to vote
        $question = $DB->get_record('hotquestion_questions', array('id'=>$q));
        if ($question && $USER->id != $question->userid) {
            add_to_log($course->id, 'hotquestion', 'update vote', "view.php?id=$cm->id", $q, $cm->id);

            if ($action == 'vote') {
                if (!has_voted($q)){
                    $votes->question = $q;
                    $votes->voter = $USER->id;

                    if(!$DB->insert_record('hotquestion_votes', $votes)){
                        error("error in inserting the votes!");
                    }
                }
            } else {
                if (has_voted($q)){
                    delete_records('hotquestion_votes', 'question', $q, 'voter', $USER->id);
                }
            }
        }
        break;

    case 'newround':
        // Close the latest round
        $old = array_pop($DB->get_records('hotquestion_rounds', array('hotquestion'=>$hotquestion->id), 'id DESC', '*', 0, 1));
        $old->endtime = time();
        $DB->update_record('hotquestion_rounds', $old);
        // Open a new round
        $new->hotquestion = $hotquestion->id;
        $new->starttime = time();
        $new->endtime = 0;
        $rid = $DB->insert_record('hotquestion_rounds', $new);
        add_to_log($course->id, 'hotquestion', 'add round', "view.php?id=$cm->id&round=$rid", $rid, $cm->id);
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

echo $OUTPUT->container_start(null, 'questions_list');
// Look for rounds
$rounds = $DB->get_records('hotquestion_rounds', array('hotquestion' => $hotquestion->id), 'id ASC');
if (empty($rounds)) {
    // Create the first round
    $round->starttime = time();
    $round->endtime = 0;
    $round->hotquestion = $hotquestion->id;
    $round->id = $DB->insert_record('hotquestion_rounds', $round);
    $rounds[] = $round;
}

$roundid  = optional_param('round', -1, PARAM_INT);

$ids = array_keys($rounds);
if ($roundid != -1 && array_key_exists($roundid, $rounds)) {
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

// Print round toolbar
$toolbuttons = array();
echo $OUTPUT->container_start("toolbar");
if (!empty($prev_round)) {
    $url = new moodle_url('/mod/hotquestion/view.php', array('id'=>$cm->id, 'round'=>$prev_round->id));
    $toolbuttons[] = html_writer::link($url, $OUTPUT->pix_icon('t/collapsed_rtl', get_string('previousround', 'hotquestion')), array('class' => 'toolbutton'));
} else {
    $toolbuttons[] = html_writer::tag('span', $OUTPUT->pix_icon('t/collapsed_empty_rtl', ''), array('class' => 'dis_toolbutton'));
}

if (!empty($next_round)) {
    $url = new moodle_url('/mod/hotquestion/view.php', array('id'=>$cm->id, 'round'=>$next_round->id));
    $toolbuttons[] = html_writer::link($url, $OUTPUT->pix_icon('t/collapsed', get_string('nextround', 'hotquestion')), array('class' => 'toolbutton'));
} else {
    $toolbuttons[] = html_writer::tag('span', $OUTPUT->pix_icon('t/collapsed_empty', ''), array('class' => 'dis_toolbutton'));
}

if (has_capability('mod/hotquestion:manage', $context)) {
    $options = array();
    $options['id'] = $cm->id;
    $options['action'] = 'newround';
    $url = new moodle_url('/mod/hotquestion/view.php', $options);
    $toolbuttons[] = html_writer::link($url, $OUTPUT->pix_icon('t/add', get_string('newround', 'hotquestion')), array('class' => 'toolbutton'));
}

// Refresh button
$toolbuttons[] = html_writer::link('view.php?id='.$cm->id, $OUTPUT->pix_icon('t/reload', get_string('reload')), array('class' => 'toolbutton'));
echo html_writer::alist($toolbuttons, array('id' => 'toolbar'));
echo $OUTPUT->container_end();

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

    $table = new html_table();
    $table->cellpadding = 10;
    $table->class = 'generaltable';
    $table->width = '100%';
    $table->align = array ('left', 'center');

    $table->head = array(get_string('question', 'hotquestion'), get_string('heat', 'hotquestion'));

    foreach ($questions as $question) {
        $line = array();

        $formatoptions->para  = false;
        $content = format_text($question->content, FORMAT_MOODLE, $formatoptions);

        $user = $DB->get_record('user', array('id'=>$question->userid));
        if ($question->anonymous) {
            $a->user = get_string('anonymous', 'hotquestion');
        } else {
            $a->user = '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $user->id . '&amp;course=' . $course->id . '">' . fullname($user) . '</a>';
        }
        $a->time = userdate($question->time).'&nbsp('.get_string('early', 'assignment', format_time(time() - $question->time)) . ')';
        $info = '<div class="author">'.get_string('authorinfo', 'hotquestion', $a).'</div>';

        $line[] = $content.$info;

        $heat = $question->votecount;
        if (has_capability('mod/hotquestion:vote', $context) && $question->userid != $USER->id){
            if (!has_voted($question->id)){
                $heat .= '&nbsp;<a href="view.php?id='.$cm->id.'&action=vote&q='.$question->id.'" class="hotquestion_vote" id="question_'.$question->id.'"><img src="'.$OUTPUT->pix_url('s/yes').'" title="'.get_string('vote', 'hotquestion') .'" alt="'. get_string('vote', 'hotquestion') .'"/></a>';
            } else {
                /* temply disable unvote to see effect
                $heat .= '&nbsp;<a href="view.php?id='.$cm->id.'&action=unvote&q='.$question->id.'"><img src="'.$OUTPUT->pix_url('s/no').'" title="'.get_string('unvote', 'hotquestion') .'" alt="'. get_string('unvote', 'hotquestion') .'"/></a>';
                 */
            }
        }

        $line[] = $heat;

        $table->data[] = $line;
    }//for

    echo html_writer::table($table);

}else{
    echo $OUTPUT->box(get_string('noquestions', 'hotquestion'), 'center', '70%');
}
echo $OUTPUT->container_end();

add_to_log($course->id, "hotquestion", "view", "view.php?id=$cm->id&round=$roundid", $roundid, $cm->id);

// Finish the page
if (!$ajax){
    echo $OUTPUT->footer();
}
