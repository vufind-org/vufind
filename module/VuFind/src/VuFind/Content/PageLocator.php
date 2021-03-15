<?php

/**
 * Class PageLocator
 *
 * PHP version 7
 *
 * Copyright (C) Moravian Library 2020.
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
 * @package  Content
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Content;

/**
 * Class PageLocator
 *
 * @category VuFind
 * @package  Content
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class PageLocator
{
    /**
     * Types/formats of content
     *
     * @var array $types
     */
    protected $types = [
        'phtml',
        'md',
    ];

    /**
     * Theme info service
     *
     * @var \VuFindTheme\ThemeInfo
     */
    protected $themeInfo;

    /**
     * Current language
     *
     * @var string
     */
    protected $language;

    /**
     * Default language
     *
     * @var string
     */
    protected $defaultLanguage;

    /**
     * Page constructor.
     *
     * @param \VuFindTheme\ThemeInfo $themeInfo       Theme information service
     * @param string                 $language        Current language
     * @param string                 $defaultLanguage Main configuration
     */
    public function __construct($themeInfo, $language, $defaultLanguage)
    {
        $this->themeInfo = $themeInfo;
        $this->language = $language;
        $this->defaultLanguage  = $defaultLanguage;
    }

    /**
     * Generate a template from a file search pattern. Examples:
     * - %pathPrefix%/%pageName%{_%language%} => content/help_en
     * - %pathPrefix%/%language%/%pageName% => HelpTranslations/en/search
     *
     * @param string $pathPrefix Subdirectory where the template should be located
     * @param string $pageName   Page name
     * @param string $pattern    Filesystem pattern
     * @param string $language   Language
     *
     * @return string
     */
    protected function generateTemplateFromPattern(
        $pathPrefix, $pageName, $pattern, $language=null
    ) {
        $template = $pattern;
        $template = str_replace('%pathPrefix%', $pathPrefix, $template);
        $template = str_replace('%pageName%', $pageName, $template);
        $languagePatternExtended = '"\\{(.*)(%language%)(.*)\\}"';
        if (isset($language)) {
            $template = preg_replace(
                $languagePatternExtended,
                "\\1$language\\3",
                $template
            );
        } else {
            $template = preg_replace($languagePatternExtended, '', $template);
        }
        $template = str_replace('%language%', $language ?? '', $template);
        $template = str_replace('//', '/', $template);
        return $template;
    }

    /**
     * Try to find a template using
     * 1) Current language suffix
     * 2) Default language suffix
     * 3) No language suffix
     *
     * @param string $pathPrefix Subdirectory where the template should be located
     * @param string $pageName   Template name
     * @param string $pattern    Filesystem pattern
     *
     * @return array Array with template options (key equals matchType)
     */
    protected function getTemplateOptionsFromPattern(
        $pathPrefix, $pageName, $pattern
    ) {
        $templates = ['language' => $this->generateTemplateFromPattern(
            $pathPrefix, $pageName, $pattern, $this->language
        )];
        if ($this->language != $this->defaultLanguage) {
            $templates['defaultLanguage'] = $this->generateTemplateFromPattern(
                $pathPrefix, $pageName, $pattern, $this->defaultLanguage
            );
        }
        $templates['pageName'] = $this->generateTemplateFromPattern(
            $pathPrefix, $pageName, $pattern
        );
        return $templates;
    }

    /**
     * Try to find template information about desired page
     *
     * @param string $pathPrefix Subdirectory where the template should be located
     * @param string $pageName   Template name
     * @param string $pattern    Optional filesystem pattern
     *
     * @return array|null Null if template is not found or array with keys renderer
     * (type of template), path (full path of template), page (page name)
     */
    public function determineTemplateAndRenderer(
        $pathPrefix, $pageName, $pattern=null
    ) {
        if (!isset($pattern)) {
            $pattern = '%pathPrefix%/%pageName%{_%language%}';
        }

        $templates = $this->getTemplateOptionsFromPattern(
            $pathPrefix, $pageName, $pattern
        );

        foreach ($templates as $matchType => $template) {
            foreach ($this->types as $type) {
                $filename = "$template.$type";
                $pathDetails = $this->themeInfo->findContainingTheme(
                    $filename, $this->themeInfo::RETURN_ALL_DETAILS
                );
                if (null != $pathDetails) {
                    return [
                        'renderer' => $type,
                        'path' => $pathDetails['path'],
                        'page' => basename($template),
                        'theme' => $pathDetails['theme'],
                        'matchType' => $matchType,
                    ];
                }
            }
        }

        return null;
    }
}
