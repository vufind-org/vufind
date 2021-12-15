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
    protected function findCss(Element $page, $selector, $timeout = 1000, $index = 0)
    {
        $session = $this->getMinkSession();
        $session->wait(
            $timeout,
            "typeof $ !== 'undefined' && $('$selector').length > 0"
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
        $timeout = 1000,
        $index = 0
    ) {
        $e = null;
        $result = $this->findCss($page, $selector, $timeout, $index);
        for ($tries = 0; $tries < 3; $tries++) {
            try {
                $result->click();
                return $result;
            } catch (\Exception $e) {
                // Expected click didn't work... snooze and retry
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
        $timeout = 1000,
        $retries = 6
    ) {
        $field = $this->findCss($page, $selector, $timeout);

        // Workaround for Chromedriver bug; sometimes setting a value
        // doesn't work on the first try.
        for ($i = 0; $i < $retries; $i++) {
            $field->setValue($value);
            // Did it work? If so, we're done and can leave....
            if ($field->getValue() === $value) {
                return;
            }
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
        $this->findCss($page, '.btn.btn-primary')->click();
        $this->snooze();
        return $page;
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
            $imageData = $this->getMinkSession()->getDriver()->getScreenshot();
            if (!empty($imageData)) {
                $filename = $this->getName() . '-' . hrtime(true) . '.png';

                if (!file_exists($imageDir)) {
                    mkdir($imageDir);
                }
                file_put_contents($imageDir . '/' . $filename, $imageData);
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
