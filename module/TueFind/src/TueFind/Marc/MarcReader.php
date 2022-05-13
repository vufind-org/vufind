<?php

namespace TueFind\Marc;

class MarcReader extends \VuFind\Marc\MarcReader
{
    public function getFieldsDelimiter($spec, $delimiter='|') : array
    {
        $matches = array();

        $listOfTegs = explode($delimiter, $spec);

        if(!empty($listOfTegs)) {
            foreach($listOfTegs as $oneTeg) {
                // Okay, we're actually looking for something specific
                $allDatafilds = $this->getFields($oneTeg);

                if(!empty($allDatafilds)) {
                    $matches[] = $allDatafilds;
                }
            }
        }

        return $matches;
    }
}
