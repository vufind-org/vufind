<?php
/**
 * Flash message view helper
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */

/**
 * Flash message view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class VuFind_Theme_Root_Helper_Flashmessages extends Zend_View_Helper_Abstract
{
    /**
     * Generate flash message <div>'s with appropriate classes based on message type.
     *
     * @return string $html
     */
    public function flashmessages()
    {
        $html = '';
        if (is_object($this->view->flashMessenger)) {
            $namespaces = array('error', 'info');
            foreach ($namespaces as $ns) {
                $this->view->flashMessenger->setNamespace($ns);
                $messages = array_merge(
                    $this->view->flashMessenger->getMessages(),
                    $this->view->flashMessenger->getCurrentMessages()
                );
                foreach ($messages as $msg) {
                    $html .= '<div class="' . $ns . '">';
                    // Advanced form:
                    if (is_array($msg)) {
                        // Use a different translate helper depending on whether
                        // or not we're in HTML mode.
                        if (!isset($msg['translate']) || $msg['translate']) {
                            $helper = (isset($msg['html']) && $msg['html'])
                                ? 'translate' : 'transEsc';
                        } else {
                            $helper = (isset($msg['html']) && $msg['html'])
                                ? false : 'escape';
                        }
                        $tokens = isset($msg['tokens']) ? $msg['tokens'] : array();
                        $default = isset($msg['default']) ? $msg['default'] : null;
                        $html .= $helper
                            ? $this->view->$helper($msg['msg'], $tokens, $default)
                            : $msg['msg'];
                    } else {
                        // Basic default string:
                        $html .= $this->view->transEsc($msg);
                    }
                    $html .= '</div>';
                }
                $this->view->flashMessenger->clearMessages();
                $this->view->flashMessenger->clearCurrentMessages();
            }
        }
        return $html;
    }
}