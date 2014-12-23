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