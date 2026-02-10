<?php

/**
 * Flash messenger.
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

use Closure;
use Laminas\Session\Container;
use Laminas\Stdlib\SplQueue;

/**
 * Flash messenger.
 *
 * @category VuFind
 * @package  View
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FlashMessenger implements FlashMessengerInterface
{
    /**
     * Default messages namespace
     *
     * @var string
     */
    public const NAMESPACE_DEFAULT = 'default';

    /**
     * Success messages namespace
     *
     * @var string
     */
    public const NAMESPACE_SUCCESS = 'success';

    /**
     * Warning messages namespace
     *
     * @var string
     */
    public const NAMESPACE_WARNING = 'warning';

    /**
     * Error messages namespace
     *
     * @var string
     */
    public const NAMESPACE_ERROR = 'error';

    /**
     * Info messages namespace
     *
     * @var string
     */
    public const NAMESPACE_INFO = 'info';

    /**
     * Lazily instantiated session container
     *
     * @var ?Container
     */
    protected ?Container $container = null;

    /**
     * Constructor.
     *
     * @param Closure $sessionContainerFactory Session container factory callback
     */
    public function __construct(protected Closure $sessionContainerFactory)
    {
    }

    /**
     * Add a message with "error" type
     *
     * @param string|array $message Message as a string, or a complex message as an array
     *
     * @return static
     */
    public function addErrorMessage(string|array $message): static
    {
        return $this->addMessage($message, self::NAMESPACE_ERROR);
    }

    /**
     * Add a message with "info" type
     *
     * @param string|array $message Message as a string, or a complex message as an array
     *
     * @return static
     */
    public function addInfoMessage(string|array $message): static
    {
        return $this->addMessage($message, self::NAMESPACE_INFO);
    }

    /**
     * Add a message with "success" type
     *
     * @param string|array $message Message as a string, or a complex message as an array
     *
     * @return static
     */
    public function addSuccessMessage(string|array $message): static
    {
        return $this->addMessage($message, self::NAMESPACE_SUCCESS);
    }

    /**
     * Add a message with "warning" type
     *
     * @param string|array $message Message as a string, or a complex message as an array
     *
     * @return static
     */
    public function addWarningMessage(string|array $message): static
    {
        return $this->addMessage($message, self::NAMESPACE_WARNING);
    }

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
    public function addMessage(string|array $message, string $namespace): static
    {
        $container = $this->getContainer();

        // We don't really need SplQueue, but it's used for compatibility with Laminas' FlashMessenger.
        if (!isset($container->{$namespace})) {
            $container->{$namespace} = new SplQueue();
        }
        $container->{$namespace}->push($message);
        return $this;
    }

    /**
     * Get error messages.
     *
     * @return array
     */
    public function getErrorMessages(): array
    {
        return $this->getMessages(self::NAMESPACE_ERROR);
    }

    /**
     * Get info messages.
     *
     * @return array
     */
    public function getInfoMessages(): array
    {
        return $this->getMessages(self::NAMESPACE_INFO);
    }

    /**
     * Get success messages.
     *
     * @return array
     */
    public function getSuccessMessages(): array
    {
        return $this->getMessages(self::NAMESPACE_SUCCESS);
    }

    /**
     * Get warning messages.
     *
     * @return array
     */
    public function getWarningMessages(): array
    {
        return $this->getMessages(self::NAMESPACE_WARNING);
    }

    /**
     * Get messages from the given namespace.
     *
     * Note: The namespace-specific methods (e.g. getSuccessMessages) above should be used whenever possible.
     *
     * @param ?string $namespace Namespace
     *
     * @return array
     */
    public function getMessages(string $namespace): array
    {
        $container = $this->getContainer();
        $queue = $container->{$namespace} ?? null;
        return $queue?->toArray() ?? [];
    }

    /**
     * Clear messages from all namespaces.
     *
     * @return void
     */
    public function clearAllMessages(): void
    {
        $container = $this->getContainer();
        foreach (array_keys($container->getArrayCopy()) as $namespace) {
            unset($container->{$namespace});
        }
    }

    /**
     * Clear messages from the given namespace.
     *
     * @param ?string $namespace Namespace
     *
     * @return void
     */
    public function clearMessages(string $namespace): void
    {
        $container = $this->getContainer();
        unset($container->{$namespace});
    }

    /**
     * Get the session container.
     *
     * @return Container
     */
    protected function getContainer(): Container
    {
        if (null === $this->container) {
            $this->container = ($this->sessionContainerFactory)();
        }
        return $this->container;
    }
}
