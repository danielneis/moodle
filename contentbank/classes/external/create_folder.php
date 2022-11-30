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
 * This is the external API for this component.
 *
 * @package    core_contentbank
 * @copyright  2020 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_contentbank\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;

/**
 * This is the external API for this component.
 *
 * @copyright  2020 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_folder extends external_api {
    /**
     * create_folder parameters.
     *
     * @since  Moodle 4.1
     * @return external_function_parameters
     */
    public static function execute_parameters(): \external_function_parameters {
        return new external_function_parameters(
            [
                'name' => new external_value(PARAM_RAW,
                    'Name for the new folder (can contain characters will be cleaned)', VALUE_REQUIRED),
                'parentid' => new external_value(PARAM_INT, 'The content id to delete', VALUE_DEFAULT, 0),
                'contextid' => new external_value(PARAM_INT, 'The context of the folder', VALUE_DEFAULT, 0)
            ]
        );
    }
    /**
     * Create a new folder in the content bank.
     *
     * @since  Moodle 4.1
     * @param string $name     Name of the new folder.
     * @param int $parentid    Id of the parent folder.
     * @param int $contextid     Context of the folder.
     * @return int Id of the new folder created
     */
    public static function execute(string $name, int $parentid = 0, int $contextid = 0): int {
        $name = clean_param($name, PARAM_PATH);
        $params = self::validate_parameters(self::execute_parameters(), [
            'name' => $name,
            'parentid' => $parentid,
            'contextid' => $contextid
        ]);

        $content = new \stdClass();
        $content->name = $params['name'];
        $content->parent = $params['parentid'];
        $content->contextid = $params['contextid'];
        $folder = \core_contentbank\folder::create_folder($content);
        if (empty($folder)) {
            return 0;
        }
        return $folder->get_id();
    }

    /**
     * create_folder return
     *
     * @since  Moodle 3.9
     * @return external_value
     */
    public static function execute_returns(): \external_value {
        return new external_value(PARAM_INT, 'The id of the created folder');
    }
}
