<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Service
 */

namespace ZendService\Amazon\Ec2;

use ZendService\Amazon;
use ZendService\Amazon\Ec2\Exception;

/**
 * An Amazon EC2 interface to create, delete and describe Ec2 KeyPairs.
 *
 * @category   Zend
 * @package    Zend_Service_Amazon
 * @subpackage Ec2
 */
class Keypair extends AbstractEc2
{
    /**
     * Creates a new 2048 bit RSA key pair and returns a unique ID that can
     * be used to reference this key pair when launching new instances.
     *
     * @param string $keyName           A unique name for the key pair.
     * @throws Exception\InvalidArgumentException
     * @return array
     */
    public function create($keyName)
    {
        $params = array();

        $params['Action'] = 'CreateKeyPair';

        if(!$keyName) {
            throw new Exception\InvalidArgumentException('Invalid Key Name');
        }

        $params['KeyName'] = $keyName;

        $response = $this->sendRequest($params);
        $xpath = $response->getXPath();

        $return = array();
        $return['keyName']          = $xpath->evaluate('string(//ec2:keyName/text())');
        $return['keyFingerprint']   = $xpath->evaluate('string(//ec2:keyFingerprint/text())');
        $return['keyMaterial']      = $xpath->evaluate('string(//ec2:keyMaterial/text())');

        return $return;
    }

    /**
     * Returns information about key pairs available to you. If you specify
     * key pairs, information about those key pairs is returned. Otherwise,
     * information for all registered key pairs is returned.
     *
     * @param string|array $keyName Key pair IDs to describe.
     * @return array
     */
    public function describe($keyName = null)
    {
        $params = array();

        $params['Action'] = 'DescribeKeyPairs';
        if(is_array($keyName) && !empty($keyName)) {
            foreach($keyName as $k=>$name) {
                $params['KeyName.' . ($k+1)] = $name;
            }
        } elseif($keyName) {
            $params['KeyName.1'] = $keyName;
        }

        $response = $this->sendRequest($params);
        $xpath = $response->getXPath();

        $nodes  = $xpath->query('//ec2:keySet/ec2:item');

        $return = array();
        foreach ($nodes as $k => $node) {
            $item = array();
            $item['keyName']          = $xpath->evaluate('string(ec2:keyName/text())', $node);
            $item['keyFingerprint']   = $xpath->evaluate('string(ec2:keyFingerprint/text())', $node);

            $return[] = $item;
            unset($item);
        }

        return $return;
    }

    /**
     * Deletes a key pair
     *
     * @param string $keyName           Name of the key pair to delete.
     * @throws Exception\InvalidArgumentException
     * @return boolean                  Return true or false from the deletion.
     */
    public function delete($keyName)
    {
        $params = array();

        $params['Action'] = 'DeleteKeyPair';

        if(!$keyName) {
            throw new Exception\InvalidArgumentException('Invalid Key Name');
        }

        $params['KeyName'] = $keyName;

        $response = $this->sendRequest($params);

        $xpath = $response->getXPath();
        $success  = $xpath->evaluate('string(//ec2:return/text())');

        return ($success === "true");
    }
}
