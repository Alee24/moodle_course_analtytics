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
 * This adds the Course Analytics link to reports container or directly to course node.
 *
 * @param global_navigation $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_courseanalytics_extend_navigation_course($navigation, $course, $context) {
    if (has_capability('local_courseanalytics:view', $context)) {
        $url = new moodle_url('/local/courseanalytics/course.php', ['id' => $course->id]);
        $name = 'Course Analytics';
        
        // Find 'reports' or 'coursereports'. In Moodle 4.x, 'reports' is the secondary nav container.
        $reportnode = $navigation->find('reports', navigation_node::TYPE_CONTAINER);
        if (!$reportnode) {
            $reportnode = $navigation->find('coursereports', navigation_node::TYPE_CONTAINER);
        }

        if ($reportnode) {
            $reportnode->add($name, $url, navigation_node::TYPE_SETTING, null, 'local_courseanalytics_report_node', new pix_icon('i/report', '', 'moodle'));
        } else {
            // Add to the main course navigation if no reports container found
            $navigation->add($name, $url, navigation_node::TYPE_SETTING, null, 'local_courseanalytics_report_direct', new pix_icon('i/report', '', 'moodle'));
        }
    }
}
