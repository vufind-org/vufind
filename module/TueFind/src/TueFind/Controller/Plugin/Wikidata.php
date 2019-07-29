<?php

namespace TueFind\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class Wikidata extends AbstractPlugin {
    const API_URL = 'https://www.wikidata.org/w/api.php?format=json';
    const CACHE_DIR = '/tmp/wikidata';
    const CACHE_LIFETIME = 3600;

    public function searchAndGetEntities($search, $language) {
        $entities = $this->searchEntities($search, $language);
        $ids = [];
        foreach($entities->search as $entity) {
            $ids[] = $entity->id;
        }
        return $this->getEntities($ids);
    }

    public function searchEntities($search, $language) {
        $url = self::API_URL . '&action=wbsearchentities&search=' . urlencode($search) . '&language=' . $language;
        return $this->getCachedUrlContents($url, true);
    }

    public function getEntities($ids) {
        $url = self::API_URL . '&action=wbgetentities&ids=' . urlencode(implode('|', $ids));
        return $this->getCachedUrlContents($url, true);
    }

    public function getImage($filename) {
        $properties = $this->getImageProperties($filename);
        $properties['image'] = $this->getCachedUrlContents($properties['url']);
        return $properties;
    }

    public function getImageProperties($filename) {
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
        else if (!preg_match('"^CC "i', $license))
            throw new Exception('Image uses a non-CC-license (' . $license . '): ' . $filename);

        return ['url' => $imageUrl, 'mime' => $mime, 'license' => $license];
    }

    /**
     * Resolve URL from cache if possible
     *
     * @param string $url
     * @return json
     * @throws \Exception
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