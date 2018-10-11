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


    protected function makeClassificationLinkMap($map) {
        $link_entries = [];
        foreach ($map as $key => $value)
           array_push($link_entries, $this->makeClassificationLink($key, $value));
        return $link_entries;
    }
   

    public function getNestedIxtheoClassificationArray($translator) {
        $locale = $translator->getLocale();
        $translations = $translator->getAllMessages('default', $locale);
        $ixtheo_classes = array_filter($translations->getArrayCopy(), $this->matcher("/^ixtheo-/"),
                                       ARRAY_FILTER_USE_KEY);
        // Remove unneeded elements
        unset($ixtheo_classes['ixtheo-Unassigned']);
        $superclasses = array_filter($ixtheo_classes, $this->matcher("/^ixtheo-[A-Z]$/"),
                                     ARRAY_FILTER_USE_KEY);
        $subclasses = array_diff_key($ixtheo_classes, $superclasses);
        $list = [];
        foreach($superclasses as $key => $value) {
            $subcategories_map = array_filter($subclasses, $this->matcher("/^$key/"), ARRAY_FILTER_USE_KEY);
            array_push($list, $this->makeClassificationLink($key, $value),
                       $this->makeClassificationLinkMap($subcategories_map));
        }
        return $list;
    }
}
