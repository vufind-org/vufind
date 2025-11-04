<?php

/**
 * Finna unsupported function view trait.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2021.
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
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace Finna\Controller;

/**
 * Finna unsupported function view trait.
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
trait FinnaUnsupportedFunctionViewTrait
{
    /**
     * Check if current library card supports a function. If not supported, show
     * a message and a notice about the possibility to change library card.
     *
     * @param string  $function      Function to check
     * @param boolean $checkFunction Use checkFunction() if true,
     * checkCapability() otherwise
     *
     * @return mixed \Laminas\View if the function is not supported, false otherwise
     */
    protected function createViewIfUnsupported($function, $checkFunction = false)
    {
        $params = ['patron' => $this->catalogLogin()];
        if ($checkFunction) {
            $supported = $this->getILS()->checkFunction($function, $params);
        } else {
            $supported = $this->getILS()->checkCapability($function, $params);
        }

        if (!$supported) {
            $view = $this->createViewModel();
            $view->noSupport = true;
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('no_ils_support_for_' . strtolower($function));
            return $view;
        }
        return false;
    }
}
