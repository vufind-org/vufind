<?php

/**
 * Widget interface definition.
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
 * @package  Controller
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Controller\Widget;

use Zend\Stdlib\Parameters;

/**
 * Widget interface definition.
 *
 * A Widget is a special type of controller plugin comparable to a web portal
 * Portlet. It is a self-contained application component that is interacted
 * and interacts with the main application. The key feature of a Widget is its
 * capability to represent its internal state as a set of query parameters.
 *
 * This interface only defines the basic methods to set and get the widget
 * state.
 *
 * @category VuFind2
 * @package  Controller
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
interface WidgetInterface
{
    /**
     * Set widget state based on query parameters.
     *
     * @param Parameters $parameters Query parameters
     *
     * @return void
     */
    public function setState(Parameters $parameters);

    /**
     * Return array representing the widget state as query parameters.
     *
     * @return array
     */
    public function getState();

}