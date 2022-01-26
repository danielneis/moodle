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

namespace core_contentbank\form;

use moodle_url;

/**
 * Rename folders on content bank form
 *
 * @package    core_contentbank
 * @copyright  2022 Daniel Neis Araujo <danielneis@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rename_folder extends \core_form\dynamic_form {

    /**
     * Add elements to this form.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'folderid');
        $mform->setType('parentid', PARAM_INT);

        $mform->addElement('hidden', 'contextid');
        $mform->setType('contextid', PARAM_INT);

        $mform->addElement('hidden', 'parentid');
        $mform->setType('parentid', PARAM_INT);

        $mform->addElement('text', 'name', get_string('newfoldername', 'core_contentbank'));
        $mform->setType('name', PARAM_RAW);
    }

    /**
     * Validate incoming data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $DB;
        $params = [
            'name' => $data['name'],
            'parent' => $data['parentid'],
            'contextid' => $data['contextid'],
        ];
        if ($DB->record_exists('contentbank_folders', $params)) {
            return ['name' => get_string('duplicatedfoldername', 'contentbank')];
        }
        return [];
    }

    /**
     * Process the form submission, used if form was submitted via AJAX
     *
     * This method can return scalar values or arrays that can be json-encoded, they will be passed to the caller JS.
     *
     * Submission data can be accessed as: $this->get_data()
     *
     * @return mixed
     */
    public function process_dynamic_submission() {
        if ($data = $this->get_data()) {
            $folder = new \stdClass();
            $folder->id = $data->folderid;
            $folder->name = $data->name;
            $folder = new \core_contentbank\folder($folder);
            $folder->update();
        }
        $url = new moodle_url('/contentbank/index.php', ['contextid' => $data->contextid, 'folderid' => $folder->get_id()]);
        return ['returnurl' => $url->out(false)];
    }

    /**
     * Return form context
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): \context {
        $contextid = $this->optional_param('contextid', null, PARAM_INT);
        return \context::instance_by_id($contextid, MUST_EXIST);
    }

    /**
     * Check if current user has access to this form, otherwise throw exception
     *
     * @throws moodle_exception
     */
    protected function check_access_for_dynamic_submission(): void {
        return;
    }

    /**
     * Load in existing data as form defaults.
     */
    public function set_data_for_dynamic_submission(): void {
        $data = (object)[
            'contextid' => $this->optional_param('contextid', 0, PARAM_INT),
            'parentid' => $this->optional_param('parentid', 0, PARAM_INT),
            'folderid' => $this->optional_param('folderid', 0, PARAM_INT),
            'name' => $this->optional_param('foldername', 0, PARAM_TEXT),
        ];
        $this->set_data($data);
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return moodle_url
     */
    public function get_page_url_for_dynamic_submission(): \moodle_url {
        $params = [
            'contextid' => $this->get_context_for_dynamic_submission()->id,
            'folderid' => $this->optional_param('folderid', null, PARAM_INT)
        ];
        return new \moodle_url('/contentbank/index.php', $params);
    }
}
