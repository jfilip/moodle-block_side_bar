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
 * Will reset the sidebar block course sections within the given course.
 *
 * @package    block_side_bar
 * @author     Justin Filip <jfilip@remote-learner.ca>
 * @copyright  2013 onwards Justin Filip
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');

$cid = required_param('cid', PARAM_INT);

require_login($cid, false);

$course = $DB->get_record('course', array('id' => $cid), '*', MUST_EXIST);
$context = context_course::instance($course->id);

require_capability('moodle/course:manageactivities', $context);

// Fetch all block instances which have saved configuration data
$select = "parentcontextid = :ctxid AND blockname = 'side_bar' AND ".$DB->sql_isnotempty('block_instances', 'configdata', true, true);
if ($bis = $DB->get_recordset_select('block_instances', $select, array('ctxid' => $context->id), 'id, configdata')) {
    foreach ($bis as $bi) {
        $blockcfg = unserialize(base64_decode($bi->configdata));

        if (!is_object($blockcfg) && !isset($blockcfg->section) && !isset($blockcfg->section_id)) {
            continue;
        }

        if (!$section = $DB->get_record('course_sections', array('id' => $blockcfg->section_id))) {
            continue;
        }

        $sectioninfo = block_side_bar_move_section($course, (int)$section->section);

        if ($sectioninfo != null) {
            // Store the new section number and update the block configuration data
            $blockcfg->section = $sectioninfo->section;
            $DB->set_field('block_instances', 'configdata', base64_encode(serialize($blockcfg)), array('id' => $bi->id));
        }
    }
}

// We're done, so head back to the course
redirect(new moodle_url('/course/view.php?id='.$course->id));
