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
 * Module to manage content bank folder actions, such as create, delete or rename.
 *
 * @module     core_contentbank/create_folder
 * @copyright  2022 Daniel Neis Araujo <danielneis@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';

/**
 * Initialize create new folder form as Modal form.
 *
 * @param {String} elementSelector
 * @param {String} formClass
 * @param {Integer} contextId
 * @param {Integer} parentId
 */
export const initModal = (elementSelector, formClass, contextId, parentId) => {
    const element = document.querySelector(elementSelector);
    element.addEventListener('click', function(e) {
        e.preventDefault();
        const form = new ModalForm({
            formClass,
            args: {
                contextid: contextId,
                parentid: parentId,
            },
            modalConfig: {title: getString('addnewfolder', 'contentbank')},
            returnFocus: e.target,
        });
        form.addEventListener(form.events.FORM_SUBMITTED, (event) => {
            document.location = event.detail.returnurl;
        });
        form.show();
    });
};
