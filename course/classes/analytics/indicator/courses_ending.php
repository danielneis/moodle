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
 * Courses ending indicator.
 *
 * @package   core
 * @copyright 2020 Daniel Neis Araujo <danielneis@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_course\analytics\indicator;

defined('MOODLE_INTERNAL') || die();

/**
 * Activities due indicator.
 *
 * @package   core
 * @copyright 2020 Daniel Neis Araujo <danielneis@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class courses_ending extends \core_analytics\local\indicator\binary {

    /**
     * Returns the name.
     *
     * If there is a corresponding '_help' string this will be shown as well.
     *
     * @return \lang_string
     */
    public static function get_name() : \lang_string {
        return new \lang_string('indicator:coursesending');
    }

    /**
     * required_sample_data
     *
     * @return string[]
     */
    public static function required_sample_data() {
        return array('user');
    }

    /**
     * calculate_sample
     *
     * @param int $sampleid
     * @param string $sampleorigin
     * @param int $starttime
     * @param int $endtime
     * @return float
     */
    protected function calculate_sample($sampleid, $sampleorigin, $starttime = false, $endtime = false) {

        $user = $this->retrieve('user', $sampleid);

        $courses = enrol_get_all_users_courses($user->id, false, ['enddate']);

        if (!empty($courses)) {
            $useractionevents = [];
            foreach ($courses as $c) {
                if (($c->enddate >= $starttime) && ($c->enddate <= $endtime)) {
                    $useractionevents[$c->id] = (object)[
                        'id' => $c->id,
                        'coursename' => format_string($c->fullname),
                        'shortname' => $c->shortname,
                        'enddate' => $c->enddate,
                        'url' => new \moodle_url('/course/view.php', ['id' => $c->id]),
                    ];
                }
            }
            if (!empty($useractionevents)) {
                $this->add_shared_calculation_info($sampleid, $useractionevents);
                return self::get_max_value();
            }
        }
        return self::get_min_value();
    }
}
