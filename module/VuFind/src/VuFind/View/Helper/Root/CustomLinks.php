<?php

/**
 * Custom links view helper.
 *
 * PHP version 8
 *
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
 * @package  View_Helpers
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\EscapeHtml;
use VuFind\Content\CustomLinkManager;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Custom links view helper.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CustomLinks implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Constructor.
     *
     * @param CustomLinkManager $customLinkManager Custom link manager
     * @param EscapeHtml        $escapeHtml        EscapeHtml helper
     * @param TransEsc          $transEsc          TransEsc helper
     */
    public function __construct(
        protected CustomLinkManager $customLinkManager,
        #[Autowire(container: 'ViewHelperManager')]
        protected EscapeHtml $escapeHtml,
        #[Autowire(container: 'ViewHelperManager')]
        protected TransEsc $transEsc,
    ) {
    }

    /**
     * Make helper invokable.
     *
     * @return static
     */
    public function __invoke(): static
    {
        return $this;
    }

    /**
     * Render custom link preview.
     *
     * @param array $customLink Custom Link
     *
     * @return string
     */
    public function renderCustomLinkPreview(array $customLink): string
    {
        $title = ($this->escapeHtml)($customLink['title'] ?? '');
        return match ($customLink['type'] ?? 'url') {
            'page' => ($this->transEsc)('Content Page') . ': ' . $title . ' [' . ($this->transEsc)('Headline')
                . ': ' . ($this->escapeHtml)($customLink['headline'] ?? '') . ']',
            'url' => ($this->transEsc)('URL') . ': ' . $title . ' [' . ($this->escapeHtml)($customLink['url']) . ']',
            default => $title,
        };
    }
}
