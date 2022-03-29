<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Block adapta renderer.
 *
 * @package     block_adapta
 * @copyright   2022 Daniel Neis Araujo <daniel@adapta.online>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_adapta;

defined('MOODLE_INTERNAL') || die();

class content {

    public function __construct($id) {
        $this->id = $id;
    }

    public function get() {
        global $DB;
        return $DB->get_record('block_instances', ['id' => $this->id]);
    }

    public function get_all() {
        global $DB, $USER;
        return $DB->get_records('user_preferences', ['userid' => $USER->id], 'id');
    }

    public function get_filtered() {
        global $DB;
        $sql = "SELECT b.*
                  FROM {block_instances} b
                 WHERE b.id > :minid
                   AND b.id <= :maxid";
        return $DB->get_records_sql($sql, ['minid' => 10, 'maxid' => 100]);
    }

    public function insert() {
        global $DB, $USER;

        $record = new \stdclass();
        $record->userid = $USER->id;
        $record->name = 'block : ' . $this->id . ' ' . (string)rand();
        $record->value = 'hahahahahaha';

        return $DB->insert_record('user_preferences', $record);
    }
}
