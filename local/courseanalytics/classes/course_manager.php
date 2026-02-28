<?php
namespace local_courseanalytics;

defined('MOODLE_INTERNAL') || die();

/**
 * Course Manager class for fetching and filtering courses.
 */
class course_manager {

    /**
     * Get list of courses based on user permissions and filters.
     *
     * @param int $categoryid
     * @param int $teacherid
     * @return array
     */
    public static function get_courses($categoryid = 0, $teacherid = 0) {
        global $DB, $USER;

        $params = [];
        $sql = "SELECT c.id, c.fullname, c.shortname, c.category, cat.name as categoryname
                FROM {course} c
                JOIN {course_categories} cat ON c.category = cat.id
                WHERE c.id > 1"; // Exclude site course

        if ($categoryid > 0) {
            $sql .= " AND c.category = :categoryid";
            $params['categoryid'] = $categoryid;
        }

        if (!is_siteadmin()) {
            // If not admin, only show courses where user has teacher role
            $user_courses = enrol_get_all_users_courses($USER->id);
            if (empty($user_courses)) {
                return [];
            }
            $courseids = array_keys($user_courses);
            list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'courseid');
            $sql .= " AND c.id $insql";
            $params = array_merge($params, $inparams);
        }

        if ($teacherid > 0) {
            $sql .= " AND EXISTS (
                SELECT 1 FROM {enrol} e
                JOIN {user_enrolments} ue ON ue.enrolid = e.id
                WHERE e.courseid = c.id AND ue.userid = :teacherid
            )";
            $params['teacherid'] = $teacherid;
        }

        $sql .= " ORDER BY c.fullname ASC";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get analytics metrics for a specific course.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_course_metrics($courseid) {
        global $DB;

        $course = get_course($courseid);
        $context = \context_course::instance($courseid);

        // Participation
        $enrolled_users = enrol_get_enrolled_users($context);
        $total_students = count($enrolled_users);
        $active_days_limit = time() - (7 * 24 * 60 * 60); // 7 days active limit
        $active_students = 0;

        foreach ($enrolled_users as $user) {
            if ($user->lastaccess > $active_days_limit) {
                $active_students++;
            }
        }

        // Completion
        $completion = new \completion_info($course);
        $completed_count = 0;
        foreach ($enrolled_users as $user) {
            if ($completion->is_course_complete($user->id)) {
                $completed_count++;
            }
        }

        // Module summary
        $modinfo = get_fast_modinfo($course);
        $total_modules = 0;
        $hidden_modules = 0;
        foreach ($modinfo->get_cms() as $cm) {
            $total_modules++;
            if (!$cm->visible) {
                $hidden_modules++;
            }
        }

        return [
            'total_students' => $total_students,
            'active_students' => $active_students,
            'inactive_students' => $total_students - $active_students,
            'completion_rate' => $total_students > 0 ? round(($completed_count / $total_students) * 100, 2) : 0,
            'total_modules' => $total_modules,
            'hidden_modules' => $hidden_modules,
        ];
    }

    /**
     * Get detailed section and activity data for a course.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_section_details($courseid) {
        $course = get_course($courseid);
        $modinfo = get_fast_modinfo($course);
        $sections = $modinfo->get_section_info_all();
        $data = [];

        foreach ($sections as $section) {
            if ($section->section == 0 && empty($modinfo->sections[0])) continue; // Skip empty section 0

            $modules = [];
            if (isset($modinfo->sections[$section->section])) {
                foreach ($modinfo->sections[$section->section] as $cmid) {
                    $cm = $modinfo->get_cm($cmid);
                    $modules[] = [
                        'name' => $cm->name,
                        'type' => $cm->modname,
                        'visible' => $cm->visible,
                        'completion' => $cm->completion != COMPLETION_TRACKING_NONE,
                        'url' => $cm->url ? $cm->url->out() : '',
                    ];
                }
            }

            $data[] = [
                'section' => $section->section,
                'name' => $section->name ?: get_string('sectionname', 'format_'.$course->format) . ' ' . $section->section,
                'modules' => $modules,
                'is_empty' => empty($modules),
            ];
        }

        return $data;
    }

    /**
     * Get list of students with their participation metrics.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_student_list($courseid) {
        global $DB;
        $context = \context_course::instance($courseid);
        $users = enrol_get_enrolled_users($context, 'mod/course:view', 0, 'u.*', 'u.lastname, u.firstname');
        $data = [];

        foreach ($users as $user) {
            $data[] = [
                'fullname' => fullname($user),
                'lastaccess' => $user->lastaccess ? userdate($user->lastaccess) : get_string('never'),
                'email' => $user->email,
            ];
        }

        return $data;
    }
}
