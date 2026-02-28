<?php
/**
 * Scheduled tasks definitions.
 *
 * @package    local_courseanalytics
 * @copyright  2024 KKDES <https://kkdes.co.ke/>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_courseanalytics\task\aggregate_stats',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '2', // Run daily at 2 AM
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
];
