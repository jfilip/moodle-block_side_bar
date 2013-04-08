<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Allows for arbitrarily adding resources or activities to extra (non-standard) course sections with instance
 * configuration for the block title.
 *
 * @package    block_side_bar
 * @author     Justin Filip <jfilip@remote-learner.ca>
 * @copyright  2013 onwards Justin Filip
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function block_side_bar_upgrade($oldversion = 0) {
    global $CFG, $DB;

    $result = true;

    if ($oldersion < 2012062500) {
        // Fetch all block instances which have saved configuration data
        $select = "blockname = 'side_bar' AND ".sql_isnotempty('block_instances', 'configdata', true, true);
        if ($bis = $DB->$DB->get_recordset_select('block_instances', $select, '', 'id, configdata') {
            // Perform a semi-cache of course records so we're not constantly fetching course records from the DB when multiple
            // block instances are found within a single course.
            $courses = array();

            foreach ($bis as $bi) {
                $blockcfg = unserialize(base64_decode($bi->configdata));

                if (!is_object($blockcfg) && !isset($blockcfg->section) && !isset($blockcfg->section_id)) {
                    continue;
                }

                if (!$section = $DB->get_record('course_sections', array('id' => $blockcfg->section_id))) {
                    continue;
                }

                if (!isset($courses[$section->course])) {
                    if (!$course = $DB->get_record('course', array('id' => $section->course))) {
                        continue;
                    }

                    $courses[$course->id] = $course;
                } else {
                    $course = $courses[$section->course];
                }

                $result = $result && block_side_bar_migrate_old_section($course, $section->section);
            }
        }
    }

    return $result;
}
