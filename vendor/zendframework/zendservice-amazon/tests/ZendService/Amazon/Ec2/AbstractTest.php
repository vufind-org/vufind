<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Service
 */

namespace ZendServiceTest\Amazon\Ec2;

use ZendService\Amazon;

/**
 * @category   Zend
 * @package    Zend_Service_Amazon
 * @subpackage UnitTests
 * @group      Zend_Service
 * @group      Zend_Service_Amazon
 * @group      Zend_Service_Amazon_Ec2
 */
class AbstractTest extends \PHPUnit_Framework_TestCase
{
    public function testSetRegion()
    {
        $ec2 = new TestAmazonAbstract('TestAccessKey', 'TestSecretKey');
        $ec2->setRegion('eu-west-1');
        $this->assertEquals('eu-west-1', $ec2->returnRegion());
    }

    public function testSetInvalidRegionThrowsException()
    {
        $ec2 = new TestAmazonAbstract('TestAccessKey', 'TestSecretKey');
        $this->setExpectedException(
            'ZendService\Amazon\Ec2\Exception\InvalidArgumentException',
            'Invalid Amazon Ec2 Region');
        $ec2->setRegion('eu-west-1a');
    }

    public function testSignParamsWithSpaceEncodesWithPercentInsteadOfPlus()
    {
        $class = new TestAmazonAbstract('TestAccessKey', 'TestSecretKey');
        $ret = $class->testSign(array('Action' => 'Space Test'));

        // this is the encode signuature with urlencode - It's Invalid!
        $invalidSignature = 'EeHAfo7cMcLyvH4SW4fEpjo51xJJ4ES1gdjRPxZTlto=';

        $this->assertNotEquals($ret, $invalidSignature);
    }
}

class TestAmazonAbstract extends \ZendService\Amazon\Ec2\AbstractEc2
{

    public function returnRegion()
    {
        return $this->_region;
    }

    public function testSign($params)
    {
        return $this->signParameters($params);
    }
}
