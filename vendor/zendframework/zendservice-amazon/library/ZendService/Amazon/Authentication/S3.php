<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Service
 */

namespace ZendService\Amazon\Authentication;

use Zend\Crypt\Hmac;

/**
 * @category   Zend
 * @package    Zend_Service_Amazon
 * @subpackage Authentication
 */
class S3 extends AbstractAuthentication
{
    /**
     * Add the S3 Authorization signature to the request headers
     *
     * @param  string $method
     * @param  string $path
     * @param  array &$headers
     * @return string
     */
    public function generateSignature($method, $path, &$headers)
    {
        if (! is_array($headers)) {
            $headers = array($headers);
        }

        $type = $md5 = $date = '';

        // Search for the Content-type, Content-MD5 and Date headers
        foreach ($headers as $key => $val) {
            if (strcasecmp($key, 'content-type') == 0) {
                $type = $val;
            } elseif (strcasecmp($key, 'content-md5') == 0) {
                $md5 = $val;
            } elseif (strcasecmp($key, 'date') == 0) {
                $date = $val;
            }
        }

        // If we have an x-amz-date header, use that instead of the normal Date
        if (isset($headers['x-amz-date']) && isset($date)) {
            $date = '';
        }

        $sig_str = "$method\n$md5\n$type\n$date\n";

        // For x-amz- headers, combine like keys, lowercase them, sort them
        // alphabetically and remove excess spaces around values
        $amz_headers = array();
        foreach ($headers as $key => $val) {
            $key = strtolower($key);
            if (substr($key, 0, 6) == 'x-amz-') {
                if (is_array($val)) {
                    $amz_headers[$key] = $val;
                } else {
                    $amz_headers[$key][] = preg_replace('/\s+/', ' ', $val);
                }
            }
        }
        if (!empty($amz_headers)) {
            ksort($amz_headers);
            foreach ($amz_headers as $key => $val) {
                $sig_str .= $key . ':' . implode(',', $val) . "\n";
            }
        }

        $sig_str .= '/'.parse_url($path, PHP_URL_PATH);
        if (strpos($path, '?location') !== false) {
            $sig_str .= '?location';
        } else
            if (strpos($path, '?acl') !== false) {
                $sig_str .= '?acl';
            } else
                if (strpos($path, '?torrent') !== false) {
                    $sig_str .= '?torrent';
                }

        $signature = Hmac::compute($this->_secretKey, 'sha1', utf8_encode($sig_str), Hmac::OUTPUT_BINARY);
        $headers['Authorization'] = 'AWS ' . $this->_accessKey . ':' . base64_encode($signature);

        return $sig_str;
    }
}
