<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Service
 */

namespace ZendService\Amazon;

use DOMElement;
use DOMXPath;
use Zend\Uri;

/**
 * @category   Zend
 * @package    Zend_Service
 * @subpackage Amazon
 */
class Image
{
    /**
     * Image URL
     *
     * @var Uri\Uri
     */
    public $Url;

    /**
     * Image height in pixels
     *
     * @var int
     */
    public $Height;

    /**
     * Image width in pixels
     *
     * @var int
     */
    public $Width;

    /**
     * Assigns values to properties relevant to Image
     *
     * @param  DOMElement $dom
     */
    public function __construct(DOMElement $dom)
    {
        $xpath = new DOMXPath($dom->ownerDocument);
        $xpath->registerNamespace('az', 'http://webservices.amazon.com/AWSECommerceService/' . Amazon::getVersion());

        $this->Url    = Uri\UriFactory::factory($xpath->query('./az:URL/text()', $dom)->item(0)->data);
        $this->Height = (int) $xpath->query('./az:Height/text()', $dom)->item(0)->data;
        $this->Width  = (int) $xpath->query('./az:Width/text()', $dom)->item(0)->data;
    }
}
