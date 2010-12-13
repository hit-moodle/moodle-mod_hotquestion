<?php

function xmldb_hotquestion_upgrade($oldversion=0) {
    global $CFG, $DB;

    $result = true;

    //===== 1.9.0 upgrade line ======//
    if ($result && $oldversion < 2007040100) {

    /// Define field course to be added to hotquestion
        $table = new XMLDBTable('hotquestion');
        $field = new XMLDBField('course');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'id');
    /// Launch add field course
        $result = $result && $table->add_field($field);

    /// Define field intro to be added to hotquestion
        $field = new xmldb_field('intro');
        $field->set_attributes(XMLDB_TYPE_TEXT, 'medium', null, null, null, null, null, null, 'name');

    /// Launch add field intro
        $result = $result && $table->add_field($field);

    /// Define field introformat to be added to hotquestion
        $field = new xmldb_field('introformat');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'intro');
    /// Launch add field introformat
        $result = $result && $table->add_field($field);
    }

    if ($result && $oldversion < 2007040101) {

    /// Define field timecreated to be added to hotquestion
        $table = new xmldb_table('hotquestion');
        $field = new xmldb_field('timecreated');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'introformat');

    /// Launch add field timecreated
        $result = $result && $table->add_field($field);

        $field = new xmldb_field('timemodified');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'timecreated');
    /// Launch add field timemodified
        $result = $result && $table->add_field($table, $field);

    /// Define index course (not unique) to be added to hotquestion
        $result = $result && $table->add_index('course', XMLDB_INDEX_NOTUNIQUE, array('course'));
    }

    if ($result && $oldversion < 2007040200) {
    /// Add some actions to get them properly displayed in the logs
        $rec = new stdClass;
        $rec->module = 'hotquestion';
        $rec->action = 'add';
        $rec->mtable = 'hotquestion';
        $rec->filed  = 'name';
    /// Insert the add action in log_display
        $result = $DB->insert_record('log_display', $rec);
    /// Now the update action
        $rec->action = 'update';
        $result = $DB->insert_record('log_display', $rec);
    /// Now the view action
        $rec->action = 'view';
        $result = $DB->insert_record('log_display', $rec);
    }
    //===== 2.0 upgrade start here ======//

    return $result;
}
