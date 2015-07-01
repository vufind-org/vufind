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

/**
 * Amazon Ec2 Interface to allow easy creation of the Ec2 Components
 *
 * @category   Zend
 * @package    Zend_Service
 * @subpackage Amazon
 */
class Ec2
{
    /**
     * Factory method to fetch what you want to work with.
     *
     * @param string $section   Create the method that you want to work with
     * @param string $key       Override the default aws key
     * @param string $secretKey Override the default aws secret key
     * @throws Exception\RuntimeException
     * @return object
     */
    public static function factory($section, $key = null, $secretKey = null)
    {
        switch (strtolower($section)) {
            case 'keypair':
                $class = '\ZendService\Amazon\Ec2\Keypair';
                break;
            case 'eip':
                // break left out
            case 'elasticip':
                $class = '\ZendService\Amazon\Ec2\ElasticIp';
                break;
            case 'ebs':
                $class = '\ZendService\Amazon\Ec2\Ebs';
                break;
            case 'availabilityzones':
                // break left out
            case 'zones':
                $class = '\ZendService\Amazon\Ec2\AvailabilityZones';
                break;
            case 'ami':
                // break left out
            case 'image':
                $class = '\ZendService\Amazon\Ec2\Image';
                break;
            case 'instance':
                $class = '\ZendService\Amazon\Ec2\Instance';
                break;
            case 'security':
                // break left out
            case 'securitygroups':
                $class = '\ZendService\Amazon\Ec2\SecurityGroups';
                break;
            default:
                throw new Exception\RuntimeException('Invalid Section: ' . $section);
                break;
        }

        return new $class($key, $secretKey);
    }
}
