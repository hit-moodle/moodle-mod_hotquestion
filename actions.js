
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
 * Handle submitting question and voting action of hotquestion 
 * using Ajax of YUI
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package   mod_hotquestion
 * @copyright 2011 Sun Zhigang
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

YUI().use('io-base', 'node', function(Y){
    var submitButton = Y.one('#id_submitbutton');
    var questionText = Y.one('#id_question');
    var courseID = Y.one('#hotquestion_courseid');
    var voteButton = Y.one('.hotquestion_vote');
    var contentdiv = Y.one('.region-content');
    var refreshButton = Y.one('#refresh_button');

    var courseid = courseID.get('value');
    var sUrl = 'view.php';
    var refresh = false;

    function reBind(){
        submitButton = Y.one('#id_submitbutton');
        questionText = Y.one('#id_question');
        courseID = Y.one('#hotquestion_courseid');
        voteButton = Y.one('.hotquestion_vote');
        contentdiv = Y.one('.region-content');
        refreshButton = Y.one('#refresh_button');

        if(submitButton){
            submitButton.on('click', submitQuestion);
        }

        if(voteButton){
            voteButton.on('click', linkAction);
        }

        if(refreshButton){
            refreshButton.on('click', linkAction);
        }
    }

    var handleSuccess = function(ioId, o){
        contentdiv.set("innerHTML", o.responseText);
        // Because it replaced the nodes, we need to 
        // find the new nodes and bind the click events again
        reBind();
        questionText.set("innerHTML", '');
        
        if(!refresh){
            showPageMessage(0);
        }
    }

    var handleFailure = function(ioId, o){
        if(submitButton.hasAttribute('disabled')){
            submitButton.removeAttribute('disabled');
        }
        showPageMessage(2);
    }

    Y.on('io:success', handleSuccess);
    Y.on('io:failure', handleFailure);
    
    /**
     * Page messages is in div#page_message. They are hiden by default.
     * Call this function to show a message by its index.
     * @param index {int} message's NO.
     */
    function showPageMessage(index){
        var page_messages = Y.all("#page_message span");
        page_messages.setStyle('display', 'none');

        var message = page_messages.item(index);
        message.setStyle('display', '');
    }

    function addAjax(data){
        data += "&async=1";
        return data;
    }

    var submitQuestion = function(e){
        e.preventDefault();
        refresh = false;
        
        // To avoid multiple clicks
        submitButton.set('disabled', 'disabled');

        var question = questionText.get('value');
        var sesskey = courseID.next();
        var qform = sesskey.next();
        var submit = submitButton.get('value');
        var anonymous = submitButton.next().one('input').get('checked');

        var data = "id="+courseid+
                   "&sesskey="+sesskey.get('value')+
                   "&_qf__hotquestion_form="+qform.get('value')+
                   "&question="+question+
                   "&submitbutton="+submit
        if(anonymous){
            data += "&anonymous=1";
        }

        data = addAjax(data);

        if(question == ''){
            submitButton.removeAttribute('disabled');
            showPageMessage(1);
        } else {
            var cfg = {
                method : "POST",
                data : data
            }
            var request = Y.io(sUrl, cfg);
        }
    }

    var linkAction = function(e){
        e.preventDefault();
        refresh = true;

        var data = this.get('href').split('?',2)[1];
        data = addAjax(data);
        var cfg = {
            method : "GET",
            data : data
        }

        var request = Y.io(sUrl, cfg);
    }

    if(submitButton){
        submitButton.on('click', submitQuestion);
    }

    if(voteButton){
        voteButton.on('click', linkAction);
    }

    if(refreshButton){
        refreshButton.on('click', linkAction);
    }
});
