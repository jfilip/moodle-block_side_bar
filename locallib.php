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

/**
 * Setup the section that is meant to contain the activities added via a given sidebar instance.
 *
 * @param object $course The course DB record object
 * @return object|null An object representing the created section or null on error
 */
function block_side_bar_create_section($course) {
    global $CFG, $DB;

    if (!is_object($course)) {
        throw new coding_exception('$course must be an object');
    }

    // What is the configured number of sections in this course?
    $formatoptions = course_get_format($course)->get_format_options();

    // Make sure the value we need was actually returned
    if (!isset($formatoptions['numsections'])) {
        return null;
    }

    // This is what the maximum section number for this course should be.
    $sectioncount = $formatoptions['numsections'];

    // This is what the new section number should be if we are simply appending a new section to the course.
    $sectionnum = $sectioncount + 1;

    // Check if there are already any "orphaned" sections in this course
    $sql = "SELECT MAX(section)
              FROM {course_sections}
             WHERE course = :courseid";

    $maxsection = $DB->get_field_sql($sql, array('courseid' => $course->id));

    // We have orphaned sections in the course so let's just add our new section after the last one
    if ($maxsection >= $sectionnum) {
        $sectionnum = $maxsection + 1;
    }

    // Just make sure that our section actually exists
    course_create_sections_if_missing($course->id, $sectionnum);
    rebuild_course_cache($course->id);

    // Update the Side Bar section with the required values to make it work
    $reseturl = new moodle_url('/blocks/side_bar/reset.php?cid='.$course->id);
    $section = $DB->get_record('course_sections', array('course' => $course->id, 'section' => $sectionnum), 'id, section, name, visible');
    $section->name          = get_string('sidebar', 'block_side_bar');
    $section->summary       = get_string('sectionsummary', 'block_side_bar', (string)html_writer::link($reseturl, $reseturl));
    $section->summaryformat = FORMAT_HTML;
    $section->visible       = true;
    $DB->update_record('course_sections', $section);

    rebuild_course_cache($course->id, true);

    $sectioninfo = new stdClass();
    $sectioninfo->id      = $section->id;
    $sectioninfo->section = $section->section;

    return $sectioninfo;
}


/**
 * This function is used to move a legacy sidebar course section so that it is the last orphaned section within a course.
 * If there are any "filler" sections that have been created between the last real section in the course and this legacy
 * section they will be deleted unless they contain activities. If they contain activities they will be moved "down" so
 * that they fall just after the last visible section within the course, with the sidebar section appearing directly
 * after them.
 *
 * @param object $course The course DB record object
 * @param int $sectionum The section number for the sidebar course section we are migrating
 * @return object|null An object representing the created section or null on error
 */
function block_side_bar_migrate_old_section($course, $sectionnum) {
    global $CFG, $DB;

    if (!is_object($course)) {
        throw new coding_exception('$course must be an object');
    }

    if (!is_int($sectionnum) || 0 >= $sectionnum) {
        throw new coding_exception('$sectionnum must be a positive integer');
    }

    // What is the configured number of sections in this course?
    $formatoptions = course_get_format($course)->get_format_options();

    // Make sure the value we need was actually returned
    if (!isset($formatoptions['numsections'])) {
        debugging('course format is missing numsections property', DEBUG_DEVELOPER);
        return null;
    }

    // This is what the maximum section number for this course should be.
    $numsections = $formatoptions['numsections'];

    // Make sure that the legacy section we are supposed to migrate actually exists
    if (!$DB->record_exists('course_sections', array('course' => $course->id, 'section' => $sectionnum))) {
        debugging('course_section '.$sectionnum.' does not exist in course '.$course->id, DEBUG_DEVELOPER);
        return null;
    }

    // Which sections actually contain orphaned activities?
    $sql = "SELECT cs.section, cs.id, cs.name, cs.summary, cs.sequence
              FROM {course_sections} cs
             WHERE cs.course = :courseid
                   AND cs.section > :numsections
                   AND cs.section < :sectionnum
          ORDER BY cs.section ASC";

    $params = array('courseid' => $course->id, 'numsections' => $numsections, 'sectionnum' => $sectionnum);

    $orphanedsections = $DB->get_records_sql($sql, $params);

    $sectionend  = $numsections + 1;
    $idstodelete = array();

    // Work our way through each orphaned section
    foreach ($orphanedsections as $orphanedsection) {
        // If this section contains activites, we have to move it so that it's at the end
        if (!empty($orphanedsection->sequence)) {
            // If the section number we would want to "move" this one to is the same, don't do anything
            if ($orphanedsection->section == $sectionend) {
                $sectionend++;
                continue;
            }

            // If this is a sidebar section then we will skip over it
            $reseturl = new moodle_url('/blocks/side_bar/reset.php?cid='.$course->id);
            $namematch    = get_string('sidebar', 'block_side_bar') == $orphanedsection->name;
            $summarymatch = get_string('sectionsummary', 'block_side_bar', (string)html_writer::link($reseturl, $reseturl)) == $orphanedsection->summary;

            if ($namematch && $summarymatch) {
                continue;
            }

            // Store the old section number for use below
            $oldsectionnum = $orphanedsection->section;

            // Shift this section itself "down" a level
            $orphanedsection->section = $sectionend++;
            $DB->update_record('course_sections', $orphanedsection);

            // Shift this section's activities to the new section number
            $params = array('course' => $course->id, 'section' => $oldsectionnum);
            if ($DB->record_exists('course_modules', $params)) {
                $DB->set_field('course_modules', 'section', $orphanedsection->section, $params);
            }
        } else {
            $idstodelete[] = $orphanedsection->id;
        }

        // Delete any unnecessary sections we may have still lying around
        if (!empty($idstodelete)) {
            list($sql, $params) = $DB->get_in_or_equal($idstodelete);
            $DB->delete_records_select('course_sections', 'id '.$sql, $params);
        }
    }

    // Finally, we will move our section into the new "bottom" section position (only if it needs moving)
    $params = array('course' => $course->id, 'section' => $sectionnum);
    $DB->set_field('course_sections', 'section', $sectionend, $params);
    if ($DB->record_exists('course_modules', $params)) {
        $DB->set_field('course_modules', 'section', $sectionend, $params);
    }

    // Refresh the course modinfo cache (necessary for the next section of code)
    get_fast_modinfo($course->id, 0, true);

    $sectioninfo = $DB->get_record('course_sections', array('course' => $course->id, 'section' => $sectionend), 'id, section, name, visible');
    if (false === $sectioninfo) {
        debugging('could not find section '.$sectionend, DEBUG_DEVELOPER);
        return null;
    }

    return $sectioninfo;
}

/**
 * This function is used to move a visible sidebar course section so that it is an orphaned section within the course.
 * If there are any other orhpaned sections in the course, the sidebar section will be moved to  appear directly after
 * them.
 *
 * @param object $course The course DB record object
 * @param int $sectionum The section number for the sidebar course section we are migrating
 * @return object|null An object representing the created section or null on error
 */
function block_side_bar_move_section($course, $sectionnum) {
    global $CFG, $DB;

    if (!is_object($course)) {
        throw new coding_exception('$course must be an object');
    }

    if (!is_int($sectionnum) || $sectionnum <= 0) {
        throw new coding_exception('$sectionnum must be a positive integer');
    }

    // What is the configured number of sections in this course?
    $formatoptions = course_get_format($course)->get_format_options();

    // Make sure the value we need was actually returned
    if (!isset($formatoptions['numsections'])) {
        debugging('course format is missing numsections property', DEBUG_DEVELOPER);
        return null;
    }

    // This is what the maximum section number for this course should be.
    $numsections = $formatoptions['numsections'];

    // Make sure that the legacy section we are supposed to migrate actually exists
    if (!$sbsection = $DB->get_record('course_sections', array('course' => $course->id, 'section' => $sectionnum))) {
        debugging('course_section '.$sectionnum.' does not exist in course '.$course->id, DEBUG_DEVELOPER);
        return null;
    }

    // If this is not a sidebar section then we return false
    $reseturl = new moodle_url('/blocks/side_bar/reset.php?cid='.$course->id);
    $namematch    = get_string('sidebar', 'block_side_bar') == $sbsection->name;
    $summarymatch = get_string('sectionsummary', 'block_side_bar', (string)html_writer::link($reseturl, $reseturl)) == $sbsection->summary;

    if (!$namematch || !$summarymatch) {
        return null;
    }

    // If the section we requested to move even visible?
    if ($sbsection->section > $numsections) {
        return null;
    }

    // Copy the section we are moving for usage later
    $oldsection = clone($sbsection);

    $sql = "SELECT MAX(section)
              FROM {course_sections}
             WHERE course = :courseid";

    $newsection = $DB->count_records_sql($sql, array('courseid' => $course->id)) + 1;

    // Move this section to be first non-visible course section
    $sbsection->section = $newsection;
    $DB->update_record('course_sections', $sbsection);

    // Shift this section's activities to the new section number
    $params = array('course' => $course->id, 'section' => $oldsection->section);
    if ($DB->record_exists('course_modules', $params)) {
        $DB->set_field('course_modules', 'section', $oldsection->section, $params);
    }

    rebuild_course_cache($course->id);

    $sectioninfo = $DB->get_record('course_sections', array('course' => $course->id, 'section' => $newsection), 'id, section, name, visible');
    if (false === $sectioninfo) {
        debugging('could not find section '.$sectionend, DEBUG_DEVELOPER);
        return null;
    }

    // And now we have to create a new empty course section to fill in the gap we just created
    unset($oldsection->id);
    $oldsection->name = '';
    $oldsection->summary = '';
    $oldsection->sequence = '';

    if (false === $DB->insert_record('course_sections', $oldsection, false)) {
        return null;
    }

    rebuild_course_cache($course->id, true);

    return $sectioninfo;
}
