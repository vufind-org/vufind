<?php
/**
 * VuFind Global Registry
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2009.
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
 * @package  Support_Classes
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://code.google.com/p/mobileesp/ MobileESP Project
 */
namespace VuFind;
use Zend\Registry as Zend_Registry;

/**
 * VuFind Global Registry
 *
 * TODO: minimize/eliminate use of this class by finding better techniques for
 * achieving the same effects.
 *
 * @category VuFind2
 * @package  Support_Classes
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://code.google.com/p/mobileesp/ MobileESP Project
 */
class Registry
{
    /**
     * Retrieve VuFind's registry.
     *
     * @return Zend\Registry
     */
    public static function getInstance()
    {
        static $reg = false;
        if (!$reg) {
            $reg = new Zend_Registry();
        }
        return $reg;
    }
}