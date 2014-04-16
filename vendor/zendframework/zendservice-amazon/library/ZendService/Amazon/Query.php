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

/**
 * @category   Zend
 * @package    Zend_Service
 * @subpackage Amazon
 */
class Query extends Amazon
{
    /**
     * Search parameters
     *
     * @var array
     */
    protected $_search = array();

    /**
     * Search index
     *
     * @var string
     */
    protected $_searchIndex = null;

    /**
     * Prepares query parameters
     *
     * @param  string $method
     * @param  array  $args
     * @throws Exception\RuntimeException
     * @return Query Provides a fluent interface
     */
    public function __call($method, $args)
    {
        if (strtolower($method) === 'asin') {
            $this->_searchIndex = 'asin';
            $this->_search['ItemId'] = $args[0];
            return $this;
        }

        if (strtolower($method) === 'category') {
            $this->_searchIndex = $args[0];
            $this->_search['SearchIndex'] = $args[0];
        } elseif (isset($this->_search['SearchIndex']) || $this->_searchIndex !== null || $this->_searchIndex === 'asin') {
            $this->_search[$method] = $args[0];
        } else {
            throw new Exception\RuntimeException('You must set a category before setting the search parameters');
        }

        return $this;
    }

    /**
     * Search using the prepared query
     *
     * @return Zend_Service_Amazon_Item|Zend_Service_Amazon_ResultSet
     */
    public function search()
    {
        if ($this->_searchIndex === 'asin') {
            return $this->itemLookup($this->_search['ItemId'], $this->_search);
        }
        return $this->itemSearch($this->_search);
    }
}
