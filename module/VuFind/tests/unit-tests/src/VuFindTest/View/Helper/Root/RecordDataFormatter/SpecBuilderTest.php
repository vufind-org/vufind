<?php

/**
 * RecordDataFormatter spec builder Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2016.
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

namespace VuFindTest\View\Helper\Root\RecordDataFormatter;

use VuFind\View\Helper\Root\RecordDataFormatter\SpecBuilder;

/**
 * RecordDataFormatter spec builder Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SpecBuilderTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ViewTrait;

    /**
     * Test the spec builder
     *
     * @return void
     */
    public function testBuilder(): void
    {
        // Test building a spec:
        $builder = new SpecBuilder();
        $builder->setLine('foo', 'getFoo');
        $builder->setLine('bar', 'getBar');
        $builder->setTemplateLine('xyzzy', 'getXyzzy', 'xyzzy.phtml');
        $expected = [
            'foo' => [
                'dataMethod' => 'getFoo',
                'renderType' => null,
                'pos' => 100,
            ],
            'bar' => [
                'dataMethod' => 'getBar',
                'renderType' => null,
                'pos' => 200,
            ],
            'xyzzy' => [
                'template' => 'xyzzy.phtml',
                'dataMethod' => 'getXyzzy',
                'renderType' => 'RecordDriverTemplate',
                'pos' => 300,
            ],
        ];
        $this->assertEquals($expected, $builder->getArray());
        // Test various methods of reordering the spec:
        $builder->reorderKeys(['xyzzy', 'bar']);
        $expected['xyzzy']['pos'] = 100;
        $expected['bar']['pos'] = 200;
        $expected['foo']['pos'] = 300;
        $this->assertEquals($expected, $builder->getArray());
        $builder->reorderKeys(['xyzzy', 'bar'], 0);
        $expected['foo']['pos'] = 0;
        $this->assertEquals($expected, $builder->getArray());
        $builder->reorderKeys(['foo', 'baz', 'xyzzy', 'bar']);
        $expected['xyzzy']['pos'] = 300;
        $expected['bar']['pos'] = 400;
        $expected['foo']['pos'] = 100;
        $this->assertEquals($expected, $builder->getArray());
        // Test that we can remove lines from the spec:
        $builder->removeLine('bar');
        $builder->removeLine('foo');
        $this->assertEquals(['xyzzy' => $expected['xyzzy']], $builder->getArray());
    }
}
