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
 * Search area for Moodle Content Bank.
 *
 * @package    core_contentbank
 * @copyright  2022 Daniel Neis Araujo <danielneis@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_contentbank\search;

/**
 * Search area for Moodle Content Bank.
 *
 * @package    core_contentbank
 * @copyright  2022 Daniel Neis Araujo <danielneis@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content extends \core_search\base {

    /**
     * The context levels the search implementation is working on.
     *
     * @var array
     */
    protected static $levels = [CONTEXT_SYSTEM, CONTEXT_COURSECAT, CONTEXT_COURSE];

    /**
     * Returns recordset containing required data for indexing courses.
     *
     * @param int $modifiedfrom timestamp
     * @param \context|null $context Restriction context
     * @return \moodle_recordset|null Recordset or null if no change possible
     */
    public function get_document_recordset($modifiedfrom = 0, \context $context = null) {
        global $DB;

        $sql = "SELECT c.*
                  FROM {contentbank_content} c ";

        $where = " WHERE c.timemodified >= ?";
        $params = [$modifiedfrom];

        if (!is_null($context)) {

            switch ($context->contextlevel) {
                case CONTEXT_COURSE:
                    $where .= " AND c.contextid = ?";
                    $params[] = $context->id;
                    break;

                case CONTEXT_COURSECAT:
                    $path = $DB->get_field('context', 'path', ['id' => $context->id]);
                    $params[] = $path . '%';

                    $pathmatch = $DB->sql_like('ctx.path', '?');

                    $sql .= " JOIN {context} ctx
                                 ON ctx.id = c.contextid ";
                    $where .= " AND {$pathmatch}";

                    break;

                case CONTEXT_BLOCK:
                case CONTEXT_USER:
                case CONTEXT_MODULE:
                   return [];
                   break;

                case CONTEXT_SYSTEM:
                    break;

                default:
                    throw new \coding_exception('Unexpected contextlevel: ' . $context->contextlevel);
            }
        }

        $sql .= $where . " ORDER BY c.timemodified ASC";

        return $DB->get_recordset_sql($sql, $params);
    }

    /**
     * Returns the document associated with this content.
     *
     * @param stdClass $record
     * @param array    $options
     * @return \core_search\document
     */
    public function get_document($record, $options = array()) {
        global $SITE;
        try {
            $context = \context::instance_by_id($record->contextid);
        } catch (\moodle_exception $ex) {
            // Notify it as we run here as admin, we should see everything.
            debugging('Error retrieving ' . $this->areaid . ' ' . $record->id . ' document, not all required data is available: ' .
                $ex->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
        // Prepare associative array with data from DB.
        $doc = \core_search\document_factory::instance($record->id, $this->componentname, $this->areaname);
        $doc->set('title', content_to_text($record->name, false));
        $doc->set('content', content_to_text($record->name, false));
        $doc->set('contextid', $context->id);
        if ($coursecontext = $context->get_course_context(false)) {
            $doc->set('courseid', $coursecontext->instanceid);
        } else {
            $doc->set('courseid', $SITE->id);
        }
        $doc->set('owneruserid', \core_search\manager::NO_OWNER_ID);
        $doc->set('modified', $record->timemodified);

        // Check if this document should be considered new.
        if (isset($options['lastindexedtime']) && $options['lastindexedtime'] < $record->timecreated) {
            // If the document was created after the last index time, it must be new.
            $doc->set_is_new(true);
        }

        return $doc;
    }

    /**
     * Whether the user can access the document or not.
     *
     * @param int $id The course instance id.
     * @return int
     */
    public function check_access($id) {
        global $DB, $USER;
        if (!$record = $DB->get_record('contentbank_content', ['id' => $id])) {
            return \core_search\manager::ACCESS_DELETED;
        }

        $systemctx = \context_system::instance();
        $canaccess =
            user_has_role_assignment($USER->id, $DB->get_field('role', 'id', ['shortname' => 'p_professor']), $systemctx->id) ||
            user_has_role_assignment($USER->id, $DB->get_field('role', 'id', ['shortname' => 'p_materiais']), $systemctx->id) ||
            user_has_role_assignment($USER->id, $DB->get_field('role', 'id', ['shortname' => 'p_administrador']), $systemctx->id) ||
            user_has_role_assignment($USER->id, $DB->get_field('role', 'id', ['shortname' => 'p_colaborador']), $systemctx->id) ||
            is_siteadmin();
        if ($canaccess) {
            return \core_search\manager::ACCESS_GRANTED;
        }
        return \core_search\manager::ACCESS_DENIED;
    }

    /**
     * Link to the course.
     *
     * @param \core_search\document $doc
     * @return \moodle_url
     */
    public function get_doc_url(\core_search\document $doc) {
        return $this->get_context_url($doc);
    }

    /**
     * Link to the course.
     *
     * @param \core_search\document $doc
     * @return \moodle_url
     */
    public function get_context_url(\core_search\document $doc) {
        return new \moodle_url('/contentbank/view.php', array('id' => $doc->get('itemid')));
    }

    /**
     * Returns true if this area uses file indexing.
     *
     * @return bool
     */
    public function uses_file_indexing() {
        return true;
    }

    /**
     * Return the context info required to index files for
     * this search area.
     *
     * Should be overridden by each search area.
     *
     * @return array
     */
    public function get_search_fileareas() {
        $fileareas = array(
        );

        return $fileareas;
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
        return new \core_search\document_icon('i/contentbank');
    }
}
