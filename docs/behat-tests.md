# Behat tests

Behat allows us to write tests in human-readable language that can be parsed by a machine to check the application works as expected.

## Installation and Usage

1. From the plugin directory run `composer install`
1. Download and run the the [Selenium driver](http://www.seleniumhq.org/download/)
1. From the plugin directory run `bin/behat`
1. A firefox window will open and run the tests. Watch the command line output for success/failure.

## Contributing

To contribute tests, first check what contexts are available by running `bin/behat -dl`. Then contribute by adding a new feature file in the `features` directory or editing an existing features file. Best to keep tests split up logically.
 
Note that the `@javascript` line atop each of the features denotes that it should use the Selenium driver. This is preferred over Mink. To make each feature run in isolation, add the `@isolation` keyword after `@javascript`.