<?php

/**
 * Flash messenger interface.
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  View
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

declare(strict_types=1);

namespace VuFind\View\FlashMessenger;

/**
 * Flash messenger interface.
 *
 * @category VuFind
 * @package  View
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
interface FlashMessengerInterface
{
    /**
     * Add a message with "error" type
     *
     * @param string|array $message Message as a string, or a complex message as an array
     *
     * @return static
     */
    public function addErrorMessage(string|array $message): static;

    /**
     * Add a message with "info" type
     *
     * @param string|array $message Message as a string, or a complex message as an array
     *
     * @return static
     */
    public function addInfoMessage(string|array $message): static;

    /**
     * Add a message with "success" type
     *
     * @param string|array $message Message as a string, or a complex message as an array
     *
     * @return static
     */
    public function addSuccessMessage(string|array $message): static;

    /**
     * Add a message with "warning" type
     *
     * @param string|array $message Message as a string, or a complex message as an array
     *
     * @return static
     */
    public function addWarningMessage(string|array $message): static;

    /**
     * Add a message.
     *
     * Note: The namespace-specific methods (e.g. addSuccessMessage) above should be used whenever possible.
     *
     * @param string|array $message   Message as a string, or a complex message as an array
     * @param ?string      $namespace Namespace
     *
     * @return static
     */
    public function addMessage(string|array $message, string $namespace): static;

    /**
     * Get error messages.
     *
     * @return array
     */
    public function getErrorMessages(): array;

    /**
     * Get info messages.
     *
     * @return array
     */
    public function getInfoMessages(): array;

    /**
     * Get success messages.
     *
     * @return array
     */
    public function getSuccessMessages(): array;

    /**
     * Get warning messages.
     *
     * @return array
     */
    public function getWarningMessages(): array;

    /**
     * Get messages from the given namespace.
     *
     * Note: The namespace-specific methods (e.g. getSuccessMessages) above should be used whenever possible.
     *
     * @param ?string $namespace Namespace
     *
     * @return array
     */
    public function getMessages(string $namespace): array;

    /**
     * Clear messages from all namespaces.
     *
     * @return void
     */
    public function clearAllMessages(): void;

    /**
     * Clear messages from the given namespace.
     *
     * @param ?string $namespace Namespace
     *
     * @return void
     */
    public function clearMessages(string $namespace): void;
}
