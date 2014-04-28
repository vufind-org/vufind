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

use ZendService\Amazon\Ec2;
use Zend\Http\Client as HttpClient;
use Zend\Http\Client\Adapter\Test as HttpClientTestAdapter;


/**
 * ZendService\Amazon\Ec2\Securitygroups test case.
 *
 * @category   Zend
 * @package    Zend_Service_Amazon
 * @subpackage UnitTests
 * @group      Zend_Service
 * @group      Zend_Service_Amazon
 * @group      Zend_Service_Amazon_Ec2
 */
class SecurityGroupsTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var ZendService\Amazon\Ec2\Securitygroups
     */
    private $securitygroupsInstance;

    /**
     * @var HttpClient
     */
    protected $httpClient = null;

    /**
     * @var HttpClientTestAdapter
     */
    protected $httpClientTestAdapter = null;

    /**
     * Prepares the environment before running a test.
     */
    protected function setUp()
    {
        $this->httpClientTestAdapter = new HttpClientTestAdapter;
        $this->httpClient = new HttpClient(null, array('adapter' => $this->httpClientTestAdapter));
        $this->securitygroupsInstance = new Ec2\SecurityGroups('access_key', 'secret_access_key', null, $this->httpClient);
    }

    /**
     * Tests ZendService\Amazon\Ec2\Securitygroups->authorize()
     */
    public function testAuthorizeSinglePort()
    {
        $rawHttpResponse = "HTTP/1.1 200 OK\r\n"
                    . "Date: Fri, 24 Oct 2008 17:24:52 GMT\r\n"
                    . "Server: hi\r\n"
                    . "Last-modified: Fri, 24 Oct 2008 17:24:52 GMT\r\n"
                    . "Status: 200 OK\r\n"
                    . "Content-type: application/xml; charset=utf-8\r\n"
                    . "Expires: Tue, 31 Mar 1981 05:00:00 GMT\r\n"
                    . "Connection: close\r\n"
                    . "\r\n"
                    . "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n"
                    . "<AuthorizeSecurityGroupIngressResponse xmlns=\"http://ec2.amazonaws.com/doc/2009-04-04/\">\r\n"
                    . "  <return>true</return>\r\n"
                    . "</AuthorizeSecurityGroupIngressResponse>\r\n";
        $this->httpClientTestAdapter->setResponse($rawHttpResponse);

        $return = $this->securitygroupsInstance->authorizeIp('MyGroup', 'tcp', '80', '80', '0.0.0.0/0');
        $this->assertTrue($return);

    }

    public function testAuthorizeRangeOfPorts()
    {
        $rawHttpResponse = "HTTP/1.1 200 OK\r\n"
                    . "Date: Fri, 24 Oct 2008 17:24:52 GMT\r\n"
                    . "Server: hi\r\n"
                    . "Last-modified: Fri, 24 Oct 2008 17:24:52 GMT\r\n"
                    . "Status: 200 OK\r\n"
                    . "Content-type: application/xml; charset=utf-8\r\n"
                    . "Expires: Tue, 31 Mar 1981 05:00:00 GMT\r\n"
                    . "Connection: close\r\n"
                    . "\r\n"
                    . "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n"
                    . "<AuthorizeSecurityGroupIngressResponse xmlns=\"http://ec2.amazonaws.com/doc/2009-04-04/\">\r\n"
                    . "  <return>true</return>\r\n"
                    . "</AuthorizeSecurityGroupIngressResponse>\r\n";
        $this->httpClientTestAdapter->setResponse($rawHttpResponse);

        $return = $this->securitygroupsInstance->authorizeIp('MyGroup', 'tcp', '6000', '7000', '0.0.0.0/0');
        $this->assertTrue($return);

    }

    public function testAuthorizeSecurityGroupName()
    {
        $rawHttpResponse = "HTTP/1.1 200 OK\r\n"
                    . "Date: Fri, 24 Oct 2008 17:24:52 GMT\r\n"
                    . "Server: hi\r\n"
                    . "Last-modified: Fri, 24 Oct 2008 17:24:52 GMT\r\n"
                    . "Status: 200 OK\r\n"
                    . "Content-type: application/xml; charset=utf-8\r\n"
                    . "Expires: Tue, 31 Mar 1981 05:00:00 GMT\r\n"
                    . "Connection: close\r\n"
                    . "\r\n"
                    . "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n"
                    . "<AuthorizeSecurityGroupIngressResponse xmlns=\"http://ec2.amazonaws.com/doc/2009-04-04/\">\r\n"
                    . "  <return>true</return>\r\n"
                    . "</AuthorizeSecurityGroupIngressResponse>\r\n";
        $this->httpClientTestAdapter->setResponse($rawHttpResponse);

        $return = $this->securitygroupsInstance->authorizeGroup('MyGroup', 'groupname', '15333848');
        $this->assertTrue($return);

    }

    /**
     * Tests ZendService\Amazon\Ec2\Securitygroups->create()
     */
    public function testCreate()
    {

        $rawHttpResponse = "HTTP/1.1 200 OK\r\n"
                    . "Date: Fri, 24 Oct 2008 17:24:52 GMT\r\n"
                    . "Server: hi\r\n"
                    . "Last-modified: Fri, 24 Oct 2008 17:24:52 GMT\r\n"
                    . "Status: 200 OK\r\n"
                    . "Content-type: application/xml; charset=utf-8\r\n"
                    . "Expires: Tue, 31 Mar 1981 05:00:00 GMT\r\n"
                    . "Connection: close\r\n"
                    . "\r\n"
                    . "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n"
                    . "<CreateSecurityGroupResponse xmlns=\"http://ec2.amazonaws.com/doc/2009-04-04/\">\r\n"
                    . "  <return>true</return>\r\n"
                    . "</CreateSecurityGroupResponse>\r\n";
        $this->httpClientTestAdapter->setResponse($rawHttpResponse);

        $return = $this->securitygroupsInstance->create('MyGroup', 'My Security Grup');

        $this->assertTrue($return);

    }

    /**
     * Tests ZendService\Amazon\Ec2\Securitygroups->delete()
     */
    public function testDelete()
    {
        $rawHttpResponse = "HTTP/1.1 200 OK\r\n"
                    . "Date: Fri, 24 Oct 2008 17:24:52 GMT\r\n"
                    . "Server: hi\r\n"
                    . "Last-modified: Fri, 24 Oct 2008 17:24:52 GMT\r\n"
                    . "Status: 200 OK\r\n"
                    . "Content-type: application/xml; charset=utf-8\r\n"
                    . "Expires: Tue, 31 Mar 1981 05:00:00 GMT\r\n"
                    . "Connection: close\r\n"
                    . "\r\n"
                    . "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n"
                    . "<DeleteSecurityGroupResponse xmlns=\"http://ec2.amazonaws.com/doc/2009-04-04/\">\r\n"
                    . "  <return>true</return>\r\n"
                    . "</DeleteSecurityGroupResponse>\r\n";
        $this->httpClientTestAdapter->setResponse($rawHttpResponse);

        $return = $this->securitygroupsInstance->delete('MyGroup');

        $this->assertTrue($return);

    }

    /**
     * Tests ZendService\Amazon\Ec2\Securitygroups->describe()
     */
    public function testDescribeMultipleSecruityGroups()
    {
        $rawHttpResponse = "HTTP/1.1 200 OK\r\n"
                    . "Date: Fri, 24 Oct 2008 17:24:52 GMT\r\n"
                    . "Server: hi\r\n"
                    . "Last-modified: Fri, 24 Oct 2008 17:24:52 GMT\r\n"
                    . "Status: 200 OK\r\n"
                    . "Content-type: application/xml; charset=utf-8\r\n"
                    . "Expires: Tue, 31 Mar 1981 05:00:00 GMT\r\n"
                    . "Connection: close\r\n"
                    . "\r\n"
                    . "<DescribeSecurityGroupsResponse xmlns=\"http://ec2.amazonaws.com/doc/2009-04-04/\">\r\n"
                    . "  <securityGroupInfo>\r\n"
                    . "    <item>\r\n"
                    . "      <ownerId>UYY3TLBUXIEON5NQVUUX6OMPWBZIQNFM</ownerId>\r\n"
                    . "      <groupName>WebServers</groupName>\r\n"
                    . "      <groupDescription>Web</groupDescription>\r\n"
                    . "      <ipPermissions>\r\n"
                    . "        <item>\r\n"
                    . "       <ipProtocol>tcp</ipProtocol>\r\n"
                    . "   <fromPort>80</fromPort>\r\n"
                    . "   <toPort>80</toPort>\r\n"
                    . "   <groups/>\r\n"
                    . "   <ipRanges>\r\n"
                    . "     <item>\r\n"
                    . "       <cidrIp>0.0.0.0/0</cidrIp>\r\n"
                    . "     </item>\r\n"
                    . "   </ipRanges>\r\n"
                    . "         </item>\r\n"
                    . "      </ipPermissions>\r\n"
                    . "    </item>\r\n"
                    . "    <item>\r\n"
                    . "      <ownerId>UYY3TLBUXIEON5NQVUUX6OMPWBZIQNFM</ownerId>\r\n"
                    . "      <groupName>RangedPortsBySource</groupName>\r\n"
                    . "      <groupDescription>A</groupDescription>\r\n"
                    . "      <ipPermissions>\r\n"
                    . "     <item>\r\n"
                    . "   <ipProtocol>tcp</ipProtocol>\r\n"
                    . "   <fromPort>6000</fromPort>\r\n"
                    . "   <toPort>7000</toPort>\r\n"
                    . "   <groups/>\r\n"
                    . "   <ipRanges>\r\n"
                    . "     <item>\r\n"
                    . "       <cidrIp>0.0.0.0/0</cidrIp>\r\n"
                    . "     </item>\r\n"
                    . "   </ipRanges>\r\n"
                    . " </item>\r\n"
                    . "      </ipPermissions>\r\n"
                    . "    </item>\r\n"
                    . "  </securityGroupInfo>\r\n"
                    . "</DescribeSecurityGroupsResponse>\r\n";
        $this->httpClientTestAdapter->setResponse($rawHttpResponse);

        $return = $this->securitygroupsInstance->describe(array('WebServers','RangedPortsBySource'));

        $this->assertEquals(2, count($return));

        $arrGroups = array(
                array(
                    'ownerId'   => 'UYY3TLBUXIEON5NQVUUX6OMPWBZIQNFM',
                    'groupName' => 'WebServers',
                    'groupDescription' => 'Web',
                    'ipPermissions' => array(0 => array(
                        'ipProtocol' => 'tcp',
                        'fromPort'  => '80',
                        'toPort'    => '80',
                        'ipRanges'  => '0.0.0.0/0'
                    ))
                ),
                array(
                    'ownerId'   => 'UYY3TLBUXIEON5NQVUUX6OMPWBZIQNFM',
                    'groupName' => 'RangedPortsBySource',
                    'groupDescription' => 'A',
                    'ipPermissions' => array(0 => array(
                        'ipProtocol' => 'tcp',
                        'fromPort'  => '6000',
                        'toPort'    => '7000',
                        'ipRanges'  => '0.0.0.0/0'
                    ))
                )
            );
        foreach($return as $k => $r) {
            $this->assertSame($arrGroups[$k], $r);
        }
    }

    public function testDescribeSingleSecruityGroup()
    {
        $rawHttpResponse = "HTTP/1.1 200 OK\r\n"
                    . "Date: Fri, 24 Oct 2008 17:24:52 GMT\r\n"
                    . "Server: hi\r\n"
                    . "Last-modified: Fri, 24 Oct 2008 17:24:52 GMT\r\n"
                    . "Status: 200 OK\r\n"
                    . "Content-type: application/xml; charset=utf-8\r\n"
                    . "Expires: Tue, 31 Mar 1981 05:00:00 GMT\r\n"
                    . "Connection: close\r\n"
                    . "\r\n"
                    . "<DescribeSecurityGroupsResponse xmlns=\"http://ec2.amazonaws.com/doc/2009-04-04/\">\r\n"
                    . "  <securityGroupInfo>\r\n"
                    . "    <item>\r\n"
                    . "      <ownerId>UYY3TLBUXIEON5NQVUUX6OMPWBZIQNFM</ownerId>\r\n"
                    . "      <groupName>WebServers</groupName>\r\n"
                    . "      <groupDescription>Web</groupDescription>\r\n"
                    . "      <ipPermissions>\r\n"
                    . "        <item>\r\n"
                    . "         <ipProtocol>tcp</ipProtocol>\r\n"
                    . "          <fromPort>80</fromPort>\r\n"
                    . "          <toPort>80</toPort>\r\n"
                    . "          <groups/>\r\n"
                    . "          <ipRanges>\r\n"
                    . "            <item>\r\n"
                    . "              <cidrIp>0.0.0.0/0</cidrIp>\r\n"
                    . "            </item>\r\n"
                    . "          </ipRanges>\r\n"
                    . "         </item>\r\n"
                    . "      </ipPermissions>\r\n"
                    . "    </item>\r\n"
                    . "  </securityGroupInfo>\r\n"
                    . "</DescribeSecurityGroupsResponse>\r\n";
        $this->httpClientTestAdapter->setResponse($rawHttpResponse);

        $return = $this->securitygroupsInstance->describe('WebServers');

        $this->assertEquals(1, count($return));

        $arrGroups = array(
                array(
                    'ownerId'   => 'UYY3TLBUXIEON5NQVUUX6OMPWBZIQNFM',
                    'groupName' => 'WebServers',
                    'groupDescription' => 'Web',
                    'ipPermissions' => array(0 => array(
                        'ipProtocol' => 'tcp',
                        'fromPort'  => '80',
                        'toPort'    => '80',
                        'ipRanges'  => '0.0.0.0/0'
                    ))
                )
            );
        foreach($return as $k => $r) {
            $this->assertSame($arrGroups[$k], $r);
        }
    }

    public function testDescribeSingleSecruityGroupWithMultipleIpsSamePort()
    {
        $rawHttpResponse = "HTTP/1.1 200 OK\r\n"
                    . "Date: Fri, 24 Oct 2008 17:24:52 GMT\r\n"
                    . "Server: hi\r\n"
                    . "Last-modified: Fri, 24 Oct 2008 17:24:52 GMT\r\n"
                    . "Status: 200 OK\r\n"
                    . "Content-type: application/xml; charset=utf-8\r\n"
                    . "Expires: Tue, 31 Mar 1981 05:00:00 GMT\r\n"
                    . "Connection: close\r\n"
                    . "\r\n"
                    . "<DescribeSecurityGroupsResponse xmlns=\"http://ec2.amazonaws.com/doc/2009-04-04/\">\r\n"
                    . "  <securityGroupInfo>\r\n"
                    . "    <item>\r\n"
                    . "      <ownerId>UYY3TLBUXIEON5NQVUUX6OMPWBZIQNFM</ownerId>\r\n"
                    . "      <groupName>WebServers</groupName>\r\n"
                    . "      <groupDescription>Web</groupDescription>\r\n"
                    . "      <ipPermissions>\r\n"
                    . "        <item>\r\n"
                    . "         <ipProtocol>tcp</ipProtocol>\r\n"
                    . "          <fromPort>80</fromPort>\r\n"
                    . "          <toPort>80</toPort>\r\n"
                    . "          <groups/>\r\n"
                    . "          <ipRanges>\r\n"
                    . "            <item>\r\n"
                    . "              <cidrIp>0.0.0.0/0</cidrIp>\r\n"
                    . "            </item>\r\n"
                    . "            <item>\r\n"
                    . "              <cidrIp>1.1.1.1/0</cidrIp>\r\n"
                    . "            </item>\r\n"
                    . "          </ipRanges>\r\n"
                    . "         </item>\r\n"
                    . "      </ipPermissions>\r\n"
                    . "    </item>\r\n"
                    . "  </securityGroupInfo>\r\n"
                    . "</DescribeSecurityGroupsResponse>\r\n";
        $this->httpClientTestAdapter->setResponse($rawHttpResponse);

        $return = $this->securitygroupsInstance->describe('WebServers');

        $this->assertEquals(1, count($return));

        $arrGroups = array(
                array(
                    'ownerId'   => 'UYY3TLBUXIEON5NQVUUX6OMPWBZIQNFM',
                    'groupName' => 'WebServers',
                    'groupDescription' => 'Web',
                    'ipPermissions' => array(0 => array(
                        'ipProtocol' => 'tcp',
                        'fromPort'  => '80',
                        'toPort'    => '80',
                        'ipRanges'  => array(
                            '0.0.0.0/0',
                            '1.1.1.1/0'
                            )
                    ))
                )
            );
        foreach($return as $k => $r) {
            $this->assertSame($arrGroups[$k], $r);
        }
    }

    /**
     * Tests ZendService\Amazon\Ec2\Securitygroups->revoke()
     */
    public function testRevokeSinglePort()
    {
        $rawHttpResponse = "HTTP/1.1 200 OK\r\n"
                    . "Date: Fri, 24 Oct 2008 17:24:52 GMT\r\n"
                    . "Server: hi\r\n"
                    . "Last-modified: Fri, 24 Oct 2008 17:24:52 GMT\r\n"
                    . "Status: 200 OK\r\n"
                    . "Content-type: application/xml; charset=utf-8\r\n"
                    . "Expires: Tue, 31 Mar 1981 05:00:00 GMT\r\n"
                    . "Connection: close\r\n"
                    . "\r\n"
                    . "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n"
                    . "<RevokeSecurityGroupIngressResponse xmlns=\"http://ec2.amazonaws.com/doc/2009-04-04/\">\r\n"
                    . "  <return>true</return>\r\n"
                    . "</RevokeSecurityGroupIngressResponse>\r\n";
        $this->httpClientTestAdapter->setResponse($rawHttpResponse);

        $return = $this->securitygroupsInstance->revokeIp('MyGroup', 'tcp', '80', '80', '0.0.0.0/0');
        $this->assertTrue($return);

    }

    public function testRevokePortRange()
    {
        $rawHttpResponse = "HTTP/1.1 200 OK\r\n"
                    . "Date: Fri, 24 Oct 2008 17:24:52 GMT\r\n"
                    . "Server: hi\r\n"
                    . "Last-modified: Fri, 24 Oct 2008 17:24:52 GMT\r\n"
                    . "Status: 200 OK\r\n"
                    . "Content-type: application/xml; charset=utf-8\r\n"
                    . "Expires: Tue, 31 Mar 1981 05:00:00 GMT\r\n"
                    . "Connection: close\r\n"
                    . "\r\n"
                    . "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n"
                    . "<RevokeSecurityGroupIngressResponse xmlns=\"http://ec2.amazonaws.com/doc/2009-04-04/\">\r\n"
                    . "  <return>true</return>\r\n"
                    . "</RevokeSecurityGroupIngressResponse>\r\n";
        $this->httpClientTestAdapter->setResponse($rawHttpResponse);

        $return = $this->securitygroupsInstance->revokeIp('MyGroup', 'tcp', '6000', '7000', '0.0.0.0/0');
        $this->assertTrue($return);

    }


    public function testRevokeSecurityGroupName()
    {
        $rawHttpResponse = "HTTP/1.1 200 OK\r\n"
                    . "Date: Fri, 24 Oct 2008 17:24:52 GMT\r\n"
                    . "Server: hi\r\n"
                    . "Last-modified: Fri, 24 Oct 2008 17:24:52 GMT\r\n"
                    . "Status: 200 OK\r\n"
                    . "Content-type: application/xml; charset=utf-8\r\n"
                    . "Expires: Tue, 31 Mar 1981 05:00:00 GMT\r\n"
                    . "Connection: close\r\n"
                    . "\r\n"
                    . "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n"
                    . "<RevokeSecurityGroupIngressResponse xmlns=\"http://ec2.amazonaws.com/doc/2009-04-04/\">\r\n"
                    . "  <return>true</return>\r\n"
                    . "</RevokeSecurityGroupIngressResponse>\r\n";
        $this->httpClientTestAdapter->setResponse($rawHttpResponse);

        $return = $this->securitygroupsInstance->revokeGroup('MyGroup', 'groupname', '15333848');
        $this->assertTrue($return);

    }

}
