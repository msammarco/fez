Feature: Pid security

    @now
  Scenario: I login as admin and set a Collection security to allow inheriting pids to view, then turn it off and check pids can no longer be viewed
    Given I login as administrator
    And I follow "Browse"
    And I follow "Create New Community"
    And I fill in "Name" with "Test Community Security to be changed after pid created"
    And I select "Fedora Collection Display Version Dublin Core 1.0" from "XSD Display Document Types"
    And I fill in "Keyword 1" with "automated testing"
    And I press "Publish"
    And I press "Create"
    And I fill in "Title" with "Test Collection Security to be changed after pid created"
    And I select "Journal Article Version MODS 1.0" from "XSD Display Document Types"
    And I fill in "Keyword 1" with "automated testing"
    And I press "Publish"
    And I press "Create"
    And I fill in "Title" with "Test Pid Security to be changed after pid created"
    And I fill in "Journal name" with "Security Test Journal name"
    And I fill in "Author 1" with "Security Test Author name"
    And I select "Article" from "Sub-type"
    And I check "Copyright Agreement"
    #this is problemmatic getting a generic label
    And I select "2010" from "xsd_display_fields[6386][Year]"
    And I press "Publish"
    And I follow "Logout"
    And I fill in "Search Entry" with "title:(\"Test Community Security to be changed after pid created\")"
    And I press "search_entry_submit"
    Then I should not see "No records could be found"
    And I fill in "Search Entry" with "title:(\"Test Collection Security to be changed after pid created\")"
    And I press "search_entry_submit"
    Then I should not see "No records could be found"
    And I fill in "Search Entry" with "title:(\"Test Pid Security to be changed after pid created\")"
    And I press "search_entry_submit"
    Then I should not see "No records could be found"
    Given I login as administrator
    And I fill in "Search Entry" with "title:(\"Test Community Security to be changed after pid created\")"
    And I press "search_entry_submit"
    When I follow "Edit Security for Selected Community"
    Given I choose the "Unit Publication Officers" group for the "Lister" role
    Given I choose the "Unit Publication Officers" group for the "Viewer" role
    And I press "Save"
    And I fill in "Search Entry" with "title:(\"Test Pid Security to be changed after pid created\")"
    And I press "search_entry_submit"
    Then I should not see "No records could be found"
    And I fill in "Search Entry" with "title:(\"Test Collection Security to be changed after pid created\")"
    And I press "search_entry_submit"
    Then I should not see "No records could be found"
    And I fill in "Search Entry" with "title:(\"Test Community Security to be changed after pid created\")"
    And I press "search_entry_submit"
    And I follow "Test Community Security to be changed after pid created"
    And I follow "Test Collection Security to be changed after pid created"
    And I follow "Test Pid Security to be changed after pid created"
    And I follow "Logout"
    When I move backward one page
    Then I should see "You must first login to access this resource"
    When I move backward one page
    Then I should see "You must first login to access this resource"
    When I move backward one page
    Then I should see "You must first login to access this resource"
    And I fill in "Search Entry" with "title:(\"Test Community Security to be changed after pid created\")"
    And I press "search_entry_submit"
    Then I should see "No records could be found"
    And I fill in "Search Entry" with "title:(\"Test Collection Security to be changed after pid created\")"
    And I press "search_entry_submit"
    Then I should see "No records could be found"
    And I fill in "Search Entry" with "title:(\"Test Pid Security to be changed after pid created\")"
    And I press "search_entry_submit"
    Then I should see "No records could be found"

  @destructive @purge
  Scenario: Delete old pids
    Given I login as administrator
    And I fill in "Search Entry" with "title:(\"Test Community Security to be changed after pid created\")"
    And I press "search_entry_submit"
    And I wait for "3" seconds
    And I press "Select All"
    And I turn off waiting checks
    And I press "Delete"
    And I confirm the popup
    And I fill "automated test data cleanup" in popup
    And I confirm the popup
    And I fill in "Search Entry" with "title:(\"Test Collection Security to be changed after pid created\")"
    And I press "search_entry_submit"
    And I wait for "3" seconds
    And I press "Select All"
    And I turn off waiting checks
    And I press "Delete"
    And I confirm the popup
    And I fill "automated test data cleanup" in popup
    And I confirm the popup
    And I fill in "Search Entry" with "title:(\"Test Pid Security to be changed after pid created\")"
    And I press "search_entry_submit"
    And I wait for "3" seconds
    And I press "Select All"
    And I turn off waiting checks
    And I press "Delete"
    And I confirm the popup
    And I fill "automated test data cleanup" in popup
    And I confirm the popup