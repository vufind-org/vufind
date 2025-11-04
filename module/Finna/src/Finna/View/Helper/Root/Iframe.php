<?php

/**
 * Iframe helper
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
 * @package  Content
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\View\Helper\Root;

use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;

/**
 * Iframe helper
 *
 * @category VuFind
 * @package  Content
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Iframe extends \Laminas\View\Helper\AbstractHelper implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    /**
     * Cookie consent configuration
     *
     * @var array
     */
    protected $consentConfig;

    /**
     * Constructor
     *
     * @param array $consentConfig Cookie consent configuration
     */
    public function __construct(array $consentConfig)
    {
        $this->consentConfig = $consentConfig;
    }

    /**
     * Render a generic iframe or link box depending on cookie consent
     *
     * @param string $style             Element style attribute used for both iframe
     * and possible placeholder div if required consent categories are not accepted
     * @param string $title             Iframe title
     * @param string $src               Iframe src attribute
     * @param array  $attributes        Other iframe attributes (if this contains
     * style, it overrides the style from the $style parameter for the iframe)
     * @param string $serviceUrl        URL to the service's own interface
     * @param array  $consentCategories Required cookie consent categories
     * @param string $templateName      Template name (overrides the default)
     *
     * @return string
     */
    public function render(
        string $style,
        string $title,
        string $src,
        array $attributes,
        string $serviceUrl,
        array $consentCategories,
        string $templateName = 'Helpers/iframe.phtml'
    ): string {
        $serviceBaseUrl = $this->getServiceBaseUrl($serviceUrl);
        $consentCategoriesTranslated
            = $this->getTranslatedConsentCategories($consentCategories);
        $embed = $this->hasConsent($consentCategories);

        return $this->getView()->render(
            'Helpers/iframe.phtml',
            compact(
                'embed',
                'style',
                'title',
                'src',
                'attributes',
                'serviceUrl',
                'consentCategories',
                'consentCategoriesTranslated',
                'serviceBaseUrl'
            )
        );
    }

    /**
     * Render a link box to a service
     *
     * @param string $serviceUrl        URL to the service's own interface
     * @param array  $consentCategories Required cookie consent categories
     * @param string $style             Element style for the link box
     *
     * @return string
     */
    public function renderLinkBox(
        string $serviceUrl,
        array $consentCategories,
        string $style = ''
    ): string {
        $serviceBaseUrl = $this->getServiceBaseUrl($serviceUrl);
        $consentCategoriesTranslated
            = $this->getTranslatedConsentCategories($consentCategories);
        $embed = false;

        return $this->getView()->render(
            'Helpers/iframe.phtml',
            compact(
                'embed',
                'style',
                'serviceUrl',
                'consentCategories',
                'consentCategoriesTranslated',
                'serviceBaseUrl'
            )
        );
    }

    /**
     * Render a Vimeo iframe or link box depending on cookie consent
     *
     * @param string  $videoId           Video ID
     * @param array   $consentCategories Required cookie consent categories
     * @param ?string $width             Element width (e.g. 512px)
     * @param ?string $height            Element height (e.g. 384px)
     * @param array   $attributes        Other iframe attributes (if this contains
     * style, it overrides the style from the $style parameter for the iframe)
     *
     * @return string
     */
    public function vimeo(
        string $videoId,
        array $consentCategories,
        ?string $width = null,
        ?string $height = null,
        array $attributes = []
    ): string {
        if (!isset($attributes['allow'])) {
            $attributes['allow'] = 'autoplay; fullscreen; picture-in-picture';
        }
        $styleParts = [];
        if ($width) {
            $styleParts[] = "width: $width;";
        }
        if ($height) {
            $styleParts[] = "height: $height;";
        }
        return $this->render(
            implode(' ', $styleParts),
            'Vimeo',
            'https://player.vimeo.com/video/' . urlencode($videoId),
            $attributes,
            'https://vimeo.com/' . urlencode($videoId),
            $consentCategories
        );
    }

    /**
     * Render a YouTube iframe or link box depending on cookie consent
     *
     * @param string  $videoId           Video ID
     * @param array   $consentCategories Required cookie consent categories
     * @param ?string $width             Element width (e.g. 512px)
     * @param ?string $height            Element height (e.g. 384px)
     * @param array   $attributes        Other iframe attributes (if this contains
     * style, it overrides the style from the $style parameter for the iframe)
     *
     * @return string
     */
    public function youtube(
        string $videoId,
        array $consentCategories,
        ?string $width = null,
        ?string $height = null,
        array $attributes = []
    ): string {
        if (!isset($attributes['allow'])) {
            $attributes['allow'] = 'accelerometer; autoplay; clipboard-write;'
                . ' encrypted-media; gyroscope; picture-in-picture';
        }
        $styleParts = [];
        if ($width) {
            $styleParts[] = "width: $width;";
        }
        if ($height) {
            $styleParts[] = "height: $height;";
        }
        return $this->render(
            implode(' ', $styleParts),
            'YouTube video player',
            'https://www.youtube.com/embed/' . urlencode($videoId),
            $attributes,
            'https://www.youtube.com/watch?v=' . urlencode($videoId),
            $consentCategories
        );
    }

    /**
     * Render an Icareus iframe or link box depending on cookie consent
     *
     * @param string $src               Icareus video source
     * @param array  $consentCategories Required cookie consent categories
     * @param array  $attributes        Other iframe attributes (if this contains
     * style, it overrides the style from the $style parameter for the iframe)
     *
     * @return string
     */
    public function icareus(
        string $src,
        array $consentCategories,
        array $attributes = []
    ): string {
        $style = implode(' ', [
            'position: absolute;',
            'top: 0;',
            'bottom: 0;',
            'right: 0;',
            'left: 0;',
            'width: 100%;',
            'height: 100%;',
        ]);
        $wrapper = true;
        $wrapperStyle['style'] = implode(' ', [
            'position: relative;',
            'width: 100%;',
            'padding-top: 56.25%;',
            'clear: both',
        ]);
        $serviceUrl = $src;
        $serviceBaseUrl = $this->getServiceBaseUrl($src);
        $consentCategoriesTranslated
            = $this->getTranslatedConsentCategories($consentCategories);
        $embed = $this->hasConsent($consentCategories);

        $title = 'Icareus video player';

        return $this->getView()->render(
            'Helpers/iframe.phtml',
            compact(
                'embed',
                'style',
                'title',
                'src',
                'attributes',
                'serviceUrl',
                'consentCategories',
                'consentCategoriesTranslated',
                'serviceBaseUrl',
                'wrapper',
                'wrapperStyle'
            )
        );
    }

    /**
     * Render a Twitter timeline iframe or link box depending on cookie consent
     *
     * @param string $screenName        User's screen name
     * @param array  $consentCategories Required cookie consent categories
     * @param ?int   $width             Element width (e.g. 512)
     * @param ?int   $height            Element height (e.g. 384)
     *
     * @return string
     */
    public function twitterTimeline(
        string $screenName,
        array $consentCategories,
        ?int $width = null,
        ?int $height = null
    ): string {
        $consentCategoriesTranslated
            = $this->getTranslatedConsentCategories($consentCategories);
        $styleParts = [];
        if ($width) {
            $styleParts[] = "width: {$width}px;";
        }
        if ($height) {
            $styleParts[] = "height: {$height}px;";
        }
        $style = implode(' ', $styleParts);
        $embed = $this->hasConsent($consentCategories);
        return  $this->getView()->render(
            'Helpers/twitter-timeline.phtml',
            compact(
                'embed',
                'screenName',
                'consentCategories',
                'consentCategoriesTranslated',
                'width',
                'height',
                'style'
            )
        );
    }

    /**
     * Get base URL for a service
     *
     * @param string $serviceUrl Service URL
     *
     * @return string
     */
    protected function getServiceBaseUrl(string $serviceUrl): string
    {
        if (!($urlParts = parse_url($serviceUrl))) {
            return $serviceUrl;
        }
        $result = '';
        if ($scheme = $urlParts['scheme'] ?? '') {
            $result = "$scheme://";
        }
        $result .= $urlParts['host'];
        if ($port = $urlParts['port'] ?? '') {
            $result = ":$port";
        }
        return $result;
    }

    /**
     * Get translated consent categories
     *
     * @param array $categories Categories to translate
     *
     * @return array
     */
    protected function getTranslatedConsentCategories(array $categories): array
    {
        $result = [];
        foreach ($categories as $category) {
            $result[]
                = $this->translate(
                    $this->consentConfig['Categories'][$category]['Title']
                    ?? 'Unknown'
                );
        }
        return $result;
    }

    /**
     * Check if user has consented to given categories
     *
     * @param array $categories Categories
     *
     * @return bool
     */
    protected function hasConsent(array $categories): bool
    {
        $cookieConsent = $this->getView()->plugin('cookieConsent');
        if ($cookieConsent->isEnabled()) {
            foreach ($categories as $category) {
                if (!$cookieConsent->isCategoryAccepted($category)) {
                    return false;
                }
            }
        }
        return true;
    }
}
