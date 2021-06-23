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
 * Manage the group list.
 *
 * @module     core_group/managegrouplist
 * @copyright  2021 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const checkSelectedGroups = (groupList, deleteGroupSelector, modifyMembersSelector) => {
    const isAnySelected = Array.from(groupList.options).some(element => element.selected);

    const setDeleteButtonState = state => {
        document.querySelector(deleteGroupSelector).disabled = !state;
    };

    const setModifyButtonState = state => {
        document.querySelector(modifyMembersSelector).disabled = !state;
    };

    if (!isAnySelected) {
        // If no group is selected, then disable both buttons.
        setDeleteButtonState(false);
        setModifyButtonState(false);

        return;
    }

    // Check whether any selected element is deletable.
    const isDeletable = !Array.from(groupList.options).some(element => {
        if (!element.selected) {
            return false;
        }
        return !parseInt(element.dataset.isDeletable);
    });

    // Check whether any selected element is editable.
    const isEditable = !Array.from(groupList.options).some(element => {
        if (!element.selected) {
            return false;
        }
        return !parseInt(element.dataset.isEditable);
    });

    setDeleteButtonState(isDeletable);
    setModifyButtonState(isEditable);
};

export const init = (groupListSelector, deleteGroupSelector, modifyMembersSelector) => {
    const groupList = document.querySelector(groupListSelector);

    checkSelectedGroups(groupList, deleteGroupSelector, modifyMembersSelector);
    groupList.addEventListener('change', () => {
        checkSelectedGroups(groupList, deleteGroupSelector, modifyMembersSelector);
    });
};
