<?php
/**
 * <tasks:unixeol> - read/write version
 *
 * PHP versions 4 and 5
 *
 * @category   pear
 * @package    PEAR
 * @author     Greg Beaver <cellog@php.net>
 * @copyright  1997-2009 The Authors
 * @license    http://opensource.org/licenses/bsd-license.php New BSD License
 * @version    CVS: $Id$
 * @link       http://pear.php.net/package/PEAR
 * @since      File available since Release 1.4.0a10
 */
/**
 * Base class
 */
require_once 'PEAR/Task/Unixeol.php';
/**
 * Abstracts the unixeol task xml.
 * @category   pear
 * @package    PEAR
 * @author     Greg Beaver <cellog@php.net>
 * @copyright  1997-2009 The Authors
 * @license    http://opensource.org/licenses/bsd-license.php New BSD License
 * @version    Release: 1.9.5
 * @link       http://pear.php.net/package/PEAR
 * @since      Class available since Release 1.4.0a10
 */
class PEAR_Task_Unixeol_rw extends PEAR_Task_Unixeol
{
    function PEAR_Task_Unixeol_rw(&$pkg, &$config, &$logger, $fileXml)
    {
        parent::PEAR_Task_Common($config, $logger, PEAR_TASK_PACKAGE);
        $this->_contents = $fileXml;
        $this->_pkg = &$pkg;
        $this->_params = array();
    }

    function validate()
    {
        return true;
    }

    function getName()
    {
        return 'unixeol';
    }

    function getXml()
    {
        return '';
    }
}
?>