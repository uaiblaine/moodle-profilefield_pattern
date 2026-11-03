@profilefield @profilefield_pattern
Feature: Pattern user profile field
  In order to collect documents in a known format
  As an administrator
  I need a profile field that masks and validates what users type

  @javascript
  Scenario: An administrator creates a pattern field and its settings come back on edit
    Given I log in as "admin"
    And I navigate to "Users > Accounts > User profile fields" in site administration
    And I click on "Create a new profile field" "link"
    And I click on "Pattern" "link"
    And I set the following fields to these values:
      | Short name | cpf                    |
      | Name       | CPF                    |
      | Format     | CPF                    |
      | Store as   | Typed characters only  |
    When I click on "Save changes" "button"
    Then I should see "CPF"
    And I click on "Edit" "link" in the "CPF" "table_row"
    And the field "Format" matches value "CPF"
    And the field "Store as" matches value "Typed characters only"

  @javascript
  Scenario: A typed CPF is masked, a wrong one is refused and a right one is shown formatted
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | One      | student1@example.com |
    And the following "custom profile fields" exist:
      | datatype | shortname | name | visible | param1 | param2 | param3                                  |
      | pattern  | cpf       | CPF  | 2       | 20     | 14     | {"preset":"cpf","storeformat":"raw"}    |
    And I am on the "student1" "user > editing" page logged in as "admin"
    And I expand all fieldsets
    When I set the field "CPF" to "52998224726"
    Then the field "CPF" matches value "529.982.247-26"
    And I click on "Update profile" "button"
    And I should see "This is not a valid CPF."
    And I set the field "CPF" to "52998224725"
    And I click on "Update profile" "button"
    And I am on the "student1" "user > profile" page
    And I should see "529.982.247-25"
