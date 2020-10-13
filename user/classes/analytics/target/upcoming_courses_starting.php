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
 * Upcoming courses starting target.
 *
 * @package   core
 * @copyright 2020 Daniel Neis Araujo <danielneis@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_user\analytics\target;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/enrollib.php');

/**
 * Upcoming courses starting target.
 *
 * @package   core
 * @copyright 2020 Daniel Neis Araujo <danielneis@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upcoming_courses_starting extends \core_analytics\local\target\binary {

    /**
     * Machine learning backends are not required to predict.
     *
     * @return bool
     */
    public static function based_on_assumptions() {
        return true;
    }

    /**
     * Only update last analysis time when analysables are processed.
     * @return bool
     */
    public function always_update_analysis_time(): bool {
        return false;
    }

    /**
     * Only upcoming stuff.
     *
     * @param  \core_analytics\local\time_splitting\base $timesplitting
     * @return bool
     */
    public function can_use_timesplitting(\core_analytics\local\time_splitting\base $timesplitting): bool {
        return ($timesplitting instanceof \core_analytics\local\time_splitting\after_now);
    }

    /**
     * Returns the name.
     *
     * If there is a corresponding '_help' string this will be shown as well.
     *
     * @return \lang_string
     */
    public static function get_name() : \lang_string {
        return new \lang_string('target:upcomingcoursesstarting', 'user');
    }

    /**
     * Overwritten to show a simpler language string.
     *
     * @param  int $modelid
     * @param  \context $context
     * @return string
     */
    public function get_insight_subject(int $modelid, \context $context) {
        return get_string('youhaveupcomingcoursesstarting', 'user');
    }

    /**
     * classes_description
     *
     * @return string[]
     */
    protected static function classes_description() {
        return array(
            get_string('no'),
            get_string('yes'),
        );
    }

    /**
     * Returns the predicted classes that will be ignored.
     *
     * @return array
     */
    public function ignored_predicted_classes() {
        // No need to process users without upcoming activities due.
        return array(0);
    }

    /**
     * get_analyser_class
     *
     * @return string
     */
    public function get_analyser_class() {
        return '\core\analytics\analyser\users';
    }

    /**
     * All users are ok.
     *
     * @param \core_analytics\analysable $analysable
     * @param mixed $fortraining
     * @return true|string
     */
    public function is_valid_analysable(\core_analytics\analysable $analysable, $fortraining = true) {
        // The calendar API used by \core_course\analytics\indicator\courses_starting is already checking
        // if the user has any courses.
        return true;
    }

    /**
     * Samples are users and all of them are ok.
     *
     * @param int $sampleid
     * @param \core_analytics\analysable $analysable
     * @param bool $fortraining
     * @return bool
     */
    public function is_valid_sample($sampleid, \core_analytics\analysable $analysable, $fortraining = true) {
        return true;
    }

    /**
     * Calculation based on courses starting indicator.
     *
     * @param int $sampleid
     * @param \core_analytics\analysable $analysable
     * @param int $starttime
     * @param int $endtime
     * @return float
     */
    protected function calculate_sample($sampleid, \core_analytics\analysable $analysable, $starttime = false, $endtime = false) {

        $coursesstartingindicator = $this->retrieve('\core_course\analytics\indicator\courses_starting', $sampleid);
        if ($coursesstartingindicator == \core_course\analytics\indicator\courses_starting::get_max_value()) {
            return 1;
        }
        return 0;
    }

    /**
     * No need to link to the insights report in this case.
     *
     * @return bool
     */
    public function link_insights_report(): bool {
        return false;
    }

    /**
     * Returns the body message for an insight of a single prediction.
     *
     * This default method is executed when the analysable used by the model generates one insight
     * for each analysable (one_sample_per_analysable === true)
     *
     * @param  \context                             $context
     * @param  \stdClass                            $user
     * @param  \core_analytics\prediction           $prediction
     * @param  \core_analytics\action[]             $actions        Passed by reference to remove duplicate links to actions.
     * @return array                                                Plain text msg, HTML message and the main URL for this
     *                                                              insight (you can return null if you are happy with the
     *                                                              default insight URL calculated in prediction_info())
     */
    public function get_insight_body_for_prediction(\context $context, \stdClass $user, \core_analytics\prediction $prediction,
            array &$actions) {
        global $OUTPUT;

        $fullmessageplaintext = get_string('youhaveupcomingcoursesstarting', 'user', $user->firstname);

        $sampledata = $prediction->get_sample_data();
        $coursesstarting = $sampledata['core_course\analytics\indicator\courses_starting:extradata'];

        if (empty($coursesstarting)) {
            // We can throw an exception here because this is a target based on assumptions and we require the
            // activities_due indicator.
            throw new \coding_exception('The courses_starting indicator must be part of the model indicators.');
        }

        $coursestext = [];
        foreach ($coursesstarting as $key => $coursestarting) {

            // Human-readable version.
            $coursesstarting[$key]->formattedtime = userdate($coursestarting->startdate);

            // We provide the URL to the activity through a script that records the user click.
            $courseurl = new \moodle_url('/course/view.php', ['id' => $coursestarting->id]);
            $actionurl = \core_analytics\prediction_action::transform_to_forward_url($courseurl, 'viewupcoming',
                $prediction->get_prediction_data()->id);
            $coursesstarting[$key]->url = $actionurl->out(false);

            if (count($coursesstarting) === 1) {
                // We will use this activity as the main URL of this insight.
                $insighturl = $actionurl;
            }

            $coursestext[] = $coursestarting->shortname . ': ' . $coursesstarting[$key]->url;
        }

        foreach ($actions as $key => $action) {
            if ($action->get_action_name() === 'viewupcoming') {

                // Use it as the main URL of the insight if there are multiple activities due.
                if (empty($insighturl)) {
                    $insighturl = $action->get_url();
                }

                // Remove the 'viewupcoming' action from the list of actions for this prediction as the action has
                // been included in the link to the activity.
                unset($actions[$key]);
                break;
            }
        }

        $courseshtml = $OUTPUT->render_from_template('core_user/upcoming_courses_starting_insight_body', (object) [
            'coursesstarting' => array_values($coursesstarting),
            'userfirstname' => $user->firstname
        ]);

        return [
            FORMAT_PLAIN => $fullmessageplaintext . PHP_EOL . PHP_EOL . implode(PHP_EOL, $coursestext) . PHP_EOL,
            FORMAT_HTML => $courseshtml,
            'url' => $insighturl,
        ];
    }

    /**
     * Adds a view upcoming events action.
     *
     * @param \core_analytics\prediction $prediction
     * @param mixed $includedetailsaction
     * @param bool $isinsightuser
     * @return \core_analytics\prediction_action[]
     */
    public function prediction_actions(\core_analytics\prediction $prediction, $includedetailsaction = false,
            $isinsightuser = false) {
        global $CFG, $USER;

        $parentactions = parent::prediction_actions($prediction, $includedetailsaction, $isinsightuser);

        if (!$isinsightuser && $USER->id != $prediction->get_prediction_data()->sampleid) {
            return $parentactions;
        }

        // We force a lookahead of 30 days so we are sure that the upcoming courses starting are shown.
        $url = new \moodle_url('/calendar/view.php', ['view' => 'upcoming', 'lookahead' => '30']);
        $pix = new \pix_icon('i/course', get_string('viewupcomingcoursesstarting', 'user'));
        $action = new \core_analytics\prediction_action('viewupcoming', $prediction,
            $url, $pix, get_string('viewupcomingcoursesstarting', 'user'));

        return array_merge([$action], $parentactions);
    }
}
