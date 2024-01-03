<?php

/**
 * Deferred recommendation module view helper
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

/**
 * Deferred recommendation module view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class DeferredRecommend extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Render code to load a recommendation module via AJAX.
     *
     * @param string $containerId HTML ID of the parent container for this recommendation
     *
     * @return string
     */
    public function __invoke(
        $containerId,
    ) {
        // Pass $containerId to the template
        $context = compact('containerId');

        // Save any existing context before the render; restore after.
        $view = $this->getView();
        $contextHelper = $view->plugin('context');
        $oldContext = $contextHelper($view)->apply($context);
        $html = $view->render('Recommend/Deferred.phtml');
        $contextHelper($view)->restore($oldContext);
        return $html;
    }
}
