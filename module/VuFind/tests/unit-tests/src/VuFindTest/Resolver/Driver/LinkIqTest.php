<?php

/**
 * LinkIq resolver driver test.
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
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Resolver\Driver;

use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use VuFind\Http\GuzzleService;
use VuFind\Resolver\Driver\LinkIq;
use VuFindTest\Feature\FixtureTrait;
use VuFindTest\Feature\TranslatorTrait;

/**
 * LinkIq resolver driver test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class LinkIqTest extends \PHPUnit\Framework\TestCase
{
    use FixtureTrait;
    use TranslatorTrait;

    /**
     * Resolver base URL.
     *
     * @var string
     */
    protected string $baseUrl = 'https://localhost/ftf/ftfaccount/ns123456.main.ftf/openurl';

    /**
     * Data provider for testLinkIq.
     *
     * @return \Iterator
     */
    public static function linkIqProvider(): \Iterator
    {
        yield 'no more options' => [null, ''];
        yield 'more options' => ['http://localhost/openurl', 'http://localhost/openurl?issn=00000019'];
    }

    /**
     * Test LinkIq.
     *
     * @param ?string $moreOptionsBaseUrl     Base URL for "More options" link
     * @param string  $moreOptionsExpectedUrl Expected "More options" link in results
     *
     * @return void
     */
    #[DataProvider('linkIqProvider')]
    public function testLinkIq(?string $moreOptionsBaseUrl, string $moreOptionsExpectedUrl): void
    {
        $linkIq = $this->createResolver('linkiq.json', $moreOptionsBaseUrl);

        $openUrl = 'issn=00000019';
        $this->assertSame(
            $this->baseUrl . '?' . $openUrl,
            $linkIq->getResolverUrl($openUrl)
        );

        $results = $linkIq->parseLinks($linkIq->fetchLinks($openUrl));
        $this->assertSame(
            [
                [
                    'title' => 'Test Publisher',
                    'href' => 'http://localhost/public/linkout/v1/ftf?ref=121212',
                    'service_type' => 'getFullTxt',
                    'coverage' => '01-01-1997 – 12-31-1999, 01-01-2001 –  (Not published during year 2000)',
                    'embargo' => '(Embargo: 3 weeks)',
                ],
            ],
            $results
        );

        if ($moreOptionsBaseUrl) {
            $this->assertTrue($linkIq->supportsMoreOptionsLink());
            $this->assertSame(
                $moreOptionsExpectedUrl,
                $linkIq->getResolverUrlForMoreOptions($openUrl)
            );
        } else {
            $this->assertFalse($linkIq->supportsMoreOptionsLink());
            $this->expectExceptionMessage('More options URL unavailable');
            $linkIq->getResolverUrlForMoreOptions($openUrl);
        }
    }

    /**
     * Create resolver with fixture file.
     *
     * @param string  $fixture            Fixture file
     * @param ?string $moreOptionsBaseUrl More options base URL
     *
     * @return LinkIq
     *
     * @throws InvalidArgumentException Fixture file does not exist
     */
    protected function createResolver(string $fixture, ?string $moreOptionsBaseUrl): LinkIq
    {
        $response = new Response(200, body: $this->getFixture("resolver/response/$fixture"));
        $guzzleService = $this->createMock(GuzzleService::class);
        $guzzleService->expects($this->once())
            ->method('get')
            ->with($this->baseUrl . '?issn=00000019', [], null, ['password' => 'secret'])
            ->willReturn($response);

        $translator = $this->getMockTranslator([
            'default' => [
                'openurl_coverage_daterange' => '%%startDate%% – %%endDate%%',
                'openurl_coverage_daterange_joiner' => ', ',
                'openurl_coverage_dateranges_only' => '%%dateranges%%',
                'openurl_coverage_dateranges_statement' => '%%dateranges%% (%%statement%%)',
                'openurl_embargo_statement' => '(Embargo: {unit, select, DAY {{value, plural, =1 {# day}'
                    . ' other {# days}}} WEEK {{value, plural, =1 {# week} other {# weeks}}}'
                    . ' MONTH {{value, plural, =1 {# month} other {# months}}}'
                    . ' YEAR {{value, plural, =1 {# year} other {# years}}} other {}})',
            ],
        ]);

        $linkIq = new LinkIq(
            $this->baseUrl,
            $guzzleService,
            new \VuFind\Date\Converter(),
            'secret',
            $moreOptionsBaseUrl
        );
        $linkIq->setTranslator($translator);
        return $linkIq;
    }
}
