<?php

/**
 * AbstractSearchObject Test Class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2026.
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
 * @author   Emmanuel Afuadajo <afuadajoe@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Recommend;

use Laminas\Stdlib\Parameters;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Config\ConfigManagerInterface;
use VuFind\Recommend\AbstractSearchObject;
use VuFind\Search\Base\Options;
use VuFind\Search\Base\Params;
use VuFind\Search\Base\Results;
use VuFind\Search\SearchRunner;

/**
 * AbstractSearchObject Test Class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Emmanuel Afuadajo <afuadajoe@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
abstract class AbstractSearchObjectTestCase extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\WithConsecutiveTrait;

    /**
     * Get the class name of the module.
     *
     * @return string
     */
    abstract protected function getTestClass(): string;

    /**
     * Get search class id for the module.
     *
     * @return string
     */
    abstract protected function getExpectedSearchClassId(): string;

    /**
     * Get the default heading for the module.
     *
     * @return string
     */
    abstract protected function getExpectedDefaultHeading(): string;

    /**
     * Create an instance of the module.
     *
     * @param ?SearchRunner           $runner        Search runner (null for mock)
     * @param ?ConfigManagerInterface $configManager Config manager (null for mock)
     *
     * @return AbstractSearchObject
     */
    protected function getRecommend(
        ?SearchRunner $runner = null,
        ?ConfigManagerInterface $configManager = null
    ): AbstractSearchObject {
        $class = $this->getTestClass();
        return new $class(
            $runner ?? $this->createStub(SearchRunner::class),
            $configManager ?? $this->createStub(ConfigManagerInterface::class)
        );
    }

    /**
     * Test that an empty recommend configuration string returns the module's default heading.
     *
     * @return void
     */
    public function testDefaultHeading(): void
    {
        $recommend = $this->getRecommend();
        $recommend->setConfig('');
        $this->assertEquals($this->getExpectedDefaultHeading(), $recommend->getHeading());
    }

    /**
     * Test that a configured heading overrides the default.
     *
     * @return void
     */
    public function testCustomHeadingFromConfig(): void
    {
        $recommend = $this->getRecommend();
        $recommend->setConfig('lookfor:10:Custom Heading Text');
        $this->assertEquals('Custom Heading Text', $recommend->getHeading());
    }

    /**
     * Test that init() runs a search using the module's expected search class
     * ID, and that getResults() exposes whatever the runner returns.
     *
     * @return void
     */
    public function testInitRunsSearchWithExpectedClassId(): void
    {
        $mockResults = $this->createMock(Results::class);
        $runner = $this->createMock(SearchRunner::class);
        $runner->expects($this->once())->method('run')
            ->with([], $this->getExpectedSearchClassId(), $this->isCallable())
            ->willReturn($mockResults);

        $recommend = $this->getRecommend($runner);
        $recommend->setConfig('');

        $params = $this->createMock(Params::class);
        $request = new Parameters(['lookfor' => 'test query']);
        $recommend->init($params, $request);

        $this->assertSame($mockResults, $recommend->getResults());
    }

    /**
     * Test that filters are applied to the search parameters when the search callback runs.
     *
     * @return void
     */
    public function testInitAppliesConfiguredFilters(): void
    {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->expects($this->once())->method('getConfigArray')
            ->with('searches')
            ->willReturn(['MyFilterSection' => ['building:main', 'format:Book']]);

        $capturedCallback = null;
        $runner = $this->createMock(SearchRunner::class);
        $runner->method('run')->willReturnCallback(
            function ($request, $classId, $callback) use (&$capturedCallback): MockObject {
                $capturedCallback = $callback;
                return $this->createMock(Results::class);
            }
        );

        $recommend = $this->getRecommend($runner, $configManager);
        $recommend->setConfig('lookfor:5:Heading:MyFilterSection');

        $outerParams = $this->createMock(Params::class);
        $request = new Parameters(['lookfor' => 'test query']);
        $recommend->init($outerParams, $request);
        $this->assertIsCallable($capturedCallback);

        $innerOptions = $this->createMock(Options::class);
        $innerOptions->method('getSearchIni')->willReturn('searches');
        $innerOptions->method('getHandlerForLabel')->willReturn('AllFields');
        $innerParams = $this->createMock(Params::class);
        $innerParams->method('getOptions')->willReturn($innerOptions);
        $innerParams->expects($this->once())->method('setLimit')->with(5);
        $this->expectConsecutiveCalls(
            $innerParams,
            'addFilter',
            [['building:main'], ['format:Book']]
        );

        ($capturedCallback)($runner, $innerParams);
    }

    /**
     * Test that process() does not replace the results already set by init().
     *
     * @return void
     */
    public function testProcessDoesNotAlterResults(): void
    {
        $mockResults = $this->createMock(Results::class);
        $runner = $this->createStub(SearchRunner::class);
        $runner->method('run')->willReturn($mockResults);

        $recommend = $this->getRecommend($runner);
        $recommend->setConfig('');
        $recommend->init(
            $this->createStub(Params::class),
            new Parameters(['lookfor' => 'test query'])
        );

        $recommend->process($this->createMock(Results::class));

        $this->assertSame($mockResults, $recommend->getResults());
    }

    /**
     * Test that getResults() throws an exception if called before init().
     *
     * @return void
     */
    public function testGetResultsWithoutInitialization(): void
    {
        $recommend = $this->getRecommend();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($this->getTestClass() . '::getResults() called before init().');
        $recommend->getResults();
    }
}
