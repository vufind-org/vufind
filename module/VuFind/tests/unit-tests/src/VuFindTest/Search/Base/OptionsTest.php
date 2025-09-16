<?php

/**
 * Base Search Object Options Test
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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

namespace VuFindTest\Search\Base;

/**
 * Base Search Object Options Test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class OptionsTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ConfigRelatedServicesTrait;
    use \VuFindTest\Feature\MockSearchOptionsTrait;

    /**
     * Test basic search handler behavior.
     *
     * @return void
     */
    public function testBasicHandlers(): void
    {
        $handlers = [
            'foo' => 'foo_label',
            'bar' => 'bar_label',
        ];
        $configs = [
            'searches' => [
                'Basic_Searches' => $handlers,
            ],
        ];
        $configManager = $this->getMockConfigManager($configs);
        $options = $this->getMockOptions($configManager);
        $this->assertEquals($handlers, $options->getBasicHandlers());
        $this->assertEquals('foo_label', $options->getLabelForBasicHandler('foo'));
        $this->assertEquals('bar_label', $options->getLabelForBasicHandler('bar'));
        $this->assertEmpty($options->getLabelForBasicHandler('baz'));
    }
}
