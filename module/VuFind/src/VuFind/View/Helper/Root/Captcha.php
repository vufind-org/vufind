<?php
/**
 * Captcha view helper
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
 * @package  View_Helpers
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;

/**
 * Captcha view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Captcha extends AbstractHelper
{
    /**
     * Captcha service
     *
     * @var \VuFind\Captcha\AbstractBase
     */
    protected $captcha;

    /**
     * Config
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * HTML prefix for ReCaptcha output.
     *
     * @var string
     */
    protected $prefixHtml = '';

    /**
     * HTML suffix for ReCaptcha output.
     *
     * @var string
     */
    protected $suffixHtml = '';

    /**
     * Constructor
     *
     * @param \VuFind\Captcha\AbstractBase|null $captcha
     * @param \Laminas\Config\Config $config
     */
    public function __construct(?\VuFind\Captcha\AbstractBase $captcha,
                                \Laminas\Config\Config $config)
    {
        $this->captcha = $captcha;
        $this->config = $config;
    }

    /**
     * Return this object
     *
     * @return \VuFind\View\Helper\Root\Captcha
     */
    public function __invoke(): \VuFind\View\Helper\Root\Captcha
    {
        return $this;
    }

    /**
     * Generate HTML depending on CAPTCHA type (empty if not active).
     *
     * @param bool $useCaptcha Boolean of active state, for compact templating
     * @param bool $wrapHtml     Include prefix and suffix?
     *
     * @return string
     */
    public function html(bool $useCaptcha = true, bool $wrapHtml = true): string
    {
        if (!isset($this->captcha) || !$useCaptcha) {
            return false;
        }
        if (!$wrapHtml) {
            return $this->captcha->getHtml();
        }
        return $this->prefixHtml . $this->captcha->getHtml() . $this->suffixHtml;
    }

    /**
     * Get list of URLs with JS dependancies to load for the active CAPTCHA type.
     *
     * @return array
     */
    public function js(): array
    {
        return isset($this->captcha) ? $this->captcha->getJsIncludes() : [];
    }

    /**
     * Return whether Captcha is active in the config
     *
     * @return bool
     */
    protected function active(): bool
    {
        return isset($this->captcha) && isset($config->Captcha->forms);
    }
}
