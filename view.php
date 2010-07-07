<?php  // $Id: view.php,v 1.6.2.3 2009/04/17 22:06:25 skodak Exp $

/**
 * This page prints a particular instance of hotquestion
 *
 * @author  Your Name <your@email.address>
 * @version $Id: view.php,v 1.6.2.3 2009/04/17 22:06:25 skodak Exp $
 * @package mod/hotquestion
 */

require_once("../../config.php");
require_once($CFG->dirroot . '/mod/hotquestion/mod_form.php');

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$h  = optional_param('h', 0, PARAM_INT);  // hotquestion instance ID

if ($id) {
    if (! $cm = get_coursemodule_from_id('hotquestion', $id)) {
        error('Course Module ID was incorrect');
    }

    if (! $course = get_record('course', 'id', $cm->course)) {
        error('Course is misconfigured');
    }

    if (! $hotquestion = get_record('hotquestion', 'id', $cm->instance)) {
        error('Course module is incorrect');
    }

} else if ($h) {
    if (! $hotquestion = get_record('hotquestion', 'id', $h)) {
        error('Course module is incorrect');
    }
    if (! $course = get_record('course', 'id', $hotquestion->course)) {
        error('Course is misconfigured');
    }
    if (! $cm = get_coursemodule_from_instance('hotquestion', $hotquestion->id, $course->id)) {
        error('Course Module ID was incorrect');
    }

} else {
    error('You must specify a course_module ID or an instance ID');
}

$context = get_context_instance(CONTEXT_MODULE, $cm->id);
require_capability('mod/hotquestion:view', $context);

/// Print the page header
$strhotquestions = get_string('modulenameplural', 'hotquestion');
$strhotquestion  = get_string('modulename', 'hotquestion');

$navlinks = array();
$navlinks[] = array('name' => $strhotquestions, 'link' => "index.php?id=$course->id", 'type' => 'activity');
$navlinks[] = array('name' => format_string($hotquestion->name), 'link' => '', 'type' => 'activityinstance');

$navigation = build_navigation($navlinks);

print_header_simple(format_string($hotquestion->name), '', $navigation, '', '', true,
              update_module_button($cm->id, $course->id, $strhotquestion), navmenu($course, $cm));


if(has_capability('mod/hotquestion:ask', $context)){
    $mform = new hotquestion_form($hotquestion->anonymouspost);

    if ($fromform=$mform->get_data()){

        $data->hotquestion = $hotquestion->id;
        $data->content = trim($fromform->question);
        $data->userid = $USER->id;
        $data->time = time();
        if (isset($fromform->anonymous) && $hotquestion->anonymouspost)
            $data->anonymous = $fromform->anonymous;

        if (!empty($data->content)){
            if(!($questionid = insert_record('hotquestion_questions', $data))){
                error("error in inserting questions!");
            }
        } else {
            redirect('view.php?id='.$cm->id, get_string('invalidquestion', 'hotquestion'));
        }

        add_to_log($course->id, 'hotquestion', 'add question', "view.php?id=$cm->id", $hotquestion->id, $data->content);

        // Redirect to show questions. So that the page can be refreshed
        redirect('view.php?id='.$cm->id, get_string('questionsubmitted', 'hotquestion'));
    }
}

//handle the new votes
$action  = optional_param('action', '', PARAM_ACTION);  // Vote or unvote
if (!empty($action)) {
    switch ($action) {

    case 'vote':
    case 'unvote':
        require_capability('mod/hotquestion:vote', $context);
        $q  = required_param('q', PARAM_INT);  // question ID to vote
        $question = get_record('hotquestion_questions', 'id', $q);
        if ($question && $USER->id != $question->userid) {
            add_to_log($course->id, 'hotquestion', 'vote', "view.php?id=$cm->id", $hotquestion->id);

            if ($action == 'vote') {
                if (!has_voted($q)){
                    $votes->question = $q;
                    $votes->voter = $USER->id;

                    if(!insert_record('hotquestion_votes', $votes)){
                        error("error in inserting the votes!");
                    }
                }
            } else {
                if (has_voted($q)){
                    delete_records('hotquestion_votes', 'question', $q, 'voter', $USER->id);
                }
            }
        }
    }
}

/// Print the main part of the page


// Print hotquestion description
if (trim($hotquestion->intro)) {
   $formatoptions->noclean = true;
   $formatoptions->para    = false;
   print_box(format_text($hotquestion->intro, FORMAT_MOODLE, $formatoptions), 'generalbox', 'intro');
}


// Ask form
if(has_capability('mod/hotquestion:ask', $context)){
    $mform->display();
}


// Questions list
add_to_log($course->id, "hotquestion", "view", "view.php?id=$cm->id", "$hotquestion->id");

$questions = get_records_sql("SELECT q.*, count(v.voter) as count
                              FROM {$CFG->prefix}hotquestion_questions q
                              LEFT JOIN {$CFG->prefix}hotquestion_votes v
                              ON v.question = q.id
                              WHERE q.hotquestion = $hotquestion->id
                              GROUP BY q.id
                              ORDER BY count DESC");

if($questions){

    $table->cellpadding = 10;
    $table->class = 'generaltable';
    $table->align = array ('left', 'center');
    $table->size = array('', '1%');

    $table->head = array(get_string('question', 'hotquestion'), get_string('heat', 'hotquestion'));

    foreach ($questions as $question) {
        $line = array();

        $formatoptions->para  = false;
        $content = format_text($question->content, FORMAT_MOODLE, $formatoptions);
        
        $user = get_record('user', 'id', $question->userid);
        if ($question->anonymous) {
            $a->user = get_string('anonymous', 'hotquestion');
        } else {
            $a->user = '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $user->id . '&amp;course=' . $course->id . '">' . fullname($user) . '</a>';
        }
        $a->time = userdate($question->time).'&nbsp('.get_string('early', 'assignment', format_time(time() - $question->time)) . ')';
        $info = '<div class="author">'.get_string('authorinfo', 'hotquestion', $a).'</div>';

        $line[] = $content.$info;
        
        $heat = $question->count;
        if (has_capability('mod/hotquestion:vote', $context) && $question->userid != $USER->id){
            if (!has_voted($question->id)){
                $heat .= '&nbsp;<a href="view.php?id='.$cm->id.'&action=vote&q='.$question->id.'"><img src="'.$CFG->pixpath.'/s/yes.gif" title="'.get_string('vote', 'hotquestion') .'" alt="'. get_string('vote', 'hotquestion') .'"/></a>';
            } else {
                /* temply disable unvote to see effect
                $heat .= '&nbsp;<a href="view.php?id='.$cm->id.'&action=unvote&q='.$question->id.'"><img src="'.$CFG->pixpath.'/s/no.gif" title="'.get_string('unvote', 'hotquestion') .'" alt="'. get_string('unvote', 'hotquestion') .'"/></a>';
                 */
            }
        }

        $line[] = $heat;

        $table->data[] = $line;
    }//for

    print_table($table);

}else{
    print_simple_box(get_string('noquestions', 'hotquestion'), 'center', '70%');
}


/// Finish the page
print_footer($course);

//return whether the user has voted on question
function has_voted($question, $user = -1) {
    global $USER;

    if ($user == -1)
        $user = $USER->id;

    return record_exists('hotquestion_votes', 'question', $question, 'voter', $user);
}

?>
