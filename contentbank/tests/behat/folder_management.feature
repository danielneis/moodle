@core @core_contentbank @javascript
Feature: Folder management in the Content Bank
  In order to organize the content in the Content bank
  As a manager
  I need to be able to create folders

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | user1    | User      | 1 | user1@example.com |
    And the following "role assigns" exist:
      | user  | role    | contextlevel | reference |
      | user1 | manager | System       |           |

  Scenario: Managers can create folders in the content bank
    Given I log in as "user1"
    When I am viewing content bank
    And I click on "Add" "link"
    And I click on "New folder" "link"
    And I set the field "New folder" to "This is new"
    And I click on "Add" "button" in the ".modal-dialog" "css_element"
    Then I should see "This is new"

  Scenario: Managers can cancel folder creation process
    Given I log in as "user1"
    When I am viewing content bank
    And I click on "Add" "link"
    And I click on "New folder" "link"
    And I set the field "New folder" to "This is new"
    And I click on "Cancel" "button" in the ".modal-dialog" "css_element"
    Then I should not see "This is new"

  Scenario: Managers can create folders into another folder
    Given the following "contenbank folders" exist:
      | name        | parent |
      | First level | 0      |
    And I log in as "user1"
    When I am viewing content bank
    And I click on "First level" "link"
    And I click on "Add" "link"
    And I click on "New folder" "link"
    And I set the field "New folder" to "Second level"
    And I click on "Add" "button" in the ".modal-dialog" "css_element"
    Then I should see "Second level"
    And I click on "/" "link"
    And I should see "First level"
    And I should not see "Second level"

  Scenario: Can't duplicate folder name at the same level
    Given the following "contenbank folders" exist:
      | name        | parent |
      | First level | 0      |
    And I log in as "user1"
    When I am viewing content bank
    And I click on "Add" "link"
    And I click on "New folder" "link"
    And I set the field "New folder" to "First level"
    And I click on "Add" "button" in the ".modal-dialog" "css_element"
    Then I should see "Folder name exists already"
