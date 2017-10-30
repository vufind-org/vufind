<?php
/**
 * Cache Controller
 *
 * PHP Version 5
 *
 * Copyright (C) The National Library of Finland 2017.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA    02111-1307    USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Controller;

use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Loads cached files
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class CacheController extends \VuFind\Controller\AbstractBase
{
    /**
     * Finna cache table
     *
     * @var \Finna\Db\Table\FinnaCache
     */
    protected $finnaCache;

    /**
     * Theme info
     *
     * @var \VuFindTheme\ThemeInfo
     */
    protected $themeInfo;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface    $sm         Service manager
     * @param \Finna\Db\Table\FinnaCache $finnaCache Finna cache table
     * @param \VuFindTheme\ThemeInfo     $themeInfo  Theme info
     */
    public function __construct(ServiceLocatorInterface $sm,
        \Finna\Db\Table\FinnaCache $finnaCache, \VuFindTheme\ThemeInfo $themeInfo
    ) {
        $this->finnaCache = $finnaCache;
        $this->themeInfo = $themeInfo;

        parent::__construct($sm);
    }

    /**
     * Default action if none provided
     *
     * @return mixed
     */
    public function fileAction()
    {
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $filename = $this->params()->fromRoute('file');
        $cachePath = $this->themeInfo->getBaseDir()
            . "/../local/cache/public/$filename";

        $data = false;
        $mtime = false;
        if (file_exists($cachePath)) {
            $data = file_get_contents($cachePath);
            if (false !== $data) {
                $mtime = filemtime($cachePath);
            }
        } else {
            $row = $this->finnaCache->getByResourceId($filename);
            if (false !== $row) {
                $data = $row->data;
                $mtime = $row->mtime;
                if (false === file_put_contents($cachePath, $data)) {
                    throw new \Exception("Could not write to file $cachePath");
                }
                if (false === touch($cachePath, $mtime)) {
                    throw new \Exception("Could not touch timestamp of $cachePath");
                }
            }
        }
        if (false !== $data && false !== $mtime) {
            // Create ETag
            $etag = sprintf(
                '%x-%s',
                strlen($data),
                base_convert(str_pad($mtime, 16, '0'), 10, 16)
            );
            // Check for If-None-Match (ETag)
            $requestEtag = $this->getRequest()->getHeaders()
                ->get('If-None-Match');
            if ($requestEtag && $etag === $requestEtag->getFieldValue()) {
                $response->setStatusCode(304);
                return $response;
            }
            // Check for If-Modified-Since
            $ifModifiedSince = $this->getRequest()->getHeaders()
                ->get('if-Modified-Since');
            if ($ifModifiedSince
                && $mtime <= strtotime($ifModifiedSince->getFieldValue())
            ) {
                $response->setStatusCode(304);
                return $response;
            }
            $headers->addHeaderLine(
                'Content-type',
                $this->getContentType($filename)
            );
            $headers->addHeaderLine(
                'Last-Modified',
                date(DATE_RFC822, $mtime)
            );
            $headers->addHeaderLine('Etag', $etag);
            $response->setContent($data);
            return $response;
        }

        $response->setStatusCode(404);
        $response->setContent('404 Not Found');
        return $response;
    }

    /**
     * Get content type from file extension
     *
     * @param string $filename File name
     *
     * @return string
     */
    protected function getContentType($filename)
    {
        if ('.js' === substr($filename, -3)) {
            return 'application/javascript';
        }
        if ('.css' === substr($filename, -4)) {
            return 'text/css';
        }
        return 'application/octet-stream';
    }
}
