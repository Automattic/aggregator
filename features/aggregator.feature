Feature: Aggregator
  In order to use Aggregator
  As a super admin
  I need to be able to manage aggregation jobs

  @javascript
  Scenario: Access the Aggregator admin screen
    Given I am on "/"
    And I am logged into WordPress with username "admin" and password "password"

    Given I am on "/wp-admin/network/settings.php?page=aggregator"
    Then I should see "Aggregator Setup"

  @javascript
  Scenario: Add a new Aggregation job
    Given I am on "/wp-admin/network/settings.php?page=aggregator"
    And I am logged into WordPress with username "admin" and password "password"
    Then I should see "Aggregator Setup"

    When I follow "Add New Job"
    Then I should see "Add New Sync Job"
    Then I select "/" from "Choose the site that will act as the \"portal\" site:"
    And I select "/source/" from "Choose the site that will act as the \"source\" site:"
    And I press "Save & Continue"
    And I wait for "2" seconds
    Then I should see "local.wordpress.dev/source/ to local.wordpress.dev/"

    When I check "cpt_post"
    And I check "taxo_category"
    And I check "taxo_post_tag"
    And I press "Save"
    Then I should see "Aggregator Setup"
    And I should see "Source (local.wordpress.dev)"
    And I should see "1 post types"
    And I should see "2 taxonomies"
    And I should see "0 terms"