<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Service
 */

namespace ZendService\Amazon\SimpleDb;

use DOMDocument;
use DOMXPath;
use Zend\Http;
use ZendXml\Security as XmlSecurity;

/**
 * @category   Zend
 * @package    Zend_Service_Amazon
 * @subpackage SimpleDb
 */
class Response
{
    /**
     * XML namespace used for SimpleDB responses.
     */
    protected $_xmlNamespace = 'http://sdb.amazonaws.com/doc/2009-04-15/';

    /**
     * The original HTTP response
     *
     * This contains the response body and headers.
     *
     * @var Http\Response
     */
    private $_httpResponse = null;

    /**
     * The response document object
     *
     * @var DOMDocument
     */
    private $_document = null;

    /**
     * The response XPath
     *
     * @var DOMXPath
     */
    private $_xpath = null;

    /**
     * Creates a new high-level SimpleDB response object
     *
     * @param Http\Response $httpResponse the HTTP response.
     */
    public function __construct(Http\Response $httpResponse)
    {
        $this->_httpResponse = $httpResponse;
    }

    /**
     * Gets the XPath object for this response
     *
     * @return DOMXPath the XPath object for response.
     */
    public function getXPath()
    {
        if ($this->_xpath === null) {
            $document = $this->getDocument();
            if ($document === false) {
                $this->_xpath = false;
            } else {
                $this->_xpath = new DOMXPath($document);
                $this->_xpath->registerNamespace('sdb',
                    $this->getNamespace());
            }
        }

        return $this->_xpath;
    }

    /**
     * Gets the SimpleXML document object for this response
     *
     * @return \SimpleXMLElement
     */
    public function getSimpleXMLDocument()
    {
        try {
            $body = $this->_httpResponse->getBody();
        } catch (Http\Exception\ExceptionInterface $e) {
            $body = false;
        }

        return XmlSecurity::scan($body);
    }

    /**
     * Get HTTP response object
     *
     * @return Http\Response
     */
    public function getHttpResponse()
    {
        return $this->_httpResponse;
    }

    /**
     * Gets the document object for this response
     *
     * @return DOMDocument the DOM Document for this response.
     */
    public function getDocument()
    {
        try {
            $body = $this->_httpResponse->getBody();
        } catch (Http\Exception\ExceptionInterface $e) {
            $body = false;
        }

        if ($this->_document === null) {
            if ($body !== false) {
                // turn off libxml error handling
                $errors = libxml_use_internal_errors();

                $this->_document = new DOMDocument();
                if (!$this->_document->loadXML($body)) {
                    $this->_document = false;
                }

                // reset libxml error handling
                libxml_clear_errors();
                libxml_use_internal_errors($errors);
            } else {
                $this->_document = false;
            }
        }

        return $this->_document;
    }

    /**
     * Return the current set XML Namespace.
     *
     * @return string
     */
    public function getNamespace()
    {
        return $this->_xmlNamespace;
    }

    /**
     * Set a new XML Namespace
     *
     * @param string $namespace
     */
    public function setNamespace($namespace)
    {
        $this->_xmlNamespace = $namespace;
    }
}
