<?php

/**
 * Action helper for flash messages.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2026.
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
 * @package  Action_Helper
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFind\ActionHelper;

use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\FlashMessenger\FlashMessenger;

/**
 * Action helper for flash messages.
 *
 * @category VuFind
 * @package  Action_Helper
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class FlashMessagesHelper implements HelperInterface
{
    /**
     * Constructor.
     *
     * @param FlashMessenger $flashMessenger Flash messenger
     */
    #[Autowire()]
    public function __construct(
        protected FlashMessenger $flashMessenger,
    ) {
    }

    /**
     * Get flash messenger.
     *
     * @return FlashMessenger
     */
    public function getFlashMessenger(): FlashMessenger
    {
        return $this->flashMessenger;
    }

    /**
     * Add a message with "error" type.
     *
     * @param string|array $message Message as a string, or a complex message as an array
     *
     * @return void
     */
    public function addErrorMessage(string|array $message): void
    {
        $this->flashMessenger->addErrorMessage($message);
    }

    /**
     * Add a message with "info" type.
     *
     * @param string|array $message Message as a string, or a complex message as an array
     *
     * @return void
     */
    public function addInfoMessage(string|array $message): void
    {
        $this->flashMessenger->addInfoMessage($message);
    }

    /**
     * Add a message with "success" type.
     *
     * @param string|array $message Message as a string, or a complex message as an array
     *
     * @return void
     */
    public function addSuccessMessage(string|array $message): void
    {
        $this->flashMessenger->addSuccessMessage($message);
    }

    /**
     * Add a message with "warning" type.
     *
     * @param string|array $message Message as a string, or a complex message as an array
     *
     * @return void
     */
    public function addWarningMessage(string|array $message): void
    {
        $this->flashMessenger->addWarningMessage($message);
    }
}
