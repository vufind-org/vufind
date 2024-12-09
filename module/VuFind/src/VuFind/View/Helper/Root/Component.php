<?php

/**
 * Component view helper
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

use Laminas\View\Helper\AbstractHelper;

/**
 * Component view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Component extends AbstractHelper
{
    /**
     * Expand path and render template
     *
     * @param string $name   Component name that matches a template
     * @param array  $params Data for the component template
     *
     * @return string
     */
    public function __invoke(string $name, $params = []): string
    {
        // A counter that can be used to create element id's etc.
        static $invocation = 0;

        $path = 'components';

        // ->component('@atoms/caret')
        // ->component('@organisms/login-menu')
        if ($name[0] == '@') {
            $parts = explode('/', $name);
            $path = substr(array_shift($parts), 1);
            $name = implode('/', $parts);
        }

        ++$invocation;
        $params['_invocation'] = $invocation;

        return $this->view->render("_ui/$path/" . $name, $params);
    }
}
