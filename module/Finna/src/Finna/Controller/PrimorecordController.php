<?php
/**
 * Primo Central Record Controller
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Controller;

/**
 * Primo Central Record Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class PrimorecordController extends \VuFind\Controller\PrimorecordController
{
    use FinnaRecordControllerTrait;

    /**
     * Handle onDispatch event
     *
     * @param \Laminas\Mvc\MvcEvent $e Event
     *
     * @return mixed
     */
    public function onDispatch(\Laminas\Mvc\MvcEvent $e)
    {
        $primoHelper = $this->getViewRenderer()->plugin('primo');
        if (!$primoHelper->isAvailable()) {
            throw new \Exception('Primo is disabled');
        }

        return parent::onDispatch($e);
    }
}
