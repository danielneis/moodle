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
 * @package   core_customfield
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . "/externallib.php");

/**
 * Class core_customfield_external
 */
class core_customfield_external extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function delete_entry_parameters() {
        return new external_function_parameters(
                array('id' => new external_value(PARAM_INT, 'Entry ID to delete', VALUE_REQUIRED))
        );
    }

    /**
     * @param $id
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function delete_entry($id) {
        $params = self::validate_parameters(self::delete_entry_parameters(), ['id' => $id]);

        $record = \core_customfield\api::get_field($params['id']);
        $handler = \core_customfield\handler::get_handler_for_field($record);
        if (!$handler->can_configure()) {
            throw new moodle_exception('nopermissionconfigure', 'core_customfield');
        }
        $record->delete();
    }

    /**
     *
     */
    public static function delete_entry_returns() {
    }

    /**
     * @return external_function_parameters
     */
    public static function reload_template_parameters() {
        return new external_function_parameters(
            array(
                'component' => new external_value(PARAM_COMPONENT, 'component', VALUE_REQUIRED),
                'area' => new external_value(PARAM_ALPHANUMEXT, 'area', VALUE_REQUIRED),
                'itemid' => new external_value(PARAM_INT, 'itemid', VALUE_REQUIRED)
            )
        );
    }

    /**
     * @param string $component
     * @param string $area
     * @param int $itemid
     * @return array|object|stdClass
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws require_login_exception
     */
    public static function reload_template($component, $area, $itemid) {
        global $PAGE;

        $params = self::validate_parameters(self::reload_template_parameters(),
                      ['component' => $component, 'area' => $area, 'itemid' => $itemid]);

        $PAGE->set_context(context_system::instance());
        $handler = \core_customfield\handler::get_handler($params['component'], $params['area'], $params['itemid']);
        self::validate_context($handler->get_configuration_context());
        if (!$handler->can_configure()) {
            throw new moodle_exception('nopermissionconfigure', 'core_customfield');
        }
        $output = $PAGE->get_renderer('core_customfield');
        $outputpage = new \core_customfield\output\management($handler);
        return $outputpage->export_for_template($output);
    }

    /**
     * @return external_single_structure
     */
    public static function reload_template_returns() {
        return new external_single_structure(
            array(
                'component' => new external_value(PARAM_COMPONENT, 'component'),
                'area' => new external_value(PARAM_ALPHANUMEXT, 'area'),
                'itemid' => new external_value(PARAM_INT, 'itemid'),
                'usescategories' => new external_value(PARAM_INT, 'view has categories'),
                'categories' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'id'),
                            'nameeditable' => new external_value(PARAM_RAW, 'inplace editable name'),
                            'addfieldmenu' => new external_value(PARAM_RAW, 'addfieldmenu'),
                            'fields' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'name' => new external_value(PARAM_NOTAGS, 'name'),
                                        'shortname' => new external_value(PARAM_NOTAGS, 'shortname'),
                                        'type' => new external_value(PARAM_NOTAGS, 'type'),
                                        'editfieldurl' => new external_value(PARAM_URL, 'edit field url'),
                                        'deletefieldurl' => new external_value(PARAM_URL, 'deleteurl'),
                                        'id' => new external_value(PARAM_INT, 'id'),
                                    )
                                )
                            , '', VALUE_OPTIONAL),
                        )
                    )
                ),
            )
        );
    }

    /**
     * @return external_function_parameters
     */
    public static function delete_category_parameters() {
        return new external_function_parameters(
                array('id' => new external_value(PARAM_INT, 'category ID to delete', VALUE_REQUIRED))
        );
    }

    /**
     * @param $id
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function delete_category($id) {
        $category = new \core_customfield\category($id);
        $handler = \core_customfield\handler::get_handler_for_category($category);
        self::validate_context($handler->get_configuration_context());
        if (!$handler->can_configure()) {
            throw new moodle_exception('nopermissionconfigure', 'core_customfield');
        }
        $category->delete();
    }

    /**
     *
     */
    public static function delete_category_returns() {
    }


    /**
     * @return external_function_parameters
     */
    public static function create_category_parameters() {
        return new external_function_parameters(
            array(
                'component' => new external_value(PARAM_COMPONENT, 'component', VALUE_REQUIRED),
                'area' => new external_value(PARAM_ALPHANUMEXT, 'area', VALUE_REQUIRED),
                'itemid' => new external_value(PARAM_INT, 'itemid', VALUE_REQUIRED)
            )
        );
    }

    /**
     * @param string $component
     * @param string $area
     * @param int $itemid
     */
    public static function create_category($component, $area, $itemid) {
        $params = self::validate_parameters(self::create_category_parameters(),
            ['component' => $component, 'area' => $area, 'itemid' => $itemid]);

        $handler = \core_customfield\handler::get_handler($params['component'], $params['area'], $params['itemid']);
        self::validate_context($handler->get_configuration_context());
        if (!$handler->can_configure()) {
            throw new moodle_exception('nopermissionconfigure', 'core_customfield');
        }
        $category = $handler->new_category();
        $category->save();
        return $category->get('id');
    }

    /**
     *
     */
    public static function create_category_returns() {
        return new external_value(PARAM_INT, 'Id of the category');
    }

    /**
     * @return external_function_parameters
     */
    public static function drag_and_drop_parameters(): external_function_parameters {
        return new external_function_parameters(
                ['from' => new external_value(PARAM_INT, 'Entry ID to move from', VALUE_REQUIRED),
                 'to'   => new external_value(PARAM_INT, 'Entry ID to move to', VALUE_REQUIRED),
                 'category' => new external_value(PARAM_INT, 'Entry new CategoryId', VALUE_REQUIRED)]
        );
    }

    /**
     * @param int $from
     * @param int $to
     * @param int $category
     * @return bool
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function drag_and_drop(int $from, int $to, int $category) {
        return \core_customfield\field::drag_and_drop($from, $to, $category);
    }

    /**
     *
     */
    public static function drag_and_drop_returns() {
    }

    /**
     * @return external_function_parameters
     */
    public static function drag_and_drop_block_parameters(): external_function_parameters {
        return new external_function_parameters(
                ['from' => new external_value(PARAM_INT, 'Entry ID to move from', VALUE_REQUIRED),
                 'to'   => new external_value(PARAM_INT, 'Entry ID to move to', VALUE_REQUIRED)]
        );
    }

    /**
     * @param int $categoryid
     * @param int $beforeid
     * @return bool
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function drag_and_drop_block(int $categoryid, int $beforeid) {
        // TODO rename to "move_category", VALIDATE ACCESS!!!!!!
        $category = new \core_customfield\category($categoryid);
        $handler = \core_customfield\handler::get_handler_for_category($category);
        self::validate_context($handler->get_configuration_context());
        if (!$handler->can_configure()) {
            throw new moodle_exception('nopermissionconfigure', 'core_customfield');
        }

        return \core_customfield\api::move_category($categoryid, $beforeid);
        // TODO: return api::move_category($category, $to);
    }

    /**
     *
     */
    public static function drag_and_drop_block_returns() {
    }
}
