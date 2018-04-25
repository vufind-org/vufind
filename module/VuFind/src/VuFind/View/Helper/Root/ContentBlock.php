<?php
/**
 * ContentBlock view helper
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018.
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

use Zend\View\Exception\RuntimeException;
use Zend\View\Helper\AbstractHelper;

/**
 * ContentBlock view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ContentBlock extends AbstractHelper
{
    /**
     * Render the output of a ContentBlock plugin.
     *
     * @param \VuFind\ContentBlock\ContentBlockInterface $block The ContentBlock
     * object to render
     *
     * @return string
     */
    public function __invoke($block)
    {
        // Set up the rendering context:
        $contextHelper = $this->getView()->plugin('context');
        $oldContext = $contextHelper($this->getView())->apply(compact('block'));

        // Get the current plugin's class name, then start a loop in case we need
        // to use a parent class' name to find the appropriate template.
        $className = get_class($block);
        $resolver = $this->getView()->resolver();
        while (true) {
            // Guess the template name for the current class:
            $classParts = explode('\\', $className);
            $template = 'ContentBlock/' . array_pop($classParts) . '.phtml';
            if ($resolver->resolve($template)) {
                // Try to render the template....
                $html = $this->getView()->render($template);
                $contextHelper($this->getView())->restore($oldContext);
                return $html;
            } else {
                // If the template doesn't exist, let's see if we can inherit a
                // template from a parent class:
                $className = get_parent_class($className);
                if (empty($className)) {
                    // No more parent classes left to try?  Throw an exception!
                    throw new RuntimeException(
                        'Cannot find template for ContentBlock class: ' .
                        get_class($block)
                    );
                }
            }
        }
    }
}
