<?php

/**
 * Secure session delegator
 *
 * Copyright (C) Villanova University 2018,
 *               Leipzig University Library <info@ub.uni-leipzig.de> 2018.
 *
 * PHP version 8
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
 * @package  Session_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:session_handlers Wiki
 */

namespace VuFind\Session;

use Laminas\Crypt\BlockCipher;
use Laminas\Math\Rand;
use VuFind\Cookie\CookieManager;
use VuFind\Db\Table\PluginManager;

use function func_get_args;

/**
 * Secure session delegator
 *
 * @category VuFind
 * @package  Session_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:session_handlers Wiki
 */
class SecureDelegator implements HandlerInterface
{
    /**
     * The block cipher for en/decrypting session data.
     *
     * @var BlockCipher
     */
    protected $cipher;

    /**
     * VuFind cookie manager service.
     *
     * @var CookieManager
     */
    protected $cookieManager;

    /**
     * The wrapped session handler.
     *
     * @var HandlerInterface
     */
    protected $handler;

    /**
     * SecureDelegator constructor.
     *
     * @param CookieManager    $cookieManager VuFind cookie manager service.
     * @param HandlerInterface $handler       The wrapped session handler.
     */
    public function __construct(
        CookieManager $cookieManager,
        HandlerInterface $handler
    ) {
        $this->handler = $handler;
        $this->cookieManager = $cookieManager;
        $this->cipher = BlockCipher::factory('openssl');
    }

    /**
     * Closes a session.
     *
     * @return bool
     */
    public function close(): bool
    {
        return $this->__call(__FUNCTION__, []);
    }

    /**
     * Destroys a session.
     *
     * @param string $id Session ID
     *
     * @return bool
     */
    public function destroy(string $id): bool
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * Performs garbage collection.
     *
     * @param int $max_lifetime Maximum session life time
     *
     * @return int|false
     */
    public function gc(int $max_lifetime): int|false
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * Opens a session.
     *
     * @param string $save_path Session save path
     * @param string $name      Session name
     *
     * @return bool
     */
    public function open($save_path, $name): bool
    {
        $cookieName = "{$name}_KEY";
        $cipherKey = ($cookieValue = $this->cookieManager->get($cookieName))
            ?? base64_encode(Rand::getBytes(64));

        if (!$cookieValue) {
            $lifetime = session_get_cookie_params()['lifetime'];
            $expire = $lifetime ? $lifetime + time() : 0;
            $this->cookieManager->set($cookieName, $cipherKey, $expire);
        }

        $this->cipher->setKey(base64_decode($cipherKey));
        return $this->handler->open($save_path, $name);
    }

    /**
     * Read a sessions data.
     *
     * @param string $session_id Session id
     *
     * @return string|false
     */
    public function read($session_id): string|false
    {
        $data = $this->handler->read($session_id);
        return $data ? ($this->cipher->decrypt($data) ?: '') : $data;
    }

    /**
     * Writes session data.
     *
     * @param string $session_id   Session id
     * @param string $session_data Session data
     *
     * @return bool
     */
    public function write($session_id, $session_data): bool
    {
        $data = $this->cipher->encrypt($session_data);
        return $this->handler->write($session_id, $data);
    }

    /**
     * Enable session writing (default)
     *
     * @return void
     */
    public function enableWrites()
    {
        $this->__call(__FUNCTION__, []);
    }

    /**
     * Disable session writing, i.e. make it read-only
     *
     * @return void
     */
    public function disableWrites()
    {
        $this->__call(__FUNCTION__, []);
    }

    /**
     * Get the plugin manager. Throw an exception if it is missing.
     *
     * @throws \Exception
     * @return PluginManager
     */
    public function getDbTableManager()
    {
        return $this->__call(__FUNCTION__, []);
    }

    /**
     * Set the plugin manager.
     *
     * @param PluginManager $manager Plugin manager
     *
     * @return void
     */
    public function setDbTableManager(PluginManager $manager)
    {
        $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * Pass calls to non-existing methods to the wrapped Handler
     *
     * @param string $name      Name of the method being called
     * @param array  $arguments Passed Arguments
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return $this->handler->{$name}(...$arguments);
    }
}
