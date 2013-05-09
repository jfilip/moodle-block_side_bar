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
 * Database upgrade code
 *
 * @package    block_side_bar
 * @author     Justin Filip <jfilip@remote-learner.ca>
 * @copyright  2013 onwards Justin Filip
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_block_side_bar_upgrade($oldversion = 0) {
    global $CFG, $DB;

    $result = true;

    if ($oldversion < 2012062500) {
        require_once($CFG->dirroot.'/blocks/side_bar/locallib.php');

        // Fetch all block instances which have saved configuration data
        $select = "blockname = 'side_bar' AND ".$DB->sql_isnotempty('block_instances', 'configdata', true, true);
        if ($bis = $DB->get_recordset_select('block_instances', $select, array(), 'id, configdata')) {
            // Perform a semi-cache of course records so we're not constantly fetching course records from the DB when multiple
            // block instances are found within a single course.
            $courses = array();

            foreach ($bis as $bi) {
                if (!$result) {
                    continue;
                }

                $blockcfg = unserialize(base64_decode($bi->configdata));

                if ((!is_object($blockcfg) && !isset($blockcfg->section) && !isset($blockcfg->section_id))) {
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

                // We've changed some of the values for text within a section and the migration code depends on this so we need to update now
                $reseturl = new moodle_url('/blocks/side_bar/reset.php?cid='.$course->id);

                $supdate = new stdClass();
                $supdate->id      = $blockcfg->section_id;
                $supdate->name    = get_string('sidebar', 'block_side_bar');
                $supdate->summary = get_string('sectionsummary', 'block_side_bar', (string)html_writer::link($reseturl, $reseturl));
                $DB->update_record('course_sections', $supdate);

                $sectioninfo = block_side_bar_migrate_old_section($course, (int)$section->section);

                if ($sectioninfo == null) {
                    $result = false;
                } else {
                    // Store the new section number and update the block configuration data
                    $blockcfg->section = $sectioninfo->section;
                    $DB->set_field('block_instances', 'configdata', base64_encode(serialize($blockcfg)), array('id' => $bi->id));
                }
            }
        }

        upgrade_plugin_savepoint($result, 2012062500, 'block', 'side_bar');
    }

    return $result;
}
