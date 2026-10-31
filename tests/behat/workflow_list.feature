@tool @tool_autoflow
Feature: Workflow list
  In order to automate tasks without writing a plugin
  As a manager
  I need to see the workflows on the site

  Scenario: An admin sees the empty state
    Given I log in as "admin"
    When I navigate to "Plugins > Admin tools > Autoflow" in site administration
    Then I should see "Workflows" in the "#region-main" "css_element"
    And I should see "No workflows yet"

  Scenario: A manager without site config can open the page
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | boss     | Mona      | Manager  | boss@example.com     |
    And the following "role assigns" exist:
      | user | role    | contextlevel | reference |
      | boss | manager | System       |           |
    When I am on the "tool_autoflow > workflows" page logged in as "boss"
    Then I should see "No workflows yet"
