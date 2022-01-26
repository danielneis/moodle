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
 * This class deletes folders from content bank.
 *
 * @package    core_contentbank
 * @copyright  2022 Daniel Neis Araujo <danielneis@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_contentbank\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;

/**
 * This class deletes folders from content bank.
 *
 * @package    core_contentbank
 * @copyright  2022 Daniel Neis Araujo <danielneis@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_folder extends external_api {
    /**
     * delete_folder parameters.
     *
     * @since  Moodle 4.1
     * @return external_function_parameters
     */
    public static function execute_parameters(): \external_function_parameters {
        return new external_function_parameters(
            [
                'folderid' => new external_value(PARAM_INT, 'The folder id to delete', VALUE_REQUIRED),
                'contextid' => new external_value(PARAM_INT, 'The context of the folder', VALUE_REQUIRED)
            ]
        );
    }
    /**
     * Delete a folder from the content bank.
     *
     * @since  Moodle 4.1
     * @param int $folderid Id of the folder.
     * @param int $contextid Context of the folder.
     * @return int Id of the parent folder
     */
    public static function execute(int $folderid, int $contextid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'folderid' => $folderid,
            'contextid' => $contextid
        ]);
        return ['parentid' => \core_contentbank\folder::delete_folder($params['folderid'], $params['contextid'])];
    }

    /**
     * create_folder return
     *
     * @since  Moodle 3.9
     * @return external_value
     */
    public static function execute_returns(): \external_single_structure {
        return new external_single_structure([
            'parentid' => new external_value(PARAM_INT, 'The id of the parent folder')
        ]);
    }
}
