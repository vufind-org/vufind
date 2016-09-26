<?php
/**
 * Recaptcha view helper
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2016.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\View\Helper\Bootstrap3;

/**
 * Recaptcha view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Recaptcha extends \VuFind\View\Helper\Root\Recaptcha
{
    /**
     * Constructor
     *
     * @param \ZendService\Recaptcha\Recaptcha $rc     Custom formatted Recaptcha
     * @param \VuFind\Config                   $config Config object
     */
    public function __construct($rc, $config)
    {
        $this->prefixHtml = '<div class="form-group">' .
            '<div class="col-sm-9 col-sm-offset-3">';
        $this->suffixHtml = '</div></div>';
        parent::__construct($rc, $config);
    }
}
