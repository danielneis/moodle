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
 * @module     core_contentbank/set_folder_visibility
 * @copyright  2023 Daniel Neis Araujo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'jquery',
    'core/ajax',
    'core/notification',
    'core/str',
    'core/url',
],
function($, Ajax, Notification, Str, Url) {

    /**
     * SetFolderVisibility class.
     */
    var SetFolderVisibility = function() {
        this.registerEvents();
    };

    /**
     * Register event listeners.
     */
    SetFolderVisibility.prototype.registerEvents = function() {
        $('[data-action="setfoldervisibility"]').click(function(e) {
            e.preventDefault();

            var folderid = $(this).data('folderid');
            var contextid = $(this).data('contextid');
            var visibility = $(this).data('visibility');

            setFolderVisibility(folderid, contextid, visibility);
        });
    };

    /**
     * Set folder visibility in the content bank.
     *
     * @param {int} folderid The folder to modify
     * @param {int} contextid The context the folder belongs to
     * @param {int} visibility The new visibility value
     */
    function setFolderVisibility(folderid, contextid, visibility) {
        var request = {
            methodname: 'core_contentbank_set_folder_visibility',
            args: {
                folderid: folderid,
                contextid: contextid,
                visibility: visibility
            }
        };
        var requestType = 'success';
        Ajax.call([request])[0].then(function(data) {
            if (data.result) {
                return 'foldervisibilitychanged';
            }
            requestType = 'error';
            return data.warnings[0].message;

        }).then(function(message) {
            var params = null;
            if (requestType == 'success') {
                params = {
                    folderid: folderid,
                    contextid: contextid,
                    statusmsg: message
                };
                // Redirect to the folder view page and display the message as a notification.
                window.location.href = Url.relativeUrl('contentbank/index.php', params, false);
            } else {
                // Fetch error notifications.
                Notification.addNotification({
                    message: message,
                    type: 'error'
                });
                Notification.fetchNotifications();
            }
            return;
        }).catch(Notification.exception);
    }

    return /** @alias module:core_contentbank/set_folder_visibility */ {
        // Public variables and functions.

        /**
         * Initialise the contentbank set folder visibility.
         *
         * @method init
         * @return {SetFolderVisibility}
         */
        'init': function() {
            return new SetFolderVisibility();
        }
    };
});
