<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_enrol_bank_upgrade($oldversion) {
    global $CFG, $DB;

    // Moodle v3.1.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2016052301) {
        // Get roles with manager archetype.
        $managerroles = get_archetype_roles('manager');
        if (!empty($managerroles)) {
            // Remove wrong CAP_PROHIBIT from bank:holdkey.
            foreach ($managerroles as $role) {
                $DB->execute("DELETE
                                FROM {role_capabilities}
                               WHERE roleid = ? AND capability = ? AND permission = ?",
                        array($role->id, 'enrol/bank:holdkey', CAP_PROHIBIT));
            }
        }
        upgrade_plugin_savepoint(true, 2016052301, 'enrol', 'bank');
    }

    // Automatically generated Moodle v3.2.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.3.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.4.0 release upgrade line.
    // Put any upgrade step following this.

    return true;
}
