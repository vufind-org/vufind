<?php
/**
 * VuFind Action Helper - Captcha handler
 *
 * PHP version 7
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
 * @package  Controller_Plugins
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFind\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * Action helper to manage Captcha fields
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Captcha extends AbstractPlugin
{
    /**
     * Captcha services
     *
     * @var array
     */
    protected $captchas = [];

    /**
     * String array of forms where Captcha is active
     */
    protected $domains = [];

    /**
     * Captcha activated in config
     */
    protected $active = false;

    /**
     * Flash message or throw Exception
     */
    protected $errorMode = 'flash';

    /**
     * Constructor
     *
     * @param \VuFind\Config $config   Config file
     * @param array          $captchas CAPTCHA objects
     *
     * @return void
     */
    public function __construct($config, array $captchas=[])
    {
        $this->captchas = $captchas;
        if (count($captchas) > 0 && isset($config->Captcha->forms)) {
            $this->active = true;
            $this->domains = '*' == trim($config->Captcha->forms)
                ? true
                : array_map(
                    'trim',
                    explode(',', $config->Captcha->forms)
                );
        }
    }

    /**
     * Flash messages ('flash') or throw exceptions ('throw')
     *
     * @param string $mode 'flash' or 'throw'
     *
     * @return bool
     */
    public function setErrorMode($mode): bool
    {
        if (in_array($mode, ['flash', 'throw', 'none'])) {
            $this->errorMode = $mode;
            return true;
        }
        return false;
    }

    /**
     * Pull the captcha field from controller params and check them for accuracy
     *
     * @return bool
     */
    public function verify(): bool
    {
        if (!$this->active()) {
            return true;
        }
        $captchaPassed = false;
        foreach ($this->captchas as $captcha) {
            try {
                $captchaPassed = $captcha->verify(
                    $this->getController()->params()
                );
            } catch (\Exception $e) {
                $captchaPassed = false;
            }

            if ($captchaPassed) {
                break;
            }
        }

        if (!$captchaPassed && $this->errorMode != 'none') {
            if ($this->errorMode == 'flash') {
                $this->getController()->flashMessenger()
                    ->addMessage('captcha_not_passed', 'error');
            } else {
                throw new \Exception('captcha_not_passed');
            }
        }
        return $captchaPassed;
    }

    /**
     * Return whether a specific form is set for Captcha in the config
     *
     * @param string $domain The specific config term are we checking; ie. "sms"
     *
     * @return bool
     */
    public function active($domain = false): bool
    {
        return $this->active
        && ($domain == false || $this->domains === true
        || in_array($domain, $this->domains));
    }
}
