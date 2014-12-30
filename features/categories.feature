Feature: Aggregator Category Syncing
  In order to use Aggregator to sync based on categor
  As a super admin
  I need to be able to use a full range of category-based rules

  @javascript
  Scenario: Add a new job to sync the "Category One" category
    Given I am on "/"
    And I am logged into WordPress with username "admin" and password "password"

    Given I am on "/wp-admin/network/settings.php?page=aggregator"
    Then I should see "Aggregator Setup"

    # Add a new job
    When I follow "Add New Job"
    Then I should see "Add New Sync Job"
    Then I select "/" from "Choose the site that will act as the \"portal\" site:"
    And I select "/source/" from "Choose the site that will act as the \"source\" site:"
    And I press "Save & Continue"
    And I wait for "2" seconds
    Then I should see "local.wordpress.dev/source/ to local.wordpress.dev/"

    # Configure the new job with post types and taxonomies
    When I check "cpt_post"
    And I check "taxo_category"
    And I wait for "1" seconds
    And I follow "+ Add New Category"
    And I fill in "newcategory" with "Category One"
    And I press "Add New Category"
    And I wait for "3" seconds
    And I press "Save"
    Then I should see "Aggregator Setup"
    And I should see "Source (local.wordpress.dev)"
    And I should see "1 post types"
    And I should see "1 taxonomies"
    And I should see "1 terms"