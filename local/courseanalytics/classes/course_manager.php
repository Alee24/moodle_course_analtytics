<?php
namespace local_courseanalytics;

defined('MOODLE_INTERNAL') || die();

/**
 * Course Manager class.
 *
 * @package    local_courseanalytics
 * @copyright  2024 KKDES <https://kkdes.co.ke/>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_manager {

    /**
     * Get list of courses based on user permissions and filters.
     */
    public static function get_courses($categoryid = 0, $teacherid = 0) {
        global $DB, $USER;

        $params = [];
        $sql = "SELECT c.id, c.fullname, c.shortname, c.category, cat.name as categoryname
                FROM {course} c
                JOIN {course_categories} cat ON c.category = cat.id
                WHERE c.id > 1";

        if ($categoryid > 0) {
            $sql .= " AND c.category = :categoryid";
            $params['categoryid'] = $categoryid;
        }

        if (!\is_siteadmin()) {
            $user_courses = \enrol_get_all_users_courses($USER->id);
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
     * Get the lecturers (strictly editing teacher) for a course.
     */
    public static function get_course_lecturers($courseid) {
        global $DB;

        $sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.lastaccess
                FROM {user} u
                JOIN {role_assignments} ra ON ra.userid = u.id
                JOIN {role} r ON r.id = ra.roleid
                JOIN {context} ctx ON ctx.id = ra.contextid
                WHERE ctx.contextlevel = :ctxlevel
                  AND ctx.instanceid = :courseid
                  AND r.shortname = 'editingteacher'
                ORDER BY u.lastaccess DESC";

        $lecturers = $DB->get_records_sql($sql, [
            'ctxlevel' => CONTEXT_COURSE,
            'courseid' => $courseid,
        ]);

        return empty($lecturers) ? [] : array_values($lecturers);
    }

    /**
     * Count module types in a course.
     * Returns associative array: modname => count.
     */
    public static function get_module_counts($courseid) {
        global $DB;

        $modinfo = \get_fast_modinfo(\get_course($courseid));
        $counts  = [
            'assign'   => 0,
            'quiz'     => 0,
            'forum'    => 0,
            'resource' => 0, // files
            'url'      => 0,
            'page'     => 0,
            'folder'   => 0,
            'video'    => 0, // label + resource with video mime
            'other'    => 0,
            'total'    => 0,
        ];

        // Detect video files by checking file mime types
        $video_mimes = ['video/mp4','video/mpeg','video/ogg','video/webm','video/quicktime','video/x-flv','video/x-msvideo'];

        foreach ($modinfo->get_cms() as $cm) {
            $counts['total']++;
            $modname = $cm->modname;
            if (isset($counts[$modname])) {
                $counts[$modname]++;
            } else {
                $counts['other']++;
            }
        }

        // Count actual video files stored in resource modules
        $sql = "SELECT COUNT(f.id)
                FROM {files} f
                JOIN {course_modules} cm ON cm.id = f.itemid
                JOIN {modules} m ON m.id = cm.module
                WHERE cm.course = :courseid
                  AND m.name = 'resource'
                  AND f.component = 'mod_resource'
                  AND f.filearea = 'content'
                  AND f.mimetype LIKE 'video/%'
                  AND f.filename != '.'";
        $counts['video'] = (int)$DB->count_records_sql($sql, ['courseid' => $courseid]);

        return $counts;
    }

    /**
     * Get comprehensive stats for a course (used in dashboard table + export).
     */
    public static function get_course_full_stats($courseid) {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        $course   = \get_course($courseid);
        $context  = \context_course::instance($courseid);
        $lecturers = self::get_course_lecturers($courseid);
        $modcounts = self::get_module_counts($courseid);

        // Format lecturers array
        $formatted_lecturers = [];
        foreach ($lecturers as $l) {
            $formatted_lecturers[] = [
                'name'       => \fullname($l),
                'email'      => $l->email,
                'lastaccess' => $l->lastaccess ? \userdate($l->lastaccess, '%d %b %Y %H:%M') : 'Never',
            ];
        }

        // Students only (not teachers)
        $enrolled_users = \get_enrolled_users($context, '', 0, 'u.*', null, 0, 0, true);
        $total_students = count($enrolled_users);

        $active_cutoff  = time() - (7 * 24 * 60 * 60);
        $active_count   = 0;
        foreach ($enrolled_users as $u) {
            if ($u->lastaccess > $active_cutoff) {
                $active_count++;
            }
        }

        // Completion
        $completion      = new \completion_info($course);
        $completed_count = 0;
        foreach ($enrolled_users as $u) {
            if ($completion->is_course_complete($u->id)) {
                $completed_count++;
            }
        }

        $completion_rate = $total_students > 0
            ? round(($completed_count / $total_students) * 100, 1)
            : 0;

        return [
            'courseid'          => $courseid,
            'fullname'          => $course->fullname,
            'shortname'         => $course->shortname,

            // Lecturers array
            'lecturers'         => $formatted_lecturers,

            // Students
            'total_students'    => $total_students,
            'active_students'   => $active_count,
            'inactive_students' => $total_students - $active_count,
            'completed_students'=> $completed_count,
            'completion_rate'   => $completion_rate,

            // Modules
            'total_modules'     => $modcounts['total'],
            'assignments'       => $modcounts['assign'],
            'quizzes'           => $modcounts['quiz'],
            'forums'            => $modcounts['forum'],
            'files'             => $modcounts['resource'],
            'urls'              => $modcounts['url'],
            'pages'             => $modcounts['page'],
            'videos'            => $modcounts['video'],
            'other_modules'     => $modcounts['other'],
        ];
    }

    /**
     * Get analytics metrics for a specific course (used in detail page).
     */
    public static function get_course_metrics($courseid) {
        $s = self::get_course_full_stats($courseid);
        return [
            'total_students'    => $s['total_students'],
            'active_students'   => $s['active_students'],
            'inactive_students' => $s['inactive_students'],
            'completion_rate'   => $s['completion_rate'],
            'total_modules'     => $s['total_modules'],
            'hidden_modules'    => 0,
        ];
    }

    /**
     * Get detailed section and activity data for a course.
     */
    public static function get_section_details($courseid) {
        $course  = \get_course($courseid);
        $modinfo = \get_fast_modinfo($course);
        $sections = $modinfo->get_section_info_all();
        $data = [];

        foreach ($sections as $section) {
            if ($section->section == 0 && empty($modinfo->sections[0])) {
                continue;
            }

            $modules = [];
            if (isset($modinfo->sections[$section->section])) {
                foreach ($modinfo->sections[$section->section] as $cmid) {
                    $cm = $modinfo->get_cm($cmid);
                    $modname = $cm->modname;

                    // Map module type to a FontAwesome icon
                    $icon = 'fa-file-o';
                    if ($modname === 'assign') $icon = 'fa-tasks text-primary';
                    elseif ($modname === 'quiz') $icon = 'fa-question-circle text-warning';
                    elseif ($modname === 'forum') $icon = 'fa-comments-o text-success';
                    elseif ($modname === 'folder') $icon = 'fa-folder-open-o text-warning';
                    elseif ($modname === 'url') $icon = 'fa-link text-info';
                    elseif ($modname === 'page') $icon = 'fa-file-text-o text-secondary';
                    elseif ($modname === 'resource') $icon = 'fa-file-pdf-o text-danger';
                    elseif ($modname === 'book') $icon = 'fa-book text-success';
                    elseif ($modname === 'chat') $icon = 'fa-comments text-primary';
                    elseif ($modname === 'feedback' || $modname === 'choice') $icon = 'fa-check-square-o text-success';

                    $modules[] = [
                        'name'       => $cm->name,
                        'type'       => ucfirst($modname),
                        'icon'       => $icon,
                        'visible'    => (bool) $cm->visible,
                        'completion' => $cm->completion != COMPLETION_TRACKING_NONE,
                        'url'        => $cm->url ? $cm->url->out() : '',
                    ];
                }
            }

            $data[] = [
                'section'  => $section->section,
                'name'     => $section->name
                    ?: \get_string('sectionname', 'format_' . $course->format) . ' ' . $section->section,
                'modules'  => $modules,
                'is_empty' => empty($modules),
            ];
        }

        return $data;
    }

    /**
     * Get list of students with participation data.
     */
    public static function get_student_list($courseid) {
        $context = \context_course::instance($courseid);
        $users   = \get_enrolled_users($context, '', 0, 'u.*', 'u.lastname, u.firstname');
        $data    = [];

        foreach ($users as $user) {
            $data[] = [
                'fullname'   => \fullname($user),
                'lastaccess' => $user->lastaccess
                    ? \userdate($user->lastaccess)
                    : \get_string('never'),
                'email'      => $user->email,
            ];
        }

        return $data;
    }
}
