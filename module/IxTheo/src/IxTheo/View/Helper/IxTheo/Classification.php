<?php
namespace IxTheo\View\Helper\IxTheo;

class Classification extends \Zend\View\Helper\AbstractHelper
{
    /**
     * If we have an ixtheo notation facet, get the correct translated+escaped value.
     * Else just return the translated+escaped input value.
     *
     * @param string $title Facet title
     * @param string $value Facet value
     * @return string
     */
    public function getTranslatedValue($title, $value) {
        $transEsc = $this->getView()->plugin('transEsc');
        if (!$this->isIxTheoClassificationFacet($title))
            return $transEsc($value);
        else
            return $transEsc($this->getValueForTrans($value));
    }


    /**
     * Return [Unassigned] or prefixed value with "ixtheo-"
     *
     * @param string $value Facet value
     *
     * @return string
     */
    public function getValueForTrans($value)
    {
        if ($this->isValueUnassigned($value))
            return $value;

        return "ixtheo-" . $value;
    }


    /**
     * Check if the facet is the IxTheoNotation Facet
     *
     * @param string $title Facet title
     *
     * @return boolean
     */
    public function isIxTheoClassificationFacet($title)
    {
        return $title === 'IxTheo Classification' ||
               $title === 'RelBib Classification';
    }


    /**
     * Check if a facet value is unassigned
     *
     * @param string $value Facet content
     *
     * @return boolean
     */
    public function isValueUnassigned($value)
    {
        $transEsc = $this->getView()->plugin('transEsc');
        return ($value == '[Unassigned]') || ($value == $transEsc('[Unassigned]'));
    }
}
