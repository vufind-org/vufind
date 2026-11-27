<?php

/**
 * File list collector with support for dynamically added files.
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
 * @package  Translator
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\I18n\Translator\TranslationCollector;

use Laminas\I18n\Translator\MessageLoaderPluginManagerInterface;
use Laminas\I18n\Translator\TextDomain;
use Laminas\I18n\Translator\TranslationCollector\TranslationCollectorInterface;
use Laminas\I18n\Translator\Translator;
use Laminas\I18n\Translator\Value\TranslationFile;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * File list collector with support for dynamically added files.
 *
 * @category VuFind
 * @package  Translator
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class DynamicFileListCollector implements TranslationCollectorInterface
{
    /**
     * Translation files.
     *
     * @var array
     */
    protected array $files = [];

    /**
     * Constructor.
     *
     * @param MessageLoaderPluginManagerInterface $loader Message loader plugin manager
     */
    #[Autowire]
    public function __construct(
        protected MessageLoaderPluginManagerInterface $loader,
    ) {
    }

    /**
     * Collect translations.
     *
     * @param string $textDomain Text domain
     * @param string $locale     Locale
     *
     * @return TextDomain
     */
    public function collect(string $textDomain, string $locale): TextDomain
    {
        $result = new TextDomain();
        $files  = $this->files[$textDomain] ?? [];
        foreach ([$locale, Translator::ANY_LOCALE] as $currentLocale) {
            if (! isset($files[$currentLocale])) {
                continue;
            }

            foreach ($files[$currentLocale] as $file) {
                $loader = $this->loader->get($file->type);
                $messages = $loader->load($locale, $file->filename);
                $result->merge($messages);
            }
        }

        return $result;
    }

    /**
     * Add a translation file.
     *
     * @param string  $type       File type
     * @param string  $filename   Filename
     * @param string  $textDomain Text domain
     * @param ?string $locale     Locale
     *
     * @return void
     */
    public function addTranslationFile(
        string $type,
        string $filename,
        string $textDomain,
        string $locale
    ): void {
        $this->files[$textDomain][$locale][] = new TranslationFile($type, $filename, $locale, $textDomain);
    }
}
