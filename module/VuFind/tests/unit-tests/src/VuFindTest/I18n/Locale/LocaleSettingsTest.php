<?php

/**
 * LocaleSettings Test Class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
 * Copyright (C) The National Library of Finland 2023-2026.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\I18n\Locale;

use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Config\Config;
use VuFind\I18n\Locale\LocaleSettings;

/**
 * LocaleSettings Test Class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
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
        $this->assertSame(['en'], $settings->getFallbackLocales());
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

    /**
     * Data provider for testFallbackLocalConfigs.
     *
     * @return \Iterator
     */
    public static function fallbackLocalConfigsProvider(): \Iterator
    {
        yield [
            ['en'],
            'en',
            null,
        ];
        yield [
            ['en'],
            'en',
            '',
        ];
        yield [
            ['fi', 'en'],
            'fi',
            null,
        ];
        yield [
            ['fi', 'en'],
            'en',
            'fi',
        ];
        yield [
            ['fi', 'en'],
            'en',
            'fi, en',
        ];
        yield [
            ['de', 'fi', 'en'],
            'en',
            'de,fi',
        ];
        yield [
            ['de', 'fi', 'sv', 'en'],
            'sv',
            'de,fi',
        ];
    }

    /**
     * Confirm default settings for nearly-empty configuration.
     *
     * @param array   $expected          Expected results
     * @param string  $language          Default language
     * @param ?string $fallbackLanguages Fallback languages or null for no setting
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('fallbackLocalConfigsProvider')]
    public function testFallbackLocaleConfigs(array $expected, string $language, ?string $fallbackLanguages): void
    {
        $config = [
            'Site' => ['language' => $language],
            'Languages' => [$language => 'Test'],
        ];
        if (null !== $fallbackLanguages) {
            $config['Site']['fallback_languages'] = $fallbackLanguages;
        }

        $settings = new LocaleSettings(new Config($config));
        $this->assertEquals($expected, $settings->getFallbackLocales());
    }

    /**
     * Data provider for testDetectLocale.
     *
     * @return \Iterator
     */
    public static function detectLocaleProvider(): \Iterator
    {
        // Default:
        yield 'default' => [
            new ServerRequest('GET', 'http://localhost/'),
            [
                'en' => 'English',
                'de' => 'German',
            ],
            'en',
            'en',
        ];

        yield 'query' => [
            (new ServerRequest('GET', 'http://localhost/'))->withQueryParams(['lng' => 'de']),
            [
                'en' => 'English',
                'de' => 'German',
            ],
            'en',
            'de',
        ];
        yield 'invalid query' => [
            (new ServerRequest('GET', 'http://localhost/'))->withQueryParams(['lng' => 'demo']),
            [
                'en' => 'English',
                'de' => 'German',
            ],
            'en',
            'en',
        ];

        yield 'cookie' => [
            (new ServerRequest('GET', 'http://localhost/'))->withCookieParams(['language' => 'de']),
            [
                'en' => 'English',
                'de' => 'German',
            ],
            'en',
            'de',
        ];

        yield 'invalid cookie' => [
            (new ServerRequest('GET', 'http://localhost/'))->withCookieParams(['language' => 'boo']),
            [
                'en' => 'English',
                'de' => 'German',
            ],
            'en',
            'en',
        ];

        yield 'Accept-Language without priority, en' => [
            (new ServerRequest('GET', 'http://localhost/', ['Accept-Language' => 'en,de'])),
            [
                'en' => 'English',
                'de' => 'German',
            ],
            'en',
            'en',
        ];

        yield 'Accept-Language without priority, de' => [
            (new ServerRequest('GET', 'http://localhost/', ['Accept-Language' => 'de,en'])),
            [
                'en' => 'English',
                'de' => 'German',
            ],
            'en',
            'de',
        ];

        yield 'Accept-Language with priority' => [
            (new ServerRequest('GET', 'http://localhost/', ['Accept-Language' => 'en;0.8,de'])),
            [
                'en' => 'English',
                'de' => 'German',
            ],
            'en',
            'de',
        ];

        yield 'Accept-Language with both priorities' => [
            (new ServerRequest('GET', 'http://localhost/', ['Accept-Language' => 'en;0.8, de;0.5'])),
            [
                'en' => 'English',
                'de' => 'German',
            ],
            'en',
            'en',
        ];

        yield 'Accept-Language with reversed priorities' => [
            (new ServerRequest('GET', 'http://localhost/', ['Accept-Language' => 'en;0.5,de;0.8'])),
            [
                'en' => 'English',
                'de' => 'German',
            ],
            'en',
            'de',
        ];

        yield 'Accept-Language with asterisk' => [
            (new ServerRequest('GET', 'http://localhost/', ['Accept-Language' => 'de, *;1.1'])),
            [
                'en' => 'English',
                'de' => 'German',
            ],
            'en',
            'en',
        ];

        yield 'invalid query and cookie' => [
            (new ServerRequest('GET', 'http://localhost/'))
                ->withQueryParams(['lng' => 'bat'])
                ->withCookieParams(['language' => 'de']),
            [
                'en' => 'English',
                'de' => 'German',
            ],
            'en',
            'de',
        ];
    }

    /**
     * Test locale detection.
     *
     * @param ?ServerRequestInterface $request  Request
     * @param array                   $enabled  Enabled locales
     * @param string                  $default  Default locale
     * @param string                  $expected Expected detection result
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('detectLocaleProvider')]
    public function testDetectLocale(
        ?ServerRequestInterface $request,
        array $enabled,
        string $default,
        string $expected
    ): void {
        $settings = new LocaleSettings(
            new Config(
                [
                    'Site' => ['language' => $default],
                    'Languages' => $enabled,
                ]
            )
        );

        $this->assertSame(
            $expected,
            $settings->detectLocale($request)
        );
    }
}
