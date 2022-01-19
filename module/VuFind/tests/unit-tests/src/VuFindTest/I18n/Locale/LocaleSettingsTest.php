<?php
/**
 * LocaleSettings Test Class
 *
 * PHP version 7
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

use Laminas\Config\Config;
use VuFind\I18n\Locale\LocaleSettings;

/**
 * LocaleSettings Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class LocaleSettingsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Confirm that an exception is thrown if no language is specified.
     *
     * @return void
     */
    public function testDefaultLocaleRequired(): void
    {
        $this->expectExceptionMessage('Default locale not configured!');
        new LocaleSettings(new Config([]));
    }

    /**
     * Confirm that an exception is thrown if a non-enabled language is set as
     * default.
     *
     * @return void
     */
    public function testDefaultMustBeEnabled(): void
    {
        $this->expectExceptionMessage("Configured default locale 'en' not enabled!");
        new LocaleSettings(new Config(['Site' => ['language' => 'en']]));
    }

    /**
     * Confirm default settings for nearly-empty configuration.
     *
     * @return void
     */
    public function testDefaultConfigs(): void
    {
        $settings = new LocaleSettings(
            new Config(
                [
                    'Site' => ['language' => 'en'],
                    'Languages' => ['en' => 'English'],
                ]
            )
        );
        $this->assertTrue($settings->browserLanguageDetectionEnabled());
        $this->assertEquals(['en'], $settings->getFallbackLocales());
    }

    /**
     * Test that browser detection can be disabled.
     *
     * @return void
     */
    public function testDisablingBrowserLanguageDetection(): void
    {
        $settings = new LocaleSettings(
            new Config(
                [
                    'Site' => ['language' => 'en', 'browserDetectLanguage' => 0],
                    'Languages' => ['en' => 'English'],
                ]
            )
        );
        $this->assertFalse($settings->browserLanguageDetectionEnabled());
    }

    /**
     * Confirm that right-to-left setting works as expected.
     *
     * @return void
     */
    public function testRightToLeft(): void
    {
        $settings = new LocaleSettings(
            new Config(
                [
                    'Site' => ['language' => 'en'],
                    'Languages' => ['en' => 'English', 'ar' => 'Arabic'],
                    'LanguageSettings' => ['rtl_langs' => 'ar'],
                ]
            )
        );
        $this->assertFalse($settings->isRightToLeftLocale('en'));
        $this->assertTrue($settings->isRightToLeftLocale('ar'));
    }

    /**
     * Test initialization status.
     *
     * @return void
     */
    public function testInitializationStatusFlagging(): void
    {
        $settings = new LocaleSettings(
            new Config(
                [
                    'Site' => ['language' => 'en'],
                    'Languages' => ['en' => 'English'],
                ]
            )
        );
        $this->assertFalse($settings->isLocaleInitialized('en'));
        $settings->markLocaleInitialized('en');
        $this->assertTrue($settings->isLocaleInitialized('en'));
    }
}
