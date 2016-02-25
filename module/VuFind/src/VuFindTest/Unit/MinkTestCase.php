<?php

/**
 * Abstract base class for PHPUnit test cases using Mink.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\Unit;
use Behat\Mink\Driver\ZombieDriver, Behat\Mink\Session,
    Behat\Mink\Element\Element,
    VuFind\Config\Locator as ConfigLocator,
    VuFind\Config\Writer as ConfigWriter;

/**
 * Abstract base class for PHPUnit test cases using Mink.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
abstract class MinkTestCase extends DbTestCase
{
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
     *
     * @return void
     */
    protected function changeConfigs($configs)
    {
        foreach ($configs as $file => $settings) {
            $this->changeConfigFile($file, $settings);
            $this->modifiedConfigs[] = $file;
        }
    }

    /**
     * Support method for changeConfig; act on a single file.
     *
     * @param string $configName Configuration to modify.
     * @param array  $settings   Settings to change.
     *
     * @return void
     */
    protected function changeConfigFile($configName, $settings)
    {
        $file = $configName . '.ini';
        $local = ConfigLocator::getLocalConfigPath($file, null, true);
        if (file_exists($local)) {
            // File exists? Make a backup!
            copy($local, $local . '.bak');
        } else {
            // File doesn't exist? Make a baseline version.
            copy(ConfigLocator::getBaseConfigPath($file), $local);
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
     * Are we using the Zombie.js driver?
     *
     * @return bool
     */
    protected function isZombieDriver()
    {
        return getenv('VUFIND_MINK_DRIVER') !== 'selenium';
    }

    /**
     * Assert an HTTP status code (if supported).
     *
     * @param int $code Expected status code.
     *
     * @return void
     */
    protected function assertHttpStatus($code)
    {
        // This assertion is not supported by Selenium.
        if ($this->isZombieDriver()) {
            $this->assertEquals(200, $this->getMinkSession()->getStatusCode());
        }
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
        // Sleep is not necessary for Zombie.js.
        if (!$this->isZombieDriver()) {
            $snoozeMultiplier = intval(getenv('VUFIND_SNOOZE_MULTIPLIER'));
            if ($snoozeMultiplier < 1) {
                $snoozeMultiplier = 1;
            }
            sleep($secs * $snoozeMultiplier);
        }
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
        // Zombie.js does not support visibility checks; just assume true.
        return $this->isZombieDriver() ? true : $element->isVisible();
    }

    /**
     * Get the Mink driver, initializing it if necessary.
     *
     * @return ZombieDriver
     */
    protected function getMinkDriver()
    {
        return !$this->isZombieDriver()
            ? new \Behat\Mink\Driver\Selenium2Driver('firefox')
            : new ZombieDriver(
                new \Behat\Mink\Driver\NodeJS\Server\ZombieServer()
            );
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
    }

    /**
     * Wait for an element to exist, then retrieve it.
     *
     * @param Element $page     Page element
     * @param string  $selector CSS selector
     * @param int     $timeout  Wait timeout (in ms)
     *
     * @return mixed
     */
    protected function findCss(Element $page, $selector, $timeout = 1000)
    {
        $session = $this->getMinkSession();
        $session->wait($timeout, "$('$selector').length > 0");
        $result = $page->find('css', $selector);
        $this->assertTrue(is_object($result));
        return $result;
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
     * Standard setup method.
     *
     * @return void
     */
    public function setUp()
    {
        // Give up if we're not running in CI or Zombie.js is unavailable:
        if (!$this->continuousIntegrationRunning()) {
            return $this->markTestSkipped('Continuous integration not running.');
        }
        if (strlen(getenv('NODE_PATH')) == 0) {
            return $this->markTestSkipped('NODE_PATH setting missing.');
        }

        // Reset the modified configs list.
        $this->modifiedConfigs = [];
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public function tearDown()
    {
        $this->stopMinkSession();
        $this->restoreConfigs();
    }

    /**
     * Standard tear-down.
     *
     * @return void
     */
    public static function tearDownAfterClass()
    {
        // No teardown actions at this time.
    }
}
