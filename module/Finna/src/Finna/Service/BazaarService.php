<?php

/**
 * Bazaar support service.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023.
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
 * @package  Service
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\Service;

use Laminas\Session\Container;
use Laminas\Session\SessionManager;
use Laminas\Stdlib\ArrayObject;
use VuFind\Db\Service\AuthHashServiceInterface;
use VuFind\Exception\Auth as AuthException;

/**
 * Bazaar support service.
 *
 * @category VuFind
 * @package  Service
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class BazaarService
{
    /**
     * Bazaar session data namespace.
     *
     * @var string
     */
    public const NAMESPACE = 'bazaar';

    /**
     * Bazaar session storage container.
     *
     * @var ?ArrayObject
     */
    protected ?ArrayObject $container = null;

    /**
     * Bazaar add resource callback payload.
     *
     * @var array
     */
    protected array $payload = [];

    /**
     * Constructor.
     *
     * @param AuthHashServiceInterface $authHashService Database service authentication hashes
     * @param SessionManager           $session         Session manager
     */
    public function __construct(
        protected AuthHashServiceInterface $authHashService,
        protected SessionManager $session
    ) {
    }

    /**
     * Creates a Bazaar session if authentication succeeds.
     *
     * @param string $hash Hash to use in authentication
     *
     * @return void
     * @throws AuthException if authentication fails
     */
    public function createSession(string $hash): void
    {
        $row = $this->authHashService->getByHashAndType($hash, 'bazaar', false);

        if (!$row) {
            // The hash has already been used or is invalid.
            throw new AuthException('authentication_error_invalid');
        }

        if (time() - $row->getCreated()->getTimestamp() > 600) {
            // The hash has expired.
            $this->authHashService->deleteAuthHash($row);
            throw new AuthException('authentication_error_expired');
        }

        // Copy data to session and delete hash from table.
        $this->container = new Container(self::NAMESPACE, $this->session);
        $data = json_decode($row['data'], true);
        foreach ($data as $key => $value) {
            $this->container[$key] = $value;
        }
        $this->authHashService->deleteAuthHash($row);
    }

    /**
     * Return whether a Bazaar session is active.
     *
     * @return bool
     */
    public function isSessionActive(): bool
    {
        return null !== $this->getSessionStorageContainer();
    }

    /**
     * Destroys a Bazaar session if one exists.
     *
     * @return void
     */
    public function destroySession()
    {
        $this->session->getStorage()->offsetUnset(self::NAMESPACE);
        $this->container = null;
    }

    /**
     * Sets selection data if a Bazaar session is active.
     *
     * @param string $uid  UID
     * @param string $name Name
     *
     * @return bool Whether the data was set or not
     */
    public function setSelectionData(string $uid, string $name): bool
    {
        if (!$this->isSessionActive()) {
            return false;
        }
        $this->payload['uid'] = $uid;
        $this->payload['name'] = $name;
        return true;
    }

    /**
     * Returns an add resource callback payload, or null if a Bazaar session is not
     * active or payload data has not been set.
     *
     * @return ?string
     */
    public function getAddResourceCallbackPayload(): ?string
    {
        if (
            !$this->isSessionActive()
            || empty($this->payload['uid'])
            || empty($this->payload['name'])
        ) {
            return null;
        }
        return base64_encode(json_encode($this->payload));
    }

    /**
     * Returns the add resource callback URL, or null if it is not set or a Bazaar
     * session is not active.
     *
     * @return ?string
     */
    public function getAddResourceCallbackUrl(): ?string
    {
        return $this->get('add_resource_callback_url');
    }

    /**
     * Returns the cancel URL, or null if it is not set or a Bazaar session is not
     * active.
     *
     * @return ?string
     */
    public function getCancelUrl(): ?string
    {
        return $this->get('cancel_url');
    }

    /**
     * Returns the Bazaar session storage container, or null if a Bazaar session is
     * not active.
     *
     * @return ?ArrayObject
     */
    protected function getSessionStorageContainer(): ?ArrayObject
    {
        if (null === $this->container) {
            $this->container
                = $this->session->getStorage()->offsetGet(self::NAMESPACE);
        }
        return $this->container;
    }

    /**
     * Returns a value from Bazaar session storage container, or null if the key does
     * not exist or a Bazaar session is not active.
     *
     * @param string $key Key
     *
     * @return mixed|null
     */
    protected function get(string $key)
    {
        return null !== $this->getSessionStorageContainer()
            ? $this->container[$key] ?? null
            : null;
    }
}
