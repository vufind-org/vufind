<?php

/**
 * VuFind Action Feature Trait - handleException method that handles ILS exceptions.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022-2026.
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
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Action;

use Psr\Http\Message\ResponseInterface;
use Throwable;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\Exception\ILS as ILSException;

/**
 * VuFind Action Feature Trait - handleException method that handles ILS exceptions.
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait HandleIlsExceptionsTrait
{
    /**
     * Optional custom exception response.
     *
     * If set, this is returned on exception instead of a default response
     *
     * @var ?ResponseInterface
     */
    protected ?ResponseInterface $ilsExceptionResponse = null;

    /**
     * Handle an exception during action.
     *
     * @param Throwable $exception Exception
     *
     * @return ResponseInterface
     */
    protected function handleException(Throwable $exception): ResponseInterface
    {
        if (!($this instanceof AbstractAction) || !($exception instanceof ILSException)) {
            parent::handleException($exception);
        }

        // Always display generic message:
        $this->getHelper(FlashMessagesHelper::class)->addErrorMessage('ils_connection_failed');
        // In development mode, also show technical failure message:
        if ('development' == APPLICATION_ENV) {
            $this->getHelper(FlashMessagesHelper::class)->addErrorMessage((string)$exception);
        }
        if (null !== $this->ilsExceptionResponse) {
            return $this->ilsExceptionResponse;
        }
        if ($this instanceof AbstractTemplateRenderingAction) {
            return $this->renderTemplate($this->request, $this->response);
        }
        return $this->response;
    }
}
