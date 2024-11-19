<?php

/**
 * Block cipher class
 *
 * This class was developed to replace the deprecated \Laminas\Crypt\BlockCipher
 * class. Its default behavior is inspired by that earlier class (but greatly
 * simplified).
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Crypt
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Crypt;

use Laminas\Crypt\Hmac;
use Laminas\Crypt\Key\Derivation\Pbkdf2;
use Laminas\Crypt\Symmetric\PaddingPluginManager;
use Psr\Container\ContainerInterface;

/**
 * Block cipher class
 *
 * @category VuFind
 * @package  Crypt
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class BlockCipher
{
    /**
     * IV
     *
     * @var string
     */
    protected $iv;

    /**
     * Encryption algorithm
     *
     * @var string
     */
    protected $algo = 'aes';

    /**
     * Encryption mode
     *
     * @var string
     */
    protected $mode = 'cbc';

    /**
     * Padding plugins
     *
     * @var ContainerInterface
     */
    protected static $paddingPlugins;

    /**
     * The encryption algorithms to support
     *
     * @var array
     */
    protected $encryptionAlgos = [
        'aes'      => 'aes-256',
        'blowfish' => 'bf',
        'des'      => 'des',
        'camellia' => 'camellia-256',
        'cast5'    => 'cast5',
        'seed'     => 'seed',
    ];

    /**
     * Encryption modes to support
     *
     * @var array
     */
    protected $encryptionModes = [
        'cbc',
        'cfb',
        'ofb',
        'ecb',
        'ctr',
    ];

    /**
     * Block sizes (in bytes) for each supported algorithm
     *
     * @var array
     */
    protected $blockSizes = [
        'aes'      => 16,
        'blowfish' => 8,
        'des'      => 8,
        'camellia' => 16,
        'cast5'    => 8,
        'seed'     => 16,
    ];

    /**
     * Key sizes (in bytes) for each supported algorithm
     *
     * @var array
     */
    protected $keySizes = [
        'aes'      => 32,
        'blowfish' => 56,
        'des'      => 8,
        'camellia' => 32,
        'cast5'    => 16,
        'seed'     => 16,
    ];

    /**
     * The OpenSSL supported encryption algorithms
     *
     * @var array
     */
    protected $opensslAlgos = [];

    /**
     * Additional authentication data (only for PHP 7.1+)
     *
     * @var string
     */
    protected $aad = '';

    /**
     * Store the tag for authentication (only for PHP 7.1+)
     *
     * @var string
     */
    protected $tag;

    /**
     * Tag size for authenticated encryption modes (only for PHP 7.1+)
     *
     * @var int
     */
    protected $tagSize = 16;

    /**
     * Supported algorithms
     *
     * @internal This property was declared for compatibility with PHP 8.2,
     *          and is not supposed to be used directly, other than for BC reasons
     *
     * @var list<string>
     */
    public $supportedAlgos;

    /**
     * Hash algorithm for Pbkdf2
     *
     * @var string
     */
    protected $pbkdf2Hash = 'sha256';

    /**
     * Symmetric cipher plugin manager
     *
     * @var SymmetricPluginManager
     */
    protected static $symmetricPlugins;

    /**
     * Hash algorithm for HMAC
     *
     * @var string
     */
    protected $hash = 'sha256';

    /**
     * The output is binary?
     *
     * @var bool
     */
    protected $binaryOutput = false;

    /**
     * Number of iterations for Pbkdf2
     *
     * @var string
     */
    protected $keyIteration = 5000;

    /**
     * Key
     *
     * @var string
     */
    protected $key;

    /**
     * Cipher key
     *
     * @var string
     */
    protected $cipherKey;

    /**
     * Constructor
     *
     * @param array $options Options (supported key: algorithm)
     */
    public function __construct(protected array $options = [])
    {
        if (!extension_loaded('openssl')) {
            throw new \RuntimeException('OpenSSL extension required!');
        }
        if (isset($options['algorithm'])) {
            $this->setAlgorithm($options['algorithm']);
        }
    }

    /**
     * Pad the string to the specified size
     *
     * @param string $string    The string to pad
     * @param int    $blockSize The size to pad to
     * @return string The padded string
     */
    protected function pkcs7Pad($string, $blockSize = 32)
    {
        $pad = $blockSize - (mb_strlen($string, '8bit') % $blockSize);
        return $string . str_repeat(chr($pad), $pad);
    }

    /**
     * Strip the padding from the supplied string
     *
     * @param string $string The string to trim
     * @return string The unpadded string
     */
    protected function pkcs7Strip($string)
    {
        $end  = mb_substr($string, -1, null, '8bit');
        $last = ord($end);
        $len  = mb_strlen($string, '8bit') - $last;
        if (mb_substr($string, $len, null, '8bit') === str_repeat($end, $last)) {
            return mb_substr($string, 0, $len, '8bit');
        }
        return false;
    }

    /**
     * Returns the padding plugin manager.
     *
     * Creates one if none is present.
     *
     * @return ContainerInterface
     */
    public static function getPaddingPluginManager()
    {
        if (static::$paddingPlugins === null) {
            self::setPaddingPluginManager(new PaddingPluginManager());
        }

        return static::$paddingPlugins;
    }

    /**
     * Set the padding plugin manager
     *
     * @param  string|ContainerInterface $plugins
     * @throws Exception\InvalidArgumentException
     * @return void
     */
    public static function setPaddingPluginManager($plugins)
    {
        if (is_string($plugins)) {
            if (! class_exists($plugins) || ! is_subclass_of($plugins, ContainerInterface::class)) {
                throw new \InvalidArgumentException(sprintf(
                    'Unable to locate padding plugin manager via class "%s"; '
                    . 'class does not exist or does not implement ContainerInterface',
                    $plugins
                ));
            }

            $plugins = new $plugins();
        }

        if (! $plugins instanceof ContainerInterface) {
            throw new \InvalidArgumentException(sprintf(
                'Padding plugins must implements %s; received "%s"',
                ContainerInterface::class,
                is_object($plugins) ? get_class($plugins) : gettype($plugins)
            ));
        }

        static::$paddingPlugins = $plugins;
    }

    /**
     * Get the key size for the selected cipher
     *
     * @return int
     */
    public function getKeySize()
    {
        return $this->keySizes[$this->algo];
    }

    /**
     * Set the encryption key
     * If the key is longer than maximum supported, it will be truncated by getKey().
     *
     * @param  string $key
     * @return Openssl Provides a fluent interface
     * @throws Exception\InvalidArgumentException
     */
    public function setCipherKey($key)
    {
        $keyLen = mb_strlen($key, '8bit');

        if (! $keyLen) {
            throw new \InvalidArgumentException('The key cannot be empty');
        }

        if ($keyLen < $this->getKeySize()) {
            throw new \InvalidArgumentException(sprintf(
                'The size of the key must be at least of %d bytes',
                $this->getKeySize()
            ));
        }

        $this->cipherKey = $key;
        return $this;
    }

    /**
     * Get the encryption key
     *
     * @return string
     */
    public function getCipherKey()
    {
        if (empty($this->cipherKey)) {
            return;
        }
        return mb_substr($this->cipherKey, 0, $this->getKeySize(), '8bit');
    }

    /**
     * Set the encryption algorithm (cipher)
     *
     * @param  string $algo
     * @return Openssl Provides a fluent interface
     * @throws Exception\InvalidArgumentException
     */
    public function setAlgorithm($algo)
    {
        if (! in_array($algo, $this->getSupportedAlgorithms())) {
            throw new \InvalidArgumentException(sprintf(
                'The algorithm %s is not supported by %s',
                $algo,
                self::class
            ));
        }
        $this->algo = $algo;
        return $this;
    }

    /**
     * Get the encryption algorithm
     *
     * @return string
     */
    public function getAlgorithm()
    {
        return $this->algo;
    }

    /**
     * Set Additional Authentication Data
     *
     * @param string $aad
     * @return self
     * @throws Exception\InvalidArgumentException
     * @throws Exception\RuntimeException
     */
    public function setAad($aad)
    {
        if (! $this->isAuthEncAvailable()) {
            throw new \RuntimeException(
                'You need PHP 7.1+ and OpenSSL with CCM or GCM mode to use AAD'
            );
        }

        if (! $this->isCcmOrGcm()) {
            throw new \RuntimeException(
                'You can set Additional Authentication Data (AAD) only for CCM or GCM mode'
            );
        }

        if (! is_string($aad)) {
            throw new \InvalidArgumentException(sprintf(
                'The provided $aad must be a string, %s given',
                gettype($aad)
            ));
        }

        $this->aad = $aad;

        return $this;
    }

    /**
     * Get the Additional Authentication Data
     *
     * @return string
     */
    public function getAad()
    {
        return $this->aad;
    }

    /**
     * Get the authentication tag
     *
     * @return string
     */
    public function getTag()
    {
        return $this->tag;
    }

    /**
     * Set the tag size for CCM and GCM mode
     *
     * @param int $size
     * @return self
     * @throws Exception\InvalidArgumentException
     * @throws Exception\RuntimeException
     */
    public function setTagSize($size)
    {
        if (! is_int($size)) {
            throw new \InvalidArgumentException(sprintf(
                'The provided $size must be an integer, %s given',
                gettype($size)
            ));
        }

        if (! $this->isAuthEncAvailable()) {
            throw new \RuntimeException(
                'You need PHP 7.1+ and OpenSSL with CCM or GCM mode to set the Tag Size'
            );
        }

        if (! $this->isCcmOrGcm()) {
            throw new \RuntimeException(
                'You can set the Tag Size only for CCM or GCM mode'
            );
        }

        if ($this->getMode() === 'gcm' && ($size < 4 || $size > 16)) {
            throw new \InvalidArgumentException(
                'The Tag Size must be between 4 to 16 for GCM mode'
            );
        }

        $this->tagSize = $size;

        return $this;
    }

    /**
     * Get the tag size for CCM and GCM mode
     *
     * @return int
     */
    public function getTagSize()
    {
        return $this->tagSize;
    }

    /**
     * Encrypt
     *
     * @param  string $data
     * @throws Exception\InvalidArgumentException
     * @return string
     */
    public function openSslEncrypt($data)
    {
        // Cannot encrypt empty string
        if (! is_string($data) || $data === '') {
            throw new \InvalidArgumentException('The data to encrypt cannot be empty');
        }

        if (null === $this->getCipherKey()) {
            throw new \InvalidArgumentException('No key specified for the encryption');
        }

        if (null === $this->getSalt() && $this->getSaltSize() > 0) {
            throw new \InvalidArgumentException('The salt (IV) cannot be empty');
        }

        // padding
        $data = $this->pkcs7Pad($data, $this->getBlockSize());
        $iv   = $this->getSalt();

        // encryption with GCM or CCM
        if ($this->isCcmOrGcm()) {
            $result    = openssl_encrypt(
                $data,
                strtolower($this->encryptionAlgos[$this->algo] . '-' . $this->mode),
                $this->getCipherKey(),
                OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
                $iv,
                $tag,
                $this->getAad(),
                $this->getTagSize()
            );
            $this->tag = $tag;
        } else {
            $result = openssl_encrypt(
                $data,
                strtolower($this->encryptionAlgos[$this->algo] . '-' . $this->mode),
                $this->getCipherKey(),
                OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
                $iv
            );
        }

        if (false === $result) {
            $errMsg = '';
            while ($msg = openssl_error_string()) {
                $errMsg .= $msg;
            }
            throw new \RuntimeException(sprintf(
                'OpenSSL error: %s',
                $errMsg
            ));
        }

        if ($this->isCcmOrGcm()) {
            return $tag . $iv . $result;
        }

        return $iv . $result;
    }

    /**
     * Decrypt
     *
     * @param  string $data
     * @throws Exception\InvalidArgumentException
     * @return string
     */
    public function openSslDecrypt($data)
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('The data to decrypt cannot be empty');
        }

        if (null === $this->getCipherKey()) {
            throw new \InvalidArgumentException('No decryption key specified');
        }

        if ($this->isCcmOrGcm()) {
            $tag       = mb_substr($data, 0, $this->getTagSize(), '8bit');
            $data      = mb_substr($data, $this->getTagSize(), null, '8bit');
            $this->tag = $tag;
        }

        $iv         = mb_substr($data, 0, $this->getSaltSize(), '8bit');
        $ciphertext = mb_substr($data, $this->getSaltSize(), null, '8bit');
        $result     = $this->attemptOpensslDecrypt($ciphertext, $iv, $this->tag);

        if (false === $result) {
            $errMsg = '';

            while ($msg = openssl_error_string()) {
                $errMsg .= $msg;
            }

            throw new \RuntimeException(sprintf(
                'OpenSSL error: %s',
                $errMsg
            ));
        }

        // unpadding
        return $this->pkcs7Strip($result);
    }

    /**
     * Get the salt (IV) size
     *
     * @return int
     */
    public function getSaltSize()
    {
        return openssl_cipher_iv_length(
            $this->encryptionAlgos[$this->algo] . '-' . $this->mode
        );
    }

    /**
     * Get the supported algorithms
     *
     * @return array
     */
    public function getSupportedAlgorithms()
    {
        if (empty($this->supportedAlgos)) {
            foreach ($this->encryptionAlgos as $name => $algo) {
                // CBC mode is supported by all the algorithms
                if (in_array($algo . '-cbc', $this->getOpensslAlgos())) {
                    $this->supportedAlgos[] = $name;
                }
            }
        }
        return $this->supportedAlgos;
    }

    /**
     * Set the salt (IV)
     *
     * @param  string $salt
     * @return Openssl Provides a fluent interface
     * @throws Exception\InvalidArgumentException
     */
    public function setSalt($salt)
    {
        if ($this->getSaltSize() <= 0) {
            throw new \InvalidArgumentException(sprintf(
                'You cannot use a salt (IV) for %s in %s mode',
                $this->algo,
                $this->mode
            ));
        }

        if (empty($salt)) {
            throw new \InvalidArgumentException('The salt (IV) cannot be empty');
        }

        if (mb_strlen($salt, '8bit') < $this->getSaltSize()) {
            throw new \InvalidArgumentException(sprintf(
                'The size of the salt (IV) must be at least %d bytes',
                $this->getSaltSize()
            ));
        }

        $this->iv = $salt;
        return $this;
    }

    /**
     * Get the salt (IV) according to the size requested by the algorithm
     *
     * @return string
     */
    public function getSalt()
    {
        if (empty($this->iv)) {
            return;
        }

        if (mb_strlen($this->iv, '8bit') < $this->getSaltSize()) {
            throw new \RuntimeException(sprintf(
                'The size of the salt (IV) must be at least %d bytes',
                $this->getSaltSize()
            ));
        }

        return mb_substr($this->iv, 0, $this->getSaltSize(), '8bit');
    }

    /**
     * Get the original salt value
     *
     * @return string
     */
    public function getOriginalSalt()
    {
        return $this->iv;
    }

    /**
     * Set the cipher mode
     *
     * @param  string $mode
     * @return Openssl Provides a fluent interface
     * @throws Exception\InvalidArgumentException
     */
    public function setMode($mode)
    {
        if (empty($mode)) {
            return $this;
        }
        if (! in_array($mode, $this->getSupportedModes())) {
            throw new \InvalidArgumentException(sprintf(
                'The mode %s is not supported by %s',
                $mode,
                $this->algo
            ));
        }
        $this->mode = $mode;
        return $this;
    }

    /**
     * Get the cipher mode
     *
     * @return string
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * Return the OpenSSL supported encryption algorithms
     *
     * @return array
     */
    protected function getOpensslAlgos()
    {
        if (empty($this->opensslAlgos)) {
            $this->opensslAlgos = openssl_get_cipher_methods(true);
        }
        return $this->opensslAlgos;
    }

    /**
     * Get all supported encryption modes for the selected algorithm
     *
     * @return array
     */
    public function getSupportedModes()
    {
        $modes = [];
        foreach ($this->encryptionModes as $mode) {
            $algo = $this->encryptionAlgos[$this->algo] . '-' . $mode;
            if (in_array($algo, $this->getOpensslAlgos())) {
                $modes[] = $mode;
            }
        }
        return $modes;
    }

    /**
     * Get the block size
     *
     * @return int
     */
    public function getBlockSize()
    {
        return $this->blockSizes[$this->algo];
    }

    /**
     * Return true if authenticated encryption is available
     *
     * @return bool
     */
    public function isAuthEncAvailable()
    {
        // Counter with CBC-MAC
        $ccm = in_array('aes-256-ccm', $this->getOpensslAlgos());
        // Galois/Counter Mode
        $gcm = in_array('aes-256-gcm', $this->getOpensslAlgos());

        return PHP_VERSION_ID >= 70100 && ($ccm || $gcm);
    }

    /**
     * @return bool
     */
    private function isCcmOrGcm()
    {
        return in_array(strtolower($this->mode), ['gcm', 'ccm'], true);
    }

    /**
     * @param string $cipherText
     * @param string $iv
     * @param string $tag
     * @return string|bool false on failure
     */
    private function attemptOpensslDecrypt($cipherText, $iv, $tag)
    {
        if ($this->isCcmOrGcm()) {
            return openssl_decrypt(
                $cipherText,
                strtolower($this->encryptionAlgos[$this->algo] . '-' . $this->mode),
                $this->getCipherKey(),
                OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
                $iv,
                $tag,
                $this->getAad()
            );
        }

        return openssl_decrypt(
            $cipherText,
            strtolower($this->encryptionAlgos[$this->algo] . '-' . $this->mode),
            $this->getCipherKey(),
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            $iv
        );
    }

    /**
     * Set the number of iterations for Pbkdf2
     *
     * @param  int $num
     * @return BlockCipher Provides a fluent interface
     */
    public function setKeyIteration($num)
    {
        $this->keyIteration = (int) $num;

        return $this;
    }

    /**
     * Get the number of iterations for Pbkdf2
     *
     * @return int
     */
    public function getKeyIteration()
    {
        return $this->keyIteration;
    }

    /**
     * Set the encryption/decryption key
     *
     * @param  string $key
     * @return BlockCipher Provides a fluent interface
     * @throws \InvalidArgumentException
     */
    public function setKey($key)
    {
        if (empty($key)) {
            throw new \InvalidArgumentException('The key cannot be empty');
        }
        $this->key = $key;

        return $this;
    }

    /**
     * Get the key
     *
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Get the hash algorithm for HMAC authentication
     *
     * @return string
     */
    public function getHashAlgorithm()
    {
        return $this->hash;
    }

    /**
     * Get the Pbkdf2 hash algorithm
     *
     * @return string
     */
    public function getPbkdf2HashAlgorithm()
    {
        return $this->pbkdf2Hash;
    }

    /**
     * Encrypt then authenticate using HMAC
     *
     * @param  string $data
     * @return string
     * @throws \InvalidArgumentException
     */
    public function encrypt($data)
    {
        // 0 (as integer), 0.0 (as float) & '0' (as string) will return false, though these should be allowed
        // Must be a string, integer, or float in order to encrypt
        if (
            (is_string($data) && $data === '')
            || is_array($data)
            || is_object($data)
        ) {
            throw new \InvalidArgumentException('The data to encrypt cannot be empty');
        }

        // Cast to string prior to encrypting
        if (! is_string($data)) {
            $data = (string) $data;
        }

        if (empty($this->key)) {
            throw new \InvalidArgumentException('No key specified for the encryption');
        }
        $keySize = $this->getKeySize();
        // generate a random salt (IV) if the salt has not been set
        $this->setSalt(random_bytes($this->getSaltSize()));

        // generate the encryption key and the HMAC key for the authentication
        $hash = Pbkdf2::calc(
            $this->getPbkdf2HashAlgorithm(),
            $this->getKey(),
            $this->getSalt(),
            $this->keyIteration,
            $keySize * 2
        );
        // set the encryption key
        $this->setCipherKey(mb_substr($hash, 0, $keySize, '8bit'));
        // set the key for HMAC
        $keyHmac = mb_substr($hash, $keySize, null, '8bit');
        // encryption
        $ciphertext = $this->openSslEncrypt($data);
        // HMAC
        $hmac = Hmac::compute($keyHmac, $this->hash, $this->getAlgorithm() . $ciphertext);

        return $this->binaryOutput ? $hmac . $ciphertext : $hmac . base64_encode($ciphertext);
    }

    /**
     * Decrypt
     *
     * @param  string $data
     * @return string|bool
     * @throws \InvalidArgumentException
     */
    public function decrypt($data)
    {
        if (! is_string($data)) {
            throw new \InvalidArgumentException('The data to decrypt must be a string');
        }
        if ('' === $data) {
            throw new \InvalidArgumentException('The data to decrypt cannot be empty');
        }
        if (empty($this->key)) {
            throw new \InvalidArgumentException('No key specified for the decryption');
        }

        $keySize = $this->getKeySize();

        $hmacSize   = Hmac::getOutputSize($this->hash);
        $hmac       = mb_substr($data, 0, $hmacSize, '8bit');
        $ciphertext = mb_substr($data, $hmacSize, null, '8bit') ?: '';
        if (! $this->binaryOutput) {
            $ciphertext = base64_decode($ciphertext);
        }
        $iv = mb_substr($ciphertext, 0, $this->getSaltSize(), '8bit');
        // generate the encryption key and the HMAC key for the authentication
        $hash = Pbkdf2::calc(
            $this->getPbkdf2HashAlgorithm(),
            $this->getKey(),
            $iv,
            $this->keyIteration,
            $keySize * 2
        );
        // set the decryption key
        $this->setCipherKey(mb_substr($hash, 0, $keySize, '8bit'));
        // set the key for HMAC
        $keyHmac = mb_substr($hash, $keySize, null, '8bit');
        $hmacNew = Hmac::compute($keyHmac, $this->hash, $this->getAlgorithm() . $ciphertext);
        if (strcmp($hmacNew, $hmac) !== 0) {
            return false;
        }

        return $this->openSslDecrypt($ciphertext);
    }
}
