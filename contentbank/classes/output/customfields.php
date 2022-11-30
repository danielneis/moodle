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
 * View document details.
 *
 * @package   contenttype_document
 * @copyright 2021 Daniel Neis Araujo <daniel@adapta.online>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_contentbank\output;

defined('MOODLE_INTERNAL') || die();

use moodle_url;
use renderable;
use templatable;
use renderer_base;
use core_contentbank\content;

/**
 * Content bank Custom Fields renderable class.
 *
 * @package   core_contentbank
 * @copyright 2022 Daniel Neis Araujo <daniel@adapta.online>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class customfields implements renderable, templatable {

    /**
     * Constructor.
     */
    public function __construct(content $content) {
        $this->content = $content;
    }

    public function export_for_template(renderer_base $output) {
        global $DB;

        $context = new \stdClass();

        $context->url = $this->content->get_file_url();
        $context->name = $this->content->get_name();

        $handler = \core_contentbank\customfield\content_handler::create();
        $customfields = $handler->get_instance_data($this->content->get_id());
        $context->data = $handler->display_custom_fields_data($customfields);

        return $context;
    }
}
