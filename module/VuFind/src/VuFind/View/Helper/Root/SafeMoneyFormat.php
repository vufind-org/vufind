<?php
/**
 * Safe money format view helper
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\View\Helper\Root;
use Zend\View\Helper\AbstractHelper;

/**
 * Safe money format view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class SafeMoneyFormat extends AbstractHelper
{
    /**
     * Currency-rendering logic.
     *
     * @param string $number The number to format
     *
     * @return string
     */
    public function __invoke($number)
    {
        // money_format() does not exist on windows
        if (function_exists('money_format')) {
            return $this->view->escapeHtml(money_format('%.2n', $number));
        } else {
            return self::windowsSafeMoneyFormat($number);
        }
    }

    /**
     * Windows-compatible equivalent to built-in money_format function.
     *
     * @param string $number Number to format.
     *
     * @return string
     */
    public static function windowsSafeMoneyFormat($number)
    {
        // '' or NULL gets the locale values from environment variables
        setlocale(LC_ALL, '');
        $locale = localeconv();
        extract($locale);

        // Windows doesn't support UTF-8 encoding in setlocale, so we'll have to
        // convert the currency symbol manually:
        $currency_symbol = self::safeMoneyFormatMakeUTF8($currency_symbol);

        // How is the amount signed?
        // Positive
        if ($number > 0) {
            $sign         = $positive_sign;
            $sign_posn    = $p_sign_posn;
            $sep_by_space = $p_sep_by_space;
            $cs_precedes  = $p_cs_precedes;
        } else {
            // Negative
            $sign         = $negative_sign;
            $sign_posn    = $n_sign_posn;
            $sep_by_space = $n_sep_by_space;
            $cs_precedes  = $n_cs_precedes;
        }

        // Format the absolute value of the number
        $m = number_format(
            abs($number), $frac_digits, $mon_decimal_point, $mon_thousands_sep
        );

        // Spaces between the number and symbol?
        if ($sep_by_space) {
            $space = ' ';
        } else {
            $space = '';
        }
        if ($cs_precedes) {
            $m = $currency_symbol.$space.$m;
        } else {
            $m = $m.$space.$currency_symbol;
        }
        // HTML spaces
        $m = str_replace(' ', '&nbsp;', $m);

        // Add symbol
        switch ($sign_posn) {
        case 0:
            $m = "($m)";
            break;
        case 1:
            $m = $sign.$m;
            break;
        case 2:
            $m = $m.$sign;
            break;
        case 3:
            $m = $sign.$m;
            break;
        case 4:
            $m = $m.$sign;
            break;
        default:
            $m = "$m [error sign_posn = $sign_posn&nbsp;!]";
        }
        return $m;
    }

    /**
     * Adapted from code at http://us.php.net/manual/en/function.utf8-encode.php
     * This is needed for Windows only as a support function for
     * windowsSafeMoneyFormat; utf8_encode by itself doesn't do the job, but this
     * is capable of properly turning currency symbols into valid UTF-8.
     *
     * @param string $instr String to convert to UTF-8
     *
     * @return string
     */
    public static function safeMoneyFormatMakeUTF8($instr)
    {
        static $nibble_good_chars = false;
        static $byte_map = array();

        if (empty($byte_map)) {
            for ($x=128;$x<256;++$x) {
                $byte_map[chr($x)]=utf8_encode(chr($x));
            }
            $cp1252_map=array(
                "\x80"=>"\xE2\x82\xAC",    // EURO SIGN
                "\x82" => "\xE2\x80\x9A",  // SINGLE LOW-9 QUOTATION MARK
                "\x83" => "\xC6\x92",      // LATIN SMALL LETTER F WITH HOOK
                "\x84" => "\xE2\x80\x9E",  // DOUBLE LOW-9 QUOTATION MARK
                "\x85" => "\xE2\x80\xA6",  // HORIZONTAL ELLIPSIS
                "\x86" => "\xE2\x80\xA0",  // DAGGER
                "\x87" => "\xE2\x80\xA1",  // DOUBLE DAGGER
                "\x88" => "\xCB\x86",      // MODIFIER LETTER CIRCUMFLEX ACCENT
                "\x89" => "\xE2\x80\xB0",  // PER MILLE SIGN
                "\x8A" => "\xC5\xA0",      // LATIN CAPITAL LETTER S WITH CARON
                "\x8B" => "\xE2\x80\xB9",  // SINGLE LEFT-POINTING ANGLE QUOTE
                "\x8C" => "\xC5\x92",      // LATIN CAPITAL LIGATURE OE
                "\x8E" => "\xC5\xBD",      // LATIN CAPITAL LETTER Z WITH CARON
                "\x91" => "\xE2\x80\x98",  // LEFT SINGLE QUOTATION MARK
                "\x92" => "\xE2\x80\x99",  // RIGHT SINGLE QUOTATION MARK
                "\x93" => "\xE2\x80\x9C",  // LEFT DOUBLE QUOTATION MARK
                "\x94" => "\xE2\x80\x9D",  // RIGHT DOUBLE QUOTATION MARK
                "\x95" => "\xE2\x80\xA2",  // BULLET
                "\x96" => "\xE2\x80\x93",  // EN DASH
                "\x97" => "\xE2\x80\x94",  // EM DASH
                "\x98" => "\xCB\x9C",      // SMALL TILDE
                "\x99" => "\xE2\x84\xA2",  // TRADE MARK SIGN
                "\x9A" => "\xC5\xA1",      // LATIN SMALL LETTER S WITH CARON
                "\x9B" => "\xE2\x80\xBA",  // SINGLE RIGHT-POINTING ANGLE QUOTE
                "\x9C" => "\xC5\x93",      // LATIN SMALL LIGATURE OE
                "\x9E" => "\xC5\xBE",      // LATIN SMALL LETTER Z WITH CARON
                "\x9F" => "\xC5\xB8"       // LATIN CAPITAL LETTER Y WITH DIAERESIS
            );
            foreach ($cp1252_map as $k=>$v) {
                $byte_map[$k]=$v;
            }
        }
        if (!$nibble_good_chars) {
            $ascii_char='[\x00-\x7F]';
            $cont_byte='[\x80-\xBF]';
            $utf8_2='[\xC0-\xDF]'.$cont_byte;
            $utf8_3='[\xE0-\xEF]'.$cont_byte.'{2}';
            $utf8_4='[\xF0-\xF7]'.$cont_byte.'{3}';
            $utf8_5='[\xF8-\xFB]'.$cont_byte.'{4}';
            $nibble_good_chars
                = "@^($ascii_char+|$utf8_2|$utf8_3|$utf8_4|$utf8_5)(.*)$@s";
        }

        $outstr='';
        $char='';
        $rest='';
        while ((strlen($instr))>0) {
            if (1==preg_match($nibble_good_chars, $instr, $match)) {
                $char=$match[1];
                $rest=$match[2];
                $outstr.=$char;
            } elseif (1==preg_match('@^(.)(.*)$@s', $instr, $match)) {
                $char=$match[1];
                $rest=$match[2];
                $outstr.=$byte_map[$char];
            }
            $instr=$rest;
        }
        return $outstr;
    }
}
