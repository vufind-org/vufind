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
use DOMText;
use DOMXPath;

/**
 * @category   Zend
 * @package    Zend_Service
 * @subpackage Amazon
 */
class Offer
{
    /**
     * @var string
     */
    public $MerchantId;

    /**
     * @var string
     */
    public $MerchantName;

    /**
     * @var string
     */
    public $GlancePage;

    /**
     * @var string
     */
    public $Condition;

    /**
     * @var string
     */
    public $OfferListingId;

    /**
     * @var string
     */
    public $Price;

    /**
     * @var string
     */
    public $CurrencyCode;

    /**
     * @var string
     */
    public $Availability;

    /**
     * @var boolean
     */
    public $IsEligibleForSuperSaverShipping = false;

    /**
     * Parse the given Offer element
     *
     * @param DOMElement $dom
     */
    public function __construct(DOMElement $dom)
    {
        $xpath = new DOMXPath($dom->ownerDocument);
        $xpath->registerNamespace('az', 'http://webservices.amazon.com/AWSECommerceService/' . Amazon::getVersion());

        $map = array(
            'MerchantId'     => './az:Merchant/az:MerchantId/text()',
            'MerchantName'   => './az:Merchant/az:Name/text()',
            'GlancePage'     => './az:Merchant/az:GlancePage/text()',
            'Condition'      => './az:OfferAttributes/az:Condition/text()',
            'OfferListingId' => './az:OfferListing/az:OfferListingId/text()',
            'Price'          => './az:OfferListing/az:Price/az:Amount/text()',
            'CurrencyCode'   => './az:OfferListing/az:Price/az:CurrencyCode/text()',
            'Availability'   => './az:OfferListing/az:Availability/text()',
            'IsEligibleForSuperSaverShipping' => './az:OfferListing/az:IsEligibleForSuperSaverShipping/text()',
        );

        foreach ($map as $param_name => $xquery) {
            $query_result = $xpath->query($xquery, $dom);
            if ($query_result->length <= 0) {
                continue;
            }
            $text = $query_result->item(0);
            if (!$text instanceof DOMText) {
                continue;
            }
            $this->$param_name = (string) $text->data;
        }

        if (isset($this->IsEligibleForSuperSaverShipping)) {
            $this->IsEligibleForSuperSaverShipping = (bool) $this->IsEligibleForSuperSaverShipping;
        }
    }
}
