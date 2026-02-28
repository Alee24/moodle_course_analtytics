<?php
namespace local_courseanalytics\task;

defined('MOODLE_INTERNAL') || die();

use core\task\scheduled_task;
use local_courseanalytics\course_manager;

class aggregate_stats extends scheduled_task {

    /**
     * Get name.
     */
    public function get_name() {
        return get_string('manageanalytics', 'local_courseanalytics');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        $courses = $DB->get_records('course', ['id' => '> 1'], '', 'id');
        $stat_date = strtotime('today');

        foreach ($courses as $course) {
            $metrics = course_manager::get_course_metrics($course->id);
            
            $record = new \stdClass();
            $record->courseid = $course->id;
            $record->stat_date = $stat_date;
            $record->student_count = $metrics['total_students'];
            $record->active_count = $metrics['active_students'];
            $record->completion_rate = $metrics['completion_rate'];
            $record->module_count = $metrics['total_modules'];

            // Check if record exists for today
            $existing = $DB->get_record('local_courseanalytics_stats', [
                'courseid' => $course->id,
                'stat_date' => $stat_date
            ]);

            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record('local_courseanalytics_stats', $record);
            } else {
                $DB->insert_record('local_courseanalytics_stats', $record);
            }
        }
    }
}
