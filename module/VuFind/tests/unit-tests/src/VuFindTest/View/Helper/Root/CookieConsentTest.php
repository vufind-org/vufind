<?php

/**
 * Cookie Consent View Helper Test Class
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\View\Helper\Root;

use Laminas\View\Helper\EscapeHtmlAttr;
use Laminas\View\Helper\Layout;
use Laminas\View\Helper\ServerUrl;
use Laminas\View\Renderer\PhpRenderer;
use Symfony\Component\Yaml\Yaml;
use VuFind\Auth\LoginTokenManager;
use VuFind\Cookie\CookieManager;
use VuFind\View\Helper\Root\CookieConsent;
use VuFind\View\Helper\Root\Url;
use VuFindTest\Feature\FixtureTrait;

/**
 * Cookie Consent View Helper Test Class
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
     * Test inactive helper
     *
     * @return void
     */
    public function testHelperInactive(): void
    {
        $helper = $this->getCookieConsent([]);
        $this->assertFalse($helper->isEnabled());
        $this->assertEquals('', $helper->render());
        $this->assertEquals($helper, $helper());
    }

    /**
     * Test helper without consent
     *
     * @return void
     */
    public function testHelperWithoutConsent(): void
    {
        $helper = $this->getCookieConsent(
            [
                'Cookies' => [
                    'consent' => true,
                    'consentCategories' => 'essential,matomo',
                ],
            ]
        );
        $helper->getView()->expects($this->once())
            ->method('render')
            ->willReturn('rendered_template');

        $this->assertTrue($helper->isEnabled());
        $this->assertEquals('rendered_template', $helper->render());
        $this->assertEquals(
            ['matomo' => ['matomo']],
            $helper->getControlledVuFindServices()
        );
        $this->assertFalse($helper->isCategoryAccepted('essential'));
        $this->assertFalse($helper->isServiceAllowed('matomo'));
    }

    /**
     * Test helper with consent
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
                    'consentTimestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                    'lastConsentTimestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                    'revision' => 0,
                ]
            ),
        ];
        $expectedParams = $this->getExpectedRenderParams(
            'CookieConsent.yaml',
            $config,
            $cookies
        );
        $helper = $this->getCookieConsent($config, $cookies);

        $helper->getView()->expects($this->once())
            ->method('render')
            ->with('Helpers/cookie-consent.phtml', $expectedParams)
            ->willReturn('rendered_template');
        $this->assertFalse($helper->isCategoryAccepted('nonexistent'));
        $this->assertTrue($helper->isCategoryAccepted('essential'));
        $this->assertTrue($helper->isServiceAllowed('matomo'));
        $this->assertEquals('rendered_template', $helper->render());
    }

    /**
     * Test helper with non-matching consent revision
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
                    'consentTimestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                    'lastConsentTimestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                    'revision' => -1,
                ]
            ),
        ];

        $helper = $this->getCookieConsent($config, $cookies);
        $this->assertFalse($helper->isCategoryAccepted('nonexistent'));
        $this->assertFalse($helper->isCategoryAccepted('essential'));
        $this->assertFalse($helper->isServiceAllowed('matomo'));
    }

    /**
     * Create a CookieConsent helper
     *
     * @param array  $config            Main configuration
     * @param array  $cookies           Cookies
     * @param string $consentConfigName Consent config fixture name
     *
     * @return CookieConsent
     */
    protected function getCookieConsent(
        array $config,
        array $cookies = [],
        string $consentConfigName = 'CookieConsent.yaml'
    ): CookieConsent {
        $url = $this->getMockBuilder(Url::class)->getMock();
        $url->expects($this->any())
            ->method('__invoke')
            ->will($this->returnValue('http://localhost/first/vufind'));
        $serverUrl = new ServerUrl();
        $serverUrl->setHost('localhost');

        // Create an anonymous class to stub out some behavior:
        $layout = new class () {
            public $rtl = false;

            /**
             * Set layout template or retrieve "layout" view model
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
            'escapeHtmlAttr' => new EscapeHtmlAttr(),
            'layout' => $layout,
            'serverUrl' => $serverUrl,
            'url' => $url,
        ];
        $view = $this->getMockBuilder(PhpRenderer::class)->getMock();
        $view->expects($this->any())
            ->method('plugin')
            ->willReturnCallback(
                function ($name) use ($plugins) {
                    return $plugins[$name] ?? null;
                }
            );

        $mockLoginTokenManager = $this->getMockBuilder(LoginTokenManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockLoginTokenManager->expects($this->any())
            ->method('getCookieName')
            ->willReturn('loginToken');
        $mockLoginTokenManager->expects($this->any())
            ->method('getCookieLifetime')
            ->willReturn(321);

        $helper = new CookieConsent(
            $config,
            $this->getConsentConfig($consentConfigName),
            $this->getCookieManager($config, $cookies),
            new \VuFind\Date\Converter(),
            $mockLoginTokenManager
        );
        $helper->setView($view);
        return $helper;
    }

    /**
     * Get expected params for the render call
     *
     * @param string $consentConfigName Consent config fixture name
     * @param array  $config            Main config
     * @param array  $cookies           Cookies
     *
     * @return array
     */
    protected function getExpectedRenderParams(
        string $consentConfigName,
        array $config,
        array $cookies
    ): array {
        $consentConfig = $this->getConsentConfig($consentConfigName);
        $language = [
            'default' => 'en',
            'autoDetect' => false,
            'translations' => [
                'en' => [
                    'consentModal' => [
                        'title' => 'popup_title_html',
                        'description' => 'popup_description_html',
                        'revisionMessage' => 'popup_revision_message_html',
                        'acceptAllBtn' => 'Accept All Cookies',
                        'acceptNecessaryBtn' => 'Accept Only Essential Cookies',
                    ],
                    'preferencesModal' => [
                        'title' => 'cookie_settings_html',
                        'savePreferencesBtn' => 'Save Settings',
                        'acceptAllBtn' => 'Accept All Cookies',
                        'acceptNecessaryBtn' => 'Accept Only Essential Cookies',
                        'closeIconLabel' => 'close',
                        'flipButtons' => false,
                        'sections' => [
                            [
                                'description' => 'category_description_html',
                            ],
                            [
                                'title' => 'essential_cookies_title_html',
                                'description'
                                    => 'essential_cookies_description_html',
                                'linkedCategory' => 'essential',
                                'cookieTable' => [
                                    'headers' => [
                                        'name' => 'Name',
                                        'domain' => 'Domain',
                                        'desc' => 'Description',
                                        'exp' => 'Expiration',
                                    ],
                                    'body' => [
                                        [
                                            'name' => 'cc_cookie',
                                            'domain' => 'localhost',
                                            'desc'
                                                => 'cookie_description_consent_html',
                                            'exp' => ' 182 days',
                                        ],
                                        [
                                            'name' => 'vufindsession',
                                            'domain' => 'localhost',
                                            'desc' => 'cookie_description_session'
                                                . '_html',
                                            'exp' => 'expiration_session',
                                        ],
                                        [
                                            'name' => 'evercookie',
                                            'domain' => 'localhost',
                                            'desc' => 'Forever',
                                            'exp' => 'expiration_never',
                                        ],
                                        [
                                            'name' => 'custom',
                                            'domain' => 'localhost',
                                            'desc' => 'Weird expiration',
                                            'exp' => '12-13 months',
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'title' => 'analytics_cookies_title_html',
                                'description'
                                    => 'analytics_cookies_description_html',
                                'linkedCategory' => 'matomo',
                                'cookieTable' => [
                                    'headers' => [
                                        'name' => 'Name',
                                        'domain' => 'Domain',
                                        'desc' => 'Description',
                                        'exp' => 'Expiration',
                                    ],
                                    'body' => [
                                        [
                                            'name' => '_pk_id.* (third_party_html)',
                                            'domain' => 'localhost',
                                            'desc' => 'cookie_description_matomo_id_'
                                                . 'html',
                                            'exp' => ' 13 months',
                                        ],
                                    ],
                                ],
                                'autoClear' => [
                                    'cookies' => [
                                        0 => [
                                            'Name' => '/^_pk_/',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'rtl' => false,
        ];
        return [
            'consentConfig' => $consentConfig,
            'consentCookieName' => 'cc_cookie',
            'consentCookieExpiration' => 182,
            'placeholders' => [
                '{{consent_cookie_name}}' => 'cc_cookie',
                '{{consent_cookie_expiration}}' => 182,
                '{{current_host_name}}' => 'localhost',
                '{{vufind_cookie_domain}}' => 'localhost',
                '{{vufind_session_cookie}}' => 'vufindsession',
                '{{vufind_login_token_cookie_name}}' => 'loginToken',
                '{{vufind_login_token_cookie_expiration}}' => 321,
            ],
            'cookieManager' => $this->getCookieManager($config, $cookies),
            'consentDialogConfig' => [
                'autoClearCookies' => true,
                'manageScriptTags' => true,
                'hideFromBots' => true,
                'cookie' => [
                    'name' => 'cc_cookie',
                    'domain' => 'localhost',
                    'path' => '/first',
                    'expiresAfterDays' => 182,
                    'sameSite' => 'Lax',
                ],
                'revision' => 0,
                'guiOptions' => [
                    'consentModal' => [
                        'layout' => 'bar',
                        'position' => 'bottom center',
                        'transition' => 'slide',
                    ],
                    'preferencesModal' => [
                        'layout' => 'box',
                        'transition' => 'none',
                    ],
                ],
                'language' => $language,
                'categories' => [
                    'essential' => [
                        'enabled' => true,
                        'readOnly' => true,
                    ],
                    'matomo' => [
                        'enabled' => false,
                        'readOnly' => false,
                    ],
                ],
            ],
            'controlledVuFindServices' => [
                'matomo' => [
                    'matomo',
                ],
            ],
        ];
    }

    /**
     * Get cookie consent configuration
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
     * Get cookie manager
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
