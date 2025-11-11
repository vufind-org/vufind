<?php

/**
 * MethodTimedBlocks View Helper Test Class
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\View\Helper\Root;

use Laminas\View\Helper\EscapeHtml;
use VuFind\Date\Converter;
use VuFind\ILS\Connection;
use VuFind\View\Helper\Root\DateTime;
use VuFind\View\Helper\Root\Ils;
use VuFind\View\Helper\Root\MethodTimedBlocks;
use VuFind\View\Helper\Root\TransEsc;
use VuFind\View\Helper\Root\Translate;

/**
 * TimedMethodBlocks View Helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class MethodTimedBlocksTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ViewTrait;
    use \VuFindTest\Feature\TranslatorTrait;

    /**
     * Data provider for testMethodTimedBlocks
     *
     * @return array
     */
    public static function methodTimedBlocksProvider()
    {
        return [
            'end defined' => [
                [
                    'start' => new \DateTime(),
                    'end' => new \DateTime('31-12-2025 23:59:59'),
                    'recurring' => false,
                ],
                'This feature is unavailable until 12-31-2025',
            ],
            'service defined' => [
                [
                    'start' => new \DateTime(),
                    'end' => new \DateTime('31-12-2025 23:59:59'),
                    'recurring' => false,
                ],
                'TestFeature is unavailable until 12-31-2025',
                'TestFeature',
            ],
            'only start' => [
                [
                    'start' => new \DateTime('now'),
                    'end' => '',
                    'recurring' => false,
                ],
                'This feature is unavailable',
            ],
            'not currently blocked' => [
                [
                    'start' => new \DateTime('01-01-2025'),
                    'end' => new \DateTime('02-02-2025'),
                    'recurring' => false,
                ],
                '',
                'test',
                false,
            ],
        ];
    }

    /**
     * Test methodTimedBlocks view helper
     *
     * @param array  $timedBlocks Timed blocks
     * @param string $expected    Expected result
     * @param string $service     Service display name
     * @param bool   $blocked     Is the method blocked
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('methodTimedBlocksProvider')]
    public function testMethodTimedBlocks(array $timedBlocks, string $expected, string $service = '', $blocked = true)
    {
        $helper = new MethodTimedBlocks();
        $helper->setView($this->getPhpRenderer($this->getViewHelpers($timedBlocks, $blocked)));
        $this->assertEquals($expected, $helper('Renewals', $service));
    }

    /**
     * Get view helpers needed by test.
     *
     * @param array $timedBlocks Timed blocks
     * @param bool  $blocked     Is the method blocked
     *
     * @return array
     */
    protected function getViewHelpers(array $timedBlocks, bool $blocked)
    {
        $translations = [
            'default' => [
                'service_blocked_until' => '%%service%% is unavailable until %%end%%',
                'service_blocked' => '%%service%% is unavailable',
                'default_service_description' => 'This feature',
            ],
        ];
        $translator = $this->getMockTranslator($translations);
        $translate = new Translate();
        $translate->setTranslator($translator);
        $transEsc = new TransEsc();
        $transEsc->setView(
            $this->getPhpRenderer(
                [
                    'escapeHtml' => new EscapeHtml(),
                    'translate' => $translate,
                ]
            )
        );

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->any())
            ->method('getMethodTimedBlocks')
            ->willReturn($timedBlocks);
        $connection->expects($this->any())
            ->method('getMethodBlock')
            ->willReturn($blocked ? $timedBlocks : []);
        $ils = new Ils($connection);
        $dateTime = new DateTime(new Converter());
        return compact('transEsc', 'translate', 'ils', 'dateTime');
    }
}
