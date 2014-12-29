<?php

use Behat\Behat\Context\ClosuredContextInterface,
	Behat\Behat\Context\TranslatedContextInterface,
	Behat\Behat\Context\BehatContext,
	Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode,
	Behat\Gherkin\Node\TableNode;

use Behat\MinkExtension\Context\MinkContext;
use WebDriver\Exception\NoAlertOpenError;

/**
 * Features context.
 */
class FeatureContext extends MinkContext {

	/**
	 * @Given /^I am logged into WordPress with username "([^"]*)" and password "([^"]*)"$/
	 */
	public function WPLogin( $username, $password ) {

		$session = $this->getSession();
		if ( ! $session->isStarted() ) {
			$session->start();
		}

		// Check that the driver is Selenium, not Goutte
		if ( ! is_a( $session->getDriver(), 'Behat\Mink\Driver\Selenium2Driver' ) ) {
			throw new \Exception( "Can't log in unless you're using Selenium driver" );
		}

		// Fill in the WordPress login form and submit
		$session->visit( $session->getCurrentUrl().'/wp-admin' );
		$this->fillField( 'user_login', $username );
		$this->fillField( 'user_pass', $password );
		$this->pressButton( 'Log In' );

		// Check login didn't fail
		$assert = $this->assertSession();
		$assert->pageTextNotContains( 'Invalid username.' );
		$assert->pageTextNotContains( 'ERROR: The password you entered for the' );

		// Check we're on the dashboard
		$assert->pageTextContains( 'Dashboard' );

	}

	/**
	 * @Given /^I wait for "(\d*)" seconds$/
	 */
	public function iWaitForSeconds( $arg1 ) {
		sleep( $arg1 );
	}

	/**
	 * Ensures that the RTE on the post edit screen is (not) selected.
	 *
	 * @param string|null $maybe_not Whether to select, or "not", the RTE
	 *
	 * @Then /^I ensure the editor is (not) the rich text editor$/
	 * @Then /^I ensure the editor is the rich text editor$/
	 *
	 * @throws Exception
	 */
	public function ensureTheEditorIsRTE( $maybe_not = null ) {
		$should_be_RTE = false;
		if ( is_null( $maybe_not ) ) {
			$should_be_RTE = true;
		}
		// Check that the driver is Selenium, not Goutte
		if ( ! is_a($this->getSession()->getDriver(),'Behat\Mink\Driver\Selenium2Driver') ) {
			throw new \Exception( "This step will not work without Selenium" );
		}
		if ( $should_be_RTE ) {
			$this->getSession()->getDriver()->evaluateScript( 'jQuery("#content-tmce").click();' );
		} else {
			$this->getSession()->getDriver()->evaluateScript( 'jQuery("#content-html").click();' );
		}
	}

}
