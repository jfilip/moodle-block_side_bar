<?php
/**
 * Side Bar block global settings file.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    base
 * @subpackage blocks-side_bar
 * @author     Justin Filip <jfilip@remote-learner.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2011 Justin Filip
 *
 */

 $settings->add(new admin_setting_configtext('block_side_bar_section_start', get_string('configsectionnumber', 'block_side_bar'),
                   get_string('sectionnumberwarning', 'block_side_bar'), 1000, PARAM_INT));

?>
