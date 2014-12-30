Feature: Aggregator Tag Syncing
  In order to use Aggregator to sync based on tags
  As a super admin
  I need to be able to use a full range of tag-based rules

  @javascript
  Scenario: Add a new job to sync the "Tag One" category
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
    And I check "taxo_post_tag"
    And I wait for "1" seconds
    And I fill in "new-tag-post_tag" with "Tag One"
    And I press "Save"
    Then I should see "Aggregator Setup"
    And I should see "Source (local.wordpress.dev)"
    And I should see "1 post types"
    And I should see "1 taxonomies"
    And I should see "1 terms"

  @javascript
  Scenario: Push a tag-based post across sites
    Given I am on "/source/wp-admin/post-new.php"
    And I am logged into WordPress with username "admin" and password "password"
    Then I should see "Add New Post"

    # Add a test post to the source blog
    When I fill in "post_title" with "Tag One test post for syncing"
    And I ensure the editor is not the rich text editor
    And I fill in "content" with "Foobar"
    And I fill in "new-tag-post_tag" with "Tag One"
    And I press "Publish"
    Then I should see "Post published."

    # Check that the post was actually published
    Given I am on "/source"
    Then I should see "Tag One test post for syncing"

    # Check that the post was pushed to the portal
    Given I am on "/"
    Then I should see "Tag One test post for syncing"

  @javascript
  Scenario: Don't push a non-tag-based post across sites
    Given I am on "/source/wp-admin/post-new.php"
    And I am logged into WordPress with username "admin" and password "password"
    Then I should see "Add New Post"

    # Add a test post to the source blog
    When I fill in "post_title" with "No tag test post for syncing"
    And I ensure the editor is not the rich text editor
    And I fill in "content" with "Foobar"
    And I press "Publish"
    Then I should see "Post published."

    # Check that the post was actually published
    Given I am on "/source"
    Then I should see "No tag test post for syncing"

    # Check that the post was pushed to the portal
    Given I am on "/"
    Then I should not see "No tag test post for syncing"