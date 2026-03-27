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

        // Ensure we are in a git repository
        if (!is_dir($path . '/.git')) {
            return false;
        }
        
        try {
            // We use --git-dir and --work-tree to be absolutely sure we stay within this folder
            $git_cmd = "git --git-dir=" . escapeshellarg($path . '/.git') . " --work-tree=" . escapeshellarg($path);
            
            @exec($git_cmd . " fetch origin main 2>&1", $output, $return_var);
            
            if ($return_var !== 0) {
                return false;
            }

            @exec($git_cmd . " status -uno 2>&1", $status_output, $status_return);
            
            foreach ($status_output as $line) {
                if (strpos($line, 'branch is behind') !== false) {
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

        if (!is_dir($path . '/.git')) {
            return [
                'success' => false, 
                'message' => 'The plugin directory is not a standalone Git repository. Please clone the plugin into local/courseanalytics.',
                'output' => ''
            ];
        }

        $git_cmd = "git --git-dir=" . escapeshellarg($path . '/.git') . " --work-tree=" . escapeshellarg($path);
        
        // Use a reset/pull strategy that only affects this directory
        @exec($git_cmd . " pull origin main 2>&1", $output, $return_var);

        if ($return_var === 0) {
            return [
                'success' => true,
                'message' => 'Plugin updated successfully. Please purge Moodle caches to reflect changes.',
                'output' => implode("\n", $output)
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to pull updates. There might be local conflicts or permission issues.',
                'output' => implode("\n", $output)
            ];
        }
    }
}
