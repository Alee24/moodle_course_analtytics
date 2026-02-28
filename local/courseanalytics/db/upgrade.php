<?php
/**
 * Upgrade logic for local_courseanalytics.
 *
 * @package    local_courseanalytics
 * @copyright  2024 KKDES <https://kkdes.co.ke/>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Run upgrade logic.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_courseanalytics_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2024052000) {
        // Migration code would go here if we were upgrading from an older version.
        // For example, adding a new field:
        /*
        $table = new xmldb_table('local_courseanalytics_stats');
        $field = new xmldb_field('new_field', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'module_count');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        */
        upgrade_plugin_savepoint(true, 2024052000, 'local', 'courseanalytics');
    }

    return true;
}
