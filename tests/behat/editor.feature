@tool @tool_autoflow
Feature: Workflow editor
  In order to build automations without code
  As an admin
  I need an editor that everyone can use, including with a keyboard and a screen reader

  @javascript @accessibility
  Scenario: The editor meets accessibility standards with a node selected
    Given the following "tool_autoflow > workflows" exist:
      | name          | message  | enabled |
      | Morning check | All good | 1       |
    And I log in as "admin"
    And I navigate to "Plugins > Admin tools > Autoflow" in site administration
    When I click on "Morning check" "link" in the "Morning check" "table_row"
    And I wait until ".tool_autoflow-toolbar" "css_element" exists
    Then the field "Runs as" matches value "Admin User"
    And the page should meet accessibility standards
    And I click on "Write to run log" "button" in the ".tool_autoflow-palette" "css_element"
    And the page should meet accessibility standards
