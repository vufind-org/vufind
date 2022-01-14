<?php
namespace TueFind\RecordDriver\Feature;


trait MarcAdvancedTrait
{
    use \VuFind\RecordDriver\Feature\MarcAdvancedTrait  { getSeriesFromMARC as getVuFindSeriesFromMARC;
                                                  getSeries as getVuFindSeries;
    }

    public function getSubfieldsWithCustomSeparator($currentField, $subfields, $subfieldSeparatorMap = null) {
        // Start building a line of text for the current field
        $matches = '';

        // Loop through all subfields, collecting results that match the whitelist;
        // note that it is important to retain the original MARC order here!
        $allSubfields = $currentField->getSubfields();
        if (!empty($allSubfields)) {
            foreach ($allSubfields as $currentSubfield) {
                $code = $currentSubfield->getCode();
                if (in_array($code, $subfields)) {
                    $separator = !is_null($subfieldSeparatorMap) && isset($subfieldSeparatorMap[$code]) ?
                                 $subfieldSeparatorMap[$code] : ' ';
                    // Grab the current subfield value and act on it if it is
                    // non-empty:
                    $data = trim($currentSubfield->getData());
                    if (!empty($data)) {
                        $matches .= !empty($matches) ? $separator . $data : $data;
                    }
                }
            }
        }

        return $matches;
    }

    public function getSeries() {
       return $this->getVuFindSeries();
    }

    public function getSeriesFromMARC($fieldInfo) {
        $seriesSeparators = [ 'c' => ', ', 't' => ', ' ];
        $matches = [];

        // Loop through the field specification....
        foreach ($fieldInfo as $field => $subfields) {
            // Did we find any matching fields?
            $series = $this->getMarcRecord()->getFields($field);
            if (is_array($series)) {
                foreach ($series as $currentField) {
                    // Can we find a name using the specified subfield list?
                    $name = $this->getSubfieldsWithCustomSeparator($currentField, $subfields, $seriesSeparators);
                    if (isset($name)) {
                        $currentArray = ['name' => $name];

                        // Can we find a number in subfield v?  (Note that number is
                        // always in subfield v regardless of whether we are dealing
                        // with 440, 490, 800 or 830 -- hence the hard-coded array
                        // rather than another parameter in $fieldInfo).
                        $number
                            = $this->getSubfieldArray($currentField, ['v']);
                        if (isset($number[0])) {
                            $currentArray['number'] = $number[0];
                        }

                        // Save the current match:
                        $matches[] = $currentArray;
                    }
                }
            }
        }
        return $matches;
    }


    public function makeDescriptionLinksClickable($description) {
        // c.f. https://stackoverflow.com/questions/5341168/best-way-to-make-links-clickable-in-block-of-text (211027)
        return preg_replace('!(((f|ht)tp(s)?://)[-a-zA-Zа-яА-Я()0-9@:%_+.~#?&;//=]+)!i', '<a href="$1" target="blank_">$1</a>', $description);
    }

}
