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
 * View page.
 *
 * @package     block_adapta
 * @copyright   2022 Daniel Neis Araujo <daniel@adapta.online>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$id = required_param('id', PARAM_INT);
$action = optional_param('action', 'view', PARAM_TEXT);

$context = context_block::instance($id);
$url = new moodle_url('/blocks/adapta/view.php', ['id' => $id]);

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'block_adapta') . ' - ' . $SITE->fullname);
$PAGE->set_heading(get_string('heading', 'block_adapta'));

$indexurl = new moodle_url('/blocks/adapta/index.php');
$PAGE->navbar->add(get_string('navbaritem', 'block_adapta'), $url);

$output = $PAGE->get_renderer('block_adapta');

$actionmenu = new action_menu();
$actionmenu->set_alignment(action_menu::TR, action_menu::BR);
$actionmenu->add_secondary_action(new action_menu_link(
    new moodle_url('#'),
    new pix_icon('t/add', get_string('home')),
    get_string('home'),
    false,
    ['data-action' => 'changesomething'] 
));

$PAGE->add_header_action(html_writer::div(
    $output->render($actionmenu),
    'd-print-none',
    ['id' => 'region-main-settings-menu']
));
//$PAGE->requires->js_call_amd('block_adapta/content', 'init', ['region-main-settings-menu']);

$content = new block_adapta\content($id);

$renderable = new block_adapta\output\content($content);

echo $output->header(),
     $output->render($renderable),
     $output->footer();
