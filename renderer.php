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
 * A custmom renderer class that extends the plugin_renderer_base and is used by the hotquestion module
 *
 * @package   mod_hotquestion
 * @copyright 2012 Zhang Anzhen
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

class mod_hotquestion_renderer extends plugin_renderer_base {
    private $current_round;
    private $next_round;
    private $pre_round;    
    private $hotquestion;
    private $cm;
    private $context;
    private $course;    
    private $db_action;

    /**
     * Initialise internal objects.
     *
     * @param object $hotquestion
     * @param object $cm
     * @param object $context
     * @param object $course
     * @param object $db_action
     */
    function init($hotquestion, $cm, $context, $course, $db_action) {
        $this->hotquestion = $hotquestion;
        $this->cm = $cm;
        $this->context = $context;
        $this->course = $course;
        $this->db_action = $db_action;
    }

    /**
     * This function print the hotquestion introduction 
     *
     * @global object
     * @param object $hotquestion
     * @param object $cm
     */
    function introduction() {
        global $OUTPUT;
        echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
        echo format_module_intro('hotquestion', $this->hotquestion, $this->cm->id);
        echo $OUTPUT->box_end();
    } 

    /**
     * This function print the toolbuttons for questionlist 
     *
     * @global object
     * @param object $cm
     * @param object $context
     * @param object $hotquestion
     * @param int roundid
     * return alist of links
     */
    function toolbuttons($roundid) {
        global $OUTPUT;
        $output = '';
        $toolbuttons = array();

        //  Find out existed rounds
        $this->db_action->search_rounds($roundid, $this->current_round, $this->prev_round, $this->next_round);

        //  Print next/prev round bar
        if (!empty($this->prev_round)) {
            $url = new moodle_url('/mod/hotquestion/view.php', array('id'=>$this->cm->id, 'round'=>$this->prev_round->id));
            $toolbuttons[] = html_writer::link($url, $OUTPUT->pix_icon('t/collapsed_rtl', get_string('previousround', 'hotquestion')), array('class' => 'toolbutton'));
        } else {
            $toolbuttons[] = html_writer::tag('span', $OUTPUT->pix_icon('t/collapsed_empty_rtl', ''), array('class' => 'dis_toolbutton'));
        }
        if (!empty($this->next_round)) {
            $url = new moodle_url('/mod/hotquestion/view.php', array('id'=>$this->cm->id, 'round'=>$this->next_round->id));
            $toolbuttons[] = html_writer::link($url, $OUTPUT->pix_icon('t/collapsed', get_string('nextround', 'hotquestion')), array('class' => 'toolbutton'));
        } else {
            $toolbuttons[] = html_writer::tag('span', $OUTPUT->pix_icon('t/collapsed_empty', ''), array('class' => 'dis_toolbutton'));
        }

        // Print new round bar
        if (has_capability('mod/hotquestion:manage', $this->context)) {
            $options = array();
            $options['id'] = $this->cm->id;
            $options['action'] = 'newround';
            $url = new moodle_url('/mod/hotquestion/view.php', $options);
            $toolbuttons[] = html_writer::link($url, $OUTPUT->pix_icon('t/add', get_string('newround', 'hotquestion')), array('class' => 'toolbutton'));
        }

        // Print refresh button
        $url = new moodle_url('/mod/hotquestion/view.php', array('id'=>$this->cm->id));
        $toolbuttons[] = html_writer::link($url, $OUTPUT->pix_icon('t/reload', get_string('reload')), array('class' => 'toolbutton'));
	
        // return all available toolbuttons
        $output .= html_writer::alist($toolbuttons, array('id' => 'toolbar'));   
        return $output;
    }

    /**
     * This function search existed questions, and print it in a question list, which include the question content, the author,the time      * and the heat, if the user has capability of vote, it will display a icron of vote
     *
     * @global object
     * @global object
     * @global object
     * @global object
     * @param object $hotquestion
     * @param object $cm
     * @param object $course
     * @param object $context
     * return table of questionlist
     */
    function display_questionlist() {
        global $DB, $CFG, $OUTPUT, $USER;	
        $output = '';
        if ($this->current_round->endtime == 0) {
            $this->current_round->endtime = 0xFFFFFFFF;  //Hack
        }

	// Search questions in current round
        $this->db_action->search_questions($this->current_round, $questions);	
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
                    $a->user = '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $user->id . '&amp;course=' . $this->course->id . '">' . fullname($user) . '</a>';
                }
                $a->time = userdate($question->time).'&nbsp('.get_string('early', 'assignment', format_time(time() - $question->time)) . ')';
                $info = '<div class="author">'.get_string('authorinfo', 'hotquestion', $a).'</div>';
                $line[] = $content.$info;
                $heat = $question->votecount;

                // Print the vote cron
                if (has_capability('mod/hotquestion:vote', $this->context) && $question->userid != $USER->id){
                    if (!$this->db_action->has_voted($question->id)){
                        $heat .= '&nbsp;<a href="view.php?id='.$this->cm->id.'&action=vote&q='.$question->id.'" class="hotquestion_vote" id="question_'.$question->id.'"><img src="'.$OUTPUT->pix_url('s/yes').'" title="'.get_string('vote', 'hotquestion') .'" alt="'. get_string('vote', 'hotquestion') .'"/></a>';
                    }
                }
                $line[] = $heat;
                $table->data[] = $line;
            }
            $output .= html_writer::table($table);
            return $output;	
        }
        else {
            $output .= $OUTPUT->box(get_string('noquestions', 'hotquestion'), 'center', '70%');
            return $output;
        }
        return $output;
    }
}

