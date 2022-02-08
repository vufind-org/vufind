<?php

/**
 * Abstract base class for PHPUnit test cases using Mink.
 *
 * PHP version 7
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
use Behat\Mink\Session;
use DMore\ChromeDriver\ChromeDriver;
use VuFind\Config\Locator as ConfigLocator;
use VuFind\Config\Writer as ConfigWriter;

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
    use \VuFindTest\Feature\AutoRetryTrait;
    use \VuFindTest\Feature\LiveDetectionTrait;

    public const DEFAULT_TIMEOUT = 5000;

    /**
     * Modified configurations
     *
     * @var array
     */
    protected $modifiedConfigs = [];

    /**
     * Mink session
     *
     * @var Session
     */
    protected $session;

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
     * Support method for changeConfig; act on a single file.
     *
     * @param string $configName Configuration to modify.
     * @param array  $settings   Settings to change.
     * @param bool   $replace    Should we replace the existing config entirely
     * (as opposed to extending it with new settings)?
     *
     * @return void
     */
    protected function changeConfigFile($configName, $settings, $replace = false)
    {
        $file = $configName . '.ini';
        $local = ConfigLocator::getLocalConfigPath($file, null, true);
        if (!in_array($configName, $this->modifiedConfigs)) {
            if (file_exists($local)) {
                // File exists? Make a backup!
                copy($local, $local . '.bak');
            } else {
                // File doesn't exist? Make a baseline version.
                copy(ConfigLocator::getBaseConfigPath($file), $local);
            }

            $this->modifiedConfigs[] = $configName;
        }
        // If we're replacing the existing file, wipe it out now:
        if ($replace) {
            file_put_contents($local, '');
        }

        $writer = new ConfigWriter($local);
        foreach ($settings as $section => $contents) {
            foreach ($contents as $key => $value) {
                $writer->set($section, $key, $value);
            }
        }
        $writer->save();
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
        $snoozeMultiplier = floatval(getenv('VUFIND_SNOOZE_MULTIPLIER'));
        if ($snoozeMultiplier <= 0) {
            $snoozeMultiplier = 1;
        }
        usleep(1000000 * $secs * $snoozeMultiplier);
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
     * Restore configurations to the state they were in prior to a call to
     * changeConfig().
     *
     * @return void
     */
    protected function restoreConfigs()
    {
        foreach ($this->modifiedConfigs as $current) {
            $file = $current . '.ini';
            $local = ConfigLocator::getLocalConfigPath($file, null, true);
            $backup = $local . '.bak';

            // Do we have a backup? If so, restore from it; otherwise, just
            // delete the local file, as it did not previously exist:
            unlink($local);
            if (file_exists($backup)) {
                rename($backup, $local);
            }
        }
        $this->modifiedConfigs = [];
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
        $timeout = self::DEFAULT_TIMEOUT,
        $index = 0
    ) {
        $session = $this->getMinkSession();
        $session->wait(
            $timeout,
            "typeof $ !== 'undefined' && $('$selector').length > $index"
        );
        $results = $page->findAll('css', $selector);
        $this->assertIsArray($results, "Selector not found: $selector");
        $result = $results[$index] ?? null;
        $this->assertTrue(
            is_object($result),
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
    protected function waitStatement($statement, $timeout = self::DEFAULT_TIMEOUT)
    {
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
        $timeout = self::DEFAULT_TIMEOUT,
        $index = 0
    ) {
        $startTime = microtime(true);
        while ((microtime(true) - $startTime) * 1000 <= $timeout) {
            $elements = $page->findAll('css', $selector);
            if (!isset($elements[$index])) {
                return;
            }
            usleep(50000);
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
        $timeout = self::DEFAULT_TIMEOUT,
        $index = 0
    ) {
        $e = null;
        $result = $this->findCss($page, $selector, $timeout, $index);
        for ($tries = 1; $tries < 4; $tries++) {
            try {
                $result->click();
                return $result;
            } catch (\Exception $e) {
                // Expected click didn't work... snooze and retry
                $this->logWarning(
                    "clickCss exception (try $tries)."
                    . ' See PHP error log for details.',
                    "clickCss exception (try $tries): " . $e->getTraceAsString()
                );
                $this->snooze();
            }
        }
        throw $e ?? new \Exception('Unexpected state reached.');
    }

    /**
     * Set a value within an element selected via CSS; retry if set fails
     * due to browser bugs.
     *
     * @param Element $page     Page element
     * @param string  $selector CSS selector
     * @param string  $value    Value to set
     * @param int     $timeout  Wait timeout for CSS selection (in ms)
     * @param int     $retries  Retry count for set loop
     *
     * @return mixed
     */
    protected function findCssAndSetValue(
        Element $page,
        $selector,
        $value,
        $timeout = self::DEFAULT_TIMEOUT,
        $retries = 6
    ) {
        $field = $this->findCss($page, $selector, $timeout, 0);

        $session = $this->getMinkSession();
        $session->wait(
            $timeout,
            "typeof $ !== 'undefined' && $('$selector:focusable').length > 0"
        );
        $results = $page->findAll('css', $selector);
        $this->assertIsArray($results, "Selector not found: $selector");
        $field = $results[0];

        // Workaround for Chromedriver bug; sometimes setting a value
        // doesn't work on the first try.
        for ($i = 1; $i <= $retries; $i++) {
            $field->setValue($value);

            // Did it work? If so, we're done and can leave....
            if ($field->getValue() === $value) {
                return;
            }
            $this->logWarning(
                'setValue failed in ' . $this->getName(false) . "(try $i)."
            );

            $this->snooze();
        }

        throw new \Exception('Failed to set value after ' . $retries . ' attempts.');
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
        $this->assertTrue(is_object($link));
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
        int $timeout = self::DEFAULT_TIMEOUT
    ) {
        $result = null;
        $startTime = microtime(true);
        while ((microtime(true) - $startTime) * 1000 <= $timeout) {
            $result = $callback();
            if ($result === $expected) {
                break;
            }
            usleep(100000);
        }
        $this->assertEquals($expected, $result);
    }

    /**
     * Search for the specified query.
     *
     * @param string $query   Search term(s)
     * @param string $handler Search type (optional)
     * @param string $path    Path to use as search starting point (optional)
     *
     * @return \Behat\Mink\Element\Element
     */
    protected function performSearch($query, $handler = null, $path = '/Search')
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . $path);
        $page = $session->getPage();
        $this->findCss($page, '#searchForm_lookfor')->setValue($query);
        if ($handler) {
            $this->findCss($page, '#searchForm_type')->setValue($handler);
        }
        $this->clickCss($page, '.btn.btn-primary');
        $this->waitForPageLoad($page);
        return $page;
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
        int $timeout = self::DEFAULT_TIMEOUT
    ) {
        $session = $this->getMinkSession();
        // Wait for page load to complete:
        $session->wait($timeout, "document.readyState === 'complete'");
        // Wait for any AJAX requests to complete:
        $session->wait(
            $timeout,
            "typeof $ !== 'undefined' && $.active === 0"
        );
        // Wait for modal load to complete:
        $this->unFindCss($page, '.modal-loading-overlay', $timeout);
        // Wait for page load to complete again in case it was triggered by
        // lightbox refresh or similar:
        $session->wait($timeout, "document.readyState === 'complete'");
        // Make sure any loading spinners are not visible:
        $session->wait(
            $timeout,
            "typeof $ !== 'undefined' && $('.loading-spinner:visible').length === 0"
        );
        // Make sure nothing is being animated:
        $session->wait(
            $timeout,
            "typeof $ !== 'undefined' && $(':animated').length === 0"
        );
    }

    /**
     * Wait for Lightbox to become hidden if it isn't already.
     *
     * @return void
     */
    protected function waitForLightboxHidden()
    {
        $this->waitStatement('$("#modal:visible").length === 0');
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
        echo PHP_EOL . $consoleMsg . PHP_EOL;
        if ($logMsg) {
            error_log($logMsg);
        }
    }

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        // Give up if we're not running in CI:
        if (!$this->continuousIntegrationRunning()) {
            $this->markTestSkipped('Continuous integration not running.');
            return;
        }

        // Reset the modified configs list.
        $this->modifiedConfigs = [];
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public function tearDown(): void
    {
        // Take screenshot of failed test, if we have a screenshot directory set
        // and we have run out of retries ($this->retriesLeft is set by the
        // AutoRetryTrait):
        if ($this->hasFailed()
            && ($imageDir = getenv('VUFIND_SCREENSHOT_DIR'))
            && $this->retriesLeft === 0
        ) {
            $filename = $this->getName() . '-' . hrtime(true);

            // Save image screenshot
            $imageData = $this->getMinkSession()->getDriver()->getScreenshot();
            if (!empty($imageData)) {
                if (!file_exists($imageDir)) {
                    mkdir($imageDir);
                }

                file_put_contents($imageDir . '/' . $filename . '.png', $imageData);
            }

            // Save HTML snapshot
            $snapshot = $this->getMinkSession()->getPage()->getOuterHtml();
            if (!empty($snapshot)) {
                if (!file_exists($imageDir)) {
                    mkdir($imageDir);
                }

                file_put_contents($imageDir . '/' . $filename . '.html', $snapshot);
            }
        }

        $this->stopMinkSession();
        $this->restoreConfigs();
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
