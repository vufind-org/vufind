<?php

namespace IxTheo\View\Helper\Root;

class Citation extends \VuFind\View\Helper\Root\Citation
{
    /**
     * Store a record driver object and return this object so that the appropriate
     * template can be rendered.
     *
     * @param \VuFind\RecordDriver\Base $driver Record driver object.
     *
     * @return Citation
     */
    public function __invoke($driver)
    {
        // Build author list:
        $authors = [];
        $primary = $driver->tryMethod('getPrimaryAuthor');
        $primary = $driver->stripTrailingDates($primary);
        if (empty($primary)) {
            $primary = $driver->tryMethod('getCorporateAuthor');
        }
        if (!empty($primary)) {
            $authors[] = $primary;
        }

        $secondary = $driver->tryMethod('getSecondaryAuthors');
        if (is_array($secondary) && !empty($secondary)) {
            foreach ($secondary as &$single) {
               $single = $driver->stripTrailingDates($single);
            }
            unset($single);
            $authors = array_unique(array_merge($authors, $secondary));
        }

        // Get best available title details:
        $title = $driver->tryMethod('getShortTitle');
        $subtitle = $driver->tryMethod('getSubtitle');
        if (empty($title)) {
            $title = $driver->tryMethod('getTitle');
        }
        if (empty($title)) {
            $title = $driver->getBreadcrumb();
        }
        // Find subtitle in title if they're not separated:
        if (empty($subtitle) && strstr($title, ':')) {
            list($title, $subtitle) = explode(':', $title, 2);
        }

        // Extract the additional details from the record driver:
        $publishers = $driver->tryMethod('getPublishers');
        $pubDates = $driver->tryMethod('getPublicationDates');
        $pubPlaces = $driver->tryMethod('getPlacesOfPublication');
        $edition = $driver->tryMethod('getEdition');

        // Store everything:
        $this->driver = $driver;
        $this->details = [
            'authors' => $this->prepareAuthors($authors),
            'title' => trim($title), 'subtitle' => trim($subtitle),
            'pubPlace' => isset($pubPlaces[0]) ? $pubPlaces[0] : null,
            'pubName' => isset($publishers[0]) ? $publishers[0] : null,
            'pubDate' => isset($pubDates[0]) ? $pubDates[0] : null,
            'edition' => empty($edition) ? [] : [$edition],
            'journal' => $driver->tryMethod('getContainerTitle')
        ];

        return $this;
    }
}
