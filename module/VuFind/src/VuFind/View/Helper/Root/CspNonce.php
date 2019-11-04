<?php

/**
 * Class CspNonce
 *
 * PHP version 7
 *
 * Copyright (C) Moravian Library 2019.
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
 * @package  VuFind\View\Helper\Root
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://knihovny.cz Main Page
 */
namespace VuFind\View\Helper\Root;

use VuFind\Security\NonceGenerator;

/**
 * CspNonce view helper
 *
 * @category VuFind
 * @package  VuFind\View\Helper\Root
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CspNonce extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Nonce generator object
     *
     * @var NonceGenerator
     */
    protected $nonceGenerator;

    /**
     * Constructor
     *
     * @param NonceGenerator $nonceGenerator Nonce generator object
     */
    public function __construct(NonceGenerator $nonceGenerator)
    {
        $this->nonceGenerator = $nonceGenerator;
    }

    /**
     * Set up context for helper
     *
     * @return string
     * @throws \Exception
     */
    public function __invoke()
    {
        return $this->nonceGenerator->getNonce();
    }
}
