<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2005 Piotr Klaban, Damien Seguy, Helgi Þormar     |
// |                        Þorbjörnsson, Pierre-Alain Joye               |
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
 * Specific validation methods for International Standard Product Numbers (ISPN)
 *
 * @category   Validate
 * @package    Validate_ISPN
 * @author     Piotr Klaban <makler@man.torun.pl>
 * @author     Damien Seguy <dams@nexen.net>
 * @author     Helgi Þormar Þorbjörnsson <dufuz@php.net>
 * @copyright   1997-2005 Piotr Klaban, Damien Seguy, Helgi Þormar Þorbjörnsson,
 *                        Pierre-Alain Joye
 * @license    http://www.opensource.org/licenses/bsd-license.php  new BSD
 * @version    CVS: $Id: ISPN.php,v 1.13 2006/08/17 19:20:51 makler Exp $
 * @link       http://pear.php.net/package/Validate_ISPN
 */

/**
 * Data validation class for International Standard Product Numbers (ISPN)
 *
 * This class provides methods to validate:
 *  - ISBN (International Standard Book Number)
 *  - ISSN (International Standard Serial Number)
 *  - ISMN (International Standard Music Number)
 *  - ISRC (International Standard Recording Code)
 *  - EAN/UCC-8 number
 *  - EAN/UCC-13 number
 *  - EAN/UCC-14 number
 *  - UCC-12 (U.P.C.) ID number
 *  - SSCC (Serial Shipping Container Code)
 *
 * @category   Validate
 * @package    Validate_ISPN
 * @author     Piotr Klaban <makler@man.torun.pl>
 * @author     Damien Seguy <dams@nexen.net>
 * @author     Helgi Þormar Þorbjörnsson <dufuz@php.net>
 * @copyright   1997-2005 Piotr Klaban, Damien Seguy, Helgi Þormar Þorbjörnsson,
 *                        Pierre-Alain Joye
 * @license    http://www.opensource.org/licenses/bsd-license.php  new BSD
 * @version    Release: @package_version@
 * @link       http://pear.php.net/package/Validate_ISPN
 */
class Validate_ISPN
{
    function isbn($isbn)
    {
        if (preg_match("/[^0-9 IXSBN-]/", $isbn)) {
            return false;
        }

        $isbn = strtoupper($isbn);
        $isbn = str_replace(array('ISBN', '-', ' ', "\t", "\n"), '', $isbn);

        if (strlen($isbn) == 13) {
            return Validate_ISPN::isbn13($isbn);
        } elseif (strlen($isbn) == 10) {
            return Validate_ISPN::isbn10($isbn);
        } else {
            return false;
        }
    }

    /**
     * Validate a ISBN 13 number
     * The ISBN is a unique machine-readable identification number,
     * which marks any book unmistakably.
     *
     * This function checks given number according
     *
     * Manual can be found at http://www.isbn-international.org
     *
     * @param  string  $isbn number (only numeric chars will be considered)
     * @return bool    true if number is valid, otherwise false
     * @access public
     * @author Helgi Þormar <dufuz@php.net>
     * @author Piotr Klaban <makler@man.torun.pl>
     */
    function isbn13($isbn)
    {
        if (preg_match("/[^0-9 ISBN-]/", $isbn)) {
            return false;
        }

        $isbn = strtoupper($isbn);
        $isbn = str_replace(array('ISBN', '-', ' ', "\t", "\n"), '', $isbn);
        if (!preg_match('/^[0-9]{13}$/', $isbn)) {
            return false;
        }

        return Validate_ISPN::ean13($isbn);
    }

    /**
     * Validate a ISBN 10 number
     * The ISBN is a unique machine-readable identification number,
     * which marks any book unmistakably.
     *
     * This function checks given number according
     *
     * Manual can be found at http://www.isbn-international.org
     *
     * @param  string  $isbn number (only numeric chars will be considered)
     * @return bool    true if number is valid, otherwise false
     * @access public
     * @author Damien Seguy <dams@nexen.net>
     * @author Helgi Þormar <dufuz@php.net>
     */
    function isbn10($isbn)
    {
        static  $weights_isbn = array(10,9,8,7,6,5,4,3,2);

        if (preg_match("/[^0-9 IXSBN-]/", $isbn)) {
            return false;
        }

        $isbn = strtoupper($isbn);
        $isbn = str_replace(array('ISBN', '-', ' ', "\t", "\n"), '', $isbn);
        if (strlen($isbn) != 10) {
            return false;
        }

        if (!preg_match('/^[0-9]{9}[0-9X]$/', $isbn)) {
            return false;
        }

        // Requires base class Validate
        require_once 'Validate.php';
        return Validate::_checkControlNumber($isbn, $weights_isbn, 11, 11);
    }


    /**
     * Validate an ISSN (International Standard Serial Number)
     *
     * This function checks given ISSN number
     * ISSN identifies periodical publications:
     * http://www.issn.org
     *
     * @param  string  $issn number (only numeric chars will be considered)
     * @return bool    true if number is valid, otherwise false
     * @access public
     * @author Piotr Klaban <makler@man.torun.pl>
     */
    function issn($issn)
    {
        static $weights_issn = array(8,7,6,5,4,3,2);

        $issn = strtoupper($issn);
        $issn = str_replace(array('ISSN', '-', '/', ' ', "\t", "\n"), '', $issn);
        $issn_num = str_replace('X', '0', $issn);

        // check if this is an 8-digit number
        if (!is_numeric($issn_num) || strlen($issn) != 8) {
            return false;
        }

        // Requires base class Validate
        require_once 'Validate.php';
        return Validate::_checkControlNumber($issn, $weights_issn, 11, 11);
    }

    /**
     * Validate a ISMN (International Standard Music Number)
     *
     * This function checks given ISMN number (ISO Standard 10957)
     * ISMN identifies all printed music publications from all over the world
     * whether available for sale, hire or gratis--whether a part, a score,
     * or an element in a multi-media kit.
     *
     * Manual can be found at:
     * http://www.ismn-international.org/
     *
     * @param  string  $ismn ISMN number
     * @return bool    true if number is valid, otherwise false
     * @access public
     * @author Piotr Klaban <makler@man.torun.pl>
     */
    function ismn($ismn)
    {
        static $weights_ismn = array(3,1,3,1,3,1,3,1,3);

        $ismn = strtoupper($ismn);
        $ismn = str_replace(array('ISMN', '-', '/', ' ', "\t", "\n"), '', $ismn);
        // First char has to be M (after ISMN has been stripped if present)
        if ($ismn{0} != 'M') {
            return false;
        }

        // change M to 3
        $ismn{0} = 3;

        // check if this is a 10-digit number
        if (!is_numeric($ismn) || strlen($ismn) != 10) {
            return false;
        }

        // Requires base class Validate
        require_once 'Validate.php';
        return Validate::_checkControlNumber($ismn, $weights_ismn, 10, 10);
    }

    /**
     * Validate a ISRC (International Standard Recording Code)
     *
     * This function validates an International Standard Recording Code
     * which is the international identification system for sound recordings
     * and music videorecordings.
     *
     * @param  string  $isrc ISRC number
     * @return bool    true if number is valid, otherwise false
     * @see    http://www.ifpi.org/isrc/isrc_handbook.html
     * @access public
     * @author David Grant <david@grant.org.uk>
     */
    function isrc($isrc)
    {
        $isrc = str_replace(array('ISRC', '-', ' '), '', strtoupper($isrc));
        if (!preg_match("/[A-Z]{2}[A-Z0-9]{3}[0-9]{7}/", $isrc)) {
            return false;
        }

        return true;
    }

    /**
     * Validate a EAN/UCC-8 number
     *
     * This function checks given EAN8 number
     * used to identify trade items and special applications.
     * http://www.ean-ucc.org/
     * http://www.uc-council.org/checkdig.htm
     *
     * @param  string  $ean number (only numeric chars will be considered)
     * @return bool    true if number is valid, otherwise false
     * @access public
     * @see Validate_ISPN::process()
     * @author Piotr Klaban <makler@man.torun.pl>
     */
    function ean8($ean)
    {
        static $weights_ean8 = array(3,1,3,1,3,1,3);
        return Validate_ISPN::process($ean, 8, $weights_ean8, 10, 10);
    }

    /**
     * Validate a EAN/UCC-13 number
     *
     * This function checks given EAN/UCC-13 number used to identify
     * trade items, locations, and special applications (e.g., coupons)
     * http://www.ean-ucc.org/
     * http://www.uc-council.org/checkdig.htm
     *
     * @param  string  $ean number (only numeric chars will be considered)
     * @return bool    true if number is valid, otherwise false
     * @access public
     * @see Validate_ISPN::process()
     * @author Piotr Klaban <makler@man.torun.pl>
     */
    function ean13($ean)
    {
        static $weights_ean13 = array(1,3,1,3,1,3,1,3,1,3,1,3);
        return Validate_ISPN::process($ean, 13, $weights_ean13, 10, 10);
    }

    /**
     * Validate a EAN/UCC-14 number
     *
     * This function checks given EAN/UCC-14 number
     * used to identify trade items.
     * http://www.ean-ucc.org/
     * http://www.uc-council.org/checkdig.htm
     *
     * @param  string  $ean number (only numeric chars will be considered)
     * @return bool    true if number is valid, otherwise false
     * @access public
     * @see Validate_ISPN::process()
     * @author Piotr Klaban <makler@man.torun.pl>
     */
    function ean14($ean)
    {
        static $weights_ean14 = array(3,1,3,1,3,1,3,1,3,1,3,1,3);
        return Validate_ISPN::process($ean, 14, $weights_ean14, 10, 10);
    }

    /**
     * Validate a UCC-12 (U.P.C.) ID number
     *
     * This function checks given UCC-12 number used to identify
     * trade items, locations, and special applications (e.g., * coupons)
     * http://www.ean-ucc.org/
     * http://www.uc-council.org/checkdig.htm
     *
     * @param  string  $ucc number (only numeric chars will be considered)
     * @return bool    true if number is valid, otherwise false
     * @access public
     * @see Validate_ISPN::process()
     * @author Piotr Klaban <makler@man.torun.pl>
     */
    function ucc12($ucc)
    {
        static $weights_ucc12 = array(3,1,3,1,3,1,3,1,3,1,3);
        return Validate_ISPN::process($ucc, 12, $weights_ucc12, 10, 10);
    }

    /**
     * Validate a SSCC (Serial Shipping Container Code)
     *
     * This function checks given SSCC number
     * used to identify logistic units.
     * http://www.ean-ucc.org/
     * http://www.uc-council.org/checkdig.htm
     *
     * @param  string  $sscc number (only numeric chars will be considered)
     * @return bool    true if number is valid, otherwise false
     * @access public
     * @see Validate_ISPN::process()
     * @author Piotr Klaban <makler@man.torun.pl>
     */
    function sscc($sscc)
    {
        static $weights_sscc = array(3,1,3,1,3,1,3,1,3,1,3,1,3,1,3,1,3);
        return Validate_ISPN::process($sscc, 18, $weights_sscc, 10, 10);
    }

    /**
     * Does all the work for EAN8, EAN13, EAN14, UCC12 and SSCC
     * and can be used for as base for similar kind of calculations
     *
     * @param int $data number (only numeric chars will be considered)
     * @param int $lenght required length of number string
     * @param int $modulo (optionsl) number
     * @param int $subtract (optional) numbier
     * @param array $weights holds the weight that will be used in calculations for the validation
     * @return bool    true if number is valid, otherwise false
     * @access public
     * @see Validate::_checkControlNumber()
     */
    function process($data, $length, &$weights, $modulo = 10, $subtract = 0)
    {
        //$weights = array(3,1,3,1,3,1,3,1,3,1,3,1,3,1,3,1,3);
        //$weights = array_slice($weights, 0, $length);

        $data = str_replace(array('-', '/', ' ', "\t", "\n"), '', $data);

        // check if this is a digit number and is the right length
        if (!is_numeric($data) || strlen($data) != $length) {
            return false;
        }

        // Requires base class Validate
        require_once 'Validate.php';
        return Validate::_checkControlNumber($data, $weights, $modulo, $subtract);
    }
}
?>
