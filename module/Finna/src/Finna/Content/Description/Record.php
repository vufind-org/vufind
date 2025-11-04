<?php

/**
 * Record description provider.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023.
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

namespace Finna\Content\Description;

use Laminas\View\Renderer\RendererInterface;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\RecordDriver\DefaultRecord;

/**
 * Record description provider.
 *
 * @category VuFind
 * @package  Content
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Record extends AbstractDescriptionProvider implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * View renderer
     *
     * @var RendererInterface
     */
    protected $renderer;

    /**
     * Constructor
     *
     * @param RendererInterface $renderer View renderer
     */
    public function __construct(RendererInterface $renderer)
    {
        $this->renderer = $renderer;
    }

    /**
     * Get description for a particular API key and record.
     *
     * @param string        $key    API key
     * @param DefaultRecord $record Record driver
     *
     * @return string Ready-to-display HTML or an empty string on error
     */
    public function get(string $key, DefaultRecord $record): string
    {
        // For LIDO records the summary is displayed separately from description in the core template
        if ($record instanceof \Finna\RecordDriver\SolrLido) {
            return '';
        }
        $language = $this->translator->getLocale();
        if (!($summary = $record->getSummary($language))) {
            return '';
        }
        $summary = implode("\n\n", $summary);

        // Replace double hash with a <br>
        $summary = str_replace('##', "\n\n", $summary);

        // Process markdown
        $summary = $this->renderer->plugin('markdown')->toHtml($summary);

        return $summary;
    }
}
