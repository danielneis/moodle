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
 * Create a folder in content bank.
 *
 * @package    core_contentbank
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../config.php');
require_once("$CFG->dirroot/contentbank/folder_form.php");

require_login();

$context = \context_system::instance();
require_capability('moodle/contentbank:createfolder', $context);

$parentid = optional_param('parent', 0, PARAM_INT);
$returnurl = new \moodle_url('/contentbank/index.php', ['parent' => $parentid]);

$PAGE->set_url('/contentbank/createfolder.php');
$PAGE->set_context($context);
// Make the content bank node active so that it shows up in the navbar and breadcrumbs correctly.
if ($node = $PAGE->navigation->find('contentbank', null)) {
    $node->make_active();
    $PAGE->navbar->add(get_string('createfolder', 'contentbank'));
}

$title = get_string('contentbank');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('standard');
$PAGE->set_pagetype('contentbank');

$mform = new contentbank_folder_form(null, ['parent' => $parentid]);
if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($formdata = $mform->get_data()) {
    require_sesskey();
    $folder = new \core_contentbank\folder($formdata->name, $formdata->parent);

    $returnurl->params(['parent' => $folder->get_id()]);
    redirect($returnurl);
}

echo $OUTPUT->header();
echo $OUTPUT->box_start('generalbox');

$mform->display();

echo $OUTPUT->box_end();
echo $OUTPUT->footer();
