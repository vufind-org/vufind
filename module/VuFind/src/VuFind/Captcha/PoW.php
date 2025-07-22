<?php

/**
 * Proof of Work CAPTCHA.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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
 * @package  CAPTCHA
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Captcha;

use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\Session\ManagerInterface;

/**
 * ReCaptcha CAPTCHA.
 *
 * @category VuFind
 * @package  CAPTCHA
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class PoW extends AbstractBase
{
    /**
     * Constructor
     *
     * @param string           $hashAlgo   Hash algorithm (default sha256)
     * @param int              $difficulty Number of leading zeroes needed for proof (default 5)
     * @param ManagerInterface $session    Session manager
     */
    public function __construct(
        protected string $hashAlgo,
        protected int $difficulty,
        protected ManagerInterface $session,
    ) {
    }

    /**
     * Get list of URLs with JS dependencies to load for the active CAPTCHA type.
     *
     * @return array
     */
    public function getJsIncludes(): array
    {
        return ['captcha-pow.js'];
    }

    /**
     * Pull the captcha field from controller params and check them for accuracy
     * We pull from the form in case config changed since challenge was sent
     *
     * @param Params $params Controller params
     *
     * @return bool
     */
    public function verify(Params $params): bool
    {
        $nonce = intval($params->fromPost('pow-captcha-nonce'));
        $start = intval($params->fromPost('pow-captcha-start', 0));

        // Verify nonce search began with start
        if ($nonce < $start) {
            error_log(
                'PoW: nonce (' . $nonce . ') not incremental from start (' . $start . ')'
            );
            return false;
        }

        // Verify work
        $challenge = $this->getChallenge($start);
        $hashAlgo = $params->fromPost('pow-captcha-hash-algo');
        $hash = hash($hashAlgo, $challenge . $nonce);
        if (substr($hash, 0, $this->difficulty) !== str_repeat('0', $this->difficulty)) {
            error_log(
                'PoW: nonce (' . $nonce . ') produces invalid hash (' . $hash . ')'
            );
            return false;
        }

        return true;
    }

    /**
     * Generate challenge string from session id
     *
     * @param int $salt Number to add to session id (we use start number)
     *
     * @return string
     */
    public function getChallenge($salt) {
        return hash('sha256', $this->session->getId() . $salt);
    }

    /**
     * Get difficulty from config
     *
     * @return int
     */
    public function getDifficulty() {
        return $this->difficulty;
    }

    /**
     * Get difficulty from config
     *
     * @return string
     */
    public function getHashAlgo() {
        return $this->hashAlgo;
    }

    /**
     * Get random starting point to prevent repeatable work
     *
     * @return int
     */
    public function getStart() {
        return random_int(1000, 9999);
    }
}
