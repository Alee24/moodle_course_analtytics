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

// Security check
require_login();
$context = context_system::instance();
require_capability('local/courseanalytics:view', $context);

$PAGE->set_url(new moodle_url('/local/courseanalytics/index.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('manageanalytics', 'local_courseanalytics'));
$PAGE->set_heading(get_string('manageanalytics', 'local_courseanalytics'));

$renderer = $PAGE->get_renderer('local_courseanalytics');

// Data fetching
$courses = \local_courseanalytics\course_manager::get_courses($categoryid);
$formatted_courses = [];
$total_active = 0;
$total_inactive = 0;
$engagement_labels = [];
$engagement_data = [];

foreach ($courses as $course) {
    $metrics = \local_courseanalytics\course_manager::get_course_metrics($course->id);
    $course->metrics = $metrics;
    $course->last_access_formatted = '-'; // Placeholder for aggregate last access if needed
    $formatted_courses[] = $course;

    $total_active += $metrics['active_students'];
    $total_inactive += $metrics['inactive_students'];
    $engagement_labels[] = $course->shortname;
    $engagement_data[] = $metrics['completion_rate'];
}

$categories = $DB->get_records('course_categories', null, 'name ASC', 'id, name');

// Convert courses to plain arrays for Mustache
$formatted_courses_out = [];
foreach ($formatted_courses as $c) {
    $formatted_courses_out[] = [
        'id'           => $c->id,
        'fullname'     => $c->fullname,
        'shortname'    => $c->shortname,
        'categoryname' => $c->categoryname,
        'metrics'      => (array) $c->metrics,
        'last_access_formatted' => $c->last_access_formatted,
        'url'          => (new moodle_url('/local/courseanalytics/course.php', ['id' => $c->id]))->out(false),
    ];
}

$data = [
    'urls' => [
        'index'  => (new moodle_url('/local/courseanalytics/index.php'))->out(false),
        'export' => (new moodle_url('/local/courseanalytics/export.php'))->out(false),
    ],
    'courses'    => $formatted_courses_out,
    'categories' => array_values(array_map(function($cat) use ($categoryid) {
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
        ]
    ],
    'footer_text' => get_string('developedby', 'local_courseanalytics')
        . ' <a href="' . get_string('kkdes_url', 'local_courseanalytics') . '" target="_blank">KKDES</a>',
];

echo $OUTPUT->header();
echo $renderer->render_dashboard($data);
echo $OUTPUT->footer();
