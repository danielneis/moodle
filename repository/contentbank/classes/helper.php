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
 * Content bank files repository helpers.
 *
 * @package    repository_contentbank
 * @copyright  2020 Mihail Geshoski <mihail@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace repository_contentbank;

use repository_contentbank\browser\contentbank_browser;

/**
 * Helper class for content bank files repository.
 *
 * @package    repository_contentbank
 * @copyright  2020 Mihail Geshoski <mihail@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Get the content bank repository browser for a certain context.
     *
     * @param \context $context The context
     * @param int $fodlerid The folder
     * @return \repository_contentbank\browser\contentbank_browser|null The content bank repository browser
     */
    public static function get_contentbank_browser(\context $context, $folderid): ?contentbank_browser {
        switch ($context->contextlevel) {
            case CONTEXT_SYSTEM:
                return new \repository_contentbank\browser\contentbank_browser_context_system($context, $folderid);
            case CONTEXT_COURSECAT:
                return new \repository_contentbank\browser\contentbank_browser_context_coursecat($context, $folderid);
            case CONTEXT_COURSE:
                return new \repository_contentbank\browser\contentbank_browser_context_course($context, $folderid);
        }
        return null;
    }

    /**
     * Create the context folder node.
     *
     * @param string $name The name of the context folder node
     * @param string $path The path to the context folder node
     * @return array The context folder node
     */
    public static function create_context_folder_node(string $name, string $path): array {
        global $OUTPUT;

        return [
            'title' => $name,
            'datemodified' => '',
            'datecreated' => '',
            'path' => $path,
            'thumbnail' => $OUTPUT->image_url(file_folder_icon(90))->out(false),
            'children' => []
        ];
    }

    /**
     * Create the content bank folder node.
     *
     * @param string $folder The folder record
     * @return array The contentbank folder node
     */
    public static function create_contentbank_folder_node(object $folder): array {
        global $OUTPUT;

        return [
            'title' => $folder->name,
            'datemodified' => $folder->timemodified,
            'datecreated' => $folder->timecreated,
            'path' => base64_encode(json_encode(['contextid' => $folder->contextid, 'folderid' => $folder->id])),
            'thumbnail' => $OUTPUT->image_url(file_folder_icon(90))->out(false),
            'children' => []
        ];
    }

    /**
     * Create the content bank content node.
     *
     * @param \core_contentbank\content $content The content bank content
     * @return array|null The content bank content node
     */
    public static function create_contentbank_content_node(\core_contentbank\content $content): ?array {
        global $OUTPUT;
        // Only content files are currently supported, but should be able to create content folder nodes in the future.
        // Early return if the content is not a stored file.
        if (!$file = $content->get_file()) {
            return null;
        }

        $params = [
            'contextid' => $file->get_contextid(),
            'component' => $file->get_component(),
            'filearea'  => $file->get_filearea(),
            'itemid'    => $file->get_itemid(),
            'filepath'  => $file->get_filepath(),
            'filename'  => $file->get_filename()
        ];

        $contenttype = $content->get_content_type_instance();
        $encodedpath = base64_encode(json_encode($params));

        $node = [
            'shorttitle' => $content->get_name(),
            'title' => $file->get_filename(),
            'datemodified' => $file->get_timemodified(),
            'datecreated' => $file->get_timecreated(),
            'author' => $file->get_author(),
            'license' => $file->get_license(),
            'isref' => $file->is_external_file(),
            'size' => $file->get_filesize(),
            'source' => $encodedpath,
            'icon' => $contenttype->get_icon($content),
            'thumbnail' => $contenttype->get_icon($content)
        ];

        if ($file->get_status() == 666) {
            $node['originalmissing'] = true;
        }

        return $node;
    }

    /**
     * Generate a navigation node.
     *
     * @param \context $context The context
     * @return array The navigation node
     */
    public static function create_navigation_node(\context $context): array {
        return [
            'path' => base64_encode(json_encode(['contextid' => $context->id])),
            'name' => $context->get_context_name(false)
        ];
    }

    /**
     * Generate a navigation folder node.
     *
     * @param \context $context The context
     * @param string $name The name of the folder
     * @param int $folderid The id of the folder
     * @return array The navigation node
     */
    public static function create_navigation_folder_node(\context $context, string $name, int $folderid): array {
        return [
            'path' => base64_encode(json_encode(['contextid' => $context->id, 'folderid' => $folderid])),
            'name' => $name
        ];
    }
}
