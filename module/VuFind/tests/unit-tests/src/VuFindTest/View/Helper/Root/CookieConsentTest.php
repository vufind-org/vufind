<?php

/**
 * Cookie Consent View Helper Test Class.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022-2026.
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

namespace VuFindTest\View\Helper\Root;

use Laminas\Http\Headers;
use Laminas\View\Helper\EscapeHtmlAttr;
use Laminas\View\Helper\ServerUrl;
use Laminas\View\Renderer\PhpRenderer;
use Symfony\Component\Yaml\Yaml;
use VuFind\Auth\LoginTokenManager;
use VuFind\Cookie\CookieManager;
use VuFind\Http\PhpEnvironment\Request;
use VuFind\View\Helper\Root\CookieConsent;
use VuFind\View\Helper\Root\Url;
use VuFindTest\Feature\FixtureTrait;

/**
 * Cookie Consent View Helper Test Class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class CookieConsentTest extends \PHPUnit\Framework\TestCase
{
    use FixtureTrait;

    /**
     * Test inactive helper.
     *
     * @return void
     */
    public function testHelperInactive(): void
    {
        $helper = $this->getCookieConsent([], expectRender: false);
        $this->assertFalse($helper->isEnabled());
        $this->assertSame('', $helper->render());
        $this->assertEquals($helper, $helper());
    }

    /**
     * Test helper without consent.
     *
     * @return void
     */
    public function testHelperWithoutConsent(): void
    {
        $config = [
            'Cookies' => [
                'consent' => true,
                'consentCategories' => 'essential,matomo',
            ],
        ];

        $helper = $this->getCookieConsent($config);

        // Test helper methods:
        $expectedResults = $this->getExpectedHelperMethodResults($config, []);
        $this->assertSame($expectedResults['getConsentConfig'], $helper->getConsentConfig());
        $this->assertSame($expectedResults['getConsentInformation'], $helper->getConsentInformation());

        $this->assertTrue($helper->isEnabled());
        $this->assertSame('rendered_template', $helper->render('bottom'));
        $this->assertSame(
            ['matomo' => ['matomo']],
            $helper->getControlledVuFindServices()
        );
        $this->assertFalse($helper->isCategoryAccepted('essential'));
        $this->assertFalse($helper->isServiceAllowed('matomo'));
    }

    /**
     * Test helper with consent.
     *
     * @return void
     */
    public function testHelperWithConsent(): void
    {
        $config = [
            'Cookies' => [
                'session_name' => 'vufindsession',
                'consent' => true,
                'consentCategories' => 'essential,matomo',
            ],
        ];

        $cookies = [
            'cc_cookie' => json_encode(
                [
                    'categories' => ['essential', 'matomo'],
                    'consentId' => 'foo123',
                    'consentTimestamp' => gmdate('Y-m-d\TH:i:s.v\Z'),
                    'lastConsentTimestamp' => gmdate('Y-m-d\TH:i:s.v\Z'),
                    'revision' => 0,
                ]
            ),
        ];
        $helper = $this->getCookieConsent($config, $cookies);

        // Test helper methods:
        $expectedResults = $this->getExpectedHelperMethodResults($config, $cookies);
        $this->assertSame($expectedResults['getConsentConfig'], $helper->getConsentConfig());
        $this->assertSame($expectedResults['getConsentInformation'], $helper->getConsentInformation());

        $this->assertFalse($helper->isCategoryAccepted('nonexistent'));
        $this->assertTrue($helper->isCategoryAccepted('essential'));
        $this->assertTrue($helper->isServiceAllowed('matomo'));
        $this->assertSame('rendered_template', $helper->render('bottom'));
    }

    /**
     * Test helper with non-matching consent revision.
     *
     * @return void
     */
    public function testHelperWithBadConsentRevision(): void
    {
        $config = [
            'Cookies' => [
                'session_name' => 'vufindsession',
                'consent' => true,
                'consentCategories' => 'essential,matomo',
            ],
        ];

        $cookies = [
            'cc_cookie' => json_encode(
                [
                    'categories' => ['essential', 'matomo'],
                    'consentId' => 'foo123',
                    'consentTimestamp' => gmdate('Y-m-d\TH:i:s.v\Z'),
                    'lastConsentTimestamp' => gmdate('Y-m-d\TH:i:s.v\Z'),
                    'revision' => -1,
                ]
            ),
        ];

        $helper = $this->getCookieConsent($config, $cookies, expectRender: false);

        // Test helper methods:
        $expectedResults = $this->getExpectedHelperMethodResults($config, $cookies);
        $this->assertSame($expectedResults['getConsentConfig'], $helper->getConsentConfig());
        $this->assertNull($helper->getConsentInformation());

        $this->assertFalse($helper->isCategoryAccepted('nonexistent'));
        $this->assertFalse($helper->isCategoryAccepted('essential'));
        $this->assertFalse($helper->isServiceAllowed('matomo'));
    }

    /**
     * Test helper with a bot.
     *
     * @return void
     */
    public function testHelperWithBot(): void
    {
        $helper = $this->getCookieConsent([], userAgent: 'I am a bot', expectRender: false);
        $this->assertFalse($helper->isEnabled());
    }

    /**
     * Test rendering without position.
     *
     * @return void
     */
    public function testHelperWithoutPosition(): void
    {
        $helper = $this->getCookieConsent([], expectRender: false);
        $this->assertSame('', $helper->render());
    }

    /**
     * Create a CookieConsent helper.
     *
     * @param array  $config       Main configuration
     * @param array  $cookies      Cookies
     * @param bool   $expectRender Expect a call to view renderer?
     * @param string $userAgent    User agent string
     *
     * @return CookieConsent
     */
    protected function getCookieConsent(
        array $config,
        array $cookies = [],
        bool $expectRender = true,
        string $userAgent = 'I could be a real user'
    ): CookieConsent {
        $url = $this->createMock(Url::class);
        $url->method('__invoke')->willReturn('http://localhost/first/vufind');
        $serverUrl = new ServerUrl();
        $serverUrl->setHost('localhost');

        // Create an anonymous class to stub out some behavior:
        $layout = new class () {
            public $rtl = false;

            /**
             * Set layout template or retrieve "layout" view model.
             *
             * If no arguments are given, grabs the "root" or "layout" view model.
             * Otherwise, attempts to set the template for that view model.
             *
             * @param null|string $template Template
             *
             * @return Model|null|self
             */
            public function __invoke($template = null)
            {
                return $this;
            }
        };

        $plugins = [
            'escapeHtmlAttr' => new EscapeHtmlAttr(new \VuFind\Escaper\Escaper()),
            'layout' => $layout,
            'serverUrl' => $serverUrl,
            'url' => $url,
        ];
        $renderer = $this->createMock(PhpRenderer::class);
        $renderer->method('plugin')
            ->willReturnCallback(
                function ($name) use ($plugins) {
                    return $plugins[$name] ?? null;
                }
            );

        $renderer->expects($expectRender ? $this->once() : $this->never())
            ->method('render')
            ->with('CookieConsent/cookie-consent.phtml')
            ->willReturn('rendered_template');

        $mockLoginTokenManager = $this->createMock(LoginTokenManager::class);
        $mockLoginTokenManager->method('getCookieName')->willReturn('loginToken');
        $mockLoginTokenManager->method('getCookieLifetime')->willReturn(321);

        $headers = Headers::fromString('User-Agent: ' . $userAgent);
        $mockRequest = $this->createMock(Request::class);
        $mockRequest->method('getHeaders')
            ->willReturn($headers);

        $helper = new CookieConsent(
            $config,
            $this->getConsentConfig(),
            $this->getCookieManager($config, $cookies),
            new \VuFind\Date\Converter(),
            $mockLoginTokenManager,
            $mockRequest,
            $renderer
        );

        return $helper;
    }

    /**
     * Get expected helper method results.
     *
     * @param array $config  Main config
     * @param array $cookies Cookies
     *
     * @return array
     */
    protected function getExpectedHelperMethodResults(array $config, array $cookies): array
    {
        $categoryConfig = [
            'essential' => [
                'Title' => 'CookieConsent::essential_cookies_title_html',
                'Description' => 'CookieConsent::essential_cookies_description_html',
                'DefaultEnabled' => true,
                'Essential' => true,
            ],
            'matomo' => [
                'Title' => 'CookieConsent::analytics_cookies_title_html',
                'Description' => 'CookieConsent::analytics_cookies_description_html',
                'DefaultEnabled' => false,
                'Essential' => false,
                'ControlVuFindServices' => [
                    'matomo',
                ],
                'AutoClearCookies' => [
                    [
                        'Name' => '/^_pk_/',
                    ],
                ],
            ],
        ];
        $enabledCategories = explode(',', $config['Cookies']['consentCategories'] ?? 'essential');
        $categoryConfig = array_intersect_key($categoryConfig, array_flip($enabledCategories));
        $jsCategoryConfig = $categoryConfig;
        foreach ($jsCategoryConfig as &$category) {
            unset($category['Title']);
            unset($category['Description']);
        }
        unset($category);
        $consentInformation = null;
        if ($cookie = $cookies['cc_cookie'] ?? null) {
            $consentInformation = json_decode($cookie, true);
            foreach ($consentInformation['categories'] as $category) {
                $consentInformation['categoriesTranslated'][] = substr($categoryConfig[$category]['Title'], 15);
            }
            $dateConverter = new \VuFind\Date\Converter();
            $consentInformation['lastConsentDateTime'] = $dateConverter->convertToDisplayDateAndTime(
                'Y-m-d\TH:i:s.vP',
                str_replace('Z', '+00:00', $consentInformation['lastConsentTimestamp'])
            );
            $consentInformation['domain'] = 'localhost';
            $consentInformation['path'] = '/first';
        }
        return [
            'getConsentConfig' => [
                'cookieName' => 'cc_cookie',
                'autoClearCookies' => true,
                'revision' => 0,
                'cookieExpirationDays' => 182,
                'categoryConfig' => $jsCategoryConfig,
                'controlledVuFindServices' => array_intersect_key(
                    [
                        'matomo' => [
                            'matomo',
                        ],
                    ],
                    array_flip($enabledCategories)
                ),
            ],
            'getConsentInformation' => $consentInformation,
        ];
    }

    /**
     * Get cookie consent configuration.
     *
     * @param string $filename Consent config fixture name
     *
     * @return array
     */
    protected function getConsentConfig(
        string $filename = 'CookieConsent.yaml'
    ): array {
        return Yaml::parse(
            $this->getFixture("configs/cookieconsent/$filename")
        )['CookieConsent'];
    }

    /**
     * Get cookie manager.
     *
     * @param array $config  Main configuration
     * @param array $cookies Cookies
     *
     * @return CookieManager
     */
    protected function getCookieManager(array $config, array $cookies): CookieManager
    {
        return new CookieManager(
            $cookies,
            '/first',
            'localhost',
            false,
            $config['Cookies']['session_name'] ?? 'SESS',
        );
    }
}
