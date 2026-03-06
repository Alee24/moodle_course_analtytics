<?php
/**
 * Main dashboard for Course Analytics.
 *
 * @package    local_courseanalytics
 * @copyright  2024 KKDES <https://kkdes.co.ke/>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/enrol/locallib.php');

$categoryid = optional_param('category', 0, PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('local/courseanalytics:view', $context);

$PAGE->set_url(new moodle_url('/local/courseanalytics/index.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('manageanalytics', 'local_courseanalytics'));
$PAGE->set_heading(get_string('manageanalytics', 'local_courseanalytics'));

$renderer = $PAGE->get_renderer('local_courseanalytics');

$categories = $DB->get_records('course_categories', null, 'name ASC', 'id, name');

$formatted_courses  = [];
$total_active       = 0;
$total_inactive     = 0;
$engagement_labels  = [];
$engagement_data    = [];

$require_category = ($categoryid == 0);

if (!$require_category) {
    // Fetch all courses for the selected category
    $courses = \local_courseanalytics\course_manager::get_courses($categoryid);

    foreach ($courses as $course) {
        $stats = \local_courseanalytics\course_manager::get_course_full_stats($course->id);

        $total_active   += $stats['active_students'];
        $total_inactive += $stats['inactive_students'];
        $engagement_labels[] = $course->shortname;
        $engagement_data[]   = $stats['completion_rate'];

        $formatted_courses[] = [
            'id'                     => $course->id,
            'fullname'               => $stats['fullname'],
            'shortname'              => $stats['shortname'],
            'categoryname'           => $course->categoryname,
            'url'                    => (new moodle_url('/local/courseanalytics/course.php', ['id' => $course->id]))->out(false),

            // Lecturer
            'lecturer_name'          => $stats['lecturer_name'],
            'lecturer_email'         => $stats['lecturer_email'],
            'lecturer_lastaccess'    => $stats['lecturer_lastaccess'],

            // Students
            'total_students'         => $stats['total_students'],
            'active_students'        => $stats['active_students'],
            'inactive_students'      => $stats['inactive_students'],
            'completion_rate'        => $stats['completion_rate'],

            // Modules
            'total_modules'          => $stats['total_modules'],
            'assignments'            => $stats['assignments'],
            'quizzes'                => $stats['quizzes'],
            'forums'                 => $stats['forums'],
            'files'                  => $stats['files'],
            'videos'                 => $stats['videos'],
            'urls'                   => $stats['urls'],
            'pages'                  => $stats['pages'],
        ];
    }
}

$data = [
    'urls' => [
        'index'  => (new moodle_url('/local/courseanalytics/index.php'))->out(false),
        'export' => (new moodle_url('/local/courseanalytics/export.php',
            $categoryid > 0 ? ['category' => $categoryid] : []))->out(false),
    ],
    'courses'        => $formatted_courses,
    'total_courses'  => count($formatted_courses),
    'has_courses'    => !empty($formatted_courses),
    'categories'     => array_values(array_map(function($cat) use ($categoryid) {
        return ['id' => $cat->id, 'name' => $cat->name, 'selected' => ($cat->id == $categoryid)];
    }, $categories)),
    'charts' => [
        'participation' => [
            'active'   => $total_active,
            'inactive' => $total_inactive,
        ],
        'engagement' => [
            'labels' => $engagement_labels,
            'data'   => $engagement_data,
        ],
    ],
    'require_category' => $require_category,
    'footer_text' => 'Developed by | <a href="https://kkdes.co.ke/" target="_blank">KKDES</a>',
];

echo $OUTPUT->header();
echo $renderer->render_dashboard($data);
echo $OUTPUT->footer();
