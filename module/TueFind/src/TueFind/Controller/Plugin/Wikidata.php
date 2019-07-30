<?php

namespace TueFind\Controller\Plugin;


use Zend\Mvc\Controller\Plugin\AbstractPlugin;


/**
 * Class implementing API & utility functions for Wikidata.org
 * For documentation, see: https://www.wikidata.org/w/api.php
 */
class Wikidata extends AbstractPlugin {
    const API_URL = 'https://www.wikidata.org/w/api.php?format=json';
    const CACHE_DIR = '/tmp/wikidata';
    const CACHE_LIFETIME = 3600;

    /**
     * Search for entities and get metadata of all found entities
     * (needs multiple API calls)
     *
     * @param type $search
     * @param type $language
     * @return object
     */
    public function searchAndGetEntities($search, $language) {
        $entities = $this->searchEntities($search, $language);
        $ids = [];
        foreach($entities->search as $entity) {
            $ids[] = $entity->id;
        }
        return $this->getEntities($ids);
    }

    /**
     * Search for entities and return a short metadata array
     * (wrapper for "wbsearchentities")
     *
     * @param string $search
     * @param string $language
     * @return object
     */
    public function searchEntities($search, $language) {
        $url = self::API_URL . '&action=wbsearchentities&search=' . urlencode($search) . '&language=' . $language;
        return $this->getCachedUrlContents($url, true);
    }

    /**
     * Get detailed metadata for objects with given IDs
     * (wrapper for "wbgetentities")
     *
     * @param array $ids
     * @return object
     */
    public function getEntities($ids) {
        $url = self::API_URL . '&action=wbgetentities&ids=' . urlencode(implode('|', $ids));
        return $this->getCachedUrlContents($url, true);
    }

    /**
     * Get image (binary contents + metadata) by a given unique filename
     *
     * @param string $filename
     * @return array
     */
    public function getImage($filename) {
        $metadata = $this->getImageMetadata($filename);
        $metadata['image'] = $this->getCachedUrlContents($properties['url']);
        return $metadata;
    }

    /**
     * Get image metadata by a given unique filename
     *
     * @param string $filename
     * @return array
     */
    public function getImageMetadata($filename) {
        $lookupUrl = self::API_URL . '&action=query&prop=imageinfo&iiprop=url|mime|extmetadata&titles=File:' . urlencode($filename);
        $lookupResult = $this->getCachedUrlContents($lookupUrl, true);
        $subindex = '-1';

        $imageInfo = $lookupResult->query->pages->$subindex->imageinfo[0] ?? null;

        $imageUrl = $imageInfo->url ?? null;
        if ($imageUrl === null)
            throw new \Exception('Image URL could not be found for: ' . $filename);

        $mime = $imageInfo->mime;
        if ($mime === null)
            throw new \Exception('Mime type could not be found for: ' . $filename);

        $license = $imageInfo->extmetadata->LicenseShortName->value ?? null;
        if ($license === null)
            throw new \Exception('License could not be found for: ' . $filename);

        if (!preg_match('"^Public domain|CC "i', $license))
            throw new \Exception('Image not usable due to license restrictions (' . $license . '): ' . $filename);

        $licenseUrl = $imageInfo->extmetadata->LicenseUrl->value ?? null;
        if (!preg_match('"^Public domain$"i', $license) && $licenseUrl === null)
            throw new \Exception('License URL could not be found for: ' . $filename);

        $artist = $imageInfo->extmetadata->Artist->value ?? null;
        if ($artist === null)
            throw new \Exception('Artist could not be found for: ' . $filename);

        return ['url' => $imageUrl,
                'mime' => $mime,
                'license' => $license,
                'licenseUrl' => $licenseUrl,
                'artist' => $artist];
    }

    /**
     * Resolve URL from cache if possible
     *
     * @param string $url
     * @return json
     */
    protected function getCachedUrlContents($url, $decodeJson=false) {
        if (!is_dir(self::CACHE_DIR)) mkdir(self::CACHE_DIR);
        $cachedFile = self::CACHE_DIR . '/' . md5($url);

        if (is_file($cachedFile)) {
            if (filemtime($cachedFile) + self::CACHE_LIFETIME > time()) {
                $contents = file_get_contents($cachedFile);
                if ($decodeJson)
                    $contents = json_decode($contents);
                return $contents;
            }
        }

        $contents = file_get_contents($url);
        if (!$contents)
            throw new \Exception('Could not resolve URL: ' + $url);

        $contentsString = $contents;
        if ($decodeJson) {
            $contents = json_decode($contents);
            if (!$contents)
                throw new \Exception('Invalid JSON returned from URL: ' + $url);
        }

        file_put_contents($cachedFile, $contentsString);

        return $contents;
    }
}