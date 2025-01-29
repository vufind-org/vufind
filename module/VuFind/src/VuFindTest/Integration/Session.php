<?php

/**
 * Session handler for PHPUnit test cases using Mink.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Integration;

/**
 * Session handler for PHPUnit test cases using Mink.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class Session extends \Behat\Mink\Session
{
    /**
     * Test name
     *
     * @var string
     */
    protected $testName = '';

    /**
     * Coverage data directory
     *
     * @var string
     */
    protected $coverageDir = '';

    /**
     * Whether Whoops error handler needs to be disabled
     *
     * @var bool
     */
    protected $disableWhoops = false;

    /**
     * Set remote code coverage configuration
     *
     * @param string $testName    Test name
     * @param string $coverageDir Coverage data directory
     *
     * @return void
     */
    public function setRemoteCoverageConfig(
        string $testName,
        string $coverageDir
    ): void {
        $this->testName = $testName;
        $this->coverageDir = $coverageDir;
    }

    /**
     * Toggle HTTP header that disables Whoops
     *
     * @param bool $disable Whether to disable Whoops
     *
     * @return void
     */
    public function setWhoopsDisabled(bool $disable): void
    {
        $this->disableWhoops = $disable;
    }

    /**
     * Visit specified URL and automatically start session if not already running.
     *
     * @param string $url url of the page
     *
     * @return void
     */
    public function visit($url)
    {
        // Request remote code coverage if enabled:
        if ($this->testName && $this->coverageDir) {
            $this->setRequestHeader(
                'X-VuFind-Remote-Coverage',
                json_encode(
                    [
                        'action' => 'record',
                        'testName' => $this->testName,
                        'outputDir' => $this->coverageDir,
                    ]
                )
            );
        }
        if ($this->disableWhoops) {
            $this->setRequestHeader('X-VuFind-Disable-Whoops', '1');
        }

        parent::visit($url);
    }
}
