@core @core_contentbank @core_h5p @contentbank_h5p @_file_upload @javascript
Feature: Content bank custom fields
  In order to add/edit custom fields for content
  As a user
  I need to be able to access the custom fields

  Background:
    Given the following "blocks" exist:
      | blockname     | contextlevel | reference | pagetypepattern | defaultregion |
      | private_files | System       | 1         | my-index        | side-post     |
    Given the following "custom field categories" exist:
      | name              | component        | area    | itemid |
      | Category for test | core_contentbank | content | 0      |
    And I log in as "admin"
    And I am on site homepage
    And I turn editing mode on
    And the following config values are set as admin:
      | unaddableblocks | | theme_boost|
    And I add the "Navigation" block if not present
    And I configure the "Navigation" block
    And I set the following fields to these values:
      | Page contexts | Display throughout the entire site |
    And I press "Save changes"
    And I navigate to "Plugins > Custom fields for Content bank" in site administration
    And I click on "Add a new custom field" "link"
    And I click on "Short text" "link"
    And I set the following fields to these values:
      | Name       | Test field |
      | Short name | testfield  |
      | Visible to | Everyone   |
    And I click on "Save changes" "button" in the "Adding a new Short text" "dialogue"

  Scenario: Users can edit customfields
    Given I follow "Dashboard"
    And I follow "Manage private files..."
    And I upload "h5p/tests/fixtures/filltheblanks.h5p" file to "Files" filemanager
    And I click on "Save changes" "button"
    And I click on "Site pages" "list_item" in the "Navigation" "block"
    And I click on "Content bank" "link" in the "Navigation" "block"
    And I click on "Upload" "link"
    And I click on "Choose a file..." "button"
    And I click on "Private files" "link" in the ".fp-repo-area" "css_element"
    And I click on "filltheblanks.h5p" "link"
    And I click on "Select this file" "button"
    And I click on "Save changes" "button"
    And I click on "Edit" "link"
    And I set the following fields to these values:
      | Test field | My test value |
    And I click on "Save" "button"
    Then I should see "Test field: My test value"
