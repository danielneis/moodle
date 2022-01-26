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
 * @module     core_contentbank/folders
 * @copyright  2022 Daniel Neis Araujo <danielneis@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Ajax from 'core/ajax';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import Url from 'core/url';
import {get_string as getString} from 'core/str';

/**
 * Initialize delete folder form as SAVE_CANCEL form.
 *
 * @param {String} elementSelector
 * @param {Integer} contextId
 * @param {Integer} folderId
 */
export const initModal = (elementSelector, contextId, folderId) => {
    const element = document.querySelector(elementSelector);
    element.addEventListener('click', function(e) {
        e.preventDefault();
        ModalFactory.create({
            type: ModalFactory.types.SAVE_CANCEL,
            title: getString('deletefolder', 'contentbank'),
            body:  getString('deletefolderconfirm', 'contentbank'),
        })
        .then(function(modal) {
            modal.setSaveButtonText(getString('delete'));
            var root = modal.getRoot();
            root.on(ModalEvents.save, function() {
                var request = {
                    methodname: 'core_contentbank_delete_folder',
                    args: {
                        contextid: contextId,
                        folderid: folderId
                    }
                };
                Ajax.call([request])[0].then(function(data) {
                    let params = {
                        folderid: data.parentid,
                        contextid: contextId
                    };
                    window.location.href = Url.relativeUrl('contentbank/index.php', params, false);
                }).fail(Notification.exception);
            });
            modal.show();
        });
    });
};
