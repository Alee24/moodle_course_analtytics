<?php
/**
 * Navigation and system functions for Course Analytics.
 *
 * @package    local_courseanalytics
 * @copyright  2024 KKDES <https://kkdes.co.ke/>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend navigation for course.
 * This puts the Course Analytics link in the 'Reports' section of the course.
 *
 * @param global_navigation $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_courseanalytics_extend_navigation_course($navigation, $course, $context) {
    if (has_capability('local/courseanalytics:view', $context)) {
        // Find the 'Reports' node. In Moodle 4.x this is often 'reports' or 'coursereports'.
        $reportnode = $navigation->find('reports', navigation_node::TYPE_CONTAINER);
        if (!$reportnode) {
            $reportnode = $navigation->find('coursereports', navigation_node::TYPE_CONTAINER);
        }
        
        if (!$reportnode) {
            $reportnode = $navigation;
        }

        $url = new moodle_url('/local/courseanalytics/course.php', ['id' => $course->id]);
        $reportnode->add(
            'Course Analytics',
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'local_courseanalytics_report_link',
            new pix_icon('i/report', '', 'moodle')
        );
    }
}
