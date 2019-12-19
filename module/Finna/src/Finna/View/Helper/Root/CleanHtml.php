<?php
/**
 * HTML Cleaner view helper
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2019.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\View\Helper\Root;

/**
 * HTML Cleaner view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class CleanHtml extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Purifier
     *
     * @var \HTMLPurifier
     */
    protected $purifier = null;

    /**
     * Clean up HTML
     *
     * @param string $html HTML
     *
     * @return string
     */
    public function __invoke($html)
    {
        if (false === strpos($html, '<')) {
            return $html;
        }
        if (null === $this->purifier) {
            $config = \HTMLPurifier_Config::createDefault();
            // Details & summary elements not supported by default, add them:
            $def = $config->getHTMLDefinition(true);
            $def->addElement(
                'details',
                'Block',
                'Flow',
                'Common',
                ['open' => new \HTMLPurifier_AttrDef_HTML_Bool(true)]
            );
            $def->addElement('summary', 'Inline', 'Inline', 'Common');
            $this->purifier = new \HTMLPurifier($config);
        }
        return $this->purifier->purify($html);
    }
}
