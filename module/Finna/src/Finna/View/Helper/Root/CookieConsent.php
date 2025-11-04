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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace Finna\View\Helper\Root;

use function in_array;

/**
 * CookieConsent view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class CookieConsent extends \VuFind\View\Helper\Root\CookieConsent
{
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

        // Check that categories in current consent cookie exist in configuration:
        if ($consentJson = $this->cookieManager->get($this->consentCookieName)) {
            if ($consent = json_decode($consentJson, true)) {
                $cookieCategories = array_values(
                    array_unique(
                        array_merge(
                            (array)($consent['categories'] ?? []),
                            array_keys((array)($consent['services'] ?? []))
                        )
                    )
                );

                $categories = array_keys($this->consentConfig['Categories']);
                $enabled = $this->config['Cookies']['consentCategories'] ?? '';
                $categories = array_intersect(
                    $categories,
                    $enabled ? explode(',', $enabled) : ['essential']
                );

                sort($categories);
                sort($cookieCategories);
                if ($categories != $cookieCategories) {
                    // Categories differ, invalidate current consent:
                    $consent['revision'] = (int)($consent['revision']) - 1;

                    $domain = $this->cookieManager->getDomain()
                        ?: $this->getHostName();
                    if (strncmp($domain, '.', 1) !== 0) {
                        $domain = ".$domain";
                    }
                    setcookie(
                        $this->consentCookieName,
                        json_encode($consent),
                        [
                            'expires' => 0,
                            'path' => $this->cookieManager->getPath(),
                            'domain' => $domain,
                            'samesite' => $this->cookieManager->getSameSite(),
                            'secure' => $this->cookieManager->isSecure(),
                        ]
                    );
                }
            }
        }

        return parent::render();
    }

    /**
     * Get title of required category for a service.
     *
     * @param string $service Service to check.
     *
     * @return string
     */
    public function getCategoryTitleForService(string $service): string
    {
        foreach ($this->getControlledVuFindServices() as $key => $values) {
            if (in_array($service, $values)) {
                return $this->consentConfig['Categories'][$key]['Title']
                    ?? 'Unknown';
            }
        }
        return 'Unknown';
    }
}
