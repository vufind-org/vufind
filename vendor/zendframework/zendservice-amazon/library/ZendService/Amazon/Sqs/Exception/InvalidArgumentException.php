<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Service
 */

namespace ZendService\Amazon\Sqs\Exception;

use ZendService\Amazon\Exception;

/**
 * @category   Zend
 * @package    Zend_Service_Amazon
 * @subpackage Sqs
 */
class InvalidArgumentException extends Exception\InvalidArgumentException implements ExceptionInterface
{
}
