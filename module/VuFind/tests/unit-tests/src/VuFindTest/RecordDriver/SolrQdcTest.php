<?php

/**
 * SolrQdc Record Driver Test Class.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2026.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\RecordDriver;

use PHPUnit\Framework\Attributes\DataProvider;
use VuFind\I18n\Locale\LocaleSettings;
use VuFind\RecordDriver\SolrQdc;

/**
 * SolrQdc Record Driver Test Class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SolrQdcTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Data provider for testMethods.
     *
     * @return \Iterator
     */
    public static function methodsProvider(): \Iterator
    {
        yield 'en' => [
            'getAbstractNotes',
            'en',
            'en',
            [],
            [
                'Abstract in English.',
                'Another abstract in English.',
            ],
        ];

        yield 'fi' => [
            'getAbstractNotes',
            'fi',
            'fi',
            [],
            [
                'Abstrakti suomeksi.',
            ],
        ];

        yield 'sv with en-gb as fallback' => [
            'getAbstractNotes',
            'sv',
            'sv',
            ['en-gb', 'fi'],
            [
                'Abstract in British English.',
            ],
        ];

        yield 'sv with en as default' => [
            'getAbstractNotes',
            'sv',
            'en',
            ['fi'],
            [
                'Abstract in English.',
                'Another abstract in English.',
            ],
        ];

        yield 'sv with fi as default' => [
            'getAbstractNotes',
            'sv',
            'fi',
            ['en'],
            [
                'Abstrakti suomeksi.',
            ],
        ];

        yield 'no fallback' => [
            'getAbstractNotes',
            'sv',
            'sv',
            [],
            [
                'Abstrakti suomeksi.',
                'Abstract in English.',
                'Another abstract in English.',
                'Abstract in British English.',
            ],
        ];
    }

    /**
     * Test driver methods that return locale-specific data.
     *
     * @param string $method            Method
     * @param string $language          Language
     * @param string $defaultLanguage   Default language
     * @param array  $fallbackLanguages Fallback languages
     * @param mixed  $expected          Expected result
     *
     * @return void
     */
    #[DataProvider('methodsProvider')]
    public function testLocaleSpecificMethods(
        string $method,
        string $language,
        string $defaultLanguage,
        array $fallbackLanguages,
        mixed $expected
    ): void {
        $driver = $this->getDriver($language, $defaultLanguage, $fallbackLanguages);
        $this->assertSame(
            $expected,
            $driver->$method()
        );
    }

    /**
     * Get a record driver.
     *
     * @param string  $language          Language
     * @param string  $defaultLanguage   Default language
     * @param array   $fallbackLanguages Fallback languages
     * @param ?string $fixture           Metadata fixture
     *
     * @return SolrQdc
     */
    protected function getDriver(
        string $language,
        string $defaultLanguage,
        array $fallbackLanguages,
        ?string $fixture = 'qdc/qdc.xml'
    ): SolrQdc {
        $fixture = $this->getFixture($fixture);
        $record = new SolrQdc();
        $record->setRawData(['id' => '12345', 'fullrecord' => $fixture]);

        $localeSettings = $this->createMock(LocaleSettings::class);
        $localeSettings
            ->method('getUserLocale')
            ->willReturn($language);
        $localeSettings
            ->method('getDefaultLocale')
            ->willReturn($defaultLanguage);
        $localeSettings
            ->method('getFallbackLocales')
            ->willReturn($fallbackLanguages);
        $record->setLocaleSettings($localeSettings);

        return $record;
    }
}
