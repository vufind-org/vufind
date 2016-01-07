<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Service
 */

namespace ZendServiceTest\Amazon\Authentication;

use ZendService\Amazon\Authentication;

/**
 * S3 authentication test case
 *
 * @category   Zend
 * @package    Zend_Service_Amazon_Authentication
 * @subpackage UnitTests
 */
class S3Test extends \PHPUnit_Framework_TestCase
{

    /**
     * @var ZendService\Amazon\Authentication\S3
     */
    private $_amazon;

    /**
     * Prepares the environment before running a test.
     */
    protected function setUp()
    {
        $this->_amazon = new Authentication\S3('0PN5J17HBGZHT7JJ3X82', 'uV3F3YluFJax1cknvbcGwgjvx4QpvB+leU8dUj2o', '2006-03-01');
    }

    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown()
    {
        $this->_amazon = null;
    }


    public function testGetGeneratesCorrectSignature()
    {
        $headers = array();
        $headers['Date'] = "Tue, 27 Mar 2007 19:36:42 +0000";

        $ret = $this->_amazon->generateSignature('GET', 'http://s3.amazonaws.com/johnsmith/photos/puppy.jpg', $headers);

        $this->assertEquals('AWS 0PN5J17HBGZHT7JJ3X82:soqB4L9flQ6AHG4d5FVnKj26D2s=', $headers['Authorization']);
        $rawHttpResponse = "GET\n\n\n"
                    . "Tue, 27 Mar 2007 19:36:42 +0000\n"
                    . "//johnsmith/photos/puppy.jpg";
        $this->assertEquals($ret, $rawHttpResponse);
    }

    public function testPutGeneratesCorrectSignature()
    {
        $headers = array();
        $headers['Date'] = "Tue, 27 Mar 2007 21:15:45 +0000";
        $headers['Content-Type'] = "image/jpeg";
        $headers['Content-Length'] = 94328;

        $ret = $this->_amazon->generateSignature('PUT', 'http://s3.amazonaws.com/johnsmith/photos/puppy.jpg', $headers);

        $this->assertEquals('AWS 0PN5J17HBGZHT7JJ3X82:88cf7BdpjrBlCsIiWWLn8wLpWzI=', $headers['Authorization']);
        $rawHttpResponse = "PUT\n\n"
                    . "image/jpeg\n"
                    . "Tue, 27 Mar 2007 21:15:45 +0000\n"
                    . "//johnsmith/photos/puppy.jpg";
        $this->assertEquals($ret, $rawHttpResponse);
    }

    public function testListGeneratesCorrectSignature()
    {
        $headers = array();
        $headers['Date'] = "Tue, 27 Mar 2007 19:42:41 +0000";

        $ret = $this->_amazon->generateSignature('GET', 'http://s3.amazonaws.com/johnsmith/?prefix=photos&max-keys=50&marker=puppy', $headers);

        $this->assertEquals('AWS 0PN5J17HBGZHT7JJ3X82:pm3Adv2BIFCCJiUSikcLcGYFtiA=', $headers['Authorization']);
        $rawHttpResponse = "GET\n\n\n"
                    . "Tue, 27 Mar 2007 19:42:41 +0000\n"
                    . "//johnsmith/";
        $this->assertEquals($ret, $rawHttpResponse);
    }

    public function testFetchGeneratesCorrectSignature()
    {
        $headers = array();
        $headers['Date'] = "Tue, 27 Mar 2007 19:44:46 +0000";

        $ret = $this->_amazon->generateSignature('GET', 'http://s3.amazonaws.com/johnsmith/?acl', $headers);

        $this->assertEquals('AWS 0PN5J17HBGZHT7JJ3X82:TCNlZPuxY41veihZbxjnjw8P93w=', $headers['Authorization']);
        $rawHttpResponse = "GET\n\n\n"
                    . "Tue, 27 Mar 2007 19:44:46 +0000\n"
                    . "//johnsmith/?acl";
        $this->assertEquals($ret, $rawHttpResponse);
    }

    public function testDeleteGeneratesCorrectSignature()
    {
        $headers = array();
        $headers['x-amz-date'] = "Tue, 27 Mar 2007 21:20:26 +0000";

        $ret = $this->_amazon->generateSignature('DELETE', 'http://s3.amazonaws.com/johnsmith/photos/puppy.jpg', $headers);

        $this->assertEquals('AWS 0PN5J17HBGZHT7JJ3X82:O9AsSXUIowhjTiJC5escAqjsAyk=', $headers['Authorization']);
        $rawHttpResponse = "DELETE\n\n\n\n"
                    . "x-amz-date:Tue, 27 Mar 2007 21:20:26 +0000\n"
                    . "//johnsmith/photos/puppy.jpg";
        $this->assertEquals($ret, $rawHttpResponse);
    }

    public function testUploadGeneratesCorrectSignature()
    {
        $headers = array();
        $headers['Date'] = "Tue, 27 Mar 2007 21:06:08 +0000";
        $headers['x-amz-acl'] = "public-read";
        $headers['content-type'] = "application/x-download";
        $headers['Content-MD5'] = "4gJE4saaMU4BqNR0kLY+lw==";
        $headers['X-Amz-Meta-ReviewedBy'][] = "joe@johnsmith.net";
        $headers['X-Amz-Meta-ReviewedBy'][] = "jane@johnsmith.net";
        $headers['X-Amz-Meta-FileChecksum'] = "0x02661779";
        $headers['X-Amz-Meta-ChecksumAlgorithm'] = "crc32";
        $headers['Content-Disposition'] = "attachment; filename=database.dat";
        $headers['Content-Encoding'] = "gzip";
        $headers['Content-Length'] = "5913339";


        $ret = $this->_amazon->generateSignature('PUT', 'http://s3.amazonaws.com/static.johnsmith.net/db-backup.dat.gz', $headers);

        $this->assertEquals('AWS 0PN5J17HBGZHT7JJ3X82:IQh2zwCpX2xqRgP2rbIkXL/GVbA=', $headers['Authorization']);
        $rawHttpResponse = "PUT\n"
                    . "4gJE4saaMU4BqNR0kLY+lw==\n"
                    . "application/x-download\n"
                    . "Tue, 27 Mar 2007 21:06:08 +0000\n"
                    . "x-amz-acl:public-read\n"
                    . "x-amz-meta-checksumalgorithm:crc32\n"
                    . "x-amz-meta-filechecksum:0x02661779\n"
                    . "x-amz-meta-reviewedby:joe@johnsmith.net,jane@johnsmith.net\n"
                    . "//static.johnsmith.net/db-backup.dat.gz";
        $this->assertEquals($ret, $rawHttpResponse);
    }
}
