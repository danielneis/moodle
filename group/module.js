/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
M.core_group = {
    init_index: function(Y, wwwroot, courseid) {
        M.core_group.groupsCombo = new UpdatableGroupsCombo(wwwroot, courseid);
        M.core_group.membersCombo = new UpdatableMembersCombo(wwwroot, courseid);
    },
};
