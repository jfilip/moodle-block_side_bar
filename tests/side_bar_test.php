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

global $CFG;
require_once($CFG->dirroot.'/blocks/side_bar/locallib.php');

/**
 * @group block_side_bar
 */
class blockSideBarTestcase extends advanced_testcase {
    /** @var phpunit_data_generator A reference to the data generator object for creating test data */
    private $dg;

    /**
     * @see PHPUnit_Framework_TestCase::setUp()
     */
    protected function setUp() {
        parent::setUp();

        // $this->resetAfterTest();

        $dg = $this->getDataGenerator();
    }

    /**
     * Validate that the course Side Bar block section was created with the cortect settings.
     *
     * @param object $section A course_sections record with, at minimum the name, summary, section, and visible properties.
     * @param int $sectionnum The section number that should be present.
     * @param int $courseid The course record ID that this block belongs to.
     */
    private function validate_sidebar_course_section($section, $sectionnum, $courseid) {
        global $CFG;

        $reseturl = new moodle_url('/blocks/side_bar/reset.php?cid='.$courseid);
        $this->assertEquals(get_string('sidebar', 'block_side_bar'), $section->name);
        $this->assertEquals(get_string('sectionsummary', 'block_side_bar', (string)html_writer::link($reseturl, $reseturl)), $section->summary);
        $this->assertEquals($sectionnum, $section->section);
        $this->assertEquals(1, $section->visible);
    }

    /**
     * Create a new sidebar course section and set it up with the required values
     *
     * @param int $courseid The course record ID for the section to belong to.
     * @param int $sectionnum The section number that should be present.
     */
    private function create_sidebar_course_section($courseid, $sectionnum) {
        global $CFG, $DB;

        $dg = $this->getDataGenerator();
        $dg->create_course_section(array('course' => $courseid, 'section' => $sectionnum));

        $reseturl = new moodle_url('/blocks/side_bar/reset.php?cid='.$courseid);

        $section = $DB->get_record('course_sections', array('course' => $courseid, 'section' => $sectionnum), 'id, section, name, visible');
        $section->name          = get_string('sidebar', 'block_side_bar');
        $section->summary       = get_string('sectionsummary', 'block_side_bar', (string)html_writer::link($reseturl, $reseturl));
        $section->summaryformat = FORMAT_HTML;
        $section->visible       = true;
        $DB->update_record('course_sections', $section);
    }

    /**
     * Validate that passing an invalid $course parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $course must be an object
     */
    public function test_create_section_invalid_course_parameter_throws_exception_null() {
        $this->resetAfterTest();
        block_side_bar_create_section(null);
    }

    /**
     * Validate that passing an invalid $course parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $course must be an object
     */
    public function test_create_section_invalid_course_parameter_throws_exception_int() {
        block_side_bar_create_section(1);
    }

    /**
     * Validate that passing an invalid $course parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $course must be an object
     */
    public function test_create_section_invalid_course_parameter_throws_exception_string() {
        block_side_bar_create_section('string');
    }

    /**
     * Validate that passing an invalid $course parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $course must be an object
     */
    public function test_create_section_invalid_course_parameter_throws_exception_array() {
        block_side_bar_create_section(array(1, 2, 3));
    }

    /**
     * Test that the Side Bar block activity section is appropriately added to a course when that course contains no orphaned
     * sections (orphaned being sections that exist beyond the number of sections configured for the course).
     */
    public function test_create_section_works_with_no_orphaned_sections() {
        global $DB;

        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        // Create test course data
        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 1));
        $section = $dg->create_course_section(array('course' => $course->id, 'section' => 1));
        $page = $dg->create_module('page', array('course' => $course->id, 'section' => 1));

        // Setup the containing course section
        $sectioninfo = block_side_bar_create_section($course);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);
        $this->assertEquals(2, $sectioninfo->section);
        $this->assertEquals(3, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 2, $course->id);

        // Validate that the activity module was moved as well
        $this->assertNotEquals(false, get_coursemodule_from_instance('page', $page->id, $course->id, 2));
    }

    /**
     * Test that the Side Bar block activity section is appropriately added to a course when that course contains orphaned sections but
     * no activity modules within an orphaned section (orphaned being sections that exist beyond the number of sections configured for
     * the course).
     */
    public function test_create_section_works_with_empty_orphaned_sections() {
        global $DB;

        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        // Create test course data
        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 1));
        $section = $dg->create_course_section(array('course' => $course->id, 'section' => 1));
        $page = $dg->create_module('page', array('course' => $course->id), array('section' => 1));
        $section = $dg->create_course_section(array('course' => $course->id, 'section' => 2));
        $section = $dg->create_course_section(array('course' => $course->id, 'section' => 3));

        // Setup the course section for the Side Bar block-managed activities
        $sectioninfo = block_side_bar_create_section($course);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);
        $this->assertEquals(4, $sectioninfo->section);
        $this->assertEquals(5, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 4, $course->id);

        // Validate that the activity module was moved as well
        $this->assertNotEquals(false, get_coursemodule_from_instance('page', $page->id, $course->id, 4));
    }

    /**
     * Test that the Side Bar block activity section is appropriately added to a course when that course contains one orphaned section
     * and that section contains an activity module (orphaned being sections that exist beyond the number of sections configured for
     * the course).
     */
    public function test_create_section_works_with_one_non_empty_orphaned_section() {
        global $DB;

        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        // Create test course data
        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 1));
        $section = $dg->create_course_section(array('course' => $course->id, 'section' => 1));
        $section = $dg->create_course_section(array('course' => $course->id, 'section' => 2));
        $page = $dg->create_module('page', array('course' => $course->id), array('section' => 2));

        // Setup the course section for the Side Bar block-managed activities
        $sectioninfo = block_side_bar_create_section($course);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);
        $this->assertEquals(3, $sectioninfo->section);
        $this->assertEquals(4, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 3, $course->id);

        // Validate that the activity module was moved as well
        $this->assertNotEquals(false, get_coursemodule_from_instance('page', $page->id, $course->id, 3));
    }

    /**
     * Test that the Side Bar block activity section is appropriately added to a course when that course contains multiple orphaned
     * sections but only the "highest" orphaned section contains an activity module (orphaned being sections that exist beyond the
     * number of sections configured for the course).
     */
    public function test_create_section_works_with_multiple_orphaned_sections_high_not_empty() {
        global $DB;

        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        // Create test course data
        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 1));
        $section = $dg->create_course_section(array('course' => $course->id, 'section' => 1));
        $section = $dg->create_course_section(array('course' => $course->id, 'section' => 2));
        $page = $dg->create_module('page', array('course' => $course->id), array('section' => 2));
        $section = $dg->create_course_section(array('course' => $course->id, 'section' => 3));
        $section = $dg->create_course_section(array('course' => $course->id, 'section' => 4));

        // Setup the course section for the Side Bar block-managed activities
        $sectioninfo = block_side_bar_create_section($course);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);
        $this->assertEquals(5, $sectioninfo->section);
        $this->assertEquals(6, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 5, $course->id);

        // Validate that the activity module was moved as well
        $this->assertNotEquals(false, get_coursemodule_from_instance('page', $page->id, $course->id, 5));
    }

    /**
     * Test that the Side Bar block activity section is appropriately added to a course when that course contains multiple orphaned
     * sections but only the "lowest" orphaned section contains an activity module (orphaned being sections that exist beyond the
     * number of sections configured for the course).
     */
    public function test_create_section_works_with_multiple_orphaned_sections_low_not_empty() {
        global $DB;

        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        // Create test course data
        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 1));
        $section = $dg->create_course_section(array('course' => $course->id, 'section' => 1));
        $section = $dg->create_course_section(array('course' => $course->id, 'section' => 2));
        $section = $dg->create_course_section(array('course' => $course->id, 'section' => 3));
        $section = $dg->create_course_section(array('course' => $course->id, 'section' => 4));
        $page = $dg->create_module('page', array('course' => $course->id), array('section' => 4));

        // Setup the course section for the Side Bar block-managed activities
        $sectioninfo = block_side_bar_create_section($course);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);
        $this->assertEquals(5, $sectioninfo->section);
        $this->assertEquals(6, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 5, $course->id);
    }

    /**
     * Validate that passing an invalid $course parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $course must be an object
     */
    public function test_migrate_old_section_invalid_course_parameter_throws_exception_null() {
        block_side_bar_migrate_old_section(null, 1);
    }

    /**
     * Validate that passing an invalid $course parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $course must be an object
     */
    public function test_migrate_old_section_invalid_course_parameter_throws_exception_int() {
        block_side_bar_migrate_old_section(1, 1);
    }

    /**
     * Validate that passing an invalid $course parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $course must be an object
     */
    public function test_migrate_old_section_invalid_course_parameter_throws_exception_string() {
        block_side_bar_migrate_old_section('string', 1);
    }

    /**
     * Validate that passing an invalid $course parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $course must be an object
     */
    public function test_migrate_old_section_invalid_course_parameter_throws_exception_array() {
        block_side_bar_migrate_old_section(array(1, 2, 3), 1);
    }

    /**
     * Validate that passing an invalid $section parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $sectionnum must be a positive integer
     */
    public function test_migrate_old_section_invalid_section_parameter_throws_exception_null() {
        block_side_bar_migrate_old_section(new stdClass(), null);
    }

    /**
     * Validate that passing an invalid $section parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $sectionnum must be a positive integer
     */
    public function test_migrate_old_section_invalid_section_parameter_throws_exception_int_zero() {
        block_side_bar_migrate_old_section(new stdClass(), 0);
    }

    /**
     * Validate that passing an invalid $section parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $sectionnum must be a positive integer
     */
    public function test_migrate_old_section_invalid_section_parameter_throws_exception_int_negative() {
        block_side_bar_migrate_old_section(new stdClass(), -1);
    }

    /**
     * Validate that passing an invalid $section parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $sectionnum must be a positive integer
     */
    public function test_migrate_old_section_invalid_section_parameter_throws_exception_string() {
        block_side_bar_migrate_old_section(new stdClass(), 'string');
    }

    /**
     * Validate that passing an invalid $section parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $sectionnum must be a positive integer
     */
    public function test_migrate_old_section_invalid_section_parameter_throws_exception_array() {
        block_side_bar_migrate_old_section(new stdClass(), array(1, 2, 3));
    }

    /**
     * Validate that the course section we wish to migrate not existing returns the appropriate result.
     */
    public function test_migrate_old_section_invalid_course_section_returns_null() {
        global $CFG;

        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();

        if (DEBUG_DEVELOPER == $CFG->debug) {
            $CFG->debug = DEBUG_NONE;
        }

        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 1));
        $this->assertNull(block_side_bar_migrate_old_section($course, 1000));
    }

    /**
     * Test that the old sidebar course section migrates into the new position when there are no "fillter" sections that have been created.
     */
    public function test_migrate_old_section_with_no_filler() {
        global $DB;

        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        // Create test course data
        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 10));

        // Create the main course sections for this course (section 1 is already created above).
        for ($i = 2; $i <= 10; $i++) {
            $dg->create_course_section(array('course' => $course->id, 'section' => $i));
        }

        $this->create_sidebar_course_section($course->id, 1000);

        // Run the migration method
        $sectioninfo = block_side_bar_migrate_old_section($course, 1000);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);
        $this->assertEquals(11, $sectioninfo->section);
        $this->assertEquals(11, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 11, $course->id);
    }

    /**
     * Test that the old sidebar course section migrates into the new position when there are "fillter" course sections that have
     * been created. Filler sections are sections created between the course section count value and the section number of the
     * sidebar block section (in this case sections 11 - 999). None of these filler sections contain activity module instances.
     */
    public function test_migrate_old_section_with_empty_filler() {
        global $DB;

        if (!PHPUNIT_LONGTEST) {
            // This test is a long one, only run if allowed.
            return;
        }

        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        // Create test course data
        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 10));

        // Create the main course sections for this course (section 1 is already created above).
        for ($i = 2; $i <= 999; $i++) {
            $dg->create_course_section(array('course' => $course->id, 'section' => $i));
        }

        // Create an "old" style sidebar course section
        $this->create_sidebar_course_section($course->id, 1000);

        // Run the migration method
        $sectioninfo = block_side_bar_migrate_old_section($course, 1000);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);
        $this->assertEquals(11, $sectioninfo->section);
        $this->assertEquals(11, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 11, $course->id);
    }


    /**
     * Test that the old sidebar course section migrates into the new position when there are "fillter" course sections that have
     * been created. Filler sections are sections created between the course section count value and the section number of the
     * sidebar block section (in this case sections 11 - 999). Every 100th filler section contains an activity module instance
     * (i.e. sections 100,200, ... ,900). The sidebar course section (1000) also contains an activity.
     */
    public function test_migrate_old_section_with_nonempty_filler() {
        global $DB;

        if (!PHPUNIT_LONGTEST) {
            // This test is a long one, only run if allowed.
            return;
        }

        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        // Create test course data
        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 10));

        // Create the main course sections for this course (section 1 is already created above).
        for ($i = 2; $i <= 999; $i++) {
            $dg->create_course_section(array('course' => $course->id, 'section' => $i));

            // Populate each 100th section with an activity module
            if ($i % 100 == 0) {
                $page = $dg->create_module('page', array('course' => $course->id), array('section' => $i));
            }
        }

        // Create an "old" style sidebar course section containing an activity module
        $this->create_sidebar_course_section($course->id, 1000);
        $page = $dg->create_module('page', array('course' => $course->id), array('section' => 1000));

        // Run the migration method
        $sectioninfo = block_side_bar_migrate_old_section($course, 1000);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);
        $this->assertEquals(20, $sectioninfo->section);
        $this->assertEquals(20, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 20, $course->id);

        // Validate that the activity module was moved as well
        $this->assertNotEquals(false, get_coursemodule_from_instance('page', $page->id, $course->id, 20));
    }

    /**
     * Validate that running the migration function when there is no migration necessary does not modify any data when the
     * sidebar section does not contain an activity.
     */
    public function test_migrate_old_section_unnecessary_without_an_activity() {
        global $DB;

        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        // Create test course data
        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 10));

        // Create the main course sections for this course (section 1 is already created above).
        for ($i = 2; $i <= 10; $i++) {
            $dg->create_course_section(array('course' => $course->id, 'section' => $i));
        }

        // Create a sidebar course section containing an activity module
        $this->create_sidebar_course_section($course->id, 11);

        // Run the migration method
        $sectioninfo = block_side_bar_migrate_old_section($course, 11);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);
        $this->assertEquals(11, $sectioninfo->section);
        $this->assertEquals(11, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 11, $course->id);
    }

    /**
     * Validate that running the migration function when there is no migration necessary does not modify any data when the
     * the sidebar section contains an activity.
     */
    public function test_migrate_old_section_unnecessary_with_an_activity() {
        global $DB;

        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        // Create test course data
        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 10));

        // Create the main course sections for this course (section 1 is already created above).
        for ($i = 2; $i <= 10; $i++) {
            $dg->create_course_section(array('course' => $course->id, 'section' => $i));
        }

        // Create a sidebar course section containing an activity module
        $this->create_sidebar_course_section($course->id, 11);
        $page = $dg->create_module('page', array('course' => $course->id), array('section' => 11));

        // Run the migration method
        $sectioninfo = block_side_bar_migrate_old_section($course, 11);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);
        $this->assertEquals(11, $sectioninfo->section);
        $this->assertEquals(11, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 11, $course->id);

        // Validate that the activity module was moved as well
        $this->assertNotEquals(false, get_coursemodule_from_instance('page', $page->id, $course->id, 11));
    }

    /**
     * Validate that running the migration function when there is no migration necessary does not modify any data when the
     * the sidebar section contains an activity.
     */
    public function test_migrate_old_section_multiple_instances_sequential() {
        global $DB;

        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        // Create test course data
        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 10));

        // Create the main course sections for this course (section 1 is already created above).
        for ($i = 2; $i <= 10; $i++) {
            $dg->create_course_section(array('course' => $course->id, 'section' => $i));
        }

        // Create a sidebar course section containing an activity module
        $this->create_sidebar_course_section($course->id, 1000);
        $page1 = $dg->create_module('page', array('course' => $course->id), array('section' => 1000));

        // Create an additional sidebar course section containing an activity module
        $this->create_sidebar_course_section($course->id, 1001);
        $page2 = $dg->create_module('page', array('course' => $course->id), array('section' => 1001));

        // Run the migration method
        $sectioninfo = block_side_bar_migrate_old_section($course, 1000);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);
        $this->assertEquals(11, $sectioninfo->section);
        $this->assertEquals(12, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 11, $course->id);

        // Validate that the activity module was moved as well
        $this->assertNotEquals(false, get_coursemodule_from_instance('page', $page1->id, $course->id, 11));

        // Run the migration method
        $sectioninfo = block_side_bar_migrate_old_section($course, 1001);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);
        $this->assertEquals(12, $sectioninfo->section);
        $this->assertEquals(12, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 12, $course->id);

        // Validate that the activity module was moved as well
        $this->assertNotEquals(false, get_coursemodule_from_instance('page', $page2->id, $course->id, 11));
    }

    /**
     * Validate that running the migration function when there is no migration necessary does not modify any data when the
     * the sidebar section contains an activity.
     */
    public function test_migrate_old_section_multiple_instances_nonsequential() {
        global $DB;

        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        // Create test course data
        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 10));

        // Create the main course sections for this course (section 1 is already created above).
        for ($i = 2; $i <= 10; $i++) {
            $dg->create_course_section(array('course' => $course->id, 'section' => $i));
        }

        // Create a sidebar course section containing an activity module
        $this->create_sidebar_course_section($course->id, 1000);
        $page1 = $dg->create_module('page', array('course' => $course->id), array('section' => 1000));

        // Create an additional sidebar course section containing an activity module
        $this->create_sidebar_course_section($course->id, 1001);
        $page2 = $dg->create_module('page', array('course' => $course->id), array('section' => 1001));

        // Run the migration method
        $sectioninfo = block_side_bar_migrate_old_section($course, 1001);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);

        $this->assertEquals(11, $sectioninfo->section);
        $this->assertEquals(12, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 11, $course->id);

        // Validate that the activity module was moved as well
        $this->assertNotEquals(false, get_coursemodule_from_instance('page', $page1->id, $course->id, 11));

        // Run the migration method
        $sectioninfo = block_side_bar_migrate_old_section($course, 1000);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);
        $this->assertEquals(12, $sectioninfo->section);
        $this->assertEquals(12, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 12, $course->id);

        // Validate that the activity module was moved as well
        $this->assertNotEquals(false, get_coursemodule_from_instance('page', $page2->id, $course->id, 12));
    }

    /**
     * Validate that passing an invalid $course parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $course must be an object
     */
    public function test_move_section_invalid_course_parameter_throws_exception_null() {
        $this->resetAfterTest();
        block_side_bar_move_section(null, 1);
    }

    /**
     * Validate that passing an invalid $course parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $course must be an object
     */
    public function test_move_section_invalid_course_parameter_throws_exception_int() {
        block_side_bar_move_section(1, 1);
    }

    /**
     * Validate that passing an invalid $course parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $course must be an object
     */
    public function test_move_section_invalid_course_parameter_throws_exception_string() {
        block_side_bar_move_section('string', 1);
    }

    /**
     * Validate that passing an invalid $course parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $course must be an object
     */
    public function test_move_section_invalid_course_parameter_throws_exception_array() {
        block_side_bar_move_section(array(1, 2, 3), 1);
    }

    /**
     * Validate that passing an invalid $section parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $sectionnum must be a positive integer
     */
    public function test_move_section_invalid_section_parameter_throws_exception_null() {
        block_side_bar_move_section(new stdClass(), null);
    }

    /**
     * Validate that passing an invalid $section parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $sectionnum must be a positive integer
     */
    public function test_move_section_invalid_section_parameter_throws_exception_int_zero() {
        block_side_bar_move_section(new stdClass(), 0);
    }

    /**
     * Validate that passing an invalid $section parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $sectionnum must be a positive integer
     */
    public function test_move_section_invalid_section_parameter_throws_exception_int_negative() {
        block_side_bar_move_section(new stdClass(), -1);
    }

    /**
     * Validate that passing an invalid $section parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $sectionnum must be a positive integer
     */
    public function test_move_section_invalid_section_parameter_throws_exception_string() {
        block_side_bar_move_section(new stdClass(), 'string');
    }

    /**
     * Validate that passing an invalid $section parameter throws the appropriate exception.
     *
     * @expectedException coding_exception
     * @expectedExceptionMessage $sectionnum must be a positive integer
     */
    public function test_move_section_invalid_section_parameter_throws_exception_array() {
        block_side_bar_move_section(new stdClass(), array(1, 2, 3));
    }

    /**
     * Validate that the course section we wish to migrate not existing returns the appropriate result.
     */
    public function test_move_section_invalid_course_section_returns_null() {
        global $CFG;

        $this->resetAfterTest(true);
        $dg = $this->getDataGenerator();

        if (DEBUG_DEVELOPER == $CFG->debug) {
            $CFG->debug = DEBUG_NONE;
        }

        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 1));
        $this->assertNull(block_side_bar_move_section($course, 1000));
    }

    /**
     * Validate that running the move_section() function on a non-sidebar course section has no effect
     */
    public function test_move_section_non_sidebar_section_returns_null() {
        global $DB;

        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        // Create test course data
        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 10));

        // Create the main course sections for this course (section 1 is already created above).
        for ($i = 2; $i <= 10; $i++) {
            $dg->create_course_section(array('course' => $course->id, 'section' => $i));
        }

        // Attempt to "move" the section to be not visible
        $this->assertNull(block_side_bar_move_section($course, 10));
    }

    /**
     * Validate that running the move_section() function with a sidebar section that is already not visible does nothing.
     */
    public function test_move_section_single_not_visible_returns_null() {
        global $DB;

        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        // Create test course data
        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 10));

        // Create the main course sections for this course (section 1 is already created above).
        for ($i = 2; $i <= 10; $i++) {
            $dg->create_course_section(array('course' => $course->id, 'section' => $i));
        }

        // Create a sidebar course section
        $this->create_sidebar_course_section($course->id, 11);

        // Attempt to "move" the section to be not visible
        $this->assertNull(block_side_bar_move_section($course, 11));
    }

    /**
     * Validate that running the move_section() function with a single sidebar section as the last visible course section
     * moves the section "down" one step to be the first (and only) non-visible course section.
     */
    public function test_move_section_single_one_up_empty() {
        global $DB;

        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        // Create test course data
        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 10));

        // Create the main course sections for this course (section 1 is already created above).
        for ($i = 2; $i <= 9; $i++) {
            $dg->create_course_section(array('course' => $course->id, 'section' => $i));
        }

        // Create an empty sidebar course section
        $this->create_sidebar_course_section($course->id, 10);

        // Attempt to "move" the section to be not visible
        $sectioninfo = block_side_bar_move_section($course, 10);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);

        $this->assertEquals(11, $sectioninfo->section);
        $this->assertEquals(11, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 11, $course->id);
    }

    /**
     * Validate that running the move_section() function with a single sidebar section as the second-last visible course
     * section moves the section "down" two steps to be the first (and only) non-visible course section.
     */
    public function test_move_section_single_two_up_empty() {
        global $DB;

        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        // Create test course data
        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 10));

        // Create the main course sections for this course (section 1 is already created above).
        for ($i = 2; $i <= 8; $i++) {
            $dg->create_course_section(array('course' => $course->id, 'section' => $i));
        }

        // Create an empty sidebar course section
        $this->create_sidebar_course_section($course->id, 9);

        // Create the remaining standard course section
        $dg->create_course_section(array('course' => $course->id, 'section' => 10));

        // Attempt to "move" the section to be not visible
        $sectioninfo = block_side_bar_move_section($course, 9);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);

        $this->assertEquals(11, $sectioninfo->section);
        $this->assertEquals(11, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 11, $course->id);
    }

    /**
     * Validate that running the move_section() function with two sidebar sections as the second last and last visible
     * course sections works correctly on each section: only moving that specific section into the invisible area and not
     * both at the same time.
     */
    public function test_move_section_double_empty() {
        global $DB;

        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        // Create test course data
        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 10));

        // Create the main course sections for this course (section 1 is already created above).
        for ($i = 2; $i <= 8; $i++) {
            $dg->create_course_section(array('course' => $course->id, 'section' => $i));
        }

        // Create a coupke empty sidebar course sections
        $this->create_sidebar_course_section($course->id, 9);
        $this->create_sidebar_course_section($course->id, 10);

        // Attempt to "move" the first section to be not visible
        $sectioninfo = block_side_bar_move_section($course, 9);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);

        $this->assertEquals(11, $sectioninfo->section);
        $this->assertEquals(11, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 11, $course->id);

        // Attempt to "move" the second section to be not visible
        $sectioninfo = block_side_bar_move_section($course, 10);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);

        $this->assertEquals(12, $sectioninfo->section);
        $this->assertEquals(12, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 12, $course->id);
    }

    /**
     * Validate that running the move_section() function with a single sidebar section as the last visible course section
     * with already existing orphaned sections moves the sidebar section "down" to be the last non-visible course section.
     */
    public function test_move_section_single_one_up_with_filler_empty() {
        global $DB;

        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        // Create test course data
        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 10));

        // Create the main course sections for this course (section 1 is already created above).
        for ($i = 2; $i <= 9; $i++) {
            $dg->create_course_section(array('course' => $course->id, 'section' => $i));
        }

        // Create an empty sidebar course section
        $this->create_sidebar_course_section($course->id, 10);

        $dg->create_course_section(array('course' => $course->id, 'section' => 11));
        $dg->create_course_section(array('course' => $course->id, 'section' => 12));

        // Attempt to "move" the section to be not visible
        $sectioninfo = block_side_bar_move_section($course, 10);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);

        $this->assertEquals(13, $sectioninfo->section);
        $this->assertEquals(13, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 13, $course->id);
    }

    /**
     * Validate that running the move_section() function with a single sidebar section as the last visible course section
     * moves the section "down" one step to be the first (and only) non-visible course section.
     */
    public function test_move_section_single_one_up_non_empty() {
        global $DB;

        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        // Create test course data
        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 10));

        // Create the main course sections for this course (section 1 is already created above).
        for ($i = 2; $i <= 9; $i++) {
            $dg->create_course_section(array('course' => $course->id, 'section' => $i));
        }

        // Create an empty sidebar course section
        $this->create_sidebar_course_section($course->id, 10);
        $page = $dg->create_module('page', array('course' => $course->id), array('section' => 10));

        // Attempt to "move" the section to be not visible
        $sectioninfo = block_side_bar_move_section($course, 10);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);

        $this->assertEquals(11, $sectioninfo->section);
        $this->assertEquals(11, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 11, $course->id);

        // Validate that the activity module was moved as well
        $this->assertNotEquals(false, get_coursemodule_from_instance('page', $page->id, $course->id, 11));
    }

    /**
     * Validate that running the move_section() function with a single sidebar section as the second-last visible course
     * section moves the section "down" two steps to be the first (and only) non-visible course section.
     */
    public function test_move_section_single_two_up_non_empty() {
        global $DB;

        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        // Create test course data
        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 10));

        // Create the main course sections for this course (section 1 is already created above).
        for ($i = 2; $i <= 8; $i++) {
            $dg->create_course_section(array('course' => $course->id, 'section' => $i));
        }

        // Create an empty sidebar course section
        $this->create_sidebar_course_section($course->id, 9);
        $page = $dg->create_module('page', array('course' => $course->id), array('section' => 9));

        // Create the remaining standard course section
        $dg->create_course_section(array('course' => $course->id, 'section' => 10));

        // Attempt to "move" the section to be not visible
        $sectioninfo = block_side_bar_move_section($course, 9);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);

        $this->assertEquals(11, $sectioninfo->section);
        $this->assertEquals(11, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 11, $course->id);

        // Validate that the activity module was moved as well
        $this->assertNotEquals(false, get_coursemodule_from_instance('page', $page->id, $course->id, 11));
    }

    /**
     * Validate that running the move_section() function with two sidebar sections as the second last and last visible
     * course sections works correctly on each section: only moving that specific section into the invisible area and not
     * both at the same time.
     */
    public function test_move_section_double_non_empty() {
        global $DB;

        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        // Create test course data
        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 10));

        // Create the main course sections for this course (section 1 is already created above).
        for ($i = 2; $i <= 8; $i++) {
            $dg->create_course_section(array('course' => $course->id, 'section' => $i));
        }

        // Create a coupke empty sidebar course sections
        $this->create_sidebar_course_section($course->id, 9);
        $page1 = $dg->create_module('page', array('course' => $course->id), array('section' => 9));

        $this->create_sidebar_course_section($course->id, 10);
        $page2 = $dg->create_module('page', array('course' => $course->id), array('section' => 10));

        // Attempt to "move" the first section to be not visible
        $sectioninfo = block_side_bar_move_section($course, 9);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);

        $this->assertEquals(11, $sectioninfo->section);
        $this->assertEquals(11, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 11, $course->id);

        // Validate that the activity module was moved as well
        $this->assertNotEquals(false, get_coursemodule_from_instance('page', $page1->id, $course->id, 11));

        // Attempt to "move" the second section to be not visible
        $sectioninfo = block_side_bar_move_section($course, 10);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);

        $this->assertEquals(12, $sectioninfo->section);
        $this->assertEquals(12, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 12, $course->id);

        // Validate that the activity module was moved as well
        $this->assertNotEquals(false, get_coursemodule_from_instance('page', $page1->id, $course->id, 12));
    }

    /**
     * Validate that running the move_section() function with a single sidebar section as the last visible course section
     * with already existing orphaned sections moves the sidebar section "down" to be the last non-visible course section.
     */
    public function test_move_section_single_one_up_with_filler_non_empty() {
        global $DB;

        $this->resetAfterTest();
        $dg = $this->getDataGenerator();

        // Create test course data
        $course = $dg->create_course(array('format' => 'topics', 'numsections' => 10));

        // Create the main course sections for this course (section 1 is already created above).
        for ($i = 2; $i <= 9; $i++) {
            $dg->create_course_section(array('course' => $course->id, 'section' => $i));
        }

        // Create an empty sidebar course section
        $this->create_sidebar_course_section($course->id, 10);
        $page1 = $dg->create_module('page', array('course' => $course->id), array('section' => 10));

        $dg->create_course_section(array('course' => $course->id, 'section' => 11));
        $page2 = $dg->create_module('page', array('course' => $course->id), array('section' => 11));

        $dg->create_course_section(array('course' => $course->id, 'section' => 12));
        $page3 = $dg->create_module('page', array('course' => $course->id), array('section' => 12));

        // Attempt to "move" the section to be not visible
        $sectioninfo = block_side_bar_move_section($course, 10);

        // Ensure returned data is what we expect
        $this->assertTrue(is_object($sectioninfo));
        $this->assertObjectHasAttribute('id', $sectioninfo);
        $this->assertObjectHasAttribute('section', $sectioninfo);

        $this->assertEquals(13, $sectioninfo->section);
        $this->assertEquals(13, $DB->count_records('course_sections', array('course' => $course->id)));

        // Load the new section record from the DB to make sure the stored values are setup correctly
        $sbsection = $DB->get_record('course_sections', array('id' => $sectioninfo->id), 'section, name, summary, visible');
        $this->validate_sidebar_course_section($sbsection, 13, $course->id);

        // Validate that the activity module was moved as well
        $this->assertNotEquals(false, get_coursemodule_from_instance('page', $page1->id, $course->id, 13));

        // Validate that the activities in the pre-existing orhpaned course sections were not moved
        $this->assertNotEquals(false, get_coursemodule_from_instance('page', $page2->id, $course->id, 11));
        $this->assertNotEquals(false, get_coursemodule_from_instance('page', $page3->id, $course->id, 12));
    }
}
