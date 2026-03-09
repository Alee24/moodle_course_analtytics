<?php
/**
 * Export analytics data to Excel.
 *
 * @package    local_courseanalytics
 * @copyright  2024 KKDES <https://kkdes.co.ke/>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/excellib.class.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/enrol/locallib.php');

require_login();
$context = context_system::instance();
require_capability('local/courseanalytics:view', $context);

$categoryid    = optional_param('category', 0, PARAM_INT);
$courseid      = optional_param('id', 0, PARAM_INT);
$coursecode    = optional_param('coursecode', '', PARAM_TEXT);
$lectureremail = optional_param('lectureremail', '', PARAM_TEXT);

// ---- Fetch all courses + stats ----
if ($courseid > 0) {
    if ($DB->record_exists('course', ['id' => $courseid])) {
        $courses = [$DB->get_record('course', ['id' => $courseid])];
    } else {
        $courses = [];
    }
} else {
    $courses = \local_courseanalytics\course_manager::get_courses($categoryid, 0, $coursecode, $lectureremail);
}

// ---- Create Excel Workbook ----
$filename = 'course_analytics_' . date('Ymd_His');
$workbook = new MoodleExcelWorkbook($filename);
$sheet    = $workbook->add_worksheet('Course Analytics');

// ---- Define styles ----
$format_header  = $workbook->add_format(['bold' => 1, 'bg_color' => '#1e3a5f', 'color' => '#ffffff', 'border' => 1, 'size' => 11, 'align' => 'centre', 'v_align' => 'vcenter', 'text_wrap' => 1]);
$format_title   = $workbook->add_format(['bold' => 1, 'size' => 14, 'color' => '#1e3a5f']);
$format_even    = $workbook->add_format(['bg_color' => '#f0f4f8', 'border' => 1, 'size' => 10, 'text_wrap' => 1]);
$format_odd     = $workbook->add_format(['bg_color' => '#ffffff', 'border' => 1, 'size' => 10, 'text_wrap' => 1]);
$format_number  = $workbook->add_format(['bg_color' => '#ffffff', 'border' => 1, 'size' => 10, 'align' => 'centre']);
$format_numbere = $workbook->add_format(['bg_color' => '#f0f4f8', 'border' => 1, 'size' => 10, 'align' => 'centre']);
$format_pct     = $workbook->add_format(['bg_color' => '#e8f5e9', 'border' => 1, 'size' => 10, 'align' => 'centre', 'bold' => 1]);
$format_pcte    = $workbook->add_format(['bg_color' => '#c8e6c9', 'border' => 1, 'size' => 10, 'align' => 'centre', 'bold' => 1]);
$format_warn    = $workbook->add_format(['bg_color' => '#fff3e0', 'border' => 1, 'size' => 10, 'align' => 'centre']);

// ---- Title Row ----
$sheet->write_string(0, 0, 'Course Monitoring & Analytics Report — Riara University Virtual Campus', $format_title);
$sheet->write_string(1, 0, 'Generated: ' . userdate(time()), null);

if (count($courses) == 1 && $courseid > 0) {
    // ---- Single Course Column Format ----
    $sheet->set_column(0, 0, 35);
    $sheet->set_column(1, 1, 50);

    $course = $courses[0];
    $stats = \local_courseanalytics\course_manager::get_course_full_stats($course->id, false);

    $lecturer_names = [];
    $lecturer_emails = [];
    $lecturer_lastaccess = [];
    foreach ($stats['lecturers'] as $l) {
        $lecturer_names[] = $l['name'];
        $lecturer_emails[] = $l['email'];
        $lecturer_lastaccess[] = $l['lastaccess'];
    }

    $data_pairs = [
        'Course Name' => $stats['fullname'],
        'Category' => $course->categoryname,
        'Lecturer Name' => implode("\n", $lecturer_names),
        'Lecturer Email' => implode("\n", $lecturer_emails),
        'Lecturer Last Access' => implode("\n", $lecturer_lastaccess),
        'Total Students' => $stats['total_students'],
        'Active Students (7d)' => $stats['active_students'],
        'Inactive Students' => $stats['inactive_students'],
        'Completed Students' => $stats['completed_students'],
        'Completion Rate %' => $stats['completion_rate'],
        'Average Time Spent' => $stats['avg_time_spent'],
        'Total Views' => $stats['total_views'],
        'Total Resources / Activities' => $stats['total_modules'],
        'H5P Interactions' => $stats['h5p'],
        'Assignments' => $stats['assignments'],
        'Quizzes' => $stats['quizzes'],
        'Forums' => $stats['forums'],
        'Files' => $stats['files'],
        'Video Files' => $stats['videos'],
        'URLs' => $stats['urls'],
        'Pages' => $stats['pages'],
        'Other' => $stats['other_modules'],
    ];

    $row = 3;
    foreach ($data_pairs as $label => $val) {
        $sheet->write_string($row, 0, $label, $format_header);
        // Ensure we only use write_number for actual numeric values.
        if (is_numeric($val) && !preg_match('/%|h|m/', (string)$val) && !in_array($label, ['Average Time Spent', 'Total Views'])) {
            $sheet->write_number($row, 1, $val, $format_odd);
        } else {
            $sheet->write_string($row, 1, (string)$val, $format_odd);
        }
        $row++;
    }

} else {
    // ---- Multi-Course Row Format ----
    // ---- Header Row ----
    $headers = [
        '#',
        'Course Name',
        'Category',
        'Lecturer Name',
        'Lecturer Email',
        'Lecturer Last Access',
        'Total Students',
        'Active Students (7d)',
        'Inactive Students',
        'Completed Students',
        'Completion Rate %',
        'Average Time Spent',
        'Total Views',
        'Total Resources / Activities',
        'H5P Interactions',
        'Assignments',
        'Quizzes',
        'Forums',
        'Files',
        'Video Files',
        'URLs',
        'Pages',
        'Other',
    ];

    $header_row = 3;
    foreach ($headers as $col => $header) {
        $sheet->write_string($header_row, $col, $header, $format_header);
    }

    // ---- Column widths ----
    $widths = [5, 40, 20, 25, 30, 22, 12, 18, 15, 18, 15, 12, 15, 12, 10, 10, 10, 10, 10, 10, 10, 10, 10];
    foreach ($widths as $col => $width) {
        $sheet->set_column($col, $col, $width);
    }

    // ---- Data Rows ----
    $row = $header_row + 1;
    $num = 1;
    foreach ($courses as $course) {
        $skip_heavy = count($courses) > 1; // Only load heavy time/views data for single course exports
        $stats = \local_courseanalytics\course_manager::get_course_full_stats($course->id, $skip_heavy);
        $is_even = ($num % 2 === 0);
        $fmt  = $is_even ? $format_even     : $format_odd;
        $fmtn = $is_even ? $format_numbere  : $format_number;
        $fmtc = $is_even ? $format_pcte     : $format_pct;

        $lecturer_names = [];
        $lecturer_emails = [];
        $lecturer_lastaccess = [];
        foreach ($stats['lecturers'] as $l) {
            $lecturer_names[] = $l['name'];
            $lecturer_emails[] = $l['email'];
            $lecturer_lastaccess[] = $l['lastaccess'];
        }

        $sheet->write_number($row, 0,  $num,                          $fmtn);
        $sheet->write_string($row, 1,  $stats['fullname'],            $fmt);
        $sheet->write_string($row, 2,  $course->categoryname,         $fmt);
        $sheet->write_string($row, 3,  implode("\n", $lecturer_names),       $fmt);
        $sheet->write_string($row, 4,  implode("\n", $lecturer_emails),      $fmt);
        $sheet->write_string($row, 5,  implode("\n", $lecturer_lastaccess),  $fmt);
        $sheet->write_number($row, 6,  $stats['total_students'],      $fmtn);
        $sheet->write_number($row, 7,  $stats['active_students'],     $fmtn);
        $sheet->write_number($row, 8,  $stats['inactive_students'],   $fmtn);
        $sheet->write_number($row, 9,  $stats['completed_students'],  $fmtn);
        $sheet->write_number($row, 10, $stats['completion_rate'],     $fmtc);
        $sheet->write_string($row, 11, $stats['avg_time_spent'],      $fmtn);
        
        if (is_numeric($stats['total_views'])) {
            $sheet->write_number($row, 12, $stats['total_views'],         $fmtn);
        } else {
            $sheet->write_string($row, 12, (string)$stats['total_views'],  $fmtn);
        }
        
        $sheet->write_number($row, 13, $stats['total_modules'],       $fmtn);
        $sheet->write_number($row, 14, $stats['h5p'],                 $fmtn);
        $sheet->write_number($row, 15, $stats['assignments'],         $fmtn);
        $sheet->write_number($row, 16, $stats['quizzes'],             $fmtn);
        $sheet->write_number($row, 17, $stats['forums'],              $fmtn);
        $sheet->write_number($row, 18, $stats['files'],               $fmtn);
        $sheet->write_number($row, 19, $stats['videos'],              $fmtn);
        $sheet->write_number($row, 20, $stats['urls'],                $fmtn);
        $sheet->write_number($row, 21, $stats['pages'],               $fmtn);
        $sheet->write_number($row, 22, $stats['other_modules'],       $fmtn);

        $row++;
        $num++;
    }

    // ---- Summary row ----
    $sheet->write_string($row, 0,  'TOTAL', $format_header);
    $sheet->write_string($row, 1,  count($courses) . ' courses', $format_header);
}

// ---- Send to browser ----
$workbook->close();
