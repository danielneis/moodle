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
 * Content bank folder management class
 *
 * @package    core_contentbank
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_contentbank;

use stdClass;

/**
 * Content bank manager class
 *
 * @package    core_contentbank
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class folder {

    /** @var stdClass $content The object to manage this content. **/
    protected $content  = null;

    /**
     * Content bank folder constructor
     *
     * @param stdClass $folder     A folder record.
     * @return folder
     */
    public function __construct(stdClass $folder) {
        $this->content = $folder;
    }

    /**
     * Fills content bank folders table with appropiate information.
     *
     * @param stdClass $folder  An optional folder record compatible object (default null)
     * @return stdClass         Object with folder information or null if a folder with given name and parentid exists.
     */
    public static function create_folder(stdClass $folder = null): ?folder {
        global $USER, $DB;

        $record = new stdClass();
        $record->name = $folder->name ?? '';
        $record->contextid = $folder->contextid ?? \context_system::instance()->id;
        $record->parent = $folder->parent ?? 0;
        $record->usercreated = $folder->usercreated ?? $USER->id;
        $record->timecreated = time();
        $record->usermodified = $record->usercreated;
        $record->timemodified = $record->timecreated;

        if ($DB->get_record('contentbank_folders', ['name' => $record->name, 'parent' => $record->parent])) {
            return null;
        }

        if ($record->parent) {
            $parent = $DB->get_record('contentbank_folders', array('id' => $record->parent), '*', MUST_EXIST);
            $record->path = $parent->path;
            $record->depth = $parent->depth + 1;
        } else {
            $record->path = '';
            $record->depth = 0;
        }

        $record->id = $DB->insert_record('contentbank_folders', $record);
        if ($record->id) {
            $record->path = $record->path.'/'.$record->id;
            $DB->update_record('contentbank_folders', $record);

            return new folder($record);
        }
        return null;
    }

    /**
     * Updates content_bank folder with information in $this properties.
     *
     * @return boolean  True if the folder has been succesfully updated. False otherwise.
     */
    public function update(): bool {
        global $USER, $DB;

        $folder = $this->content;
        $folder->usermodified = $USER->id;
        $folder->timemodified = time();

        return $DB->update_record('contentbank_folders', $folder);
    }

    /**
     * Deletes a folder and return the id of it's parent
     *
     * @param int $folderid The ID of the folder to delete
     * @param int $contextid The contextid of the folder to delete
     * @return int ID of parent folder
     */
    public static function delete_folder(int $folderid, int $contextid): int {
        global $DB;
        $parentid = $DB->get_field('contentbank_folders', 'parent', ['id' => $folderid, 'contextid' => $contextid]);
        $DB->delete_records('contentbank_folders', ['id' => $folderid, 'contextid' => $contextid]);
        $sql = 'UPDATE {contentbank_content} SET folderid = 0 WHERE folderid = ?';
        $DB->execute($sql, [$folderid]);
        return $parentid;
    }

    /**
     * Returns the folder ID.
     *
     * @return int   The folder ID.
     */
    public function get_id(): int {
        return $this->content->id;
    }

    /**
     * Returns the name of the folder.
     *
     * @return string   The name of the folder.
     */
    public function get_name(): string {
        return $this->content->name;
    }

    /**
     * Set the name of the folder.
     *
     * @param string $name  The name of the folder.
     * @return boolean  True if the folder has been succesfully updated. False otherwise.
     */
    public function set_name(string $name): bool {
        $this->content->name = $name;
        return $this->update();
    }

    /**
     * Returns the parentid of the folder.
     *
     * @return int   The parentid of the folder.
     */
    public function get_parent_id(): int {
        return $this->content->parent;
    }

    /**
     * Returns the path of the folder.
     *
     * @return string   The path of the folder.
     */
    public function get_path(): string {
        return $this->content->path;
    }

    /**
     * Set the path of the folder.
     *
     * @param string $path  New path of the folder.
     * @return boolean  True if the folder has been succesfully updated. False otherwise.
     */
    public function set_path(string $path): bool {
        $this->content->path = $path;
        return $this->update();
    }

    /**
     * Check if folder is empty (there is no folder or content inside it).
     *
     * @return boolean  True if the folder is empty, false otherwise.
     */
    public function is_empty(): bool {
        global $DB;
        $sql = "SELECT COUNT(*) as folderscount
                  FROM {contentbank_folders} cbf
                 WHERE cbf.contextid = :contextid
                   AND " . $DB->sql_like('cbf.path', ':path', false, false);
        $params = [
            'path' => $DB->sql_like_escape($this->content->path) . '/%',
            'contextid' => $this->content->contextid
        ];
        $count = $DB->get_records_sql($sql, $params);
        $count = array_pop($count);
        if ($count->folderscount) {
            return false;
        }
        return empty($DB->record_exists('contentbank_content', ['folderid' => $this->get_id(), 'deleted' => 0]));
    }

    /**
     * Returns the HTML code to render the icon for content bank contents.
     *
     * @return string           HTML code to render the icon
     * @throws \coding_exception if not loaded.
     */
    public static function get_icon(): string {
        global $OUTPUT;
        return $OUTPUT->image_url('f/folder-64',  'moodle')->out(false);
    }
}
