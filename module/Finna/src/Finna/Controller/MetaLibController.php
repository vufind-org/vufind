<?php
/**
 * MetaLib Controller
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015-2016.
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
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace Finna\Controller;

/**
 * MetaLib Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class MetaLibController extends \VuFind\Controller\AbstractBase
{
    /**
     * Home action -- show MetaLib unavailable message
     *
     * @return mixed
     */
    public function homeAction()
    {
        return $this->showMetalibUnavailableMessage();
    }

    /**
     * Search action -- show MetaLib unavailable message
     *
     * @return mixed
     */
    public function searchAction()
    {
        return $this->showMetalibUnavailableMessage();
    }

    /**
     * Advanced search -- show MetaLib unavailable message
     *
     * @return mixed
     */
    public function advancedAction()
    {
        return $this->showMetalibUnavailableMessage();
    }

    /**
     * Show MetaLib unavailable show
     *
     * @return mixed
     */
    protected function showMetalibUnavailableMessage()
    {
        return $this->forwardTo('Content', 'Content', ['page' => 'metalib']);
    }
}
