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
 * hotquestion render class
 *
 * @package   mod_hotquestion
 * @copyright 2012 Zhang Anzhen
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/*defined('MOODLE_INTERNAL') || die();

//require_once(dirname(__FILE__).'/config.php');
//require_once(dirname(__FILE__).'/lib.php');
//require_once(dirname(__FILE__).'/locallib.php');
//require_once($CFG->dirroot . '/mod/hotquestion/mod_form.php');

/**
 * hotquestion renderer class
 */
/*
*/
class mod_hotquestion_renderer {

    public $current_round;
    public $next_round;
    public $pre_round;    

    // handle get_data()->question
    function handle_question($fromform, $hotquestion, $course, $cm){
        global $USER, $CFG, $DB;
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
            add_to_log($course->id, 'hotquestion', 'add question', "view.php?id=$cm->id", $data->content, $cm->id);
	    redirect('view.php?id='.$cm->id, get_string('questionsubmitted', 'hotquestion'));
        } else {
            redirect('view.php?id='.$cm->id, get_string('invalidquestion', 'hotquestion'));
        }
    }

    // handle new vote on question
    function handle_vote($course, $cm){
	global $DB, $USER;
	$q = required_param('q',PARAM_INT); //quesiton ID to vote
	$question = $DB->get_record('hotquestion_questions', array('id'=>$q));
	if ($question && $USER->id != $question->userid) {
	    add_to_log($course->id, 'hotquestion', 'update vote', "view.php?id=$cm->id", $q, $cm->id);
	    if (!has_voted($q)) {
	        $votes->question = $q;
	        $votes->voter = $USER->id;
	        if (!$DB->insert_record('hotquestion_votes', $votes))
	            error("error in inserting the votes!");
	     } else 
	          delete_records('hotquestion_votes', 'question', $q, 'voter', $USER->id); 
	 }
    }

    // handle new_round process
    function new_round($hotquestion, $cm) {
	global $DB;
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

   //look for exist rounds from database
   function lookfor_rounds($hotquestion, $roundid) {
	global $DB;
        $rounds = $DB->get_records('hotquestion_rounds', array('hotquestion' => $hotquestion->id), 'id ASC');
	if (empty($rounds)) {
	    // Create the first round
	    $round->starttime = time();
	    $round->endtime = 0;
	    $round->hotquestion = $hotquestion->id;
	    $round->id = $DB->insert_record('hotquestion_rounds', $round);
	    $rounds[] = $round;
	}
//	$roundid  = optional_param('round', -1, PARAM_INT);
	$ids = array_keys($rounds);
	if ($roundid != -1 && array_key_exists($roundid, $rounds)) {
	    $this->current_round = $rounds[$roundid];
	    $current_key = array_search($roundid, $ids);
	    if (array_key_exists($current_key-1, $ids)) {
		$this->prev_round = $rounds[$ids[$current_key-1]];
	    }
	    if (array_key_exists($current_key+1, $ids)) {
		$this->next_round = $rounds[$ids[$current_key+1]];
	    }

	    $roundnum = $current_key+1;
	} else {
	    // Use the last round
	    $this->current_round = array_pop($rounds);
	    $this->prev_round = array_pop($rounds);
	    $roundnum = array_search($this->current_round->id, $ids) + 1;
	}
    }

    // show toolbuttons
    function toolbuttons($cm, $context) {
	global $OUTPUT;
	$output = '';
	$toolbuttons = array();
	//  add next/prev round bar
	if (!empty($this->prev_round)) {
	    $url = new moodle_url('/mod/hotquestion/view.php', array('id'=>$cm->id, 'round'=>$this->prev_round->id));
	    $toolbuttons[] = html_writer::link($url, $OUTPUT->pix_icon('t/collapsed_rtl', get_string('previousround', 'hotquestion')), array('class' => 'toolbutton'));
	} else {
	    $toolbuttons[] = html_writer::tag('span', $OUTPUT->pix_icon('t/collapsed_empty_rtl', ''), array('class' => 'dis_toolbutton'));
	}

	if (!empty($this->next_round)) {
	    $url = new moodle_url('/mod/hotquestion/view.php', array('id'=>$cm->id, 'round'=>$this->next_round->id));
	    $toolbuttons[] = html_writer::link($url, $OUTPUT->pix_icon('t/collapsed', get_string('nextround', 'hotquestion')), array('class' => 'toolbutton'));
	} else {
	    $toolbuttons[] = html_writer::tag('span', $OUTPUT->pix_icon('t/collapsed_empty', ''), array('class' => 'dis_toolbutton'));
	}

	// add new round bar
	if (has_capability('mod/hotquestion:manage', $context)) {
	    $options = array();
	    $options['id'] = $cm->id;
	    $options['action'] = 'newround';
	    $url = new moodle_url('/mod/hotquestion/view.php', $options);
	    $toolbuttons[] = html_writer::link($url, $OUTPUT->pix_icon('t/add', get_string('newround', 'hotquestion')), array('class' => 'toolbutton'));
	}

	// add refresh button
	$url = new moodle_url('/mod/hotquestion/view.php', array('id'=>$cm->id));
	$toolbuttons[] = html_writer::link($url, $OUTPUT->pix_icon('t/reload', get_string('reload')), array('class' => 'toolbutton'));
	
	// all available toolbuttons
	$output .= html_writer::alist($toolbuttons, array('id' => 'toolbar'));   
	return $output;
    }

   
    // display question list
    function display_questionlist($hotquestion, $cm, $course, $context) {
	global $DB, $CFG, $OUTPUT, $USER;	
	$output = '';
	if ($this->current_round->endtime == 0)
   	    $this->current_round->endtime = 0xFFFFFFFF;  //Hack

	$questions = $DB->get_records_sql("SELECT q.*, count(v.voter) as votecount
                              FROM {$CFG->prefix}hotquestion_questions q
                              LEFT JOIN {$CFG->prefix}hotquestion_votes v
                              ON v.question = q.id
                              WHERE q.hotquestion = $hotquestion->id
                                    AND q.time >= {$this->current_round->starttime}
                                    AND q.time <= {$this->current_round->endtime}
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
		//    if (!has_voted($question->id)){

		          $heat .= '&nbsp;<a href="view.php?id='.$cm->id.'&action=vote&q='.$question->id.'" class="hotquestion_vote" id="question_'.$question->id.'"><img src="'.$OUTPUT->pix_url('s/yes').'" title="'.get_string('vote', 'hotquestion') .'" alt="'. get_string('vote', 'hotquestion') .'"/></a>';
		   
		  //  } else {

		        // temply disable unvote to see effect
		  //      $heat .= '&nbsp;<a href="view.php?id='.$cm->id.'&action=unvote&q='.$question->id.'"><img src="'.$OUTPUT->pix_url('s/no').'" title="'.get_string('unvote', 'hotquestion') .'" alt="'. get_string('unvote', 'hotquestion') .'"/></a>';
		         
		    }

	        $line[] = $heat;
		$table->data[] = $line;
	    }
	    $output .= html_writer::table($table);
	    return $output;	
       }
       else {
	    $output .= box(get_string('noquestions', 'hotquestion'), 'center', '70%');
	    return $output;
        }
	return $output;
    }
}

