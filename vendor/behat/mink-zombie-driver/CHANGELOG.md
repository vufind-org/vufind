1.2.0 / 2014-09-26
==================

BC break:

* Rewrote the driver based on Zombie 2.0 rather than the old 1.x versions
* Changed the behavior of `getValue` for checkboxes according to the BC break in Mink 1.6

New features:

* Added the support of select elements in `setValue`
* Implemented `getOuterHtml`
* Added support for request headers
* Implemented `submitForm`
* Implemented `isSelected`

Bug fixes:

* Fixed the selection of options for multiple selects to ensure the change event is triggered only once
* Fixed the selection of options for radio groups
* Fixed `getValue` for radio groups
* Fixed the retrieval of response headers
* Fixed a leak of outdated references in the node server when changing page
* Fixed the resetting of the driver to reset everything
* Fixed the code to throw exceptions for invalid usages of the driver
* Fixed handling of errors to throw exceptions in the driver rather than crashing the node server
* Fixed `evaluateScript` and `executeScript` to support all syntaxes required by the Mink API
* Fixed `getContent` to return the source of the page without decoding entities
* Fixed the removal of cookies
* Fixed the basic auth implementation

Testing:

* Updated the testsuite to use the new Mink 1.6 driver testsuite
* Added testing on HHVM
