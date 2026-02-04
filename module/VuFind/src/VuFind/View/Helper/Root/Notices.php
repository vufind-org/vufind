<?php

/**
 * Notices view helper
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
use Laminas\View\Renderer\PhpRenderer;
use VuFind\Content\NoticeManager;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Notices view helper.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Notices implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Default style classes.
     *
     * @var array
     */
    protected array $defaultStyleClasses = [];

    /**
     * Constructor
     *
     * @param NoticeManager $noticeManager Notice manager
     * @param PhpRenderer   $renderer      PhpRenderer
     * @param EscapeHtml    $escapeHtml    EscapeHtml view helper
     */
    public function __construct(
        #[Autowire(service: NoticeManager::class)]
        protected NoticeManager $noticeManager,
        #[Autowire(service: PhpRenderer::class)]
        protected PhpRenderer $renderer,
        #[Autowire(container: 'ViewHelperManager')]
        protected EscapeHtml $escapeHtml
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
     * Get notice manager.
     *
     * @return NoticeManager
     */
    public function getManager(): NoticeManager
    {
        return $this->noticeManager;
    }

    /**
     * Render notices for given position.
     *
     * @param string $position Position
     *
     * @return string
     */
    public function renderNoticesForPosition(string $position): string
    {
        return $this->renderer->render(
            'Helpers/notices/notices.phtml',
            compact('position')
        );
    }

    /**
     * Render notice.
     *
     * @param array $notice Notice
     *
     * @return string
     */
    public function renderNotice(array $notice): string
    {
        $content = $notice['content'] ?? null;
        if ($content === null) {
            return '';
        }
        $content = ($this->escapeHtml)($content);
        $classes = '';
        if ($style = $notice['style'] ?? null) {
            $classes = $this->noticeManager->getConfig()['styles'][$style]['classes']
                ?? $this->defaultStyleClasses[$style]
                ?? '';
        }
        return $this->renderer->render(
            'Helpers/notices/notice.phtml',
            compact('content', 'classes')
        );
    }
}
