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
 * Prints the unified filter on Activity Completion report.
 *
 * @package    report_progress
 * @copyright  2020 Daniel Neis Araujo <danielneis@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_progress\output;

defined('MOODLE_INTERNAL') || die;

use plugin_renderer_base;
use renderable;

/**
 * Prints the unified filter on Activity Completion report.
 *
 * @copyright  2020 Daniel Neis Araujo <danielneis@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * Renders the unified filter element for the course participants page.
     *
     * @param stdClass $course The course object.
     * @param context $context The context object.
     * @param array $filtersapplied Array of currently applied filters.
     * @param string|moodle_url $baseurl The url with params needed to call up this page.
     * @return bool|string
     */
    public function unified_filter($course, $context, $filtersapplied, $baseurl = null) {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/enrol/locallib.php');
        require_once($CFG->dirroot . '/lib/grouplib.php');
        $manager = new \course_enrolment_manager($this->page, $course);

        $filteroptions = [];

        // Filter options for role.
        $roleseditable = has_capability('moodle/role:assign', $context);
        $roles = get_viewable_roles($context);
        if ($roleseditable) {
            $roles += get_assignable_roles($context, ROLENAME_ALIAS);
        }

        $criteria = get_string('role');
        $roleoptions = $this->format_filter_option(USER_FILTER_ROLE, $criteria, -1, get_string('noroles', 'role'));
        foreach ($roles as $id => $role) {
            $roleoptions += $this->format_filter_option(USER_FILTER_ROLE, $criteria, $id, $role);
        }
        $filteroptions += $roleoptions;

        // Filter options for groups, if available.
        if (has_capability('moodle/site:accessallgroups', $context) || $course->groupmode != SEPARATEGROUPS) {
            // List all groups if the user can access all groups, or we are in visible group mode or no groups mode.
            $groups = $manager->get_all_groups();
            if (!empty($groups)) {
                // Add 'No group' option, to enable filtering users without any group.
                $nogroup[USERSWITHOUTGROUP] = (object)['name' => get_string('nogroup', 'group')];
                $groups = $nogroup + $groups;
            }
        } else {
            // Otherwise, just list the groups the user belongs to.
            $groups = groups_get_all_groups($course->id, $USER->id);
        }
        $criteria = get_string('group');
        $groupoptions = [];
        foreach ($groups as $id => $group) {
            $groupoptions += $this->format_filter_option(USER_FILTER_GROUP, $criteria, $id, $group->name);
        }
        $filteroptions += $groupoptions;

        $canreviewenrol = has_capability('moodle/course:enrolreview', $context);

        $isfrontpage = ($course->id == SITEID);

        // Get the list of fields we have to hide.
        $hiddenfields = array();
        if (!has_capability('moodle/course:viewhiddenuserfields', $context)) {
            $hiddenfields = array_flip(explode(',', $CFG->hiddenuserfields));
        }

        $indexpage = new \core_user\output\unified_filter($filteroptions, $filtersapplied, $baseurl);
        $context = $indexpage->export_for_template($this->output);

        return $this->output->render_from_template('core_user/unified_filter', $context);
    }

    /**
     * Returns a formatted filter option.
     *
     * @param int $filtertype The filter type (e.g. status, role, group, enrolment, last access).
     * @param string $criteria The string label of the filter type.
     * @param int $value The value for the filter option.
     * @param string $label The string representation of the filter option's value.
     * @return array The formatted option with the ['filtertype:value' => 'criteria: label'] format.
     */
    protected function format_filter_option($filtertype, $criteria, $value, $label) {
        $optionlabel = get_string('filteroption', 'moodle', (object)['criteria' => $criteria, 'value' => $label]);
        $optionvalue = "$filtertype:$value";
        return [$optionvalue => $optionlabel];
    }
}
