<?php
/**
 * Secure session delegator
 *
 * Copyright (C) Villanova University 2018,
 *               Leipzig University Library <info@ub.uni-leipzig.de> 2018.
 *
 * PHP version 7
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

use VuFind\Cookie\CookieManager;
use VuFind\Db\Table\PluginManager;
use Zend\Config\Config;
use Zend\Crypt\BlockCipher;
use Zend\Math\Rand;

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
     * @param HandlerInterface $handler {@see $handler}
     */
    public function __construct(CookieManager $cookieManager, HandlerInterface $handler)
    {
        $this->handler = $handler;
        $this->cookieManager = $cookieManager;
        $this->cipher = BlockCipher::factory('openssl');
    }

    /**
     * Enable session writing (default)
     *
     * @return void
     */
    public function enableWrites()
    {
        $this->handler->enableWrites();
    }

    /**
     * Disable session writing, i.e. make it read-only
     *
     * @return void
     */
    public function disableWrites()
    {
        $this->handler->disableWrites();
    }

    /**
     * Set configuration.
     *
     * @param Config $config Session configuration ([Session] section of
     * config.ini)
     *
     * @return void
     */
    public function setConfig(Config $config)
    {
        $this->handler->setConfig($config);
    }

    /**
     * Closes the wrapped handler;
     *
     * @return bool
     */
    public function close()
    {
        return $this->handler->close();
    }

    /**
     * Destroys a session.
     *
     * @param string $session_id Session id
     *
     * @return bool
     */
    public function destroy($session_id)
    {
        return $this->handler->destroy($session_id);
    }

    /**
     * Triggers garbage collection.
     *
     * @param int $maxlifetime Session maximum lifetime.
     *
     * @return bool
     */
    public function gc($maxlifetime)
    {
        return $this->handler->gc($maxlifetime);
    }

    /**
     * Opens a session.
     *
     * @param string $save_path Session save path
     * @param string $name      Session name
     *
     * @return bool
     */
    public function open($save_path, $name)
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
     * @return bool|string
     */
    public function read($session_id)
    {
        $data = $this->handler->read($session_id);
        return $data ? $this->cipher->decrypt($data) : $data;
    }

    /**
     * Writes session data.
     *
     * @param string $session_id   Session id
     * @param string $session_data Session data
     *
     * @return bool
     */
    public function write($session_id, $session_data)
    {
        $data = $this->cipher->encrypt($session_data);
        return $this->handler->write($session_id, $data);
    }

    /**
     * Get the wrapped handlers table manager.
     *
     * @return PluginManager
     * @throws \Exception
     */
    public function getDbTableManager()
    {
        return $this->handler->getDbTableManager();
    }

    /**
     * Set the wrapped handlers table manager.
     *
     * @param PluginManager $manager Table manager
     *
     * @return void
     */
    public function setDbTableManager(PluginManager $manager)
    {
        $this->handler->setDbTableManager($manager);
    }
}
