<?php

/**
 * ExternalSearch recommendation module Test Class
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Recommend;

use VuFind\Recommend\ExternalSearch;

/**
 * ExternalSearch recommendation module Test Class
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
     * Run a test scenario
     *
     * @param string $label       Link text
     * @param string $template    Link template
     * @param string $lookfor     Search query
     * @param string $expectedUrl Expected URL
     *
     * @return void
     */
    protected function runProcedure($label, $template, $lookfor, $expectedUrl)
    {
        $rec = new ExternalSearch();
        $rec->setConfig($label . ':' . $template);
        $params = new \Laminas\Stdlib\Parameters();
        $params->set('lookfor', $lookfor);
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

    /**
     * Test concatenation behavior
     *
     * @return void
     */
    public function testDefaultConcatenation()
    {
        $this->runProcedure(
            'my label',
            'http://foo?q=',
            'beep',
            'http://foo?q=beep'
        );
    }

    /**
     * Test template insertion behavior
     *
     * @return void
     */
    public function testTemplateBehavior()
    {
        $this->runProcedure(
            'my label',
            'http://foo?q=%%lookfor%%&z=xyzzy',
            'beep',
            'http://foo?q=beep&z=xyzzy'
        );
    }
}
