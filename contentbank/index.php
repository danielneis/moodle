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
 * List content in content bank.
 *
 * @package    core_contentbank
 * @copyright  2020 Amaia Anabitarte <amaia@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../config.php');

require_login();

$contextid = optional_param('contextid', \context_system::instance()->id, PARAM_INT);
$search = optional_param('search', '', PARAM_CLEAN);
$context = context::instance_by_id($contextid, MUST_EXIST);

$cb = new \core_contentbank\contentbank();
if (!$cb->is_context_allowed($context)) {
    throw new \moodle_exception('contextnotallowed', 'core_contentbank');
}

$folderid = optional_param('folderid', 0, PARAM_INT);

$breadcrumb = \core_contentbank\contentbank::make_breadcrumb($folderid, $contextid);

if (isset($breadcrumb[0]) && (!$breadcrumb[0]['name'] == 'Professores') &&
     user_has_role_assignment($USER->id, $DB->get_field('role', 'id', ['shortname' => 'editingteacher']))) {
    require_capability('moodle/contentbank:access', $context);
}

$statusmsg = optional_param('statusmsg', '', PARAM_ALPHANUMEXT);
$errormsg = optional_param('errormsg', '', PARAM_ALPHANUMEXT);

$title = get_string('contentbank');
\core_contentbank\helper::get_page_ready($context, $title);
if ($PAGE->course) {
    require_login($PAGE->course->id);
}
$PAGE->set_url('/contentbank/index.php', ['contextid' => $contextid]);
if ($contextid == \context_system::instance()->id) {
    $PAGE->set_context(context_course::instance($contextid));
} else {
    $PAGE->set_context($context);
}

if ($context->contextlevel == CONTEXT_COURSECAT) {
    $PAGE->set_primary_active_tab('home');
}

foreach ($breadcrumb as $bc) {
    $PAGE->navbar->add($bc['name'], $bc['link']);
}

$PAGE->set_title($title);
$PAGE->add_body_class('limitedwidth');
$PAGE->set_pagetype('contentbank');
$PAGE->set_secondary_active_tab('contentbank');

// Create the cog menu with all the secondary actions, such as delete, rename...
// TODO: trocar pelo menu terciário do 4.0
$actionmenu = new action_menu();
$actionmenu->set_menu_left();
if (has_capability('moodle/contentbank:createfolder', $context) ||
    user_has_role_assignment($USER->id, $DB->get_field('role', 'id', ['shortname' => 'editingteacher']))) {
    if ($folderid) {
        $folderrecord = $DB->get_record('contentbank_folders', ['id' => $folderid, 'contextid' => $contextid]);
        $folder = new \core_contentbank\folder($folderrecord);
        $label = get_string('renamefolder', 'core_contentbank');

        $attributes = [
            'data-action' => 'renamefolder',
            'data-contextid' => $contextid,
            'data-folderid' => $folderid,
        ];

        $actionmenu->add_secondary_action(new action_menu_link(
            new moodle_url('#'),
            new pix_icon('i/edit', $label),
            $label,
            false,
            $attributes
        ));

        $PAGE->requires->js_call_amd(
            'core_contentbank/rename_folder',
            'initModal',
            [
                '[data-action="renamefolder"]',
                \core_contentbank\form\rename_folder::class,
                $contextid, $folderrecord->parent, $folderid, $folderrecord->name
            ]);


        if ($folder->is_empty()) {
            $label = get_string('deletefolder', 'core_contentbank');

            $attributes = [
                'data-action' => 'deletefolder',
                'data-contextid' => $contextid,
                'data-parentid' => $folderid,
            ];

            $actionmenu->add_secondary_action(new action_menu_link(
                new moodle_url('#'),
                new pix_icon('i/delete', $label),
                $label,
                false,
                $attributes
            ));

            $PAGE->requires->js_call_amd(
                'core_contentbank/delete_folder',
                'initModal',
                ['[data-action="deletefolder"]', $contextid, $folderid]);
        }
    }
}

if (has_capability('moodle/contentbank:deleteanycontent', $context)) {
    $trashlabel = get_string('trash', 'contentbank');
    $actionmenu->add_secondary_action(new action_menu_link(
        new moodle_url('/contentbank/trash.php'),
        new pix_icon('i/trash', $trashlabel),
        $trashlabel,
        false,
        []
    ));
}

if (has_capability('moodle/contentbank:viewunlistedcontent', $context)) {
    $setdisplay = optional_param('displayunlisted', null, PARAM_INT);
    if (is_null($setdisplay)) {
        $display = get_user_preferences('contentbank_displayunlisted', 1);
    } else {
        set_user_preference('contentbank_displayunlisted', $setdisplay);
        $display = $setdisplay;
    }
    $seturl = new moodle_url('/contentbank/index.php', ['contextid' => $contextid, 'search' => $search, 'folderid' => $folderid]);
    if ($display) {
        $displaylabel = get_string('dontdisplayunlisted', 'contentbank');
        $seturl->param('displayunlisted', 0);
        $icon = 't/show';
    } else {
        $displaylabel = get_string('displayunlisted', 'contentbank');
        $seturl->param('displayunlisted', 1);
        $icon = 't/hide';
    }
    $actionmenu->add_secondary_action(new action_menu_link(
        new moodle_url($seturl),
        new pix_icon($icon, $displaylabel),
        $displaylabel,
        false,
        []
    ));
}

// Add the cog menu to the header.
$PAGE->add_header_action(html_writer::div(
    $OUTPUT->render($actionmenu),
    'd-print-none',
    ['id' => 'region-main-settings-menu']
));

// Get all contents managed by active plugins where the user has permission to render them.
$contenttypes = [];
$enabledcontenttypes = $cb->get_enabled_content_types();
foreach ($enabledcontenttypes as $contenttypename) {
    $contenttypeclass = "\\contenttype_$contenttypename\\contenttype";
    $contenttype = new $contenttypeclass($context);
    if ($contenttype->can_access()) {
        $contenttypes[] = $contenttypename;
    }
}

// Get all folders in this path.
$folders = \core_contentbank\contentbank::get_folders_in_folder($folderid, $contextid);

$foldercontents = $cb->search_contents($search, $contextid, $contenttypes, $folderid);

// Get the toolbar ready.
$toolbar = array();

// Place the Add button in the toolbar.
if (has_capability('moodle/contentbank:useeditor', $context)) {
    // Get the content types for which the user can use an editor.
    $editabletypes = $cb->get_contenttypes_with_capability_feature(\core_contentbank\contenttype::CAN_EDIT, $context);
    if (!empty($editabletypes)) {
        // Editor base URL.
        $editbaseurl = new moodle_url('/contentbank/edit.php', ['contextid' => $contextid]);
        $toolbar[] = [
            'name' => get_string('add'),
            'link' => $editbaseurl, 'dropdown' => true,
            'contenttypes' => $editabletypes,
            'action' => 'add'
        ];
    }
}

if (isset($folderrecord)) {
    $foldersinpath = explode('/', $folderrecord->path);
    $topfolder = $foldersinpath[1];
    if ($DB->get_field('contentbank_folders', 'name', ['id' => $topfolder]) == 'Professores') {
        $systemctx = \context_system::instance();
        $canupload =
            user_has_role_assignment($USER->id, $DB->get_field('role', 'id', ['shortname' => 'p_professor']), $systemctx->id) ||
            user_has_role_assignment($USER->id, $DB->get_field('role', 'id', ['shortname' => 'p_materiais']), $systemctx->id) ||
            user_has_role_assignment($USER->id, $DB->get_field('role', 'id', ['shortname' => 'p_administrador']), $systemctx->id) ||
            user_has_role_assignment($USER->id, $DB->get_field('role', 'id', ['shortname' => 'p_colabobrador']), $systemctx->id) ||
            has_capability('moodle/contentbank:upload', $context);
    } else {
        $canupload = has_capability('moodle/contentbank:upload', $context);
    }
} else {
    $canupload = has_capability('moodle/contentbank:upload', $context);
}

// Place the Upload button in the toolbar.
if ($canupload) {
    // Don' show upload button if there's no plugin to support any file extension.
    $accepted = $cb->get_supported_extensions_as_string($context);
    if (!empty($accepted)) {
        $importurl = new moodle_url('/contentbank/index.php', ['contextid' => $contextid, 'folder' => $folderid]);
        $toolbar[] = [
            'name' => get_string('upload', 'contentbank'),
            'link' => $importurl->out(false),
            'icon' => 'i/upload',
            'action' => 'upload',
        ];
        $PAGE->requires->js_call_amd(
            'core_contentbank/upload',
            'initModal',
            ['[data-action=upload]', \core_contentbank\form\upload_files::class, $contextid, 0, $folderid]
        );
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title, 2);
echo $OUTPUT->box_start('generalbox');

// If needed, display notifications.
if ($errormsg !== '' && get_string_manager()->string_exists($errormsg, 'core_contentbank')) {
    $errormsg = get_string($errormsg, 'core_contentbank');
    echo $OUTPUT->notification($errormsg);
} else if ($statusmsg !== '' && get_string_manager()->string_exists($statusmsg, 'core_contentbank')) {
    $statusmsg = get_string($statusmsg, 'core_contentbank');
    echo $OUTPUT->notification($statusmsg, 'notifysuccess');
}

// Render the contentbank contents.
$folder = new \core_contentbank\output\bankcontent($foldercontents, $toolbar, $context, $cb, $folderid, $folders);
echo $OUTPUT->render($folder);

echo $OUTPUT->box_end();
echo $OUTPUT->footer();
