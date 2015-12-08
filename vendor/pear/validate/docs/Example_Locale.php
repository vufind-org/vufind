<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2005 [Your Name]                                  |
// +----------------------------------------------------------------------+
// | This source file is subject to the New BSD license, That is bundled  |
// | with this package in the file LICENSE, and is available through      |
// | the world-wide-web at                                                |
// | http://www.opensource.org/licenses/bsd-license.php                   |
// | If you did not receive a copy of the new BSDlicense and are unable   |
// | to obtain it through the world-wide-web, please send a note to       |
// | pajoye@php.net so we can mail you a copy immediately.                |
// +----------------------------------------------------------------------+
// | Author: Tomas V.V.Cox  <cox@idecnet.com>                             |
// |         Pierre-Alain Joye <pajoye@php.net>                           |
// +----------------------------------------------------------------------+
//
/**
 * Specific validation methods for data used in the [LocaleName]
 *
 * @category   Validate
 * @package    Validate_[LocaleName]
 * @author     [Your Name] <example@example.org>
 * @copyright  1997-2005 [Your name]
 * @license    http://www.opensource.org/licenses/bsd-license.php  New BSD License
 * @version    CVS: $Id$
 * @link       http://pear.php.net/package/Validate_[LocaleName]
 */

/**
 * Data validation class for the [LocaleName]
 *
 * This class provides methods to validate:
 *  - SSN
 *  - Postal code
 *  - Telephone number
 *
 * @category   Validate
 * @package    Validate_[LocaleName]
 * @author     [Your Name] <example@example.org>
 * @copyright  1997-2005 [Your name]
 * @license    http://www.opensource.org/licenses/bsd-license.php  New BSD License
 * @version    Release: @package_version@
 * @link       http://pear.php.net/package/Validate_[LocaleName]
 */
class Validate_LocaleName
{
    /**
     * validates a postcode
     *
     * [Further info goes here]
     *
     * @access public
     * @author [Your name] <example@example.org>
     * @param  string the postcode to be validated
     * @param  bool   optional; strong checks (e.g. against a list of postcodes) (not implanted)
     *
     * @return bool true on success false on failure
     */
    function postalCode($postcode, $strong = false)
    {
        if (ctype_digit($number)) {
            return true;
        }

        return false;
    }

    /**
     * Validates a social security number
     *
     * [Further info goes here]
     *
     * @access  public
     * @author  [Your name] <example@example.org>
     * @param   string $ssn SSN
     *
     * @return  bool true on success false on failure
     */
    function ssn($ssn)
    {
        if (ctype_digit($number)) {
            return true;
        }

        return false;
    }

    /**
     * Validate a phone number
     *
     * [Further info goes here]
     *
     * @access public
     * @author [Your name] <example@example.org>
     * @param  string $number the tel number
     *
     * @return bool true on success false on failure
     */
    function phoneNumber($number)
    {
        if (ctype_digit($number)) {
            return true;
        }

        return false;
    }
}
?>
