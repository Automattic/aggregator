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
    And I check "taxo_post_tag"
    And I press "Save"
    Then I should see "Aggregator Setup"
    And I should see "Source (local.wordpress.dev)"
    And I should see "1 post types"
    And I should see "2 taxonomies"
    And I should see "0 terms"

  @javascript
  Scenario: Push a post across sites
    Given I am on "/source/wp-admin/post-new.php"
    And I am logged into WordPress with username "admin" and password "password"
    Then I should see "Add New Post"

    # Add a test post to the source blog
    When I fill in "post_title" with "Behat test post for syncing"
    And I ensure the editor is not the rich text editor
    And I fill in "content" with "Foobar"
    And I press "Publish"
    Then I should see "Post published."

    # Check that the post was actually published
    Given I am on "/source"
    Then I should see "Behat test post for syncing"

    # Check that the post was pushed to the portal
    Given I am on "/"
    Then I should see "Behat test post for syncing"

  @javascript
  Scenario: Pushed posts are modified on the portal
    Given I am on "/source/wp-admin"
    And I am logged into WordPress with username "admin" and password "password"
    Given I am on "/source"
    And I follow "Behat test post for syncing"
    And I follow "Edit Post"

    # Change the post title
    And I fill in "post_title" with "Behat test post edited"
    And I press "Update"

    # Check it's changed
    Given I am on "/source"
    Then I should see "Behat test post edited"
    And I should not see "Behat test post for syncing"

    # Check the portal copy has changed
    Given I am on "/"
    Then I should see "Behat test post edited"
    And I should not see "Behat test post for syncing"

  @javascript
  Scenario: Pushed posts are deleted on the portal
    Given I am on "/source/wp-admin"
    And I am logged into WordPress with username "admin" and password "password"
    Given I am on "/source"
    And I follow "Behat test post edited"
    And I follow "Edit Post"

    # Delete the post
    And I wait for "3" seconds
    And I follow "Move to Trash"

    # Check it's deleted
    Given I am on "/source"
    Then I should not see "Behat test post edited"

    # Check the portal copy has been deleted
    Given I am on "/"
    Then I should not see "Behat test post edited"

  @javascript
  Scenario: Edit the aggregation job
    Given I am on "/wp-admin/network/settings.php?page=aggregator"
    And I am logged into WordPress with username "admin" and password "password"
    Then I should see "Aggregator Setup"

    When I follow "Edit Job"
    Then I should see "Edit Sync Job"
    And I should see "local.wordpress.dev/source/ to local.wordpress.dev/"
    And I uncheck "taxo_category"
    And I press "Save"
    Then I should see "Aggregator Setup"