<?php

/**
 * Flash message view helper
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;
use VuFind\View\FlashMessenger\FlashMessengerInterface;

use function is_array;

/**
 * Flash message view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Flashmessages extends AbstractHelper
{
    /**
     * Flash messenger namespaces and methods for getting the messages.
     *
     * The list is in priority order (errors are displayed first).
     *
     * @var array
     */
    protected $namespaces = [
        'error' => 'getErrorMessages',
        'warning' => 'getWarningMessages',
        'info' => 'getInfoMessages',
        'success' => 'getSuccessMessages',
    ];

    /**
     * Constructor
     *
     * @param FlashMessengerInterface $flashMessenger Flash messenger controller helper
     */
    public function __construct(protected FlashMessengerInterface $flashMessenger)
    {
    }

    /**
     * Get the CSS class to correspond with a messenger namespace
     *
     * @param string $ns Namespace
     *
     * @return string
     */
    protected function getClassForNamespace($ns)
    {
        return $ns;
    }

    /**
     * Generate flash message <div>'s with appropriate classes based on message type.
     *
     * @return string $html
     */
    public function __invoke()
    {
        if (!empty($this->getView()->layout()->lightboxChild)) {
            return '';
        }
        $html = '';
        foreach ($this->namespaces as $ns => $method) {
            $messages = $this->flashMessenger->$method();
            foreach (array_unique($messages, SORT_REGULAR) as $msg) {
                $html .= '<div role="alert" class="'
                    . $this->getClassForNamespace($ns) . '"';
                if (isset($msg['dataset'])) {
                    foreach ($msg['dataset'] as $attr => $value) {
                        $html .= ' data-' . $attr . '="'
                            . htmlspecialchars($value) . '"';
                    }
                }
                $html .= '>';
                // Advanced form:
                if (is_array($msg)) {
                    $msgHtml = $msg['html'] ?? false;
                    $message = $msg['msg'];
                    $escapeHtml = $this->getView()->plugin('escapeHtml');
                    // Process tokens and translate the message unless requested not
                    // to:
                    if ($msg['translate'] ?? true) {
                        $translate = $this->getView()->plugin('translate');
                        $tokens = $msg['tokens'] ?? [];
                        if ($tokens) {
                            if ($msg['translateTokens'] ?? false) {
                                $tokens = array_map(
                                    $translate,
                                    $tokens
                                );
                            }
                            // Escape tokens if the main message is HTML, unless
                            // requested not to by setting tokensHtml to true:
                            if ($msgHtml && !($msg['tokensHtml'] ?? false)) {
                                $tokens = array_map($escapeHtml, $tokens);
                            }
                        }
                        $default = $msg['default'] ?? null;

                        // Translate the message:
                        $message = $translate($message, $tokens, $default, $msg['icu'] ?? false);
                    }
                    // Escape the message unless requested not to:
                    if (!$msgHtml) {
                        $message = $escapeHtml($message);
                    }

                    $html .= $message;
                } else {
                    // Basic default string:
                    $transEsc = $this->getView()->plugin('transEsc');
                    $html .= $transEsc($msg);
                }
                $html .= '</div>';
            }
        }
        $this->flashMessenger->clearAllMessages();
        return $html;
    }
}
