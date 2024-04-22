<?php

/**
 * CookieConsent view helper
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
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\View\Helper\Root;

use VuFind\Auth\LoginTokenManager;
use VuFind\Cookie\CookieManager;
use VuFind\Date\Converter as DateConverter;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;

use function in_array;
use function is_string;

/**
 * CookieConsent view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class CookieConsent extends \Laminas\View\Helper\AbstractHelper implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    /**
     * Consent cookie name
     *
     * @var string
     */
    protected $consentCookieName;

    /**
     * Consent cookie expiration time (days)
     *
     * @var int
     */
    protected $consentCookieExpiration;

    /**
     * Server name
     *
     * @var string
     */
    protected $hostName = null;

    /**
     * Constructor
     *
     * @param array             $config            Main configuration
     * @param array             $consentConfig     Cookie consent configuration
     * @param CookieManager     $cookieManager     Cookie manager
     * @param DateConverter     $dateConverter     Date converter
     * @param LoginTokenManager $loginTokenManager Login token manager
     */
    public function __construct(
        protected array $config,
        protected array $consentConfig,
        protected CookieManager $cookieManager,
        protected DateConverter $dateConverter,
        protected LoginTokenManager $loginTokenManager
    ) {
        $this->consentCookieName = $this->consentConfig['CookieName'] ?? 'cc_cookie';
        $this->consentCookieExpiration = $this->consentConfig['CookieExpiration'] ?? 182; // half a year
    }

    /**
     * Return this object
     *
     * @return \VuFind\View\Helper\Root\CookieConsent
     */
    public function __invoke(): \VuFind\View\Helper\Root\CookieConsent
    {
        return $this;
    }

    /**
     * Render cookie consent initialization script
     *
     * @return string
     */
    public function render(): string
    {
        if (!$this->isEnabled()) {
            return '';
        }
        $params = [
            'consentConfig' => $this->consentConfig,
            'consentCookieName' => $this->consentCookieName,
            'consentCookieExpiration' => $this->consentCookieExpiration,
            'placeholders' => $this->getPlaceholders(),
            'cookieManager' => $this->cookieManager,
            'consentDialogConfig' => $this->getConsentDialogConfig(),
            'controlledVuFindServices' => $this->getControlledVuFindServices(),
        ];
        return $this->getView()->render('Helpers/cookie-consent.phtml', $params);
    }

    /**
     * Check if the cookie consent mechanism is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return !empty($this->config['Cookies']['consent']);
    }

    /**
     * Get controlled VuFind services (services integrated into VuFind)
     *
     * @return array
     */
    public function getControlledVuFindServices(): array
    {
        $controlledVuFindServices = [];
        foreach ($this->consentConfig['Categories'] ?? [] as $name => $category) {
            if ($serviceNames = $category['ControlVuFindServices'] ?? []) {
                $controlledVuFindServices[$name] = [
                    ...$controlledVuFindServices[$name] ?? [], ...$serviceNames,
                ];
            }
        }
        return $controlledVuFindServices;
    }

    /**
     * Check if a cookie category is accepted
     *
     * Checks the consent cookie for accepted category information
     *
     * @param string $category Category
     *
     * @return bool
     */
    public function isCategoryAccepted(string $category): bool
    {
        if (!isset($this->consentConfig['Categories'][$category])) {
            return false;
        }
        if ($consent = $this->getCurrentConsent()) {
            return in_array($category, (array)($consent['categories'] ?? []));
        }
        return false;
    }

    /**
     * Check if a VuFind service is allowed
     *
     * @param string $service Service
     *
     * @return bool
     */
    public function isServiceAllowed(string $service): bool
    {
        foreach ($this->getControlledVuFindServices() as $category => $services) {
            if (
                in_array($service, $services)
                && $this->isCategoryAccepted($category)
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get information about user's given consent
     *
     * The following fields are guaranteed to be returned if consent has been given:
     *
     * - consentId            Consent ID
     * - domain               Cookie domain
     * - path                 Cookie path
     * - lastConsentTimestamp Timestamp the consent was given or updated
     * - lastConsentDateTime  Formatted date and time the consent was given or
     *                        updated
     * - categories           Categories allowed in the consent
     * - categoriesTranslated Translated names of categories allowed in the consent
     *
     * @return ?array Associative array or null if no consent has been given or it
     * cannot be decoded
     */
    public function getConsentInformation(): ?array
    {
        if ($result = $this->getCurrentConsent()) {
            if (
                !empty($result['consentId'])
                && !empty($result['lastConsentTimestamp'])
                && !empty($result['categories'])
            ) {
                $result['categories'] = (array)$result['categories'];
                foreach ($result['categories'] as $category) {
                    $result['categoriesTranslated'][]
                        = $this->translate(
                            $this->consentConfig['Categories'][$category]['Title']
                            ?? 'Unknown'
                        );
                }
                $result['lastConsentDateTime']
                    = $this->dateConverter->convertToDisplayDateAndTime(
                        'Y-m-d\TH:i:s.vP',
                        str_replace('Z', '+00:00', $result['lastConsentTimestamp'])
                    );
                $result['domain'] = $this->cookieManager->getDomain()
                    ?: $this->getView()->plugin('serverUrl')->getHost();
                $result['path'] = $this->cookieManager->getPath();
                return $result;
            }
        }
        return null;
    }

    /**
     * Get configuration for the consent dialog
     *
     * @return array
     */
    protected function getConsentDialogConfig(): array
    {
        $descriptionPlaceholders = $this->getDescriptionPlaceholders();
        $categories = $this->config['Cookies']['consentCategories'] ?? '';
        $enabledCategories = $categories ? explode(',', $categories) : ['essential'];
        $lang = $this->getTranslatorLocale();
        $cookieSettings = [
            'name' => $this->consentCookieName,
            'path' => $this->cookieManager->getPath(),
            'expiresAfterDays' => $this->consentCookieExpiration,
            'sameSite' => $this->cookieManager->getSameSite(),
        ];
        // Set domain only if we have a value for it to avoid overriding the default
        // (i.e. window.location.hostname):
        if ($domain = $this->cookieManager->getDomain()) {
            $cookieSettings['domain'] = $domain;
        }
        $rtl = ($this->getView()->plugin('layout'))()->rtl;
        $consentDialogConfig = [
            'autoClearCookies' => $this->consentConfig['AutoClear'] ?? true,
            'manageScriptTags' => $this->consentConfig['ManageScripts'] ?? true,
            'hideFromBots' => $this->consentConfig['HideFromBots'] ?? true,
            'cookie' => $cookieSettings,
            'revision' => $this->getConsentRevision(),
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
            'language' => [
                'default' => $lang,
                'autoDetect' => false,
                'rtl' => $rtl,
                'translations' => [
                    $lang => [
                        'consentModal' => [
                            'title' => $this->translate(
                                'CookieConsent::popup_title_html'
                            ),
                            'description' => $this->translate(
                                'CookieConsent::popup_description_html',
                                $descriptionPlaceholders
                            ),
                            'revisionMessage' => $this->translate(
                                'CookieConsent::popup_revision_message_html'
                            ),
                            'acceptAllBtn' => $this->translate(
                                'CookieConsent::Accept All Cookies'
                            ),
                            'acceptNecessaryBtn' => $this->translate(
                                'CookieConsent::Accept Only Essential Cookies'
                            ),
                        ],
                        'preferencesModal' => [
                            'title' => $this->translate(
                                'CookieConsent::cookie_settings_html'
                            ),
                            'savePreferencesBtn' => $this->translate(
                                'CookieConsent::Save Settings'
                            ),
                            'acceptAllBtn' => $this->translate(
                                'CookieConsent::Accept All Cookies'
                            ),
                            'acceptNecessaryBtn' => $this->translate(
                                'CookieConsent::Accept Only Essential Cookies'
                            ),
                            'closeIconLabel' => $this->translate('close'),
                            'flipButtons' => $rtl,
                            'sections' => [
                                [
                                    'description' => $this->translate(
                                        'CookieConsent::category_description_html',
                                        $descriptionPlaceholders
                                    ),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $headers = [
            'name' => $this->translate('CookieConsent::Name'),
            'domain' => $this->translate('CookieConsent::Domain'),
            'desc' => $this->translate('CookieConsent::Description'),
            'exp' => $this->translate('CookieConsent::Expiration'),
        ];
        $categoryData = $this->consentConfig['Categories'] ?? [];
        foreach ($categoryData as $categoryId => $categoryConfig) {
            if ($enabledCategories && !in_array($categoryId, $enabledCategories)) {
                continue;
            }
            $consentDialogConfig['categories'][$categoryId] = [
                'enabled' => ($categoryConfig['Essential'] ?? false)
                    || ($categoryConfig['DefaultEnabled'] ?? false),
                'readOnly' => $categoryConfig['Essential'] ?? false,
            ];
            $section = [
                'title' => $this->translate($categoryConfig['Title'] ?? ''),
                'description'
                    => $this->translate($categoryConfig['Description'] ?? ''),
                'linkedCategory' => $categoryId,
                'cookieTable' => [
                    'headers' => $headers,
                ],
            ];
            foreach ($categoryConfig['Cookies'] ?? [] as $cookie) {
                $name = $cookie['Name'];
                if (!empty($cookie['ThirdParty'])) {
                    $name .= ' ('
                        . $this->translate('CookieConsent::third_party_html') . ')';
                }
                switch ($cookie['Expiration']) {
                    case 'never':
                        $expiration
                            = $this->translate('CookieConsent::expiration_never');
                        break;
                    case 'session':
                        $expiration
                            = $this->translate('CookieConsent::expiration_session');
                        break;
                    default:
                        if (!empty($cookie['ExpirationUnit'])) {
                            $expiration = ' ' . $this->translate(
                                'CookieConsent::expiration_unit_'
                                . $cookie['ExpirationUnit'],
                                ['%%expiration%%' => $cookie['Expiration']],
                                $cookie['Expiration'] . ' '
                                . $cookie['ExpirationUnit']
                            );
                        } else {
                            $expiration = $cookie['Expiration'];
                        }
                }
                $section['cookieTable']['body'][] = [
                    'name' => $name,
                    'domain' => $cookie['Domain'],
                    'desc' => $this->translate($cookie['Description'] ?? ''),
                    'exp' => $expiration,
                ];
            }
            if ($autoClear = $categoryConfig['AutoClearCookies'] ?? []) {
                $section['autoClear']['cookies'] = $autoClear;
            }

            $translationsElem = &$consentDialogConfig['language']['translations'];
            $translationsElem[$lang]['preferencesModal']['sections'][] = $section;
            unset($translationsElem);
        }
        // Replace placeholders:
        $placeholders = $this->getPlaceholders();
        $placeholderSearch = array_keys($placeholders);
        $placeholderReplace =  array_values($placeholders);
        array_walk_recursive(
            $consentDialogConfig,
            function (&$value) use ($placeholderSearch, $placeholderReplace) {
                if (is_string($value)) {
                    $value = str_replace(
                        $placeholderSearch,
                        $placeholderReplace,
                        $value
                    );
                }
            }
        );

        return $consentDialogConfig;
    }

    /**
     * Get placeholders for strings
     *
     * @return array
     */
    protected function getPlaceholders(): array
    {
        return [
            '{{consent_cookie_name}}' => $this->consentCookieName,
            '{{consent_cookie_expiration}}' => $this->consentCookieExpiration,
            '{{current_host_name}}' => $this->getHostName(),
            '{{vufind_cookie_domain}}' => $this->cookieManager->getDomain()
                ?: $this->getHostName(),
            '{{vufind_session_cookie}}' => $this->cookieManager->getSessionName(),
            '{{vufind_login_token_cookie_name}}' => $this->loginTokenManager->getCookieName(),
            '{{vufind_login_token_cookie_expiration}}' => $this->loginTokenManager->getCookieLifetime(),
        ];
    }

    /**
     * Get placeholders for description translations
     *
     * @return array
     */
    protected function getDescriptionPlaceholders(): array
    {
        $root = rtrim(($this->getView()->plugin('url'))('home'), '/');
        $escapeHtmlAttr = $this->getView()->plugin('escapeHtmlAttr');
        return [
            '%%siteRoot%%' => $root,
            '%%siteRootAttr%%' => $escapeHtmlAttr($root),
        ];
    }

    /**
     * Get current host name
     *
     * @return string
     */
    protected function getHostName(): string
    {
        if (null === $this->hostName) {
            $this->hostName = $this->getView()->plugin('serverUrl')->getHost();
        }
        return $this->hostName;
    }

    /**
     * Get current consent revision
     *
     * @return int
     */
    protected function getConsentRevision(): int
    {
        return (int)($this->config['Cookies']['consentRevision'] ?? 0);
    }

    /**
     * Get current consent data
     *
     * @return array
     */
    protected function getCurrentConsent(): array
    {
        if ($consentJson = $this->cookieManager->get($this->consentCookieName)) {
            if ($consent = json_decode($consentJson, true)) {
                if (($consent['revision'] ?? null) === $this->getConsentRevision()) {
                    return $consent;
                }
            }
        }
        return [];
    }
}
