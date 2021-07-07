<?php
/**
 * CSV Importer Test Class
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2021.
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
namespace VuFindTest\CSV;

use VuFind\CSV\Importer;

/**
 * CSV Importer Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ImporterTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Test importer functionality.
     *
     * @return void
     */
    public function testImport(): void
    {
        $container = new \VuFindTest\Container\MockContainer($this);
        $fixtureDir = $this->getFixtureDir() . 'csv/';
        $configBaseDir = implode('/', array_slice(explode('/', realpath($fixtureDir)), -5));
        $importer = new Importer($container, compact('configBaseDir'));
        $result = $importer->save(
            $fixtureDir . 'test.csv', 'test.ini', 'Solr', true
        );
        $expected = file_get_contents($fixtureDir . 'test.json');
        $this->assertJsonStringEqualsJsonString($expected, $result);
    }
}
