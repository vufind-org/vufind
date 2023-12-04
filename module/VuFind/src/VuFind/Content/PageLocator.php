<?php

/**
 * Class PageLocator
 *
 * PHP version 8
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
        string $pathPrefix,
        string $pageName,
        string $pattern,
        string $language = ''
    ): string {
        $standardReplacements = [
            '%pathPrefix%' => $pathPrefix,
            '%pageName%' => $pageName,
            '%language%' => $language,
            '//' => '/',
        ];
        $languagePatternExtended = '"\\{(.*)%language%(.*)\\}"';
        $languagePatternExtendedReplacement = $language ? "\\1$language\\2" : '';
        return str_replace(
            array_keys($standardReplacements),
            array_values($standardReplacements),
            preg_replace(
                $languagePatternExtended,
                $languagePatternExtendedReplacement,
                $pattern
            )
        );
    }

    /**
     * Try to find a template using
     * 1) Current language
     * 2) Default language
     * 3) No language
     *
     * @param string $pathPrefix Subdirectory where the template should be located
     * @param string $pageName   Template name
     * @param string $pattern    Filesystem pattern
     *
     * @return \Generator Array generator with template options
     *                    (key equals matchType)
     */
    protected function getTemplateOptionsFromPattern(
        string $pathPrefix,
        string $pageName,
        string $pattern
    ): \Generator {
        yield 'language' => $this->generateTemplateFromPattern(
            $pathPrefix,
            $pageName,
            $pattern,
            $this->language
        );
        if ($this->language != $this->defaultLanguage) {
            yield 'defaultLanguage' => $this->generateTemplateFromPattern(
                $pathPrefix,
                $pageName,
                $pattern,
                $this->defaultLanguage
            );
        }
        yield 'pageName' => $this->generateTemplateFromPattern(
            $pathPrefix,
            $pageName,
            $pattern
        );
    }

    /**
     * Try to find template information about desired page
     *
     * @param string $pathPrefix Subdirectory where the template should be located
     * @param string $pageName   Template name
     * @param string $pattern    Optional filesystem pattern
     *
     * @return array|null Null if template is not found or array with keys renderer
     * (type of template), path (full path of template), relativePath (relative
     * path within the templates directory), page (page name), theme,
     * matchType (see getTemplateOptionsFromPattern)
     */
    public function determineTemplateAndRenderer(
        $pathPrefix,
        $pageName,
        $pattern = null
    ) {
        if ($pattern === null) {
            $pattern = '%pathPrefix%/%pageName%{_%language%}';
        }

        $templates = $this->getTemplateOptionsFromPattern(
            $pathPrefix,
            $pageName,
            $pattern
        );

        foreach ($templates as $matchType => $template) {
            foreach ($this->types as $type) {
                $filename = "$template.$type";
                $pathDetails = $this->themeInfo->findContainingTheme(
                    $filename,
                    $this->themeInfo::RETURN_ALL_DETAILS
                );
                if (null != $pathDetails) {
                    $relativeTemplatePath = preg_replace(
                        '"^templates/"',
                        '',
                        $pathDetails['relativePath']
                    );
                    return [
                        'renderer' => $type,
                        'path' => $pathDetails['path'],
                        'relativePath' => $relativeTemplatePath,
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
