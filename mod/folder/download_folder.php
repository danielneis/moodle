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
 * Folder download
 *
 * @package   mod_folder
 * @copyright 2015 Andrew Hancox <andrewdchancox@googlemail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . "/../../config.php");

$id = required_param('id', PARAM_INT);  // Course module ID.
$cm = get_coursemodule_from_id('folder', $id, 0, true, MUST_EXIST);

$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/folder:view', $context);

$coursecontext = $context->get_course_context(false);

$folder = $DB->get_record('folder', array('id' => $cm->instance), '*', MUST_EXIST);

$downloadable = folder_archive_available($folder, $cm);
if (!$downloadable) {
    print_error('cannotdownloaddir', 'repository');
}

$fs = get_file_storage();
$files = $fs->get_area_files($context->id, 'mod_folder', 'content');
if (empty($files)) {
    print_error('cannotdownloaddir', 'repository');
}

// Log zip as downloaded.
folder_downloaded($folder, $course, $cm, $context);

// Close the session.
\core\session\manager::write_close();

$filename = shorten_filename(clean_filename($folder->name . "-" . date("Ymd")) . ".zip");
$zipwriter = \core_files\archive_writer::get_stream_writer($filename, \core_files\archive_writer::ZIP_WRITER);

foreach ($files as $file) {
    if ($file->is_directory()) {
        continue;
    }
    $pathinzip = $file->get_filepath() . $file->get_filename();

    $sql = "SELECT f.*
              FROM {files} f
              WHERE f.contenthash = ?
                AND f.component = ?
                AND f.filearea = ?
                AND f.filename != ?
              LIMIT 1";
    $params = [$file->get_contenthash(), 'contentbank', 'public', '.'];
    if ($contentbankfile = $DB->get_record_sql($sql, $params)) {
        $stored_file = $fs->get_file($contentbankfile->contextid,
            'contentbank', 'public', $contentbankfile->itemid, $contentbankfile->filepath, $contentbankfile->filename);
        if ($stored_file && !$stored_file->is_directory()) {

            $filename = $stored_file->get_filename();
            $originalfilename = $filename;
            if ((strpos(strtolower($filename), '.odp') !== false) ||
                (strpos(strtolower($filename), '.ppt') !== false) ||
                (strpos(strtolower($filename), '.doc') !== false)) {

                $converter = new \core_files\converter();
                $conversion = $converter->start_conversion($stored_file, 'pdf', true);
                if (!$conversion || !$stored_file = $conversion->get_destfile()) {
                    throw new moodle_exception('convertererror', 'contenttype_document');
                }
                $filenamearray = explode('.', $filename);
                $filename = $filenamearray[0] . '.pdf';

            }
            if (strpos(strtolower($filename), '.pdf') !== false) {
                try {
                    require_once($CFG->dirroot . '/contentbank/contenttype/document/lib.php');
                    $pdf = contenttype_document_process_pdf($stored_file, $coursecontext, $contentbankfile->itemid, $originalfilename);
                    \core\session\manager::write_close(); // Unlock session during file serving.
                    $zipwriter->add_file_from_string($file->get_filepath() . $filename, $pdf->Output('S', $filename, true));
                } catch (Exception $e) {
                    $zipwriter->add_file_from_stored_file($pathinzip, $file);
                }
            }
        }
    } else {
        $zipwriter->add_file_from_stored_file($pathinzip, $file);
    }
}

// Finish the archive.
$zipwriter->finish();
exit();
