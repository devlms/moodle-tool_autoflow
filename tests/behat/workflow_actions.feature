@tool @tool_autoflow
Feature: Run, inspect and delete workflows from the list
  In order to manage automations
  As an admin
  I need to run a workflow, read its history and delete it

  Background:
    Given the following "tool_autoflow > workflows" exist:
      | name          | message           | enabled |
      | Morning check | All good at {{trigger.userid}} | 1 |
    And I log in as "admin"
    And I navigate to "Plugins > Admin tools > Autoflow" in site administration

  Scenario: Run a workflow now and read the run log
    When I click on "Run now" "link" in the "Morning check" "table_row"
    Then I should see "Run queued. It starts with the next cron run."
    And I run all adhoc tasks
    And I am on the "tool_autoflow > workflows" page
    And I click on "Runs" "link" in the "Morning check" "table_row"
    And I should see "Run history: Morning check"
    And I should see "Succeeded"
    And I should see "All good at"

  Scenario: Delete a workflow after confirming
    When I click on "Delete" "link" in the "Morning check" "table_row"
    And I should see "Delete the workflow \"Morning check\" and its run history?"
    And I press "Continue"
    Then I should not see "Morning check"
    And I should see "No workflows yet"

  Scenario: The list shows the trigger and when it last ran
    Then I should see "Started by hand" in the "Morning check" "table_row"
    And I should see "Never" in the "Morning check" "table_row"
    And I should see "Admin User" in the "Morning check" "table_row"
