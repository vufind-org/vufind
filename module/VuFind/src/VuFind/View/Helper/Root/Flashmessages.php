<?php
/**
 * Flash message view helper
 *
 * PHP version 7
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\View\Helper\Root;

use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Laminas\View\Helper\AbstractHelper;

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
     * Flash messenger controller helper
     *
     * @var FlashMessenger
     */
    protected $fm;

    /**
     * Flash messenger namespaces
     *
     * @var string[]
     */
    protected $namespaces = ['error', 'warning', 'info', 'success', 'default'];

    /**
     * Constructor
     *
     * @param FlashMessenger $fm Flash messenger controller helper
     */
    public function __construct(FlashMessenger $fm)
    {
        $this->fm = $fm;
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
        $html = '';
        foreach ($this->namespaces as $ns) {
            $messages = array_merge(
                $this->fm->getMessages($ns),
                $this->fm->getCurrentMessages($ns)
            );
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
                    // Use a different translate helper depending on whether
                    // or not we're in HTML mode.
                    if (!isset($msg['translate']) || $msg['translate']) {
                        $helper = (isset($msg['html']) && $msg['html'])
                            ? 'translate' : 'transEsc';
                    } else {
                        $helper = (isset($msg['html']) && $msg['html'])
                            ? false : 'escapeHtml';
                    }
                    $helper = $helper ? $this->getView()->plugin($helper) : false;
                    $tokens = $msg['tokens'] ?? [];
                    if ($tokens && $mode = ($msg['translateTokens'] ?? false)) {
                        // Escape translated tokens unless html is requested or the
                        // main message is translated:
                        $translator = 'html' === $mode || empty($msg['html'])
                            ? 'translate' : 'transEsc';
                        $tokens = array_map(
                            $this->getView()->plugin($translator),
                            $tokens
                        );
                    }
                    $default = $msg['default'] ?? null;
                    $html .= $helper
                        ? $helper($msg['msg'], $tokens, $default) : $msg['msg'];
                } else {
                    // Basic default string:
                    $transEsc = $this->getView()->plugin('transEsc');
                    $html .= $transEsc($msg);
                }
                $html .= '</div>';
            }
            $this->fm->clearMessages($ns);
            $this->fm->clearCurrentMessages($ns);
        }
        return $html;
    }
}
