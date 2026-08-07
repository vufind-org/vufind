<?php

/**
 * CookieConsent view helper.
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
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Renderer\RendererInterface;
use VuFind\Auth\LoginTokenManager;
use VuFind\Config\Feature\ExplodeSettingTrait;
use VuFind\Cookie\CookieManager;
use VuFind\Date\Converter as DateConverter;
use VuFind\Http\PhpEnvironment\Request;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;
use VuFind\ServiceManager\Factory\Autowire;

use function in_array;
use function is_string;

/**
 * CookieConsent view helper.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class CookieConsent implements TranslatorAwareInterface
{
    use ExplodeSettingTrait;
    use TranslatorAwareTrait;

    /**
     * Consent cookie name.
     *
     * @var string
     */
    protected $consentCookieName;

    /**
     * Consent cookie expiration time (days).
     *
     * @var int
     */
    protected $consentCookieExpiration;

    /**
     * Server name.
     *
     * @var string
     */
    protected $hostName = null;

    /**
     * Category configuration elements exposed to client JavaScript.
     *
     * @var array
     */
    protected array $jsCategoryConfigElements = [
        'DefaultEnabled',
        'Essential',
        'ControlVuFindServices',
        'AutoClearCookies',
    ];

    /**
     * Constructor.
     *
     * @param array             $config            Main configuration
     * @param array             $consentConfig     Cookie consent configuration
     * @param CookieManager     $cookieManager     Cookie manager
     * @param DateConverter     $dateConverter     Date converter
     * @param LoginTokenManager $loginTokenManager Login token manager
     * @param Request           $request           Request
     * @param RendererInterface $renderer          View renderer
     */
    public function __construct(
        #[Autowire(config: 'config')]
        protected array $config,
        #[Autowire(config: 'CookieConsent', path: 'CookieConsent', configType: 'yaml')]
        protected array $consentConfig,
        protected CookieManager $cookieManager,
        protected DateConverter $dateConverter,
        protected LoginTokenManager $loginTokenManager,
        #[Autowire(service: 'Request')]
        protected Request $request,
        #[Autowire(service: 'ViewRenderer')]
        protected RendererInterface $renderer,
    ) {
        $this->consentCookieName = $this->consentConfig['CookieName'] ?? 'cc_cookie';
        $this->consentCookieExpiration = $this->consentConfig['CookieExpiration'] ?? 182; // half a year
        // Filter out disabled categories from the configuration:
        $this->consentConfig['Categories'] = array_intersect_key(
            $this->consentConfig['Categories'] ?? [],
            array_flip($this->getEnabledCategories())
        );
    }

    /**
     * Return this object.
     *
     * @return \VuFind\View\Helper\Root\CookieConsent
     */
    public function __invoke(): \VuFind\View\Helper\Root\CookieConsent
    {
        return $this;
    }

    /**
     * Render cookie consent.
     *
     * @param ?string $type Dialog type (only valid option is 'bottom'; null value will disable cookie consent)
     *
     * @return string
     */
    public function render(?string $type = null): string
    {
        // Don't render anything unless enabled and 'bottom' given as the type. Checking the type avoids rendering
        // inside the head element if layout template has not been properly updated.
        if (!$this->isEnabled() || 'bottom' !== $type) {
            return '';
        }

        // Hide from bots:
        if ($this->consentConfig['HideFromBots'] ?? true) {
            $headers = $this->request->getHeaders();
            if ($headers->has('User-Agent')) {
                $agent = $headers->get('User-Agent')->getFieldValue();
                $crawlerDetect = new \Jaybizzle\CrawlerDetect\CrawlerDetect();
                if ($crawlerDetect->isCrawler($agent)) {
                    return '';
                }
            }
        }

        return $this->renderer->render('CookieConsent/cookie-consent.phtml');
    }

    /**
     * Check if the cookie consent mechanism is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return (bool)($this->config['Cookies']['consent'] ?? false);
    }

    /**
     * Get controlled VuFind services (services integrated into VuFind).
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
     * Check if a cookie category is accepted.
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
     * Check if a VuFind service is allowed.
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
     * Get information about user's given consent.
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
                    ?: $this->renderer->plugin('serverUrl')->getHost();
                $result['path'] = $this->cookieManager->getPath();
                return $result;
            }
        }
        return null;
    }

    /**
     * Check for consent given for another revision.
     *
     * @return bool
     */
    public function isInvalidConsentRevision(): bool
    {
        if ($consent = $this->getCurrentConsent(true)) {
            return ($consent['revision'] ?? null) !== $this->getConsentRevision();
        }
        return false;
    }

    /**
     * Get the enabled cookie categories.
     *
     * @return array
     */
    public function getEnabledCategories(): array
    {
        $categories = $this->config['Cookies']['consentCategories'] ?? '';
        return $categories ? $this->explodeListSetting($categories) : ['essential'];
    }

    /**
     * Get consent category configuration.
     *
     * @return array
     */
    public function getCategoryConfig(): array
    {
        $categoryConfig = $this->consentConfig['Categories'];
        // Replace placeholders:
        $placeholders = $this->getPlaceholders();
        $placeholderSearch = array_keys($placeholders);
        $placeholderReplace = array_values($placeholders);
        array_walk_recursive(
            $categoryConfig,
            function (&$value) use ($placeholderSearch, $placeholderReplace): void {
                if (is_string($value)) {
                    $value = str_replace(
                        $placeholderSearch,
                        $placeholderReplace,
                        $value
                    );
                }
            }
        );
        return $categoryConfig;
    }

    /**
     * Check if non-essential categories are available.
     *
     * @return bool
     */
    public function hasNonEssentialCategories(): bool
    {
        foreach ($this->consentConfig['Categories'] as $category) {
            if (!($category['Essential'] ?? false)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get cookie consent configuration.
     *
     * @return array
     */
    public function getConsentConfig(): array
    {
        $categoryConfig = $this->consentConfig['Categories'];
        foreach ($categoryConfig as &$category) {
            $category = array_intersect_key($category, array_flip($this->jsCategoryConfigElements));
        }
        unset($category);

        return [
            'cookieName' => $this->consentCookieName,
            'autoClearCookies' => $this->consentConfig['AutoClear'] ?? true,
            'revision' => $this->getConsentRevision(),
            'cookieExpirationDays' => $this->consentCookieExpiration,
            'categoryConfig' => $categoryConfig,
            'controlledVuFindServices' => $this->getControlledVuFindServices(),
        ];
    }

    /**
     * Get placeholders for strings.
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
     * Get current host name.
     *
     * @return string
     */
    protected function getHostName(): string
    {
        if (null === $this->hostName) {
            $this->hostName = $this->renderer->plugin('serverUrl')->getHost();
        }
        return $this->hostName;
    }

    /**
     * Get current consent revision.
     *
     * @return int
     */
    protected function getConsentRevision(): int
    {
        return (int)($this->config['Cookies']['consentRevision'] ?? 0);
    }

    /**
     * Get current consent data.
     *
     * @param bool $ignoreRevision Ignore revision mismatch?
     *
     * @return array
     */
    protected function getCurrentConsent(bool $ignoreRevision = false): array
    {
        if ($consentJson = $this->cookieManager->get($this->consentCookieName)) {
            if ($consent = json_decode($consentJson, true)) {
                if ($ignoreRevision || ($consent['revision'] ?? null) === $this->getConsentRevision()) {
                    return $consent;
                }
            }
        }
        return [];
    }
}
