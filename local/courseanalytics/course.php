<?php
/**
 * Detailed analytics for a specific course.
 *
 * @package    local_courseanalytics
 * @copyright  2024 KKDES <https://kkdes.co.ke/>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/enrol/locallib.php');

$id = required_param('id', PARAM_INT);
$course = get_course($id);

require_login($course);
$context = context_course::instance($id);
require_capability('local/courseanalytics:view', $context);

$PAGE->set_url(new moodle_url('/local/courseanalytics/course.php', ['id' => $id]));
$PAGE->set_context($context);
$PAGE->set_title($course->fullname . ' - ' . get_string('courseanalytics', 'local_courseanalytics'));
$PAGE->set_heading($course->fullname);

$renderer = $PAGE->get_renderer('local_courseanalytics');

// Data fetching
$metrics = \local_courseanalytics\course_manager::get_course_metrics($id);
$sections = \local_courseanalytics\course_manager::get_section_details($id);
$students = \local_courseanalytics\course_manager::get_student_list($id);

$data = [
    'course' => [
        'id'        => $id,
        'fullname'  => $course->fullname,
        'shortname' => $course->shortname,
    ],
    'metrics'  => $metrics,
    'sections' => $sections,
    'students' => $students,
    'urls' => [
        'back'   => (new moodle_url('/local/courseanalytics/index.php'))->out(false),
        'export' => (new moodle_url('/local/courseanalytics/export.php', ['id' => $id]))->out(false),
    ],
    'footer_text' => get_string('developedby', 'local_courseanalytics')
        . ' <a href="' . get_string('kkdes_url', 'local_courseanalytics') . '" target="_blank">KKDES</a>',
];

echo $OUTPUT->header();
echo $renderer->render_course_details($data);
echo $OUTPUT->footer();
