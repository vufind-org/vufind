<?php

/**
 * ExternalSearch recommendation module Test Class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Recommend;

use Generator;
use VuFind\Recommend\ExternalSearch;

/**
 * ExternalSearch recommendation module Test Class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ExternalSearchTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Data provider for.
     *
     * @return Generator
     */
    public static function dataProvider(): Generator
    {
        yield 'default concatenation' => [
            'my label',
            'http://foo?q=',
            'beep',
            'http://foo?q=beep',
        ];
        yield 'template behavior' => [
            'my label',
            'http://foo?q=%%lookfor%%&z=xyzzy',
            'beep',
            'http://foo?q=beep&z=xyzzy',
        ];
        yield 'non-default query parameter' => [
            'my label',
            'http://foo?q=%%lookfor%%&z=xyzzy',
            'beep',
            'http://foo?q=beep&z=xyzzy',
            'foo',
        ];
    }

    /**
     * Run a test scenario.
     *
     * @param string  $label        Link text
     * @param string  $template     Link template
     * @param string  $lookfor      Search query
     * @param string  $expectedUrl  Expected URL
     * @param ?string $lookforParam Name of query parameter holding $lookfor value (null for default)
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataProvider')]
    public function testRecommend(
        string $label,
        string $template,
        string $lookfor,
        string $expectedUrl,
        ?string $lookforParam = null
    ): void {
        $rec = new ExternalSearch();
        $rec->setConfig($label . ':' . $template . ($lookforParam ? ":$lookforParam" : ''));
        $params = new \Laminas\Stdlib\Parameters();
        $params->set($lookforParam ?? 'lookfor', $lookfor);
        $rec->init(
            $this->createMock(\VuFind\Search\Solr\Params::class),
            $params
        );
        $rec->process(
            $this->createMock(\VuFind\Search\Solr\Results::class)
        );
        $this->assertEquals($label, $rec->getLinkText());
        $this->assertEquals($expectedUrl, $rec->getUrl());
    }
}
