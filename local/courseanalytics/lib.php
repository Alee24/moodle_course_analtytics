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
        // Find the 'Reports' node or similar
        $reportnode = $navigation->find('coursereports', navigation_node::TYPE_CONTAINER);
        
        if (!$reportnode) {
            // If not found (some themes), we can add to course settings or similar
            $reportnode = $navigation;
        }

        $url = new moodle_url('/local/courseanalytics/course.php', ['id' => $course->id]);
        $reportnode->add(
            'Course Analytics Premium',
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'local_courseanalytics_report',
            new pix_icon('i/report', '', 'local_courseanalytics')
        );
    }
}
