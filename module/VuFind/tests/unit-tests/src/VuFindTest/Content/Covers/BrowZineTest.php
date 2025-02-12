<?php

/**
 * Unit tests for BrowZine cover loader.
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
 * @link     https://vufind.org
 */

namespace VuFindTest\Content\Covers;

use VuFind\Content\Covers\BrowZine;
use VuFind\Content\Covers\BrowZineFactory;
use VuFindTest\Container\MockContainer;

/**
 * Unit tests for BrowZine cover loader.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class BrowZineTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Data provider for testCoverLoading.
     *
     * @return array[]
     */
    public static function coverProvider(): array
    {
        return [
            'no issn' => [[], null, false],
            'default cover' => [['issn' => '12345678'], 'browzine/cover-default.json', false],
            'non-default cover' => [
                ['issn' => '12345678'],
                'browzine/cover-non-default.json',
                'https://assets.thirdiron.com/simulated-real-cover.png',
            ],
        ];
    }

    /**
     * Test cover loading
     *
     * @param array       $ids      Array of IDs to look up
     * @param ?string     $fixture  Fixture to return from backend (null to assume backend will not be called)
     * @param string|bool $expected Expected cover URL
     *
     * @return void
     *
     * @dataProvider coverProvider
     */
    public function testCoverLoading(array $ids, ?string $fixture, string|bool $expected): void
    {
        $service = $this->createMock(\VuFindSearch\Service::class);
        if ($fixture) {
            $service->method('invoke')->willReturnCallback(
                function ($command) use ($fixture, $ids) {
                    $this->assertEquals([$ids['issn']], $command->getArguments());
                    $fakeCommand = $this->createMock($command::class);
                    $fakeCommand->method('getResult')->willReturn($this->getJsonFixture($fixture));
                    return $fakeCommand;
                }
            );
        }
        $factory = new BrowZineFactory();
        $container = new MockContainer($this);
        $container->set(\VuFindSearch\Service::class, $service);
        $loader = ($factory)($container, BrowZine::class);
        $this->assertEquals($expected, $loader->getUrl('', 'small', $ids));
    }
}
