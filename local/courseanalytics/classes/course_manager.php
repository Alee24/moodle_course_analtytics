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
        global $DB, $CFG;

        $params = [
            'ctxlevel' => CONTEXT_COURSE,
            'courseid' => $courseid,
        ];

        // Moodle native way of finding which roles are considered "teachers"
        if (!empty($CFG->coursecontact)) {
            $roleids = explode(',', $CFG->coursecontact);
            list($rolesql, $roleparams) = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'r');
            $rolecheck = "ra.roleid $rolesql";
            $params = array_merge($params, $roleparams);
        } else {
            // Fallback for custom university shortnames
            $rolecheck = "r.shortname IN ('editingteacher', 'teacher', 'coursecreator', 'lecturer', 'facilitator', 'tutor', 'instructor')";
        }

        $sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.lastaccess
                FROM {user} u
                JOIN {role_assignments} ra ON ra.userid = u.id
                JOIN {role} r ON r.id = ra.roleid
                JOIN {context} ctx ON ctx.id = ra.contextid
                WHERE (ctx.contextlevel = :ctxlevel AND ctx.instanceid = :courseid)
                  AND ($rolecheck)
                ORDER BY u.lastaccess DESC";

        $lecturers = $DB->get_records_sql($sql, $params);

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
            'h5p'      => 0, // h5pactivity or hvp plugin
            'other'    => 0,
            'total'    => 0,
        ];

        // Detect video files by checking file mime types
        $video_mimes = ['video/mp4','video/mpeg','video/ogg','video/webm','video/quicktime','video/x-flv','video/x-msvideo'];

        foreach ($modinfo->get_cms() as $cm) {
            $counts['total']++;
            $modname = $cm->modname;
            
            if ($modname === 'h5pactivity' || $modname === 'hvp') {
                $counts['h5p']++;
            } elseif (isset($counts[$modname])) {
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
     * Helper to get average time spent and total views securely.
     */
    public static function get_time_and_views($courseid, $total_students) {
        global $DB;
        $views = 0;
        $avg_time_str = "0m";
        
        try {
            if ($DB->get_manager()->table_exists('logstore_standard_log')) {
                $sql_views = "SELECT COUNT(id) FROM {logstore_standard_log} WHERE courseid = :courseid";
                $views = $DB->count_records_sql($sql_views, ['courseid' => $courseid]);
                
                $sql_logs = "SELECT userid, timecreated FROM {logstore_standard_log} WHERE courseid = :courseid ORDER BY userid, timecreated ASC";
                $rs = $DB->get_recordset_sql($sql_logs, ['courseid' => $courseid], 0, 250000);
                
                $total_time_seconds = 0;
                $last_user = null;
                $last_time = 0;
                $session_timeout = 1800;
                
                foreach ($rs as $log) {
                    if ($last_user === $log->userid) {
                        $diff = $log->timecreated - $last_time;
                        if ($diff < $session_timeout && $diff > 0) {
                            $total_time_seconds += $diff;
                        } else {
                            $total_time_seconds += 120;
                        }
                    } else {
                        $total_time_seconds += 120;
                    }
                    $last_user = $log->userid;
                    $last_time = $log->timecreated;
                }
                $rs->close();
                
                if ($total_students > 0) {
                    $avg_seconds = $total_time_seconds / $total_students;
                    if ($avg_seconds < 60) {
                        $avg_time_str = "< 1m";
                    } else {
                        $hours = floor($avg_seconds / 3600);
                        $mins = floor(($avg_seconds % 3600) / 60);
                        if ($hours > 0) {
                            $avg_time_str = "{$hours}h {$mins}m";
                        } else {
                            $avg_time_str = "{$mins}m";
                        }
                    }
                }
            }
        } catch (\Exception $e) {}
        
        return [
            'views' => $views,
            'avg_time' => $avg_time_str
        ];
    }

    /**
     * Get comprehensive stats for a course (used in dashboard table + export).
     */
    public static function get_course_full_stats($courseid, $skip_heavy = false) {
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
        $completed_count = 0; // Initialize completed_count

        $total_completion_percentage = 0;

        if (!$skip_heavy) {
            $completion = new \completion_info($course);
            $modinfo = \get_fast_modinfo($course);
            $tracked_activities = [];
            foreach ($modinfo->get_cms() as $cm) {
                if ($cm->completion != COMPLETION_TRACKING_NONE) {
                    $tracked_activities[] = $cm;
                }
            }

            foreach ($enrolled_users as $u) {
                if ($u->lastaccess > $active_cutoff) {
                    $active_count++;
                }
                if (count($tracked_activities) > 0) {
                    $comp = 0;
                    foreach ($tracked_activities as $cm) {
                        $c_data = $completion->get_data($cm, true, $u->id);
                        if ($c_data->completionstate == COMPLETION_COMPLETE || $c_data->completionstate == COMPLETION_COMPLETE_PASS) {
                            $comp++;
                        }
                    }
                    $total_completion_percentage += ($comp / count($tracked_activities)) * 100;
                }
            }

            $completion_rate = $total_students > 0
                ? round(($total_completion_percentage / $total_students), 1)
                : 0;

            $time_views = self::get_time_and_views($courseid, $total_students);
        } else {
            // Fast mode: just use basic course completion states and skip time/views logs
            $completion_rate = 0;
            $completion = new \completion_info($course);
            
            foreach ($enrolled_users as $u) {
                if ($u->lastaccess > $active_cutoff) {
                    $active_count++;
                }
                if ($completion->is_course_complete($u->id)) {
                    $completed_count++;
                }
            }
            if ($total_students > 0) {
                $completion_rate = round(($completed_count / $total_students) * 100, 1);
            }
            $time_views = ['views' => 'N/A', 'avg_time' => 'N/A'];
        }

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
            'total_views'       => $time_views['views'],
            'avg_time_spent'    => $time_views['avg_time'],
            'total_modules'     => $modcounts['total'],
            'assignments'       => $modcounts['assign'],
            'quizzes'           => $modcounts['quiz'],
            'forums'            => $modcounts['forum'],
            'files'             => $modcounts['resource'],
            'urls'              => $modcounts['url'],
            'pages'             => $modcounts['page'],
            'h5p'               => $modcounts['h5p'],
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
            'avg_time_spent'    => $s['avg_time_spent'],
            'total_views'       => $s['total_views'],
            'h5p'               => $s['h5p'],
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

        global $DB;
        $view_counts = [];
        try {
            if ($DB->get_manager()->table_exists('logstore_standard_log')) {
                $sql_views = "SELECT contextinstanceid AS cmid, COUNT(id) AS views 
                        FROM {logstore_standard_log} 
                        WHERE courseid = :courseid AND contextlevel = :ctxlevel 
                        GROUP BY contextinstanceid";
                $view_counts = $DB->get_records_sql($sql_views, [
                    'courseid' => $courseid,
                    'ctxlevel' => CONTEXT_MODULE
                ]);
            }
        } catch (\Exception $e) {}

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
                    elseif ($modname === 'h5pactivity' || $modname === 'hvp') $icon = 'fa-play-circle text-info font-weight-bold';

                    $cm_views = isset($view_counts[$cmid]) ? $view_counts[$cmid]->views : 0;
                    
                    $modules[] = [
                        'name'       => $cm->name,
                        'type'       => ucfirst($modname),
                        'icon'       => $icon,
                        'views'      => number_format($cm_views),
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
