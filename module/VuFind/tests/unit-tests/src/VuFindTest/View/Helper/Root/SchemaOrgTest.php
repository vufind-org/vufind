<?php

/**
 * SchemaOrg View Helper Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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

namespace VuFindTest\View\Helper\Root;

use Laminas\View\Helper\HtmlAttributes;
use VuFind\View\Helper\Root\SchemaOrg;
use VuFindTest\RecordDriver\TestHarness;

/**
 * SchemaOrg View Helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SchemaOrgTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get a helper to test.
     *
     * @param bool $enabled Should schema.org be enabled in the helper?
     *
     * @return SchemaOrg
     */
    public function getHelper(bool $enabled): SchemaOrg
    {
        return new SchemaOrg(new HtmlAttributes(), $enabled);
    }

    /**
     * Test that the helper outputs content if enabled.
     *
     * @return void
     */
    public function testEnabled(): void
    {
        $helper = $this->getHelper(true);
        $this->assertEquals(' foo="bar"', $helper->getAttributes(['foo' => 'bar']));
        $this->assertEquals('<foo bar="baz">', $helper->getTag('foo', ['bar' => 'baz']));
        $this->assertEquals(
            '<link href="http&#x3A;&#x2F;&#x2F;foo" property="bar" baz="xyzzy">',
            $helper->getLink('http://foo', 'bar', ['baz' => 'xyzzy'])
        );
        $this->assertEquals(
            '<meta property="foo" content="bar" baz="xyzzy">',
            $helper->getMeta('foo', 'bar', ['baz' => 'xyzzy'])
        );
    }

    /**
     * Test that the helper outputs nothing if disabled.
     *
     * @return void
     */
    public function testDisabled(): void
    {
        $helper = $this->getHelper(false);
        $this->assertEquals('', $helper->getAttributes(['foo' => 'bar']));
        $this->assertEquals('', $helper->getTag('foo', ['bar' => 'baz']));
        $this->assertEquals('', $helper->getLink('http://foo', 'bar', ['baz' => 'xyzzy']));
        $this->assertEquals('', $helper->getMeta('foo', 'bar', ['baz' => 'xyzzy']));
    }

    /**
     * Data provider for testGetRecordTypes().
     *
     * @return void
     */
    public static function getRecordTypesProvider(): array
    {
        return [
            'no types' => [[], ''],
            'one type' => [['foo'], 'foo'],
            'two types' => [['foo', 'bar'], 'foo bar'],
        ];
    }

    /**
     * Test that we can get a formatted list of record types.
     *
     * @param string[] $types    Types to test with
     * @param string   $expected Expected return value
     *
     * @return void
     *
     * @dataProvider getRecordTypesProvider
     */
    public function testGetRecordTypes(array $types, string $expected): void
    {
        $helper = $this->getHelper(true);
        $driver = new TestHarness();
        $driver->setRawData(
            ['SchemaOrgFormatsArray' => $types]
        );
        $this->assertEquals($expected, $helper->getRecordTypes($driver));
    }
}
