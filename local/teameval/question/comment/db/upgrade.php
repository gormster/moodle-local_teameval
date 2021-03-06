<?php

function xmldb_teamevalquestion_comment_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2015121801) {
        // Code to add the column, generated by the 'View PHP Code' option of the XMLDB editor.

        upgrade_plugin_savepoint(true, 2015121801, 'teamevalquestion', 'comment');
    }

    if ($oldversion < 2016061400) {

        // Define field anonymous to be added to teamevalquestion_comment.
        $table = new xmldb_table('teamevalquestion_comment');

        $field = new xmldb_field('anonymous', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'description');

        // Conditionally launch add field anonymous.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('optional', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'anonymous');

        // Conditionally launch add field optional.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }


        upgrade_plugin_savepoint(true, 2016061400, 'teamevalquestion', 'comment');
    }

    if ($oldversion < 2016061500) {
        upgrade_plugin_savepoint(true, 2016061500, 'teamevalquestion', 'comment');
    }

    if ($oldversion < 2016100700) {
        upgrade_plugin_savepoint(true, 2016100700, 'teamevalquestion', 'comment');
    }

    if ($oldversion < 2016101400) {
        upgrade_plugin_savepoint(true, 2016101400, 'teamevalquestion', 'comment');
    }

}
