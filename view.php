<?php  // $Id: view.php,v 1.6.2.3 2009/04/17 22:06:25 skodak Exp $

/**
 * This page prints a particular instance of hotquestion
 *
 * @author  Your Name <your@email.address>
 * @version $Id: view.php,v 1.6.2.3 2009/04/17 22:06:25 skodak Exp $
 * @package mod/hotquestion
 */

/// (Replace hotquestion with the name of your module and remove this line)

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

require_login($course, true, $cm);

add_to_log($course->id, "hotquestion", "view", "view.php?id=$cm->id", "$hotquestion->id");

/// Print the page header
$strhotquestions = get_string('modulenameplural', 'hotquestion');
$strhotquestion  = get_string('modulename', 'hotquestion');

$navlinks = array();
$navlinks[] = array('name' => $strhotquestions, 'link' => "index.php?id=$course->id", 'type' => 'activity');
$navlinks[] = array('name' => format_string($hotquestion->name), 'link' => '', 'type' => 'activityinstance');

$navigation = build_navigation($navlinks);

print_header_simple(format_string($hotquestion->name), '', $navigation, '', '', true,
              update_module_button($cm->id, $course->id, $strhotquestion), navmenu($course, $cm));

/// Print the main part of the page


$mform = new hotquestion_form();

if ($fromform=$mform->get_data()){
	
	//print_object($fromform);

	$data->hotquestion = $hotquestion->id;
	$data->content = $fromform->question;
	if(!($questionid = insert_record('hotquestion_questions', $data))){
		error("error in inserting questions!");
	}

    // Set current user as the first voter
	$votes->hotquestion = $hotquestion->id;
	$votes->question = $questionid;
    $votes->voter = $USER->id;
    if(!insert_record('hotquestion_votes', $votes)){
        error("error in inserting the votes!");
    }

    // Redirect to show questions. So that the page can be refreshed
	redirect('view.php?id='.$cm->id, get_string('questionsubmitted', 'hotquestion'));
}

//handle the new votes
//TODO: 判断是否有投票权
$q  = optional_param('q', -1, PARAM_INT);  // question ID to vote
if($q != -1 && !has_voted($q)){
    $votes->hotquestion = $hotquestion->id;
    $votes->question = $q;
    $votes->voter = $USER->id;

    if(!insert_record("hotquestion_votes", $votes)){
        error("error in inserting the votes!");
    }
}

$context = get_context_instance(CONTEXT_MODULE, $cm->id);
if(has_capability('mod/hotquestion:view', $context)){
    $mform->display();
}

$qusetions = new stdclass;
$questions->question_text = 0;
$questions->hotquestion = 0;
$questions->question_content = '';
//题目ID 所属活动 支持人数
$questions = get_records_sql("SELECT q.id, q.content, count(*) as count
                              FROM {$CFG->prefix}hotquestion_votes v,
                              {$CFG->prefix}hotquestion_questions q
                              WHERE v.question = q.id                              
                              AND   q.hotquestion = $hotquestion->id
                              GROUP BY v.question
                              ORDER BY count(*) DESC");                              

if($questions){
    $table->align = array ('left', 'right');//每一列在表格的left or right

    $table->cellpadding = 10;
    $table->width = '70%';

    $table->head = array('a', 'b');

    foreach ($questions as $question) {
        $line = array();
        $line[] = $question->content;
        
        $degree = $question->count;
        //print_object($question);
        if(has_capability('mod/hotquestion:view', $context)){
            if(!has_voted($question->id)){
                $degree .= "<a href=\"view.php?id=$cm->id&amp;q=$question->id\">
                    <img src=\"$CFG->pixpath/t/up.gif\"/></a>";
            }
        }
        $line[] = $degree;

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
