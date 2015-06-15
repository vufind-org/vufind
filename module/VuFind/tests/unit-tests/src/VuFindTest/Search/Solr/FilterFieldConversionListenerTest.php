<?php

/**
 * Unit tests for FilterFieldConversionListener.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2015.
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
 * @category VuFind2
 * @package  Search
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFindTest\Search\Solr;

use VuFindSearch\ParamBag;

use VuFind\Search\Solr\FilterFieldConversionListener;
use VuFindTest\Unit\TestCase;
use Zend\EventManager\Event;

/**
 * Unit tests for FilterFieldConversionListener.
 *
 * @category VuFind2
 * @package  Search
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class FilterFieldConversionListenerTest extends TestCase
{
    /**
     * Test attaching listener.
     *
     * @return void
     */
    public function testAttach()
    {
        $listener = new FilterFieldConversionListener(['foo' => 'bar']);
        $mock = $this->getMock('Zend\EventManager\SharedEventManagerInterface');
        $mock->expects($this->once())->method('attach')->with(
            $this->equalTo('VuFind\Search'),
            $this->equalTo('pre'),
            $this->equalTo([$listener, 'onSearchPre'])
        );
        $listener->attach($mock);
    }

    /**
     * Test the listener without setting an authorization service.
     * This should return an empty array.
     *
     * @return void
     */
    public function testFilterTranslation()
    {
        $params   = new ParamBag(
            [
                'fq' => [
                    'foo:value',
                    'baz:"foo:value"',
                    'foofoo:value',
                    "foo\\:value",
                    'baz:value OR foo:value',
                    '(foo:value)',
                ]
            ]
        );
        $listener = new FilterFieldConversionListener(
            ['foo' => 'bar', 'baz' => 'boo']
        );

        $backend = $this->getMockBuilder('VuFindSearch\Backend\Solr\Backend')
            ->disableOriginalConstructor()->getMock();
        $event = new Event('pre', $backend, ['params' => $params]);
        $listener->onSearchPre($event);

        $fq   = $params->get('fq');
        $expected = [
            'bar:value',
            'boo:"foo:value"',
            'foofoo:value',
            "foo\\:value",
            'boo:value OR bar:value',
            '(bar:value)',
        ];
        $this->assertEquals($expected, $fq);
    }
}