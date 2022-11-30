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
 * Test for Content bank folder class.
 *
 * @package    core_contentbank
 * @category   test
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Test for Content bank folder class.
 *
 * @package    core_contentbank
 * @category   test
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_contentbank_folder_testcase extends advanced_testcase {

    /**
     * Test create_folder() with empty parentid.
     */
    public function test_create_root_folder() {
        $this->resetAfterTest();

        $record = new stdClass();
        $record->name = 'New folder';
        $folder = \core_contentbank\folder::create_folder($record);

        $this->assertEquals('New folder', $folder->get_name());
        $this->assertEquals(0, $folder->get_parent_id());
    }

    /**
     * Test create_folder() with non empty parentid.
     */
    public function test_create_folder_into_another_folder() {
        $this->resetAfterTest();

        $record = new stdClass();
        $record->name = 'First level';
        $folder = \core_contentbank\folder::create_folder($record);

        $subrecord = new stdClass();
        $subrecord->name = 'Second level';
        $subrecord->parent = $folder->get_id();
        $subfolder = \core_contentbank\folder::create_folder($subrecord);

        $this->assertEquals('Second level', $subfolder->get_name());
        $this->assertEquals($folder->get_id(), $subfolder->get_parent_id());
    }

    /**
     * Test create_folder() with an existing name at same level.
     */
    public function test_cant_create_duplicated_name_folder() {
        $this->resetAfterTest();

        $record = new stdClass();
        $record->name = 'First level';
        $folder = \core_contentbank\folder::create_folder($record);

        $duplicated = new stdClass();
        $duplicated->name = 'First level';
        $newfolder = \core_contentbank\folder::create_folder($duplicated);

        $this->assertEmpty($newfolder);
    }

    /**
     * Test create_folder() with an existing name at different level.
     */
    public function test_can_create_duplicated_name_folder_at_different_level() {
        $this->resetAfterTest();

        $record = new stdClass();
        $record->name = 'First level';
        $folder = \core_contentbank\folder::create_folder($record);

        $duplicated = new stdClass();
        $duplicated->name = 'First level';
        $duplicated->parent = $folder->get_id();
        $newfolder = \core_contentbank\folder::create_folder($duplicated);

        $this->assertEquals('First level', $newfolder->get_name());
        $this->assertEquals($folder->get_id(), $newfolder->get_parent_id());
    }
}
