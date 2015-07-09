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

/**
 * @category   Zend
 * @package    Zend_Service
 * @subpackage Amazon
 */
class Item
{
    /**
     * @var string
     */
    public $ASIN;

    /**
     * @var string
     */
    public $DetailPageURL;

    /**
     * @var int
     */
    public $SalesRank;

    /**
     * @var int
     */
    public $TotalReviews;

    /**
     * @var int
     */
    public $AverageRating;

    /**
     * @var string
     */
    public $SmallImage;

    /**
     * @var string
     */
    public $MediumImage;

    /**
     * @var string
     */
    public $LargeImage;

    /**
     * @var string
     */
    public $Subjects;

    /**
     * @var OfferSet
     */
    public $Offers;

    /**
     * @var array CustomerReview
     */
    public $CustomerReviews = array();

    /**
     * @var array Of SimilarProduct
     */
    public $SimilarProducts = array();

    /**
     * @var array Of Accessories
     */
    public $Accessories = array();

    /**
     * @var array
     */
    public $Tracks = array();

    /**
     * @var array Of ListmaniaList
     */
    public $ListmaniaLists = array();

    protected $_dom;


    /**
     * Parse the given <Item> element
     *
     * @param DOMElement $dom
     *
     * @group ZF-9547
     */
    public function __construct(DOMElement $dom)
    {
        $xpath = new DOMXPath($dom->ownerDocument);
        $xpath->registerNamespace('az', 'http://webservices.amazon.com/AWSECommerceService/' . Amazon::getVersion());
        $this->ASIN = $xpath->query('./az:ASIN/text()', $dom)->item(0)->data;

        $result = $xpath->query('./az:DetailPageURL/text()', $dom);
        if ($result->length == 1) {
            $this->DetailPageURL = $result->item(0)->data;
        }

        if ($xpath->query('./az:ItemAttributes/az:ListPrice', $dom)->length >= 1) {
            $this->CurrencyCode = (string) $xpath->query('./az:ItemAttributes/az:ListPrice/az:CurrencyCode/text()', $dom)->item(0)->data;
            $this->Amount = (int) $xpath->query('./az:ItemAttributes/az:ListPrice/az:Amount/text()', $dom)->item(0)->data;
            $this->FormattedPrice = (string) $xpath->query('./az:ItemAttributes/az:ListPrice/az:FormattedPrice/text()', $dom)->item(0)->data;
        }

        $result = $xpath->query('./az:ItemAttributes/az:*/text()', $dom);
        if ($result->length >= 1) {
            foreach ($result as $v) {
                if (isset($this->{$v->parentNode->tagName})) {
                    if (is_array($this->{$v->parentNode->tagName})) {
                        array_push($this->{$v->parentNode->tagName}, (string) $v->data);
                    } else {
                        $this->{$v->parentNode->tagName} = array($this->{$v->parentNode->tagName}, (string) $v->data);
                    }
                } else {
                    $this->{$v->parentNode->tagName} = (string) $v->data;
                }
            }
        }

        foreach (array('SmallImage', 'MediumImage', 'LargeImage') as $im) {
            $result = $xpath->query("./az:ImageSets/az:ImageSet[@Category='primary']/az:$im", $dom);
            if ($result->length == 1) {
                $this->$im = new Image($result->item(0));
            }
        }

        $result = $xpath->query('./az:SalesRank/text()', $dom);
        if ($result->length == 1) {
            $this->SalesRank = (int) $result->item(0)->data;
        }

        $result = $xpath->query('./az:CustomerReviews/az:Review', $dom);
        if ($result->length >= 1) {
            foreach ($result as $review) {
                $this->CustomerReviews[] = new CustomerReview($review);
            }
            $this->AverageRating = (float) $xpath->query('./az:CustomerReviews/az:AverageRating/text()', $dom)->item(0)->data;
            $this->TotalReviews = (int) $xpath->query('./az:CustomerReviews/az:TotalReviews/text()', $dom)->item(0)->data;
        }

        $result = $xpath->query('./az:EditorialReviews/az:*', $dom);
        if ($result->length >= 1) {
            foreach ($result as $r) {
                $this->EditorialReviews[] = new EditorialReview($r);
            }
        }

        $result = $xpath->query('./az:SimilarProducts/az:*', $dom);
        if ($result->length >= 1) {
            foreach ($result as $r) {
                $this->SimilarProducts[] = new SimilarProduct($r);
            }
        }

        $result = $xpath->query('./az:ListmaniaLists/*', $dom);
        if ($result->length >= 1) {
            foreach ($result as $r) {
                $this->ListmaniaLists[] = new ListmaniaList($r);
            }
        }

        $result = $xpath->query('./az:Tracks/az:Disc', $dom);
        if ($result->length > 1) {
            foreach ($result as $disk) {
                foreach ($xpath->query('./*/text()', $disk) as $t) {
                    // TODO: For consistency in a bugfix all tracks are appended to one single array
                    // Erroreous line: $this->Tracks[$disk->getAttribute('number')] = (string) $t->data;
                    $this->Tracks[] = (string) $t->data;
                }
            }
        } elseif ($result->length == 1) {
            foreach ($xpath->query('./*/text()', $result->item(0)) as $t) {
                $this->Tracks[] = (string) $t->data;
            }
        }

        $result = $xpath->query('./az:Offers', $dom);
        $resultSummary = $xpath->query('./az:OfferSummary', $dom);
        if ($result->length > 1 || $resultSummary->length == 1) {
            $this->Offers = new OfferSet($dom);
        }

        $result = $xpath->query('./az:Accessories/*', $dom);
        if ($result->length > 1) {
            foreach ($result as $r) {
                $this->Accessories[] = new Accessories($r);
            }
        }

        $this->_dom = $dom;
    }


    /**
     * Returns the item's original XML
     *
     * @return string
     */
    public function asXml()
    {
        return $this->_dom->ownerDocument->saveXML($this->_dom);
    }
}
