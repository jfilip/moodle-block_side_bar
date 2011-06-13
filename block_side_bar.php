<?php
/**
 * Allows for arbitrarily adding resources or activities to extra (non-standard) course sections with instance
 * configuration for the block title.
 *
 * NOTE: Code modified from Moodle site_main_menu block.
 *
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    base
 * @subpackage blocks-side_bar
 * @see        blocks-site_main_menu
 * @author     Justin Filip <jfilip@remote-learner.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2011 Justin Filip
 *
 */

class block_side_bar extends block_list {

    function init() {
        global $CFG;

        $this->title = get_string('pluginname', 'block_side_bar');

        // Make sure the global section start value is set.
        if (!isset($CFG->block_side_bar_section_start)) {
            set_config('block_side_bar_section_start', 1000);
        }
    }

    function get_content() {
        global $USER, $CFG, $DB, $OUTPUT;

        if ($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->items  = array();
        $this->content->icons  = array();
        $this->content->footer = '';

        if (empty($this->instance)) {
            return $this->content;
        }

        if (!isset($this->config->title)) {
            $this->config->title = '';
        }

        $course = $this->page->course;

        require_once($CFG->dirroot.'/course/lib.php');

        $context   = get_context_instance(CONTEXT_COURSE, $course->id);
        $isediting = $this->page->user_is_editing() && has_capability('moodle/course:manageactivities', $context);
        $modinfo   = get_fast_modinfo($course);

        $section_start = $CFG->block_side_bar_section_start;

        // Create a new section for this block (if necessary).
        if (empty($this->config->section)) {
            $sql = "SELECT MAX(section) as sectionid
                    FROM {course_sections}
                    WHERE course = ?";

            $rec = $DB->get_record_sql($sql, array($course->id));

            $sectionnum = $rec->sectionid;

            if ($sectionnum < $section_start) {
                $sectionnum = $section_start;
            } else {
                $sectionnum++;
            }

            $section = new stdClass;
            $section->course        = $course->id;
            $section->name          = get_string('sidebar', 'block_side_bar');
            $section->section       = $sectionnum;
            $section->summary       = '';
            $section->summaryformat = 0;
            $section->sequence      = '';
            $section->visible       = 1;
            $section->id = $DB->insert_record('course_sections', $section);

            if (empty($section->id)) {
                if ($course->id == SITEID) {
                    $link = $CFG->wwwroot.'/';
                } else {
                    $link = $CFG->wwwroot.'/course/view.php?id='.$course->id;
                }

                print_error('error_couldnotaddsection', 'block_side_bar', $link);
            }

            // Store the section number and ID of the DB record for that section.
            $this->config->section    = $section->section;
            $this->config->section_id = $section->id;
            parent::instance_config_commit();

        } else {
            if (empty($this->config->section_id)) {
                $params = array(
                    'course' =>  $course->id,
                    'section' => $this->config->section
                );
                $section = $DB->get_record('course_sections', $params);

                $this->config->section_id = $section->id;
                parent::instance_config_commit();
            } else {
                $section = $DB->get_record('course_sections', array('id' => $this->config->section_id));
            }

            // Double check that the section number hasn't been modified by something else.
            // Fixes problem found by Charlotte Owen when moving 'center column' course sections.
print_object('$section->section: '.$section->section);
print_object('$this->config->section: '.$this->config->section);
print_object('$this->config->section_id: '.$this->config->section_id);
            if ($section->section != $this->config->section) {
                $section->section = $this->config->section;

                $DB->update_record('course_sections', $section);
            }
        }

        // extra fast view mode
        if (!$isediting) {
            if (!empty($modinfo->sections[$this->config->section])) {
                $options = array('overflowdiv'=>true);
                foreach($modinfo->sections[$this->config->section] as $cmid) {
                    $cm = $modinfo->cms[$cmid];
                    if (!$cm->uservisible) {
                        continue;
                    }

                    list($content, $instancename) = get_print_section_cm_text($cm, $course);

                    if (!($url = $cm->get_url())) {
                        $this->content->items[] = $content;
                        $this->content->icons[] = '';
                    } else {
                        $linkcss = $cm->visible ? '' : ' class="dimmed" ';
                        //Accessibility: incidental image - should be empty Alt text
                        $icon = '<img src="'.$cm->get_icon_url().'" class="icon" alt="" />&nbsp;';
                        $this->content->items[] = '<a title="'.$cm->modplural.'" '.$linkcss.' '.$cm->extra.' href="'.
                                                  $url.'">'.$icon.$instancename.'</a>';
                    }
                }
            }

            return $this->content;
        }

        // slow & hacky editing mode
        $ismoving = ismoving($course->id);
        $section  = get_course_section($this->config->section, $course->id);

        get_all_mods($course->id, $mods, $modnames, $modnamesplural, $modnamesused);

        $groupbuttons     = $course->groupmode;
        $groupbuttonslink = (!$course->groupmodeforce);

        if ($ismoving) {
            $strmovehere = get_string('movehere');
            $strmovefull = strip_tags(get_string('movefull', '', "'$USER->activitycopyname'"));
            $strcancel= get_string('cancel');
            $stractivityclipboard = $USER->activitycopyname;
        }

        // Casting $course->modinfo to string prevents one notice when the field is null
        $editbuttons = '';

        if ($ismoving) {
            $this->content->icons[] = '<img src="'.$OUTPUT->pix_url('t/move') . '" class="iconsmall" alt="" />';
            $this->content->items[] = $USER->activitycopyname.'&nbsp;(<a href="'.$CFG->wwwroot.'/course/mod.php?'.
                                      'cancelcopy=true&amp;sesskey='.sesskey().'">'.$strcancel.'</a>)';
        }

        if (!empty($section->sequence)) {
            $sectionmods = explode(',', $section->sequence);
            $options = array('overflowdiv'=>true);
            foreach ($sectionmods as $modnumber) {
                if (empty($mods[$modnumber])) {
                    continue;
                }
                $mod = $mods[$modnumber];
                if (!$ismoving) {
                    if ($groupbuttons) {
                        if (! $mod->groupmodelink = $groupbuttonslink) {
                            $mod->groupmode = $course->groupmode;
                        }

                    } else {
                        $mod->groupmode = false;
                    }
                    $editbuttons = '<div class="buttons">'.make_editing_buttons($mod, true, true).'</div>';
                } else {
                    $editbuttons = '';
                }
                if ($mod->visible || has_capability('moodle/course:viewhiddenactivities', $context)) {
                    if ($ismoving) {
                        if ($mod->id == $USER->activitycopy) {
                            continue;
                        }
                        $this->content->items[] = '<a title="'.$strmovefull.'" href="'.$CFG->wwwroot.'/course/mod.php'.
                                                  '?moveto='.$mod->id.'&amp;sesskey='.sesskey().'"><img style="height'.
                                                  ':16px; width:80px; border:0px" src="'.$OUTPUT->pix_url('movehere').
                                                  '" alt="'.$strmovehere.'" /></a>';
                        $this->content->icons[] = '';
                    }
                    list($content, $instancename) = get_print_section_cm_text($modinfo->cms[$modnumber], $course);
                    $linkcss = $mod->visible ? '' : ' class="dimmed" ';

                    if (!($url = $mod->get_url())) {
                        $this->content->items[] = $content . $editbuttons;
                        $this->content->icons[] = '';
                    } else {
                        //Accessibility: incidental image - should be empty Alt text
                        $icon = '<img src="'.$mod->get_icon_url().'" class="icon" alt="" />&nbsp;';
                        $this->content->items[] = '<a title="'.$mod->modfullname.'" '.$linkcss.' '.$mod->extra.
                                                  ' href="'.$url.'">'.$icon.$instancename.'</a>'.$editbuttons;
                    }
                }
            }
        }

        if ($ismoving) {
            $this->content->items[] = '<a title="'.$strmovefull.'" href="'.$CFG->wwwroot.'/course/mod.php?'.
                                      'movetosection='.$section->id.'&amp;sesskey='.sesskey().'"><img style="height'.
                                      ':16px; width:80px; border:0px" src="'.$OUTPUT->pix_url('movehere').'" alt="'.
                                      $strmovehere.'" /></a>';
            $this->content->icons[] = '';
        }

        if (!empty($modnames)) {
            $this->content->footer = print_section_add_menus($course, $this->config->section, $modnames, true, true);
        } else {
            $this->content->footer = '';
        }

        return $this->content;
    }

    function instance_delete() {
        global $CFG, $DB;

        if (empty($this->instance) || !isset($this->config->section)) {
            return true;
        }

        // Cleanup the section created by this block and any course modules.
        $params = array(
            'section' => $this->config->section,
            'course'  => $this->page->course->id
        );

        if (!$section = $DB->get_record('course_sections', $params)) {
            return true;
        }

        if ($rs = $DB->get_recordset('course_modules', array('section' => $section->id))) {
            $mods = array();

            while ($module = $rs->fetch_next_record) {
                $modid = $module->module;

                if (!isset($mods[$modid])) {
                    $mods[$modid] = $DB->get_field('modules', 'name', array('id' => $modid));
                }

                $mod_lib = $CFG->dirroot.'/mod/'.$mods[$modid].'/lib.php';

                if (file_exists($mod_lib)) {
                    require_once($mod_lib);

                    $delete_func = $mods[$modid].'_delete_instance';

                    if (function_exists($delete_func)) {
                        $delete_func($module->instance);
                    }
                }
            }

            $rs->close();
        }

        return $DB->delete_records('course_sections', array('id' => $section->id));
    }

    function specialization() {
        if (!empty($this->config->title)) {
            $this->title = $this->config->title;
        }
    }

    function has_config() {
        return true;
    }

    function config_save($data) {
        if (!empty($data->block_side_bar_section_start)) {
            set_config('block_side_bar_section_start', intval($data->block_side_bar_section_start));
        }
    }

    function instance_allow_multiple() {
        return true;
    }

    function applicable_formats() {
        return array(
            'site-index'  => true,
            'course-view' => true
        );
    }

    function after_restore($restore) {
        // Get the correct course_sections record ID for the new course
        $section = $DB->get_record('course_sections', 'course', $this->instance->pageid, 'section', $this->config->section);

        if (!empty($section->id)) {
            $this->config->section_id = $section->id;
            parent::instance_config_commit();
        }

        return true;
    }

}
