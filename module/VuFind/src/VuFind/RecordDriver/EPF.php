<?php

namespace VuFind\RecordDriver;

class EPF extends EDS
{

    public function getUniqueId() 
    {
        return $this->fields['Header']['PublicationId'];
    }

}