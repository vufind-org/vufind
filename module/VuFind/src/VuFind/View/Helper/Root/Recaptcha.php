<?php
/**
 * Recaptcha view helper
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\View\Helper\Root;
use Zend\View\Helper\AbstractHelper;

/**
 * Recaptcha view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Recaptcha extends AbstractHelper
{
    /**
     * Recaptcha controller helper
     *
     * @var Recaptcha
     */
    protected $recaptcha;

    /**
     * Recaptcha config
     *
     * @var Config
     */
    protected $active;

    /**
     * Constructor
     *
     * @param \ZendService\Recaptcha\Recaptcha $rc     Custom formatted Recaptcha
     * @param \VuFind\Config                   $config Config object
     */
    public function __construct($rc, $config)
    {
        $this->recaptcha = $rc;
        $this->active = isset($config->Captcha);
    }

    /**
     * Return this object
     *
     * @return VuFind\View\Helper\Root\Recaptcha
     */
    public function __invoke()
    {
        return $this;
    }

    /**
     * Generate <div> with ReCaptcha from render.
     *
     * @param boolean $useRecaptcha Boolean of active state, for compact templating
     *
     * @return string $html
     */
    public function html($useRecaptcha = true)
    {
        if (!isset($useRecaptcha) || !$useRecaptcha) {
            return false;
        }
        return $this->recaptcha->getHtml();
    }

    /**
     * Return whether Captcha is active in the config
     *
     * @return boolean
     */
    public function active()
    {
        return $this->active;
    }
}