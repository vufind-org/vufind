<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | Copyright (c) 2007-2007 Bertrand Gugger           |
// +----------------------------------------------------------------------+
// | This source file is subject to the New BSD license, That is bundled  |
// | with this package in the file LICENSE, and is available through      |
// | the world-wide-web at                                                |
// | http://www.opensource.org/licenses/bsd-license.php                   |
// | If you did not receive a copy of the new BSDlicense and are unable   |
// | to obtain it through the world-wide-web, please send a note to       |
// | pajoye@php.net so we can mail you a copy immediately.                |
// +----------------------------------------------------------------------+
// | Author: Bertrand Gugger  <toggg@php.net>                             |
// +----------------------------------------------------------------------+
//
/**
 * A wrapper to call common local Validate methods
 *
 * @category   Validate
 * @package    Validate_World
 * @author     Bertrand Gugger <bertrand@toggg.com>
 * @copyright  2007-2007 Bertrand Gugger
 * @license    http://www.opensource.org/licenses/bsd-license.php  new BSD
 * @version    CVS: $Id$
 * @link       http://pear.php.net/package/Validate_World
 */

/**
* The value returned when nation class or method does not exists
*/
if (!defined('VALIDATE_WORLD_DEFAULT')) {
    define('VALIDATE_WORLD_DEFAULT', 1);
}

/**
 * Worldwide data validation class for installed locals
 *
 * This class provides methods to validate, if implemented by the local package:
 *  - Identity number
 *  - Social insurance number (ssn)
 *  - Postal code
 *  - Region
 *  - Phone number
 *
 * Any of these functions takes as first argument the nation code
 * then the data to check and method/nation depending extra arguments
 * (following each nation implementation)
 *
 * If nation Validate_XX package is installed and method is available
 * methods return an actual boolean, else VALIDATE_WORLD_DEFAULT default to 1
 * so, the check is weak by default, but 1 indicates it's this default
 * false or true indicate that the check was actually run
 *
 * To set the check as bad if nation method is unavailable, define it to 0 as
 *   define('VALIDATE_WORLD_DEFAULT', 0); // please dont use false here
 * 
 *
 * @category   PHP
 * @package    Validate
 * @subpackage Validate_World
 * @author     Bertrand Gugger <bertrand@toggg.com>
 * @copyright  2007-2007 Bertrand Gugger
 * @license    http://www.opensource.org/licenses/bsd-license.php  new BSD
 * @version    Release: @package_version@
 * @link       http://pear.php.net/package/Validate_World
 */
class Validate_World
{
    /**
     * Validate a personal identity number
     * Formerly mixed with ssn, social security number,
     * but some nations make the distinction
     *
     * @param  string $nationCode nation code
     * @param  string $pin national identity number to check
     * @param  mixed optionnal extra parameters depending on the implementation
     * @return mixed bool if method available: data is valid
     *               number if method unavailable  
     * @access public
     * @static
     */
    function pin($nationCode, $pin)
    {
        // some packages have national signifiant function names
        static $compat = array( 'BE'   => 'nationalId',
                                'esMX' => 'dni',
                                'ES'   => 'dni',
                                'FI'   => 'pin',
                                'IE'   => 'passport',
                                'LV'   => 'personId',
                                'PL'   => 'pesel');

        // we keep the behaviour to default to ssn
        $fun = isset($compat[$nationCode]) ? $compat[$nationCode] : 'ssn';
        return Validate_World::check($fun, func_get_args());
    }

    /**
     * Validate a social insurance (or security) number
     *
     * @param  string $nationCode nation code
     * @param  string $ssn national identity number to check
     * @param  mixed optionnal extra parameters depending on the implementation
     * @return mixed bool if method available: data is valid
     *               number if method unavailable  
     * @access public
     * @static
     */
    function ssn($nationCode, $ssn)
    {
        // some packages have national signifiant function names
        static  $compat = array('esMX' => 'dni',
                                'ES'   => 'dni',
                                'FI'   => 'pin',
                                'IE'   => 'ppsn',
                                'LV'   => 'personId',
                                'PL'   => 'pesel');

        $fun = isset($compat[$nationCode]) ? $compat[$nationCode] : 'ssn';
        return Validate_World::check($fun, func_get_args());
    }

    /**
     * Validates a postal code format
     *
     * @param  string $nationCode nation code
     * @param  string $postalCode postal code to check
     * @param  mixed optionnal extra parameters depending on the implementation
     * @return mixed bool if method available: data is valid
     *               number if method unavailable  
     * @access public
     * @static
     */
    function postalCode($nationCode, $postalCode)
    {
        return Validate_World::check('postalCode', func_get_args());
    }

    /**
     * Validate a region code as US states, french departements, etc.
     *
     * @param  string $nationCode nation code
     * @param  string $region region code to check
     * @param  mixed optionnal extra parameters depending on the implementation
     * @return mixed bool if method available: data is valid
     *               number if method unavailable  
     * @access public
     * @static
     */
    function region($nationCode, $region)
    {
        return Validate_World::check('region', func_get_args());
    }

    /**
     * Validates a phone number
     *
     * @param  string $nationCode nation code
     * @param  string $phoneNumber phone number to check
     * @param  mixed optionnal extra parameters depending on the implementation
     * @return mixed bool if method available: data is valid
     *               number if method unavailable  
     * @access public
     * @static
     */
    function phoneNumber($nationCode, $phoneNumber)
    {
        return Validate_World::check('phoneNumber', func_get_args());
    }

    /**
     * Abstract method to call the national Validate methods
     *
     * @param  string $what method to call
     * @param  array  $args nation code followed by method's own arguments
     * @return mixed bool if method available: data is valid
     *               number if method unavailable  
     * @access public
     * @static
     */
    function check($what, $args)
    {
        static $nations = array();
        $nationCode = array_shift($args);
        if (!isset($nations[$nationCode])) {
            $nations[$nationCode] = is_readable(dirname(__FILE__) .
                                    DIRECTORY_SEPARATOR . $nationCode . '.php')
                                    && require_once 'Validate' . DIRECTORY_SEPARATOR . $nationCode . '.php';
        }
        
        if (!$nations[$nationCode] || !method_exists(
                $class = 'Validate' . '_' . $nationCode, $what)) 
        {
            return VALIDATE_WORLD_DEFAULT;
        }
        
        return (bool) call_user_func_array(array($class, $what), $args);
    }

    /**
     * Lists available locales for Validate
     *
     * @param  mixed   string/array of strings $locale
     *                 Optional searched locale code(s).
     *                 If not present: all available locales.
     *
     * @return array   The available locales (optionaly only the requested ones)
     *
     * @access public
     * @static
     */
    function getLocales($locale = null)
    {
        static $last = '';
        static $ret = null;
        if ($locale === $last and isset($ret)) {
            return $ret;
        }
        $last = $locale;
        $ret = array();
        if (!empty($locale) && is_string($locale)) {
            $locale = array($locale);
        }
        $dh = opendir(dirname(__FILE__));
        if ($dh) {
            while ($fname = readdir($dh)) {
                if (preg_match('#^([A-Z]{2}[a-z_]*)\.php$#i', $fname, $matches)) {
                    if (is_file($dname . $fname) && is_readable($dname . $fname) &&
                        (empty($locale) || in_array($matches[1], $locale))) {
                        $ret[] = $matches[1];
                    }
                }
            }
            closedir($dh);
            sort($ret);
        }
        return $ret;
    }
}
