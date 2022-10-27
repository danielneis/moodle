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
 * Move a content between folders.
 *
 * @package    core_contentbank
 * @copyright  2022 Daniel Neis Araujo <danielneis@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class move_content extends \core_form\dynamic_form {

    /**
     * Add elements to this form.
     */
    public function definition() {
        global $DB;

        $mform = $this->_form;

        $mform->addElement('hidden', 'contextid');
        $mform->setType('contextid', PARAM_INT);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        if ($this->_ajaxformdata['folderid']) {
            $foldername = $DB->get_field('contentbank_folders', 'name', ['id' => $this->_ajaxformdata['folderid']]);
        } else {
            $foldername = get_string('topfolder', 'contentbank');
        }

        $mform->addElement('static', 'currentfolder', get_string('currentfolder', 'core_contentbank', $foldername));
        $mform->setType('name', PARAM_RAW);

        $folders = \core_contentbank\contentbank::get_folders_menu(0, $this->_ajaxformdata['contextid'], [], '');
        $mform->addElement('select', 'folderid', get_string('newfolder', 'core_contentbank'), $folders);
    }

    /**
     * Validate incoming data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
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
        global $DB, $USER;
        if ($data = $this->get_data()) {
            $content = new \stdClass();
            $content->id = $data->id;
            $content->folderid = $data->folderid;
            $content->usermodified = $USER->id;
            $content->timemodified = time();
            $DB->update_record('contentbank_content', $content);
        }
        $url = new moodle_url('/contentbank/view.php', ['id' => $data->id]);
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
            'id' => $this->optional_param('id', 0, PARAM_INT),
            'folderid' => $this->optional_param('folderid', 0, PARAM_INT),
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
            'id' => $this->optional_param('id', 0, PARAM_INT),
            'contextid' => $this->get_context_for_dynamic_submission()->id,
        ];
        return new \moodle_url('/contentbank/view.php', $params);
    }
}

