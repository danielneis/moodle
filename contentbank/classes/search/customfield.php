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
 * Search area for content bank custom fields.
 *
 * @package    core_contentbank
 * @copyright  2022 Daniel Neis Araujo <danielneis@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_contentbank\search;

use core_contentbank\customfield\content_handler;
use core_customfield\data_controller;
use core_customfield\field_controller;

defined('MOODLE_INTERNAL') || die();

/**
 * Search area for content bank custom fields.
 *
 * @package    core_contentbank
 * @copyright  2022 Daniel Neis Araujo <danielneis@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class customfield extends \core_search\base {

    /**
     * Custom fields are indexed at course context.
     *
     * @var array
     */
    protected static $levels = [CONTEXT_SYSTEM, CONTEXT_COURSECAT, CONTEXT_COURSE];

    /**
     * Returns recordset containing required data for indexing
     * contentbank custom fields.
     *
     * @param int $modifiedfrom timestamp
     * @param \context|null $context Restriction context
     * @return \moodle_recordset|null Recordset or null if no change possible
     */
    public function get_document_recordset($modifiedfrom = 0, \context $context = null) {
        global $DB;

        $where = '';
        $params = [];
        $contextjoin = '';
        $contextparams = [];
        if ($context && $context->contextlevel) {
            list ($contextjoin, $contextparams) = $this->get_course_level_context_restriction_sql($context, 'c', SQL_PARAMS_NAMED);
        }

        $fields = content_handler::create()->get_fields();
        if (!$fields) {
            $fields = array();
        }
        list($fieldsql, $fieldparam) = $DB->get_in_or_equal(array_keys($fields), SQL_PARAMS_NAMED, 'fld', true, 0);

        $sql = "SELECT d.*
                  FROM {customfield_data} d
                  JOIN {contentbank_content} c
                    ON c.id = d.instanceid
                  JOIN {context} cnt
                    ON cnt.id = d.contextid
           {$contextjoin}
                 WHERE d.timemodified >= :modifiedfrom
                   AND d.fieldid {$fieldsql}
              ORDER BY d.timemodified ASC";
        return $DB->get_recordset_sql($sql , array_merge($contextparams,
            ['modifiedfrom' => $modifiedfrom], $fieldparam));
    }

    /**
     * Returns the document associated with this section.
     *
     * @param \stdClass $record
     * @param array $options
     * @return \core_search\document|bool
     */
    public function get_document($record, $options = array()) {
        global $SITE;

        $handler = content_handler::create();
        $field = $handler->get_fields()[$record->fieldid];
        $data = data_controller::create(0, $record, $field);

        $context = \context::instance_by_id($record->contextid);
        if ($course = $context->get_course_context(false)) {
            $courseid = $course->instanceid;
        } else {
            $courseid = $SITE->id;
        }

        // Prepare associative array with data from DB.
        $doc = \core_search\document_factory::instance($record->id, $this->componentname, $this->areaname);
        $doc->set('title', content_to_text($field->get('name'), false));
        $doc->set('content', content_to_text($data->export_value(), FORMAT_HTML));
        $doc->set('contextid', $record->contextid);
        $doc->set('courseid', $courseid);
        $doc->set('itemid', $record->instanceid);
        $doc->set('owneruserid', \core_search\manager::NO_OWNER_ID);
        $doc->set('modified', $record->timemodified);

        // Check if this document should be considered new.
        if (isset($options['lastindexedtime']) && ($options['lastindexedtime'] < $record->timecreated)) {
            // If the document was created after the last index time, it must be new.
            $doc->set_is_new(true);
        }

        return $doc;
    }

    /**
     * Whether the user can access the document or not.
     *
     * @param int $id The custom field data ID
     * @return int
     */
    public function check_access($id) {
        global $DB;

        $coursesql = '
            SELECT c.*
              FROM {contentbank_content} c
             WHERE c.id = :id';

        $record = $DB->get_record_sql($coursesql, ['id' => $id]);
        $managerclass = "\\{$record->contenttype}\\content";
        $content = new $managerclass($record);

        if ($content->is_view_allowed()) {
            if (has_capability('moodle/contentbank:configurecustomfields', \context::instance_by_id($record->contextid))) {
                return \core_search\manager::ACCESS_GRANTED;
            }
        }
        return \core_search\manager::ACCESS_DENIED;
    }

    /**
     * Link to content bank.
     *
     * @param \core_search\document $doc
     * @return \moodle_url
     */
    public function get_doc_url(\core_search\document $doc) {
        return new \moodle_url('/contentbank/view.php', ['id' => $doc->get('itemid')]);
    }

    /**
     * Link to content bank.
     *
     * @param \core_search\document $doc
     * @return \moodle_url
     */
    public function get_context_url(\core_search\document $doc) {
        return new \moodle_url('/contentbank/view.php', ['id' => $doc->get('itemid')]);
    }

    /**
     * Returns the moodle component name.
     *
     * It might be the plugin name (whole frankenstyle name) or the core subsystem name.
     *
     * @return string
     */
    public function get_component_name() {
        return 'core_contentbank';
    }

    /**
     * Returns an icon instance for the document.
     *
     * @param \core_search\document $doc
     * @return \core_search\document_icon
     */
    public function get_doc_icon(\core_search\document $doc) : \core_search\document_icon {
        return new \core_search\document_icon('i/customfield');
    }

    /**
     * Returns a list of category names associated with the area.
     *
     * @return array
     */
    public function get_category_names() {
        return [
            \core_search\manager::SEARCH_AREA_CATEGORY_COURSES,
            \core_search\manager::SEARCH_AREA_CATEGORY_OTHER
        ];
    }
}
