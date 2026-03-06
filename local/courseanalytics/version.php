<?php
/**
 * Plugin version and metadata.
 *
 * @package    local_courseanalytics
 * @copyright  2024 KKDES <https://kkdes.co.ke/>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_courseanalytics';
$plugin->version   = 2024052003; // Bumped version to force Moodle update & cache purge
$plugin->requires  = 2022111800; // Moodle 4.1 or later
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = 'v1.1.1';
