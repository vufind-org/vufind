<?php

/**
 * Block cipher class
 *
 * This class was developed to replace the deprecated \Laminas\Crypt\BlockCipher
 * class. Its default behavior is inspired by that earlier class and some of its
 * support classes (but greatly simplified).
 *
 * See https://github.com/laminas/laminas-crypt for original (abandoned) code.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Crypt
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Crypt;

use InvalidArgumentException;

use function chr;
use function extension_loaded;
use function in_array;
use function ord;

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
     * Salt
     *
     * @var string
     */
    protected $salt;

    /**
     * Encryption algorithm
     *
     * @var string
     */
    protected $algo = 'aes';

    /**
     * OpenSSL encryption mode
     *
     * @var string
     */
    protected $mode = 'cbc';

    /**
     * The encryption algorithms to support
     *
     * @var array
     */
    protected $encryptionAlgos = [
        'aes' => 'aes-256',
        'blowfish' => 'bf',
        'des' => 'des',
        'camellia' => 'camellia-256',
        'cast5' => 'cast5',
        'seed' => 'seed',
    ];

    /**
     * Block sizes (in bytes) for each supported algorithm
     *
     * @var array
     */
    protected $blockSizes = [
        'aes' => 16,
        'blowfish' => 8,
        'des' => 8,
        'camellia' => 16,
        'cast5' => 8,
        'seed' => 16,
    ];

    /**
     * Key sizes (in bytes) for each supported algorithm
     *
     * @var array
     */
    protected $keySizes = [
        'aes' => 32,
        'blowfish' => 56,
        'des' => 8,
        'camellia' => 32,
        'cast5' => 16,
        'seed' => 16,
    ];

    /**
     * Hash algorithm for Pbkdf2
     *
     * @var string
     */
    protected $pbkdf2Hash = 'sha256';

    /**
     * Hash algorithm for HMAC
     *
     * @var string
     */
    protected $hash = 'sha256';

    /**
     * Number of iterations for Pbkdf2
     *
     * @var string
     */
    protected $keyIteration = 5000;

    /**
     * Raw key provided by user
     *
     * @var string
     */
    protected $key;

    /**
     * Key prepared for OpenSSL
     *
     * @var string
     */
    protected $openSslKey = null;

    /**
     * Should we use the legacy pbkdf2 algorithm by default?
     *
     * @var bool
     */
    protected $legacyPbkdf2 = true;

    /**
     * Constructor
     *
     * @param array $options Options (supported keys: algorithm, legacyPbkdf2)
     */
    public function __construct(array $options = [])
    {
        if (!extension_loaded('openssl')) {
            throw new \RuntimeException('OpenSSL extension required!');
        }
        if (isset($options['algorithm'])) {
            $this->setAlgorithm($options['algorithm']);
        }
        if (isset($options['legacyPbkdf2'])) {
            $this->legacyPbkdf2 = (bool)$options['legacyPbkdf2'];
        }
    }

    /**
     * Pad the string using PKCS#7
     *
     * @param string $string    String to pad
     * @param int    $blockSize Target size
     *
     * @return string
     */
    protected function pkcs7Pad(string $string, int $blockSize = 32): string
    {
        $pad = $blockSize - (mb_strlen($string, '8bit') % $blockSize);
        return $string . str_repeat(chr($pad), $pad);
    }

    /**
     * Unpad a PKCS#7 string
     *
     * @param string $string String to unpad
     *
     * @return string
     */
    protected function pkcs7Strip(string $string): string
    {
        $end = mb_substr($string, -1, null, '8bit');
        $last = ord($end);
        $len = mb_strlen($string, '8bit') - $last;
        if (mb_substr($string, $len, null, '8bit') === str_repeat($end, $last)) {
            return mb_substr($string, 0, $len, '8bit');
        }
        throw new \Exception('Cannot unpad string');
    }

    /**
     * Look up appropriate key size for selected algorithm.
     *
     * @return int
     */
    protected function getKeySize(): int
    {
        return $this->keySizes[$this->algo];
    }

    /**
     * Set the encryption key for use by OpenSSL; note that this may be truncated by getOpenSslKey()
     * if it exceeds the length limit for the selected algorithm.
     *
     * @param string $key OpenSSL encryption key
     *
     * @return static
     * @throws InvalidArgumentException
     */
    protected function setOpenSslKey(string $key): static
    {
        $keyLen = mb_strlen($key, '8bit');

        if ($keyLen < $this->getKeySize()) {
            throw new InvalidArgumentException('OpenSSL key is too short.');
        }

        $this->openSslKey = $key;
        return $this;
    }

    /**
     * Get the key to use for OpenSSL encryption/decryption.
     *
     * @return string
     */
    protected function getOpenSslKey(): string
    {
        if (empty($this->openSslKey)) {
            throw new \Exception('OpenSSL key not set');
        }
        return mb_substr($this->openSslKey, 0, $this->getKeySize(), '8bit');
    }

    /**
     * Set the encryption algorithm (cipher) and mode (optionally)
     *
     * @param string  $algo New algorithm
     * @param ?string $mode New mode (null to keep current setting)
     *
     * @return static
     * @throws InvalidArgumentException
     */
    protected function setAlgorithmAndMode(string $algo, ?string $mode = null): static
    {
        $openSslMethod = ($this->encryptionAlgos[$algo] ?? 'UNSUPPORTED') . '-' . ($mode ?? $this->mode);
        if (!in_array($openSslMethod, openssl_get_cipher_methods(true))) {
            throw new InvalidArgumentException("Unsupported algorithm/mode: $algo/$mode");
        }
        $this->algo = $algo;
        if ($mode !== null) {
            $this->mode = $mode;
        }
        return $this;
    }

    /**
     * Set the encryption algorithm (cipher)
     *
     * @param string $algo New algorithm
     *
     * @return static
     * @throws InvalidArgumentException
     */
    public function setAlgorithm(string $algo): static
    {
        return $this->setAlgorithmAndMode($algo);
    }

    /**
     * Encrypt data using OpenSSL
     *
     * @param string $data Data to encrypt
     *
     * @throws InvalidArgumentException
     * @return string
     */
    protected function openSslEncrypt(string $data): string
    {
        if ($data === '') {
            throw new InvalidArgumentException('Empty strings cannot be encrypted');
        }

        if (null === $this->getSalt() && $this->getSaltSize() > 0) {
            throw new InvalidArgumentException('The salt (IV) cannot be empty');
        }

        // padding
        $data = $this->pkcs7Pad($data, $this->getBlockSize());
        $iv = $this->getSalt();

        $result = openssl_encrypt(
            $data,
            strtolower($this->encryptionAlgos[$this->algo] . '-' . $this->mode),
            $this->getOpenSslKey(),
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            $iv
        );

        if (false === $result) {
            throw new \RuntimeException(openssl_error_string() ?: 'Unexpected OpenSSL error');
        }

        return $iv . $result;
    }

    /**
     * Decrypt data using OpenSSL
     *
     * @param string $data Data to decrypt
     *
     * @throws InvalidArgumentException
     * @return string
     */
    protected function openSslDecrypt(string $data): string
    {
        if (empty($data)) {
            throw new InvalidArgumentException('The data to decrypt cannot be empty');
        }

        $result = openssl_decrypt(
            mb_substr($data, $this->getSaltSize(), null, '8bit'),
            strtolower($this->encryptionAlgos[$this->algo] . '-' . $this->mode),
            $this->getOpenSslKey(),
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            mb_substr($data, 0, $this->getSaltSize(), '8bit')
        );

        if (false === $result) {
            throw new \RuntimeException(openssl_error_string() ?: 'Unexpected OpenSSL error');
        }

        return $this->pkcs7Strip($result);
    }

    /**
     * Get the salt (IV) size
     *
     * @return int
     */
    protected function getSaltSize(): int
    {
        return openssl_cipher_iv_length(
            $this->encryptionAlgos[$this->algo] . '-' . $this->mode
        );
    }

    /**
     * Set the salt value
     *
     * @param string $salt Salt value
     *
     * @return static
     * @throws InvalidArgumentException
     */
    protected function setSalt(string $salt): static
    {
        $this->salt = $salt;
        return $this;
    }

    /**
     * Get an appropriate salt for the current algorithm.
     *
     * @return string
     */
    protected function getSalt(): string
    {
        if (mb_strlen($this->salt, '8bit') < $this->getSaltSize()) {
            throw new \RuntimeException('Salt is too short');
        }

        return mb_substr($this->salt, 0, $this->getSaltSize(), '8bit');
    }

    /**
     * Get the block size for the selected algorithm
     *
     * @return int
     */
    protected function getBlockSize(): int
    {
        return $this->blockSizes[$this->algo];
    }

    /**
     * Set the encryption/decryption key
     *
     * @param string $key Encryption/decryption key
     *
     * @return static
     * @throws InvalidArgumentException
     */
    public function setKey(string $key): static
    {
        if (empty($key)) {
            throw new InvalidArgumentException('The key cannot be empty');
        }
        $this->key = $key;

        return $this;
    }

    /**
     * Get the expected hash size based on algorithm and output format
     *
     * @param string $hash   Hash algorithm to measure
     * @param bool   $binary Output in binary mode?
     *
     * @return int
     */
    protected function getHashSize(string $hash, bool $binary = false): int
    {
        return mb_strlen(hash_hmac($hash, 'data', 'key', $binary), '8bit');
    }

    /**
     * Generate a Pbkdf2 key (PKCS #5 v2.0 standard RFC 2898) using the Laminas\Crypt
     * algorithm, which varies from PHP's built-in hash_pbkdf2(). We need to use this
     * logic for compatibility with data generated by versions of VuFind prior to 11.0.
     *
     * @param string $hash       The hash algorithm to be used by HMAC
     * @param string $password   The source password/key
     * @param string $salt       Salt value
     * @param int    $iterations The number of iterations
     * @param int    $length     The output size
     *
     * @throws InvalidArgumentException
     * @return string
     */
    protected function getLegacyPbkdf2(
        string $hash,
        string $password,
        string $salt,
        int $iterations,
        int $length
    ): string {
        $num = ceil($length / $this->getHashSize($hash, true));
        $result = '';
        for ($block = 1; $block <= $num; $block++) {
            $hmac = hash_hmac($hash, $salt . pack('N', $block), $password, true);
            $mix = $hmac;
            for ($i = 1; $i < $iterations; $i++) {
                $hmac = hash_hmac($hash, $hmac, $password, true);
                $mix ^= $hmac;
            }
            $result .= $mix;
        }
        return mb_substr($result, 0, $length, '8bit');
    }

    /**
     * Get a Pbkdf2 hash.
     *
     * @param string $salt    Salt
     * @param int    $keySize Key size
     * @param ?bool  $legacy  Use legacy pbkdf2 algorithm for compatibility with old data?
     * (pass null to use configured setting)
     *
     * @return string
     */
    protected function getPbKdf2(string $salt, int $keySize, ?bool $legacy = null): string
    {
        $callback = ($legacy ?? $this->legacyPbkdf2) ? [$this, 'getLegacyPbkdf2'] : 'hash_pbkdf2';
        return $callback(
            $this->pbkdf2Hash,
            $this->key,
            $salt,
            $this->keyIteration,
            $keySize * 2
        );
    }

    /**
     * Encrypt data (with HMAC authentication)
     *
     * @param string $data Data to encrypt
     *
     * @return string
     * @throws InvalidArgumentException
     */
    public function encrypt(string $data): string
    {
        if ($data === '') { // don't use empty() because of "falsy" values
            throw new InvalidArgumentException('Cannot encrypt empty data');
        }
        if (empty($this->key)) {
            throw new InvalidArgumentException('A key is required');
        }
        $keySize = $this->getKeySize();
        $this->setSalt(random_bytes($this->getSaltSize()));

        // generate the encryption key
        $hash = $this->getPbKdf2($this->getSalt(), $keySize);
        // set the encryption key
        $this->setOpenSslKey(mb_substr($hash, 0, $keySize, '8bit'));
        // create the key for HMAC validation
        $keyHmac = mb_substr($hash, $keySize, null, '8bit');
        // encrypt the data
        $ciphertext = $this->openSslEncrypt($data);
        // generate the HMAC key for validation
        $hmac = hash_hmac($this->hash, $this->algo . $ciphertext, $keyHmac);

        return $hmac . base64_encode($ciphertext);
    }

    /**
     * Generate the encryption/decryption key and the HMAC key for the authentication
     *
     * @param string $ciphertext Text to decrypt
     * @param string $salt       Salt
     * @param int    $keySize    Key size
     * @param ?bool  $legacy     Use legacy pbkdf2 algorithm for compatibility with old data?
     * (pass null to use configured setting)
     *
     * @return string
     * @throws InvalidArgumentException
     */
    protected function setOpenSslKeyAndGetValidationHmac(
        string $ciphertext,
        string $salt,
        int $keySize,
        ?bool $legacy = null
    ): string {
        // create the hash
        $hash = $this->getPbKdf2($salt, $keySize, $legacy);
        // set the decryption key
        $this->setOpenSslKey(mb_substr($hash, 0, $keySize, '8bit'));
        // set the key for HMAC
        $keyHmac = mb_substr($hash, $keySize, null, '8bit');
        return hash_hmac($this->hash, $this->algo . $ciphertext, $keyHmac);
    }

    /**
     * Decrypt data
     *
     * @param string $data Data to decrypt
     *
     * @return string|bool
     *
     * @throws InvalidArgumentException
     */
    public function decrypt(string $data): string|bool
    {
        if ('' === $data) {
            throw new InvalidArgumentException('The data to decrypt cannot be empty');
        }
        if (empty($this->key)) {
            throw new InvalidArgumentException('A key is required');
        }

        $keySize = $this->getKeySize();

        $hmacSize = $this->getHashSize($this->hash);
        $hmac = mb_substr($data, 0, $hmacSize, '8bit');
        $ciphertext = base64_decode(mb_substr($data, $hmacSize, null, '8bit') ?: '');
        $iv = mb_substr($ciphertext, 0, $this->getSaltSize(), '8bit');
        $hmacNew = $this->setOpenSslKeyAndGetValidationHmac($ciphertext, $iv, $keySize);
        if ($hmacNew !== $hmac) {
            // If authentication failed using the configured legacy setting, try flipping it as a fallback:
            $hmacNew = $this->setOpenSslKeyAndGetValidationHmac($ciphertext, $iv, $keySize, !$this->legacyPbkdf2);
            if ($hmacNew !== $hmac) {
                return false;
            }
        }

        return $this->openSslDecrypt($ciphertext);
    }
}
