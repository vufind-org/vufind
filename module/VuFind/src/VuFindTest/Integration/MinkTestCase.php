<?php

/**
 * Abstract base class for PHPUnit test cases using Mink.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Integration;

use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Element\Element;
use DMore\ChromeDriver\ChromeDriver;
use ReflectionException;
use Symfony\Component\Yaml\Yaml;
use VuFind\Config\PathResolver;
use VuFind\Config\Writer as ConfigWriter;

use function call_user_func;
use function floatval;
use function in_array;
use function intval;
use function is_string;
use function strlen;

/**
 * Abstract base class for PHPUnit test cases using Mink.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
abstract class MinkTestCase extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\LiveDetectionTrait;
    use \VuFindTest\Feature\PathResolverTrait;
    use \VuFindTest\Feature\RemoteCoverageTrait;

    public const DEFAULT_TIMEOUT = 5000;

    /**
     * Modified configurations
     *
     * @var array
     */
    protected $modifiedConfigs = [];

    /**
     * Modified yaml configurations
     *
     * @var array
     */
    protected $modifiedYamlConfigs = [];

    /**
     * Mink session
     *
     * @var Session
     */
    protected $session;

    /**
     * Configuration file path resolver
     *
     * @var PathResolver
     */
    protected $pathResolver;

    /**
     * Selector for an open button group dropdown menu
     *
     * First for Bootstrap 3, second for Bootstrap 5
     *
     * @var string
     */
    protected $btnGroupDropdownMenuSelector = '.btn-group.open .dropdown-menu, .btn-group .dropdown-menu.show';

    /**
     * Selector for first item in a dropdown menu
     *
     * First for Bootstrap 3, second for Bootstrap 5
     *
     * @var string
     */
    protected $firstOpenDropdownMenuItemSelector
        = '.mainbody .open .dropdown-menu li:nth-child(2) a, .mainbody .dropdown-menu.show li:nth-child(2) a';

    /**
     * Selector for popover content
     *
     * First for Bootstrap 3, second for Bootstrap 5
     *
     * @var string
     */
    protected $popoverContentSelector = '.popover-body, .popover-content';

    /**
     * Selector for an open modal dialog
     *
     * First for Bootstrap 3, second for Bootstrap 5
     *
     * @var string
     */
    protected $openModalSelector = '#modal.in, #modal.show';

    /**
     * Selector for a button link in an open modal dialog
     *
     * First for Bootstrap 3, second for Bootstrap 5
     *
     * @var string
     */
    protected $openModalButtonLinkSelector = '#modal.in a.btn, #modal.show a.btn';

    /**
     * Selector for a username field in open modal dialog
     *
     * First for Bootstrap 3, second for Bootstrap 5
     *
     * @var string
     */
    protected $openModalUsernameFieldSelector = '#modal.in [name="username"], #modal.show [name="username"]';

    /**
     * Selector for next page link
     *
     * First for Bootstrap 3, second for Bootstrap 5
     *
     * @var string
     */
    protected $pageNextSelector = 'a.page-next, .page-next a';

    /**
     * Selector for previous page link
     *
     * First for Bootstrap 3, second for Bootstrap 5
     *
     * @var string
     */
    protected $pagePrevSelector = 'a.page-prev, .page-prev a';

    /**
     * Selector for active record tab
     *
     * First for Bootstrap 3, second for Bootstrap 5
     *
     * @var string
     */
    protected $activeRecordTabSelector = 'li.record-tab.active, li.record-tab a.active';

    /**
     * Get name of the current test
     *
     * @return string
     */
    protected function getTestName(): string
    {
        return $this::class . '::' . $this->name();
    }

    /**
     * Reconfigure VuFind for the current test.
     *
     * @param array $configs Array of settings to change. Top-level keys correspond
     * with config filenames (i.e. use 'config' for config.ini, etc.); within each
     * file's array, top-level key is config section. Within each section's array
     * are key-value configuration pairs.
     * @param array $replace Array of config files to completely override (as
     * opposed to modifying); if a config file from $configs is included in this
     * array, the $configs setting will be used as the entire configuration, and
     * the defaults from the config/vufind directory will be ignored.
     *
     * @return void
     */
    protected function changeConfigs($configs, $replace = [])
    {
        foreach ($configs as $file => $settings) {
            $this->changeConfigFile($file, $settings, in_array($file, $replace));
        }
    }

    /**
     * Reconfigure VuFind for the current test.
     *
     * @param array $configs Array of settings to change. Top-level keys correspond
     * with yaml config filenames (i.e. use 'searchspecs' for searchspecs.yaml,
     * etc.);
     * @param array $replace Array of config files to completely override (as
     * opposed to modifying); if a config file from $configs is included in this
     * array, the $configs setting will be used as the entire configuration, and
     * the defaults from the config/vufind directory will be ignored.
     *
     * @return void
     */
    protected function changeYamlConfigs($configs, $replace = [])
    {
        foreach ($configs as $file => $settings) {
            $this->changeYamlConfigFile($file, $settings, in_array($file, $replace));
        }
    }

    /**
     * Support method for changeConfig; act on a single file.
     *
     * @param string $configName Configuration to modify.
     * @param array  $settings   Settings to change.
     * @param bool   $replace    Should we replace the existing config entirely
     * (as opposed to extending it with new settings)?
     *
     * @return void
     */
    protected function changeConfigFile(string $configName, array $settings, bool $replace = false): void
    {
        $file = $configName . '.ini';
        $local = $this->pathResolver->getLocalConfigPath($file, null, true);
        if (!in_array($configName, $this->modifiedConfigs)) {
            if (file_exists($local)) {
                // File exists? Make a backup!
                copy($local, $local . '.bak');
            } else {
                // File doesn't exist? Make a baseline version.
                copy($this->pathResolver->getBaseConfigPath($file), $local);
            }

            $this->modifiedConfigs[] = $configName;
        }
        $this->writeConfigFile($local, $settings, $replace);
    }

    /**
     * Write settings to a file.
     *
     * @param string $path     Path of file to modify.
     * @param array  $settings Settings to change.
     * @param bool   $replace  Should we replace the existing config entirely
     * (as opposed to extending it with new settings)?
     *
     * @return void
     */
    protected function writeConfigFile(string $path, array $settings, bool $replace = false): void
    {
        // If we're replacing the existing file, wipe it out now:
        if ($replace) {
            file_put_contents($path, '');
        }

        $writer = new ConfigWriter($path);
        foreach ($settings as $section => $contents) {
            foreach ($contents as $key => $value) {
                $writer->set($section, $key, $value);
            }
        }
        $writer->save();
    }

    /**
     * Support method for changeYamlConfig; act on a single file.
     *
     * @param string $configName Configuration to modify.
     * @param array  $settings   Settings to change.
     * @param bool   $replace    Should we replace the existing config entirely
     * (as opposed to extending it with new settings)?
     *
     * @return void
     */
    protected function changeYamlConfigFile($configName, $settings, $replace = false)
    {
        $file = $configName . '.yaml';
        $local = $this->pathResolver->getLocalConfigPath($file, null, true);
        if (!in_array($configName, $this->modifiedYamlConfigs)) {
            if (file_exists($local)) {
                // File exists? Make a backup!
                copy($local, $local . '.bak');
            } else {
                // File doesn't exist? Make a baseline version.
                copy($this->pathResolver->getBaseConfigPath($file), $local);
            }

            $this->modifiedYamlConfigs[] = $configName;
        }

        // Read the original file, modify and write it out:
        $config = $replace ? [] : Yaml::parseFile($local);
        $config = array_replace_recursive($config, $settings);
        file_put_contents($local, Yaml::dump($config));
    }

    /**
     * Sleep if necessary.
     *
     * @param int $secs Seconds to sleep
     *
     * @return void
     */
    protected function snooze($secs = 1)
    {
        $snoozeMultiplier = $this->getSnoozeMultiplier();
        if ($snoozeMultiplier <= 0) {
            $snoozeMultiplier = 1;
        }
        usleep(1000000 * $secs * $snoozeMultiplier);
    }

    /**
     * Get the snooze multiplier.
     *
     * @return float
     */
    protected function getSnoozeMultiplier(): float
    {
        return floatval(getenv('VUFIND_SNOOZE_MULTIPLIER'));
    }

    /**
     * Get the default timeout in milliseconds
     *
     * @return int
     */
    protected function getDefaultTimeout(): int
    {
        return intval(
            getenv('VUFIND_DEFAULT_TEST_TIMEOUT') ?: self::DEFAULT_TIMEOUT
        );
    }

    /**
     * Test an element for visibility.
     *
     * @param Element $element Element to test
     *
     * @return bool
     */
    protected function checkVisibility(Element $element)
    {
        return $element->isVisible();
    }

    /**
     * Get the Mink driver, initializing it if necessary.
     *
     * @return Selenium2Driver
     */
    protected function getMinkDriver()
    {
        $driver = getenv('VUFIND_MINK_DRIVER') ?? 'selenium';
        if ($driver === 'chrome') {
            return new ChromeDriver('http://localhost:9222', null, 'data:;');
        }
        $browser = getenv('VUFIND_SELENIUM_BROWSER') ?? 'firefox';
        return new Selenium2Driver($browser);
    }

    /**
     * Get a Mink session.
     *
     * @return Session
     */
    protected function getMinkSession()
    {
        if (empty($this->session)) {
            $this->session = new Session($this->getMinkDriver());
            if ($coverageDir = $this->getRemoteCoverageDirectory()) {
                $this->session->setRemoteCoverageConfig(
                    $this->getTestName(),
                    $coverageDir
                );
            }
            $this->session->start();
        }
        return $this->session;
    }

    /**
     * Shut down the Mink session.
     *
     * @return void
     */
    protected function stopMinkSession()
    {
        if (!empty($this->session)) {
            $this->session->stop();
            $this->session = null;
        }
    }

    /**
     * Get base URL of running VuFind instance.
     *
     * @param string $path Relative path to add to base URL.
     *
     * @return string
     */
    protected function getVuFindUrl($path = '')
    {
        $base = getenv('VUFIND_URL');
        if (empty($base)) {
            $base = 'http://localhost/vufind';
        }
        return $base . $path;
    }

    /**
     * Load the Search/Home page as a foundation for searching.
     *
     * @param ?Session $session Mink session (will be automatically established if not provided).
     *
     * @return Element
     */
    protected function getSearchHomePage(?Session $session = null): Element
    {
        $session ??= $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Home');
        return $session->getPage();
    }

    /**
     * Get query string for the current page
     *
     * @param bool $excludeSid Whether to remove any sid from the query string
     *
     * @return string
     */
    protected function getCurrentQueryString(bool $excludeSid = false): string
    {
        return str_replace(
            ['%5B', '%5D', '%7C'],
            ['[', ']', '|'],
            parse_url(
                $excludeSid ? $this->getCurrentUrlWithoutSid()
                    : $this->getMinkSession()->getCurrentUrl(),
                PHP_URL_QUERY
            )
        );
    }

    /**
     * Get current URL without any sid parameter in the query string
     *
     * @return string
     */
    protected function getCurrentUrlWithoutSid(): string
    {
        $this->getMinkSession();
        $url = $this->getMinkSession()->getCurrentUrl();
        $url = preg_replace('/([&?])sid=[^&]*&?/', '$1', $url);
        $url = rtrim($url, '?&');
        return $url;
    }

    /**
     * Restore configurations to the state they were in prior to a call to
     * changeConfig().
     *
     * @return void
     */
    protected function restoreConfigs()
    {
        $configs = [
            '.ini' => $this->modifiedConfigs,
            '.yaml' => $this->modifiedYamlConfigs,
        ];
        foreach ($configs as $extension => $files) {
            foreach ($files as $current) {
                $file = $current . $extension;
                $local = $this->pathResolver->getLocalConfigPath($file, null, true);
                $backup = $local . '.bak';

                // Do we have a backup? If so, restore from it; otherwise, just
                // delete the local file, as it did not previously exist:
                unlink($local);
                if (file_exists($backup)) {
                    rename($backup, $local);
                }
            }
        }
        $this->modifiedConfigs = [];
        $this->modifiedYamlConfigs = [];
    }

    /**
     * Wait for an element to exist, then retrieve it.
     *
     * @param Element $page     Page element
     * @param string  $selector CSS selector
     * @param int     $timeout  Wait timeout (in ms)
     * @param int     $index    Index of the element (0-based)
     *
     * @return mixed
     */
    protected function findCss(
        Element $page,
        $selector,
        $timeout = null,
        $index = 0
    ) {
        $timeout ??= $this->getDefaultTimeout();
        $session = $this->getMinkSession();
        $session->wait(
            $timeout,
            "document.querySelectorAll('$selector').length > $index"
        );
        $results = $page->findAll('css', $selector);
        $this->assertIsArray($results, "Selector not found: $selector");
        $result = $results[$index] ?? null;
        $this->assertIsObject(
            $result,
            "Element not found: $selector index $index"
        );
        return $result;
    }

    /**
     * Wait for a JavaScript statement to result in true.
     *
     * Includes a check for $ to be available to make sure jQuery has been loaded.
     *
     * @param string $statement JavaScript statement to evaluate
     * @param int    $timeout   Wait timeout (in ms)
     *
     * @return mixed
     */
    protected function waitStatement($statement, $timeout = null)
    {
        $timeout ??= $this->getDefaultTimeout();
        $session = $this->getMinkSession();
        $this->assertTrue(
            $session->wait(
                $timeout,
                "(typeof $ !== 'undefined') && ($statement)"
            ),
            "Statement '$statement'"
        );
    }

    /**
     * Wait for an element to NOT exist.
     *
     * @param Element $page     Page element
     * @param string  $selector CSS selector
     * @param int     $timeout  Wait timeout (in ms)
     * @param int     $index    Index of the element (0-based)
     *
     * @return void
     */
    protected function unFindCss(
        Element $page,
        $selector,
        $timeout = null,
        $index = 0
    ) {
        $timeout ??= $this->getDefaultTimeout();
        $startTime = microtime(true);
        $exception = null;
        while ((microtime(true) - $startTime) * 1000 <= $timeout) {
            try {
                $elements = $page->findAll('css', $selector);
                if (!isset($elements[$index])) {
                    // Assert so that this method can be the only check in a test
                    // without it being marked as risky with the message
                    // "This test did not perform any assertions". Also makes this
                    // check count as an assertion in test statistics.
                    $this->assertNull(null);
                    return;
                }
            } catch (\Exception $e) {
                // This may happen e.g. if the page is reloaded right in the middle
                // due to an event. Store the exception and throw later if we don't
                // succeed with retries:
                $exception ??= $e;
            }
            usleep(50000);
        }
        if (null !== $exception) {
            throw $exception;
        }

        throw new \Exception("Selector '$selector' remains accessible");
    }

    /**
     * Click on a CSS element.
     *
     * @param Element $page     Page element
     * @param string  $selector CSS selector
     * @param int     $timeout  Wait timeout (in ms)
     * @param int     $index    Index of the element (0-based)
     *
     * @return mixed
     */
    protected function clickCss(
        Element $page,
        $selector,
        $timeout = null,
        $index = 0
    ) {
        $maxTries = 3;
        for ($tries = 1; $tries <= $maxTries; $tries++) {
            try {
                $result = $this->findCss($page, $selector, $timeout, $index);
                $result->click();
                return $result;
            } catch (\Exception $e) {
                // This may happen e.g. if the page is reloaded right in the middle
                // due to an event. Snooze and retry unless this is the last loop:
                if ($tries === $maxTries) {
                    throw $e;
                }
                $this->snooze();
            }
        }
        throw new \Exception('Unexpected state reached.');
    }

    /**
     * Set a value within an element selected via CSS; retry if set fails
     * due to browser bugs.
     *
     * @param Element $page        Page element
     * @param string  $selector    CSS selector
     * @param string  $value       Value to set
     * @param int     $timeout     Wait timeout for CSS selection (in ms)
     * @param int     $retries     Retry count for set loop
     * @param bool    $verifyValue Whether to verify that the value was written
     * @param bool    $reFocus     Whether to focus the element when done setting the value
     *
     * @return mixed
     */
    protected function findCssAndSetValue(
        Element $page,
        $selector,
        $value,
        $timeout = null,
        $retries = 6,
        $verifyValue = true,
        $reFocus = false
    ) {
        $timeout ??= $this->getDefaultTimeout();

        // Workaround for Chromedriver bug; sometimes setting a value
        // doesn't work on the first try.
        for ($i = 1; $i <= $retries; $i++) {
            try {
                $field = $this->findCss($page, $selector, $timeout, 0);
                $field->setValue($value);
                // Did it work? If so, we're done and can leave....
                if (
                    !$verifyValue
                    || $field->getValue() === $value
                ) {
                    if ($reFocus) {
                        $field->focus();
                    }
                    return;
                }

                $this->logWarning(
                    'RETRY setValue after failure in ' . $this->getTestName()
                    . " (try $i)."
                );
            } catch (\Exception $e) {
                $this->logWarning(
                    'RETRY setValue after exception in ' . $this->getTestName()
                    . " (try $i): " . (string)$e
                );
            }

            $this->snooze();
        }

        throw new \Exception('Failed to set value after ' . $retries . ' attempts.');
    }

    /**
     * Get text of an element selected via CSS; retry if it fails due to DOM change.
     *
     * @param Element $page     Page element
     * @param string  $selector CSS selector
     * @param int     $timeout  Wait timeout for CSS selection (in ms)
     * @param int     $index    Index of the element (0-based)
     * @param int     $retries  Retry count for set loop
     *
     * @return string
     */
    protected function findCssAndGetText(
        Element $page,
        $selector,
        $timeout = null,
        $index = 0,
        $retries = 6
    ) {
        return $this->findCssAndCallMethod($page, $selector, 'getText', $timeout, $index, $retries);
    }

    /**
     * Get value of an element selected via CSS; retry if it fails due to DOM change.
     *
     * @param Element $page     Page element
     * @param string  $selector CSS selector
     * @param int     $timeout  Wait timeout for CSS selection (in ms)
     * @param int     $index    Index of the element (0-based)
     * @param int     $retries  Retry count for set loop
     *
     * @return string
     */
    protected function findCssAndGetValue(
        Element $page,
        $selector,
        $timeout = null,
        $index = 0,
        $retries = 6
    ) {
        return $this->findCssAndCallMethod($page, $selector, 'getValue', $timeout, $index, $retries);
    }

    /**
     * Get text of an element selected via CSS; retry if it fails due to DOM change.
     *
     * @param Element $page     Page element
     * @param string  $selector CSS selector
     * @param int     $timeout  Wait timeout for CSS selection (in ms)
     * @param int     $index    Index of the element (0-based)
     * @param int     $retries  Retry count for set loop
     *
     * @return string
     */
    protected function findCssAndGetHtml(
        Element $page,
        $selector,
        $timeout = null,
        $index = 0,
        $retries = 6
    ) {
        return $this->findCssAndCallMethod($page, $selector, 'getHtml', $timeout, $index, $retries);
    }

    /**
     * Return value of a method of an element selected via CSS; retry if it fails due to DOM change.
     *
     * @param Element         $page     Page element
     * @param string          $selector CSS selector
     * @param string|callable $method   Node's method to call (string) or callable that gets the node as parameter
     * @param int             $timeout  Wait timeout for CSS selection (in ms)
     * @param int             $index    Index of the element (0-based)
     * @param int             $retries  Retry count for set loop
     *
     * @return string
     */
    protected function findCssAndCallMethod(
        Element $page,
        $selector,
        $method,
        $timeout = null,
        $index = 0,
        $retries = 6,
    ) {
        $timeout ??= $this->getDefaultTimeout();

        for ($i = 1; $i <= $retries; $i++) {
            try {
                $element = $this->findCss($page, $selector, $timeout, $index);
                return is_string($method) ? call_user_func([$element, $method]) : $method($element);
            } catch (\Exception $e) {
                $this->logWarning(
                    'RETRY findCssAndGetText after exception in ' . $this->getTestName()
                    . " (try $i): " . (string)$e
                );
            }

            $this->snooze();
        }

        throw new \Exception("Failed to call $method on '$selector' after $retries attempts.");
    }

    /**
     * Retrieve a link and assert that it exists before returning it.
     *
     * @param Element $page Page element
     * @param string  $text Link text to match
     *
     * @return mixed
     */
    protected function findAndAssertLink(Element $page, $text)
    {
        $link = $page->findLink($text);
        $this->assertIsObject($link);
        return $link;
    }

    /**
     * Check whether an element containing the specified text exists.
     *
     * @param Element $page     Page element
     * @param string  $selector CSS selector
     * @param string  $text     Expected text
     *
     * @return bool
     */
    protected function hasElementsMatchingText(Element $page, $selector, $text)
    {
        foreach ($page->findAll('css', $selector) as $current) {
            if ($text === $current->getText()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check that a field content is valid (does not have the :invalid pseudo class).
     *
     * @param Element $page     Page element (not currently used)
     * @param string  $selector CSS selector
     *
     * @return void
     */
    protected function checkFieldIsValid(Element $page, string $selector): void
    {
        $session = $this->getMinkSession();
        $session->wait(
            $this->getDefaultTimeout(),
            "document.querySelector('$selector:invalid') === null"
        );
    }

    /**
     * Check that a field content is invalid (has the :invalid pseudo class).
     *
     * @param Element $page     Page element (not currently used)
     * @param string  $selector CSS selector
     *
     * @return void
     */
    protected function checkFieldIsInvalid(Element $page, string $selector): void
    {
        $session = $this->getMinkSession();
        $session->wait(
            $this->getDefaultTimeout(),
            "document.querySelector('$selector:invalid') !== null"
        );
    }

    /**
     * Wait for a callback to return the expected value
     *
     * @param mixed    $expected    Expected value
     * @param callable $callback    Callback used to get the results
     * @param callable $compareFunc Callback used to compare the results
     * @param callable $assertion   Assertion to make
     * @param int      $timeout     Wait timeout (in ms)
     *
     * @return void
     */
    protected function assertWithTimeout(
        $expected,
        callable $callback,
        callable $compareFunc,
        callable $assertion,
        int $timeout = null
    ) {
        $timeout ??= $this->getDefaultTimeout();
        $result = null;
        $startTime = microtime(true);
        $exception = null;
        while ((microtime(true) - $startTime) * 1000 <= $timeout) {
            try {
                $result = $callback();
                if (call_user_func($compareFunc, $expected, $result)) {
                    // Ignore any previous exception since the callback succeeded eventually:
                    $exception = null;
                    break;
                }
            } catch (\Exception $e) {
                // Defer throwing the exception:
                $exception = $e;
            }
            usleep(100000);
        }
        if ($exception) {
            throw $exception;
        }
        call_user_func($assertion, $expected, $result);
    }

    /**
     * Wait for a callback to return the expected value
     *
     * @param mixed    $expected Expected value
     * @param callable $callback Callback
     * @param int      $timeout  Wait timeout (in ms)
     *
     * @return void
     */
    protected function assertEqualsWithTimeout(
        $expected,
        callable $callback,
        int $timeout = null
    ) {
        $this->assertWithTimeout(
            $expected,
            $callback,
            function ($expected, $result): bool {
                return $expected === $result;
            },
            [$this, 'assertEquals'],
            $timeout
        );
    }

    /**
     * Wait for a callback to return a string containing the expected value
     *
     * @param string   $expected Expected value
     * @param callable $callback Callback
     * @param int      $timeout  Wait timeout (in ms)
     *
     * @return void
     */
    protected function assertStringContainsStringWithTimeout(
        string $expected,
        callable $callback,
        int $timeout = null
    ) {
        $this->assertWithTimeout(
            $expected,
            $callback,
            function (string $expected, string $result): bool {
                return str_contains($result, $expected);
            },
            [$this, 'assertStringContainsString'],
            $timeout
        );
    }

    /**
     * Search for the specified query.
     *
     * @param string $query   Search term(s)
     * @param string $handler Search type (optional)
     * @param string $path    Path to use as search starting point (optional)
     *
     * @return Element
     */
    protected function performSearch($query, $handler = null, $path = '/Search')
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . $path);
        $page = $session->getPage();
        $this->submitSearchForm($page, $query, $handler);
        return $page;
    }

    /**
     * Submit a search on the provided page.
     *
     * @param Element $page    Current page object
     * @param string  $query   Search term(s)
     * @param string  $handler Search type (optional)
     *
     * @return void
     *
     * @throws \Exception
     */
    protected function submitSearchForm(
        Element $page,
        string $query,
        ?string $handler = null
    ): void {
        $this->findCssAndSetValue($page, '#searchForm_lookfor', $query);
        if ($handler) {
            $this->findCssAndSetValue($page, '#searchForm_type', $handler);
        }
        $this->clickCss($page, '.btn.btn-primary');
        $this->waitForPageLoad($page);
    }

    /**
     * Wait for page load (full page or any element) to complete
     *
     * @param Element $page    Page element
     * @param int     $timeout Wait timeout (in ms)
     *
     * @return void
     */
    protected function waitForPageLoad(
        Element $page,
        int $timeout = null
    ) {
        $timeout ??= $this->getDefaultTimeout();
        $session = $this->getMinkSession();
        // Wait for page load to complete:
        $session->wait($timeout, "document.readyState === 'complete'");
        // Wait for any AJAX requests to complete (and that jQuery is loaded):
        $session->wait(
            $timeout,
            "typeof $ !== 'undefined' && $.active === 0"
        );
        // Wait for modal load to complete:
        $this->unFindCss($page, '.modal-loading-overlay', $timeout);
        // Wait for page load to complete again in case it was triggered by
        // lightbox refresh or similar:
        $session->wait($timeout, "document.readyState === 'complete'");
        // Make sure any loading spinners are not visible (and jQuery is still loaded):
        $session->wait(
            $timeout,
            "typeof $ !== 'undefined' && $('.loading-spinner:visible').length === 0"
        );
        // Make sure nothing is being animated (and jQuery is still loaded):
        $jqueryOk = $session->wait(
            $timeout,
            "typeof $ !== 'undefined' && $(':animated').length === 0"
        );
        if ($jqueryOk) {
            // Finally, make sure all jQuery ready handlers are done:
            $session->evaluateScript(
                <<<EOS
                    if (window.__documentIsReady !== true) {
                        $(document).ready(function() { window.__documentIsReady = true; });
                    }
                    EOS
            );
            $session->wait(
                $timeout,
                'window.__documentIsReady === true'
            );
        }
    }

    /**
     * Verify that lightbox title contains the expected value
     *
     * @param Element $page        Page element
     * @param bool    $closeButton Whether there should be a close button in the
     * modal body
     *
     * @return void
     */
    protected function closeLightbox(Element $page, $closeButton = false)
    {
        if ($closeButton) {
            $button = $this->findCss($page, '#modal .modal-body .btn');
            $this->assertEquals('close', $button->getText());
        } else {
            $button = $this->findCss($page, '#modal .modal-content > button.close');
        }
        $button->click();
        $this->waitForLightboxHidden();
    }

    /**
     * Wait for Lightbox to become hidden if it isn't already.
     *
     * @return void
     */
    protected function waitForLightboxHidden()
    {
        $this->waitStatement(
            '$("#modal:visible").length === 0'
            . ' && $("#modal .modal-body").html() === ""'
        );
    }

    /**
     * Verify that lightbox title contains the expected value
     *
     * @param Element $page  Page element
     * @param string  $title Expected title
     *
     * @return void
     */
    protected function assertLightboxTitle(Element $page, string $title): void
    {
        $this->assertEquals(
            $title,
            $page->find('css', '#lightbox-title')->getText()
        );
    }

    /**
     * Mink support function: assert a warning message in the lightbox.
     *
     * @param Element $page    Page element
     * @param string  $message Expected message
     *
     * @return void
     */
    protected function assertLightboxWarning(Element $page, $message)
    {
        $warning = $page->find('css', '.modal-body .alert-danger .message');
        if (!$warning || strlen(trim($warning->getText())) == 0) {
            $warning = $this->findCss($page, '.modal-body .alert-danger');
        }
        $this->assertEquals($message, $warning->getText());
    }

    /**
     * Log a warning message
     *
     * @param string $consoleMsg Message to output to console
     * @param string $logMsg     Message to output to PHP error log
     *
     * @return void
     */
    protected function logWarning(string $consoleMsg, string $logMsg = ''): void
    {
        file_put_contents('php://stderr', PHP_EOL . $consoleMsg . PHP_EOL);
        if ($logMsg) {
            error_log($logMsg);
        }
    }

    /**
     * Extract the first parameter of the first attribute matching the specified
     * criteria.
     *
     * @param string $method    Method name to check for attributes
     * @param string $attribute Attribute class name to look up
     * @param mixed  $default   Default value to use if no match found
     *
     * @return mixed
     * @throws ReflectionException
     */
    protected function getFirstMethodAttributeValue(
        string $method,
        string $attribute,
        mixed $default = null
    ): mixed {
        $reflection = new \ReflectionObject($this);
        $matches = $reflection->getMethod($method)->getAttributes($attribute);
        $args = ($matches[0] ?? null)?->getArguments() ?? [];
        return $args[0] ?? $default;
    }

    /**
     * Validate current page HTML if validation is enabled and a session exists
     *
     * @param ?Element $page Page to check (optional; uses the page from session by
     * default)
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    protected function validateHtml(?Element $page = null): void
    {
        $validatorEnabled = $this->getFirstMethodAttributeValue(
            $this->name(),
            \VuFindTest\Attribute\HtmlValidation::class,
            true
        );
        if (
            !$validatorEnabled
            || (!$this->session && !$page)
            || !($nuAddress = getenv('VUFIND_HTML_VALIDATOR'))
        ) {
            return;
        }

        $http = new \VuFindHttp\HttpService();
        $client = $http->createClient(
            $nuAddress,
            \Laminas\Http\Request::METHOD_POST
        );
        $client->setEncType(\Laminas\Http\Client::ENC_FORMDATA);
        $client->setParameterPost(
            [
                'out' => 'json',
            ]
        );
        $page ??= $this->session->getPage();
        $this->waitForPageLoad($page);
        $client->setFileUpload(
            $this->session->getCurrentUrl(),
            'file',
            "<!DOCTYPE html>\n" . $page->getOuterHtml(),
            'text/html'
        );
        $response = $client->send();
        if (!$response->isSuccess()) {
            throw new \RuntimeException(
                'Could not validate HTML: '
                . $response->getStatusCode() . ', '
                . $response->getBody()
            );
        }
        $result = json_decode($response->getBody(), true);
        if (!empty($result['messages'])) {
            $errors = [];
            $info = [];
            foreach ($result['messages'] as $message) {
                if ('info' === $message['type']) {
                    $info[] = $this->htmlValidationMsgToStr($message);
                } else {
                    $errors[] = $this->htmlValidationMsgToStr($message);
                }
            }
            $logFile = (string)getenv('VUFIND_HTML_VALIDATOR_LOG_FILE');
            $quiet = (bool)getenv('VUFIND_HTML_VALIDATOR_QUIET');
            if ($info) {
                $this->outputHtmlValidationMessages($info, 'info', $logFile, $quiet);
            }
            if ($errors) {
                $this->outputHtmlValidationMessages(
                    $errors,
                    'error',
                    $logFile,
                    $quiet
                );
                if (getenv('VUFIND_HTML_VALIDATOR_FAIL_TESTS') !== '0') {
                    throw new \RuntimeException('HTML validation failed');
                }
            }
        }
    }

    /**
     * Convert a NU HTML Validator message to a string
     *
     * @param array $message Validation message
     *
     * @return string
     */
    protected function htmlValidationMsgToStr(array $message): string
    {
        $result = '  [' . ($message['firstLine'] ?? $message['lastLine'] ?? 0) . ':'
            . ($message['firstColumn'] ?? 0)
            . '] ';
        $stampLen = strlen($result);
        $result .= $message['message'];
        if (!empty($message['extract'])) {
            $result .= PHP_EOL . str_pad('', $stampLen) . 'Extract: '
                . $message['extract'];
        }
        return $result;
    }

    /**
     * Output HTML validation messages to log file and/or console
     *
     * @param array  $messages Messages
     * @param string $level    Message level (info or error)
     * @param string $logFile  Log file name
     * @param bool   $quiet    Whether the console output should be quiet
     *
     * @return void
     */
    protected function outputHtmlValidationMessages(
        array $messages,
        string $level,
        string $logFile,
        bool $quiet
    ): void {
        $logMessage = $this->session->getCurrentUrl() . ': ' . PHP_EOL . PHP_EOL
            . implode(PHP_EOL . PHP_EOL, $messages);

        if ($logFile) {
            $method = $this->getTestName();
            file_put_contents(
                $logFile,
                date('Y-m-d H:i:s') . ' [' . strtoupper($level) . "] [$method] "
                . $logMessage . PHP_EOL . PHP_EOL,
                FILE_APPEND
            );
        }
        if (!$quiet) {
            $this->logWarning(
                'HTML validation ' . ('info' === $level ? 'messages' : 'errors')
                . " for $logMessage"
            );
        }
    }

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        // Give up if we're not running in CI (throws, so no problem with any
        // further actions in any setUp methods of child classes):
        if (!$this->continuousIntegrationRunning()) {
            $this->markTestSkipped('Continuous integration not running.');
            return;
        }

        // Reset the modified configs list.
        $this->modifiedConfigs = [];

        // Create a pathResolver:
        $this->pathResolver = $this->getPathResolver();

        // Change theme if requested:
        if ($theme = (string)getenv('VUFIND_TEST_THEME')) {
            $this->changeConfigs(
                [
                    'config' => [
                        'Site' => [
                            'theme' => $theme,
                        ],
                    ],
                ]
            );
        }
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public function tearDown(): void
    {
        // Take screenshot of failed test, if we have a screenshot directory set:
        if (
            ($this->status()->isError() || $this->status()->isFailure())
            && ($imageDir = getenv('VUFIND_SCREENSHOT_DIR'))
        ) {
            $filename = $this->name() . '-' . hrtime(true);

            // Save HTML snapshot
            $snapshot = $this->getMinkSession()->getPage()->getOuterHtml();
            if (!empty($snapshot)) {
                if (!file_exists($imageDir)) {
                    mkdir($imageDir);
                }

                file_put_contents($imageDir . '/' . $filename . '.html', $snapshot);
            }

            // Save image screenshot
            $imageData = $this->getMinkSession()->getDriver()->getScreenshot();
            if (!empty($imageData)) {
                if (!file_exists($imageDir)) {
                    mkdir($imageDir);
                }

                file_put_contents($imageDir . '/' . $filename . '.png', $imageData);
            }
        }

        $htmlValidationException = null;
        if (!$this->status()->isFailure()) {
            try {
                $this->validateHtml();
            } catch (\Exception $e) {
                // Store the exception and throw after cleanup:
                $htmlValidationException = $e;
            }
        }

        $this->stopMinkSession();
        $this->restoreConfigs();

        if (null !== $htmlValidationException) {
            throw $htmlValidationException;
        }
    }

    /**
     * Standard tear-down.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        // No teardown actions at this time.
    }
}
