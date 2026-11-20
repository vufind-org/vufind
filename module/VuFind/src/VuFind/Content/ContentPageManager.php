<?php

/**
 * Content Page Manager.
 *
 * PHP version 8
 *
 * Copyright (C) Moravian Library 2020.
 * Copyright (C) effective WEBWORK GmbH 2023.
 * Copyright (C) Hebis Verbundzentrale 2026.
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
 * @author   Josef Moravec <moravec@mzk.cz>
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Content;

use VuFind\Db\Entity\ContentPageEntityInterface;
use VuFind\Db\Service\ContentPageServiceInterface;
use VuFind\I18n\Locale\LocaleSettingsAwareInterface;
use VuFind\I18n\Locale\LocaleSettingsAwareTrait;
use VuFind\ServiceManager\Factory\Autowire;
use VuFindTheme\ThemeInfo;

/**
 * Content Page Manager.
 *
 * @category VuFind
 * @package  Content
 * @author   Josef Moravec <moravec@mzk.cz>
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ContentPageManager implements LocaleSettingsAwareInterface
{
    use LocaleSettingsAwareTrait;

    /**
     * Types/formats of content files.
     *
     * @var array $fileTypes
     */
    protected $fileTypes = [
        'phtml',
        'md',
    ];

    /**
     * Database content pages cache.
     *
     * @var array
     */
    protected array $databaseContentPages = [];

    /**
     * Constructor.
     *
     * @param ThemeInfo                   $themeInfo          Theme information service
     * @param ContentPageServiceInterface $contentPageService Content page service
     */
    public function __construct(
        protected ThemeInfo $themeInfo,
        #[Autowire(container: \VuFind\Db\Service\PluginManager::class)]
        protected ContentPageServiceInterface $contentPageService
    ) {
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
     * 3) No language.
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
            $this->localeSettings->getUserLocale()
        );
        if ($this->localeSettings->getUserLocale() != $this->localeSettings->getDefaultLocale()) {
            yield 'defaultLanguage' => $this->generateTemplateFromPattern(
                $pathPrefix,
                $pageName,
                $pattern,
                $this->localeSettings->getDefaultLocale()
            );
        }
        yield 'pageName' => $this->generateTemplateFromPattern(
            $pathPrefix,
            $pageName,
            $pattern
        );
    }

    /**
     * Try to find template information about desired page.
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
        if ($this->getByHridFromDatabase($pageName)) {
            return [
                'renderer' => 'database',
                'page' => $pageName,
            ];
        }

        if ($pattern === null) {
            $pattern = '%pathPrefix%/%pageName%{_%language%}';
        }

        $templates = $this->getTemplateOptionsFromPattern(
            $pathPrefix,
            $pageName,
            $pattern
        );

        foreach ($templates as $matchType => $template) {
            foreach ($this->fileTypes as $type) {
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

    /**
     * Get content page from the database by HRID.
     *
     * @param string $hrid Content page HRID
     *
     * @return ?array
     */
    public function getByHridFromDatabase(string $hrid): ?array
    {
        if ($cachedContentPage = $this->databaseContentPages[$hrid] ?? null) {
            return $cachedContentPage;
        }
        $contentPageEntity = $this->contentPageService->getByHrid($hrid);
        if ($contentPageEntity === null) {
            return null;
        }
        $contentPage = $this->contentPageEntityToArray($contentPageEntity);

        $this->databaseContentPages[$hrid] = $contentPage;

        return $contentPage;
    }

    /**
     * Add content page to the database and get its HRID.
     *
     * @param array $contentPage Content page data
     *
     * @return string
     */
    public function addContentPageToDatabaseAndGetHrid(array $contentPage): string
    {
        return $this->contentPageService->insert($contentPage)->getHrid();
    }

    /**
     * Edit content page in the database by HRID.
     *
     * @param string $hrid        Content page HRID
     * @param array  $contentPage Content page data
     *
     * @return void
     */
    public function editDatabaseContentPageByHrid(string $hrid, array $contentPage): void
    {
        $this->contentPageService->updateByHrid($hrid, $contentPage);
    }

    /**
     * Delete content page from the database by HRID.
     *
     * @param string $hrid Content page HRID
     *
     * @return void
     */
    public function deleteByDatabaseHrid(string $hrid): void
    {
        $this->contentPageService->deleteByHrid($hrid);
    }

    /**
     * Map a content page db entity to the array format.
     *
     * @param ContentPageEntityInterface $contentPageEntity Content page entity
     *
     * @return array
     */
    protected function contentPageEntityToArray(ContentPageEntityInterface $contentPageEntity): array
    {
        $translations = [];
        foreach ($contentPageEntity->getTranslations() as $translationEntity) {
            $translations[$translationEntity->getLanguage()] = [
                'headline' => $translationEntity->getHeadline(),
                'content' => $translationEntity->getContent(),
            ];
        }
        $contentPage = [
            'id' => $contentPageEntity->getId(),
            'translations' => $translations,
        ];

        $headline = $this->getActiveTranslation(array_map(
            fn ($translation) => $translation['headline'] ?? null,
            $translations
        ));
        if ($headline !== null) {
            $contentPage['headline'] = $headline;
        }

        $content = $this->getActiveTranslation(array_map(
            fn ($translation) => $translation['content'] ?? null,
            $translations
        ));
        if ($content !== null) {
            $contentPage['content'] = $content;
        }
        return $contentPage;
    }
}
