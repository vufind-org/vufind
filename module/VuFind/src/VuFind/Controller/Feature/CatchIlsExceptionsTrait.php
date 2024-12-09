<?php

/**
 * VuFind Action Feature Trait - Catch ILS exceptions from actions with an OnDispatch
 * handler
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @package  Controller_Plugins
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Controller\Feature;

use Laminas\Mvc\Exception\DomainException;
use VuFind\Exception\ILS as ILSException;

/**
 * VuFind Action Feature Trait - Catch ILS exceptions from actions with an OnDispatch
 * handler
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait CatchIlsExceptionsTrait
{
    /**
     * Optional custom exception response
     *
     * If set, this is returned on exception instead of a default ViewModel
     *
     * @var mixed
     */
    protected $ilsExceptionResponse = null;

    /**
     * Execute the request
     *
     * @param \Laminas\Mvc\MvcEvent $event Event
     *
     * @return mixed
     * @throws DomainException
     */
    public function onDispatch(\Laminas\Mvc\MvcEvent $event)
    {
        // Catch any ILSExceptions thrown during processing and display a generic
        // failure message to the user (instead of going to the fatal exception
        // screen). This offers a slightly more forgiving experience when there is
        // an unexpected ILS issue. Note that most ILS exceptions are handled at a
        // lower level in the code (see \VuFind\ILS\Connection and the config.ini
        // loadNoILSOnFailure setting), but there are some rare edge cases (for
        // example, when the MultiBackend driver fails over to NoILS while used in
        // combination with MultiILS authentication) that could lead here.
        try {
            return parent::onDispatch($event);
        } catch (ILSException $exception) {
            // Always display generic message:
            $this->flashMessenger()->addErrorMessage('ils_connection_failed');
            // In development mode, also show technical failure message:
            if ('development' == APPLICATION_ENV) {
                $this->flashMessenger()->addErrorMessage($exception->getMessage());
            }
            $actionResponse = $this->ilsExceptionResponse ?? $this->createViewModel();
            $event->setResult($actionResponse);
            return $actionResponse;
        }
    }
}
