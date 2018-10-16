<?php

namespace IxTheo\View\Helper\Root;

use Zend\ServiceManager\ServiceManager;

/**
 * Some IxTheo specific struff
 */
class IxTheo extends \Zend\View\Helper\AbstractHelper
{
    protected $sm;

    public function __construct(ServiceManager $sm) {
        $this->sm = $sm;
    }


    protected function matcher($regex) {
        return function ($element) use ($regex) {
            return preg_match($regex, $element);
        };
    }


    protected function makeClassificationLink($key, $value) {
        return '<a href="/classification/' . preg_replace("/^ixtheo-/", "", $key) . '" target="_blank">' . $value . "</a>";
    }


    // Do not produce links for two letter categories that have further subcategories
    // since these are not present in browsing and faceting
    protected function makeClassificationOutputItem($key, $value, $has_subitems) {
        if ($has_subitems && preg_match("/^ixtheo-[A-Z][A-Z]$/", $key))
            return $value;
        else
            return $this->makeClassificationLink($key, $value);
    }


    protected function makeClassificationLinkMap($map, $base_regex) {
        $link_entries = [];
        $items = array_filter($map, $this->matcher("/^" . $base_regex . "$/"), ARRAY_FILTER_USE_KEY);
        foreach ($items as $key => $value) {
            $new_base_regex = $key . "[A-Z]";
            $submap = array_filter($map, $this->matcher("/^" . $new_base_regex . "/"), ARRAY_FILTER_USE_KEY);
            array_push($link_entries, $this->makeClassificationOutputItem($key, $value, !empty($submap)));
            if (!empty($submap))
                array_push($link_entries, $this->makeClassificationLinkMap($submap, $new_base_regex));
        }
        return $link_entries;
    }


    public function getNestedIxtheoClassificationArray($translator) {
        $locale = $translator->getLocale();
        $translations = $translator->getAllMessages('default', $locale);
        $ixtheo_classes = array_filter($translations->getArrayCopy(), $this->matcher("/^ixtheo-/"),
                                       ARRAY_FILTER_USE_KEY);
        // Remove unneeded elements
        unset($ixtheo_classes['ixtheo-[Unassigned]']);
        $list = $this->makeClassificationLinkMap($ixtheo_classes, "ixtheo-[A-Z]");
        return $list;
    }
}
