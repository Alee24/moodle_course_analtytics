<?php
namespace local_courseanalytics;

defined('MOODLE_INTERNAL') || die();

/**
 * Git Manager class to handles self-updates.
 */
class git_manager {

    /**
     * Check if there are updates available on the remote repository.
     * @return bool
     */
    public static function has_update() {
        global $CFG;
        $path = $CFG->dirroot . '/local/courseanalytics';
        
        try {
            // Fetch latest from remote (this needs the web server to have git access)
            @exec("cd " . escapeshellarg($path) . " && git fetch origin main 2>&1", $output, $return_var);
            
            if ($return_var !== 0) {
                return false;
            }

            // Check if local is behind remote
            @exec("cd " . escapeshellarg($path) . " && git status -uno 2>&1", $status_output, $status_return);
            
            foreach ($status_output as $line) {
                if (strpos($line, 'Your branch is behind') !== false) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            return false;
        }

        return false;
    }

    /**
     * Perform the git pull operation.
     * @return array [success => bool, message => string]
     */
    public static function pull_update() {
        global $CFG;
        $path = $CFG->dirroot . '/local/courseanalytics';

        @exec("cd " . escapeshellarg($path) . " && git pull origin main 2>&1", $output, $return_var);

        if ($return_var === 0) {
            return [
                'success' => true,
                'message' => 'Plugin updated successfully. Please purge Moodle caches to reflect changes.',
                'output' => implode("\n", $output)
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to pull updates. Check permissions on the VPS.',
                'output' => implode("\n", $output)
            ];
        }
    }
}
