<?php

/**
 * Trait adding functionality for loading fixtures.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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

namespace VuFindTest\Feature;

use RuntimeException;

use function sprintf;

/**
 * Trait adding functionality for loading fixtures.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait FixtureTrait
{
    /**
     * Get the base directory containing fixtures.
     *
     * @param string $module Module containing fixture.
     *
     * @return string
     */
    protected function getFixtureDir($module = 'VuFind')
    {
        return __DIR__ . '/../../../../' . $module . '/tests/fixtures/';
    }

    /**
     * Resolve fixture path.
     *
     * @param string $filename Filename relative to fixture directory.
     * @param string $module   Module containing fixture.
     *
     * @return string
     * @throws RuntimeException
     */
    protected function getFixturePath($filename, $module = 'VuFind')
    {
        $realFilename = realpath($this->getFixtureDir($module) . $filename);
        if (
            !$realFilename || !file_exists($realFilename)
            || !is_readable($realFilename)
        ) {
            throw new RuntimeException(
                sprintf('Unable to resolve fixture to fixture file: %s', $filename)
            );
        }
        return $realFilename;
    }

    /**
     * Load a fixture file.
     *
     * @param string $filename Filename relative to fixture directory.
     * @param string $module   Module containing fixture.
     *
     * @return string
     * @throws RuntimeException
     */
    protected function getFixture($filename, $module = 'VuFind')
    {
        return file_get_contents($this->getFixturePath($filename, $module));
    }

    /**
     * Load a JSON fixture from file (using associative array return type).
     *
     * @param string $filename Filename relative to fixture directory.
     * @param string $module   Module containing fixture.
     *
     * @return array
     */
    protected function getJsonFixture($filename, $module = 'VuFind')
    {
        try {
            return json_decode($this->getFixture($filename, $module), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            throw new \Exception("Could not decode JSON file $filename: " . (string)$e, 0, $e);
        }
    }
}
