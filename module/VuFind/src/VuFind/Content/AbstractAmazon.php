<?php
/**
 * Generic Amazon content loader.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Content;

/**
 * Generic Amazon content loader.
 *
 * @category VuFind
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractAmazon extends AbstractBase
{
    /**
     * Associate ID
     *
     * @var string
     */
    protected $associate;

    /**
     * Secret key
     *
     * @var string
     */
    protected $secret;

    /**
     * "Supplied by Amazon" label, appropriately translated
     *
     * @var string
     */
    protected $label;

    /**
     * Constructor
     *
     * @param string $associate Associate ID
     * @param string $secret    Secret key
     * @param string $label     "Supplied by Amazon" label, appropriately translated
     */
    public function __construct($associate, $secret, $label)
    {
        $this->associate = $associate;
        $this->secret = $secret;
        $this->label = $label;
    }

    /**
     * Get copyright message
     *
     * @param string $isbn ISBN to use for linking
     *
     * @return string
     */
    protected function getCopyright($isbn)
    {
        return '<div><a target="new" href="http://amazon.com/dp/'
            . $isbn . '">' . htmlspecialchars($this->label) . '</a></div>';
    }
}
