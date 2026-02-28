<?php
namespace local_courseanalytics\output;

defined('MOODLE_INTERNAL') || die();

use plugin_renderer_base;

class renderer extends plugin_renderer_base {

    /**
     * Render the dashboard.
     *
     * @param array $data
     * @return string
     */
    public function render_dashboard($data) {
        return $this->render_from_template('local_courseanalytics/dashboard', $data);
    }

    /**
     * Render course detail page.
     *
     * @param array $data
     * @return string
     */
    public function render_course_details($data) {
        return $this->render_from_template('local_courseanalytics/course_details', $data);
    }
}
