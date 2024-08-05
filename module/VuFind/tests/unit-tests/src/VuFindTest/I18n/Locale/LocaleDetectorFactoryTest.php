<?php

/**
 * LocaleDetectorFactory Test Class
 *
 * PHP version 8
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

namespace VuFindTest\I18n\Locale;

use SlmLocale\Strategy\HttpAcceptLanguageStrategy;
use SlmLocale\Strategy\QueryStrategy;
use VuFind\I18n\Locale\LocaleDetectorCookieStrategy;
use VuFind\I18n\Locale\LocaleDetectorFactory;
use VuFind\I18n\Locale\LocaleDetectorParamStrategy;
use VuFind\I18n\Locale\LocaleSettings;

use function func_get_args;

/**
 * LocaleDetectorFactory Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class LocaleDetectorFactoryTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Call getStrategies on a LocaleDetectorFactory and return a list of classes
     * constructed as a result.
     *
     * Parameters passed to this method will be forwarded to getStrategies().
     *
     * @return string[]
     */
    protected function getStrategyClasses()
    {
        $factory = new LocaleDetectorFactory();
        $strategies = $this->callMethod($factory, 'getStrategies', func_get_args());
        return array_map('get_class', iterator_to_array($strategies));
    }

    /**
     * Test that we get the full strategy list by default.
     *
     * @return void
     */
    public function testStrategyListWithNoSettings(): void
    {
        $this->assertEquals(
            [
                LocaleDetectorParamStrategy::class,
                QueryStrategy::class,
                LocaleDetectorCookieStrategy::class,
                HttpAcceptLanguageStrategy::class,
            ],
            $this->getStrategyClasses()
        );
    }

    /**
     * Test that we get the full strategy list when browser language detection is on.
     *
     * @return void
     */
    public function testStrategyListWithBrowserDetection(): void
    {
        $mockSettings = $this->getMockBuilder(LocaleSettings::class)
            ->disableOriginalConstructor()->getMock();
        $mockSettings->expects($this->once())
            ->method('browserLanguageDetectionEnabled')
            ->will($this->returnValue(true));
        $this->assertEquals(
            [
                LocaleDetectorParamStrategy::class,
                QueryStrategy::class,
                LocaleDetectorCookieStrategy::class,
                HttpAcceptLanguageStrategy::class,
            ],
            $this->getStrategyClasses($mockSettings)
        );
    }

    /**
     * Test that we get an abridged strategy list when browser language detection is
     * disabled.
     *
     * @return void
     */
    public function testStrategyListWithoutBrowserDetection(): void
    {
        $mockSettings = $this->getMockBuilder(LocaleSettings::class)
            ->disableOriginalConstructor()->getMock();
        $mockSettings->expects($this->once())
            ->method('browserLanguageDetectionEnabled')
            ->will($this->returnValue(false));
        $this->assertEquals(
            [
                LocaleDetectorParamStrategy::class,
                QueryStrategy::class,
                LocaleDetectorCookieStrategy::class,
            ],
            $this->getStrategyClasses($mockSettings)
        );
    }
}
