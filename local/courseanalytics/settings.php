<?php
/**
 * Settings configuration for local_courseanalytics
 *
 * @package    local_courseanalytics
 * @copyright  2024 KKDES <https://kkdes.co.ke/>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    // Add top-level "Analytics" tab to Site Administration.
    $ADMIN->add('root', new admin_category('local_courseanalytics_tab', get_string('admin_analytics_tab', 'local_courseanalytics')));

    // Insert the Dashboard page link under the new Analytics tab.
    $ADMIN->add('local_courseanalytics_tab', new admin_externalpage(
        'local_courseanalytics_index',
        get_string('manageanalytics', 'local_courseanalytics'),
        new moodle_url('/local/courseanalytics/index.php'),
        'local/courseanalytics:view'
    ));
}
