<?php
/**
 * Export analytics data to CSV.
 *
 * @package    local_courseanalytics
 * @copyright  2024 KKDES <https://kkdes.co.ke/>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');

$id = optional_param('id', 0, PARAM_INT); // Course ID, 0 for all courses

require_login();
$context = $id ? context_course::instance($id) : context_system::instance();
require_capability('local/courseanalytics:view', $context);

$filename = 'course_analytics_' . date('Ymd_His') . '.csv';
$export = new \csv_export_writer();
$export->set_filename($filename);

if ($id) {
    // Export single course details (students)
    $course = get_course($id);
    $students = \local_courseanalytics\course_manager::get_student_list($id);
    
    $export->add_data([get_string('coursename', 'local_courseanalytics'), $course->fullname]);
    $export->add_data([]);
    $export->add_data(['Name', 'Email', get_string('lastaccess', 'local_courseanalytics')]);
    
    foreach ($students as $student) {
        $export->add_data([$student['fullname'], $student['email'], $student['lastaccess']]);
    }
} else {
    // Export course overview
    $courses = \local_courseanalytics\course_manager::get_courses();
    $export->add_data([
        get_string('coursename', 'local_courseanalytics'),
        get_string('category', 'local_courseanalytics'),
        get_string('students', 'local_courseanalytics'),
        'Completion Rate (%)'
    ]);
    
    foreach ($courses as $course) {
        $metrics = \local_courseanalytics\course_manager::get_course_metrics($course->id);
        $export->add_data([
            $course->fullname,
            $course->categoryname,
            $metrics['total_students'],
            $metrics['completion_rate']
        ]);
    }
}

$export->download_file();
