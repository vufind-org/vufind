<?php

/**
 * STDOUT stream
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @package  File
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\File;

use Psr\Http\Message\StreamInterface;

use function strlen;

/**
 * STDOUT stream
 *
 * @category VuFind
 * @package  File
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class StdoutStream implements StreamInterface
{
    /**
     * Whether STDOUT output is active
     *
     * @var bool
     */
    protected bool $outputActive = false;

    /**
     * Toggle STDOUT output
     *
     * @param bool $value STDOUT active setting
     *
     * @return void
     */
    public function setOutputActive(bool $value): void
    {
        $this->outputActive = $value;
    }

    /**
     * Reads all data from the stream into a string, from the beginning to end.
     *
     * This method MUST attempt to seek to the beginning of the stream before
     * reading data and read the stream until the end is reached.
     *
     * Warning: This could attempt to load a large amount of data into memory.
     *
     * This method MUST NOT raise an exception in order to conform with PHP's
     * string casting operations.
     *
     * @see http://php.net/manual/en/language.oop5.magic.php#object.tostring
     *
     * @return string
     */
    public function __toString()
    {
        return '';
    }

    /**
     * Closes the stream and any underlying resources.
     *
     * @return void
     */
    public function close(): void
    {
    }

    /**
     * Separates any underlying resources from the stream.
     *
     * After the stream has been detached, the stream is in an unusable state.
     *
     * @return resource|null Underlying PHP stream, if any
     */
    public function detach()
    {
        return null;
    }

    /**
     * Get the size of the stream if known.
     *
     * @return int|null Returns the size in bytes if known, or null if unknown.
     */
    public function getSize(): ?int
    {
        return null;
    }

    /**
     * Returns the current position of the file read/write pointer
     *
     * @return int Position of the file pointer
     * @throws \RuntimeException on error.
     */
    public function tell(): int
    {
        return 0;
    }

    /**
     * Returns true if the stream is at the end of the stream.
     *
     * @return bool
     */
    public function eof(): bool
    {
        return true;
    }

    /**
     * Returns whether or not the stream is seekable.
     *
     * @return bool
     */
    public function isSeekable(): bool
    {
        return true;
    }

    /**
     * Seek to a position in the stream.
     *
     * @param int $offset Stream offset
     * @param int $whence Specifies how the cursor position will be calculated
     *     based on the seek offset. Valid values are identical to the built-in
     *     PHP $whence values for `fseek()`.  SEEK_SET: Set position equal to
     *     offset bytes SEEK_CUR: Set position to current location plus offset
     *     SEEK_END: Set position to end-of-stream plus offset.
     *
     * @return void
     *
     * @link http://www.php.net/manual/en/function.fseek.php
     *
     * @throws \RuntimeException on failure.
     */
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
    }

    /**
     * Seek to the beginning of the stream.
     *
     * If the stream is not seekable, this method will raise an exception;
     * otherwise, it will perform a seek(0).
     *
     * @return void
     *
     * @see seek()
     *
     * @link http://www.php.net/manual/en/function.fseek.php
     *
     * @throws \RuntimeException on failure.
     */
    public function rewind(): void
    {
    }

    /**
     * Returns whether or not the stream is writable.
     *
     * @return bool
     */
    public function isWritable(): bool
    {
        return true;
    }

    /**
     * Write data to the stream.
     *
     * @param string $string The string that is to be written.
     *
     * @return int Returns the number of bytes written to the stream.
     *
     * @throws \RuntimeException on failure.
     */
    public function write(string $string): int
    {
        if ($this->outputActive) {
            echo $string;
        }
        return strlen($string);
    }

    /**
     * Returns whether or not the stream is readable.
     *
     * @return bool
     */
    public function isReadable(): bool
    {
        return true;
    }

    /**
     * Read data from the stream.
     *
     * @param int $length Read up to $length bytes from the object and return
     *     them. Fewer than $length bytes may be returned if underlying stream
     *     call returns fewer bytes.
     *
     * @return string Returns the data read from the stream, or an empty string
     *     if no bytes are available.
     *
     * @throws \RuntimeException if an error occurs.
     */
    public function read(int $length): string
    {
        return '';
    }

    /**
     * Returns the remaining contents in a string
     *
     * @return string
     *
     * @throws \RuntimeException if unable to read or an error occurs while
     *     reading.
     */
    public function getContents(): string
    {
        return '';
    }

    /**
     * Get stream metadata as an associative array or retrieve a specific key.
     *
     * The keys returned are identical to the keys returned from PHP's
     * stream_get_meta_data() function.
     *
     * @param string|null $key Specific metadata to retrieve.
     *
     * @return array|mixed|null Returns an associative array if no key is
     *     provided. Returns a specific key value if a key is provided and the
     *     value is found, or null if the key is not found.
     *
     * @link http://php.net/manual/en/function.stream-get-meta-data.php
     */
    public function getMetadata(?string $key = null)
    {
        return null;
    }
}
