<?php

namespace TueFind\RecordDriver;

class SolrAuthMarc extends SolrAuthDefault {

    const EXTERNAL_REFERENCES_DATABASES = ['GND' , 'ISNI', 'LOC', 'ORCID', 'VIAF', 'Wikidata', 'Wikipedia'];

    /**
     * Our metadata is in german, but VuFind requires english keys for translation.
     * Since we do not have many of these cases, we map them by hand.
     */
    const LABEL_TRANSLATION_MAP = [
        // Places
        'Geburtsort'    => 'Place of birth',
        'Sterbeort'     => 'Place of death',
        'Wirkungsort'   => 'Place of activity',

        // Personal Relations
        'Ehemann'       => 'Husband',
        'Ehefrau'       => 'Wife',

        'Sohn'          => 'Son',
        'Tochter'       => 'Daughter',

        'Vater'         => 'Father',
        'Mutter'        => 'Mother',
        'Großvater'     => 'Grandfather',
        'Großmutter'    => 'Grandmother',

        'Bruder'        => 'Brother',
        'Schwester'     => 'Sister',

        'Schwager'      => 'Brother-in-law',
        'Schwägerin'    => 'Sister-in-law',

        'Cousin'        => 'Cousin',
        'Cousine'       => 'Cousin',
    ];

    /**
     * Get List of all beacon references.
     * @return [['title', 'url']]
     */
    public function getBeaconReferences(): array
    {
        $beacon_references = [];
        $beacon_fields = $this->getMarcRecord()->getFields('BEA');
        if (is_array($beacon_fields)) {
            foreach($beacon_fields as $beacon_field) {
                $name_subfield  = $beacon_field->getSubfield('a');
                $url_subfield   = $beacon_field->getSubfield('u');

                if ($name_subfield !== false && $url_subfield !== false)
                    $beacon_references[] = ['title' => $name_subfield->getData(),
                                            'url' => $url_subfield->getData()];
            }
        }
        return $beacon_references;
    }

    protected function getExternalReferencesFiltered(array $blacklist=[], array $whitelist=[]): array
    {
        $references = [];

        $fields = $this->getMarcRecord()->getFields('670');
        if (is_array($fields)) {
            foreach ($fields as $field) {
                $nameSubfield = $field->getSubfield('a');
                if ($nameSubfield === false)
                    continue;

                $name = $nameSubfield->getData();
                if (in_array($name, $blacklist) || (count($whitelist) > 0 && !in_array($nameSubfield->getData(), $whitelist)))
                    continue;

                $urlSubfield = $field->getSubfield('u');
                if ($urlSubfield !== false) {
                    $url = $urlSubfield->getData();
                    if ($name == 'Wikipedia')
                        $url = preg_replace('"&(oldid|diff)=[^&]+"', '', $url);

                    $references[] = ['title' => $name,
                                     'url' => $url];
                }
            }
        }

        return $references;
    }

    public function getBibliographicalReferences(): array
    {
        $references = [];

        $gndNumber = $this->getGNDNumber();
        if ($gndNumber != null)
            $references[] = ['title' => 'GND',
                             'url' => 'http://d-nb.info/gnd/' . urlencode($gndNumber)];

        $isnis = $this->getISNIs();
        foreach ($isnis as $isni) {
            $references[] = ['title' => 'ISNI',
                             'url' => 'https://isni.org/isni/' . urlencode(str_replace(' ', '', $isni))];
        }

        $lccn = $this->getLCCN();
        if ($lccn != null)
            $references[] = ['title' => 'LOC',
                             'url' => 'https://lccn.loc.gov/' . urlencode($lccn)];

        $orcids = $this->getORCIDs();
        foreach ($orcids as $orcid) {
            $references[] = ['title' => 'ORCID',
                             'url' => 'https://orcid.org/' . urlencode($orcid)];
        }

        $viafs = $this->getVIAFs();
        foreach ($viafs as $viaf) {
            $references[] = ['title' => 'VIAF',
                             'url' => 'https://viaf.org/viaf/' . urlencode($viaf)];
        }

        $wikidataId = $this->getWikidataId();
        if ($wikidataId != null)
            $references[] = ['title' => 'Wikidata',
                             'url' => 'https:////www.wikidata.org/wiki/' . urlencode($wikidataId)];

        $references = array_merge($references, $this->getExternalReferencesFiltered(/*blacklist=*/[], /*whitelist=*/['Wikipedia']));
        return $references;
    }

    public function getArchivedMaterial(): array
    {
        $references = $this->getExternalReferencesFiltered(/*blacklist=*/[], /*whitelist=*/['Archivportal-D', 'Kalliope']);
        $references = array_merge($references, $this->getBeaconReferences());
        return $references;
    }

    public function getExternalSubsystems(): array
    {
        // This needs to be overridden in IxTheo/KrimDok if subsystems are present
        return [];
    }

    protected function getLifeDates()
    {
        $lifeDates = ['birth' => null, 'death' => null];

        $fields = $this->getMarcRecord()->getFields('548');
        foreach ($fields as $field) {
            $typeSubfield = $field->getSubfield('4');
            if ($typeSubfield !== false && $typeSubfield->getData() == 'datx') {
                if (preg_match('"^(\d{1,2}\.\d{1,2}\.\d{1,4})-(\d{1,2}\.\d{1,2}\.\d{1,4})$"', $field->getSubfield('a')->getData(), $hits)) {
                    $lifeDates['birth'] = $hits[1];
                    $lifeDates['death'] = $hits[2];
                    break;
                }
            }
        }

        return $lifeDates;
    }

    protected function getLifePlaces()
    {
        $lifePlaces = ['birth' => null, 'death' => null];

        $fields = $this->getMarcRecord()->getFields('551');
        foreach ($fields as $field) {
            $typeSubfield = $field->getSubfield('4');
            if ($typeSubfield !== false) {
                switch($typeSubfield->getData()) {
                case 'ortg':
                    $lifePlaces['birth'] = $field->getSubfield('a')->getData() ?? null;
                    break;
                case 'orts':
                    $lifePlaces['death'] = $field->getSubfield('a')->getData() ?? null;
                    break;
                }

            }
        }
        return $lifePlaces;
    }

    /**
     * Get birth date or year if date is not set
     * @return string
     */
    public function getBirthDateOrYear()
    {
        return $this->getBirthDate() ?? $this->getBirthYear();
    }

    /**
     * Get exact birth date
     * @return string
     */
    public function getBirthDate()
    {
        $lifeDates = $this->getLifeDates();
        return $lifeDates['birth'] ?? null;
    }

    /**
     * Get birth place
     * @return string
     */
    public function getBirthPlace()
    {
        return $this->getLifePlaces()['birth'] ?? null;
    }

    /**
     * Get birth year
     * @return string
     */
    public function getBirthYear()
    {
        $pattern = '"^(\d+)(-?)(\d+)?$"';
        $values = $this->getFieldArray('100', ['d']);
        foreach ($values as $value) {
            if (preg_match($pattern, $value, $hits))
                return $hits[1];
        }
    }

    /**
     * Get death date or year if date is not set
     * @return string
     */
    public function getDeathDateOrYear()
    {
        return $this->getDeathDate() ?? $this->getDeathYear();
    }

    /**
     * Get exact death date
     * @return string
     */
    public function getDeathDate()
    {
        $lifeDates = $this->getLifeDates();
        return $lifeDates['death'] ?? null;
    }

    /**
     * Get death place
     * @return string
     */
    public function getDeathPlace()
    {
        return $this->getLifePlaces()['death'] ?? null;
    }

    /**
     * Get death year
     * @return string
     */
    public function getDeathYear()
    {
        $pattern = '"^(\d+)(-?)(\d+)?$"';
        $values = $this->getFieldArray('100', ['d']);
        foreach ($values as $value) {
            if (preg_match($pattern, $value, $hits) && isset($hits[3]))
                return $hits[3];
        }
    }

    /**
     * Get geographical relations from 551
     * @return [['name', 'type']]
     */
    public function getGeographicalRelations()
    {
        $locations = [];
        $fields = $this->getMarcRecord()->getFields('551');
        foreach ($fields as $field) {
            $locations[] = ['name' => $field->getSubfield('a')->getData(),
                            'type' => $this->translateLabel($field->getSubfield('i')->getData())];
        }

        $fields = $this->getMarcRecord()->getFields('043');
        foreach ($fields as $field) {
            foreach ($field->getSubfields('c') as $subfield) {
                $locations[] = ['name' => $subfield->getData(),
                                'type' => 'DIN-ISO-3166'];
            }
        }
        return $locations;
    }

    public function getMeetingName()
    {
        foreach ($this->getMarcRecord()->getFields('111') as $field) {
            $name = $field->getSubfield('a')->getData();

            $subfield_c = $field->getSubfield('c');
            $subfield_d = $field->getSubfield('d');
            $subfield_g = $field->getSubfield('g');

            if ($subfield_c != false || $subfield_g != false)
                $name .= '.';
            if ($subfield_g != false)
                $name .= ' ' . $subfield_g->getData();
            if ($subfield_c != false)
                $name .= ' ' . $subfield_c->getData();
            if ($subfield_d != false)
                $name .= ' (' . $subfield_d->getData() . ')';

            return $name;
        }

        return '';
    }

    /**
     * Get Name from 100a
     * @return string
     */
    public function getName()
    {
        foreach ($this->getMarcRecord()->getFields('100') as $field) {
            $aSubfield = $field->getSubfield('a');
            if ($aSubfield == false)
                continue;

            $name = $aSubfield->getData();

            $bSubfield = $field->getSubfield('b');
            if ($bSubfield != false)
                $name .= ' ' . $bSubfield->getData();
            return $name;
        }

        return '';
    }

    /**
     * Get multiple notations of the name
     * (e.g. for external searches like wikidata)
     * (e.g. "King, Martin Luther" => "Martin Luther King")
     */
    public function getNameAliases(): array
    {
        $names = [];
        $name = $this->getName();
        $names[] = $name;
        $alias = preg_replace('"^([^,]+)\s*,\s*([^,]+)$"', '\\2 \\1', $name);
        if ($alias != $name)
            $names[] = $alias;
        return $names;
    }

    /**
     * Get name variants as listed in MARC21 400a
     */
    public function getNameVariants(): array
    {
        $nameVariants = [];
        $fields = $this->getMarcRecord()->getFields('400|410|411', true);
        if (is_array($fields)) {
            foreach ($fields as $field) {
                $nameSubfield = $field->getSubfield('a');
                if ($nameSubfield !== false) {
                    $name = $nameSubfield->getData();

                    $numberSubfield = $field->getSubfield('b');
                    if ($numberSubfield !== false)
                        $name .= ' ' . $numberSubfield->getData();

                    $titleSubfield = $field->getSubfield('c');
                    if ($titleSubfield !== false)
                        $name .= ' ' . $titleSubfield->getData();

                    $nameVariants[] = $name;
                }
            }
        }

        sort($nameVariants);
        return $nameVariants;
    }

    public function getPersonalRelations(): array
    {
        $relations = [];

        $fields = $this->getMarcRecord()->getFields('500');
        if (is_array($fields)) {
            foreach ($fields as $field) {
                $nameSubfield = $field->getSubfield('a');

                if ($nameSubfield !== false) {
                    $relation = ['name' => $nameSubfield->getData()];

                    $idPrefixPattern = '/^\(DE-627\)/';
                    $idSubfield = $field->getSubfield('0', $idPrefixPattern);
                    if ($idSubfield !== false)
                        $relation['id'] = preg_replace($idPrefixPattern, '', $idSubfield->getData());

                    $typeSubfield = $field->getSubfield('9');
                    if ($typeSubfield !== false)
                        $relation['type'] = $this->translateLabel(preg_replace('/^v:/', '', $typeSubfield->getData()));

                    $relations[] = $relation;
                }
            }
        }

        return $relations;
    }

    /**
     * "Titles" does NOT mean title data from the biblio index,
     * it refers to the field 100c instead. Due to the MARC21 authority standard,
     * this subfield is named "Titles and other words associated with a name".
     * It may contain occupations like "Theologe", but also other attributes
     * like "Familie".
     */
    public function getPersonalTitles(): array
    {
        $personalTitles = [];
        $personalTitlesStrings = $this->getFieldArray('100', ['c']);
        foreach ($personalTitlesStrings as $personalTitlesString) {
            $personalTitlesArray = explode(',', $personalTitlesString);
            foreach ($personalTitlesArray as $personalTitle)
                $personalTitles[] = trim($personalTitle);
        }
        return $personalTitles;
    }

    public function getCorporateRelations(): array
    {
        $relations = [];

        $fields = $this->getMarcRecord()->getFields('510');
        if (is_array($fields)) {
            foreach ($fields as $field) {
                $nameSubfield = $field->getSubfield('a');
                if ($nameSubfield !== false) {
                    $relation = ['name' => $nameSubfield->getData()];

                    $addSubfield = $field->getSubfield('b');
                    if ($addSubfield !== false)
                        $relation['institution'] = $addSubfield->getData();

                    $locationSubfield = $field->getSubfield('g');
                    if ($locationSubfield !== false)
                        $relation['location'] = $locationSubfield->getData();

                    $idPrefixPattern = '/^\(DE-627\)/';
                    $idSubfield = $field->getSubfield('0', $idPrefixPattern);
                    if ($idSubfield !== false)
                        $relation['id'] = preg_replace($idPrefixPattern, '', $idSubfield->getData());

                    $localSubfields = $field->getSubfields('9');
                    foreach ($localSubfields as $localSubfield) {
                        if (preg_match('"^(.):(.+)"', $localSubfield->getData(), $matches)) {
                            if ($matches[1] == 'Z')
                                $relation['timespan'] = $matches[2];
                            else if ($matches[1] == 'v')
                                $relation['type'] = $matches[2];
                        }
                    }

                    $relations[] = $relation;
                }
            }
        }

        return $relations;
    }

    public function getTimespans(): array
    {
        $timespans = [];
        $fields = $this->getMarcRecord()->getFields('548');
        if (is_array($fields)) {
            foreach ($fields as $field) {
                $subfield_a = $field->getSubfield('a');
                if ($subfield_a !== false)
                    $timespans[] = $subfield_a->getData();
            }
        }
        return $timespans;
    }

    public function getTitle()
    {
        if ($this->isMeeting())
            return $this->getMeetingName();
        return parent::getTitle();
    }

    public function isFamily(): bool
    {
        $fields = $this->getMarcRecord()->getFields('079');
        if (is_array($fields)) {
            foreach ($fields as $field) {
                $typeSubfield = $field->getSubfield('v');
                if ($typeSubfield != false && $typeSubfield->getData() == 'pif')
                    return true;
            }
        }
        return false;
    }

    public function isMeeting(): bool
    {
        return $this->getType() == 'meeting';
    }

    /**
     * This function is used to detect "Tn"-sets, which are similar to persons.
     *
     * @return bool
     */
    public function isName(): bool
    {
        $fields = $this->getMarcRecord()->getFields('079');
        if (is_array($fields)) {
            foreach ($fields as $field) {
                $typeSubfield = $field->getSubfield('b');
                if ($typeSubfield != false && $typeSubfield->getData() == 'n')
                    return true;
            }
        }
        return false;
    }

    /**
     * This just checks whether the main type is "person".
     * Be careful => if the main type is "person", it can e.g. still be sub-type "name", "family" or others.
     *
     * @return bool
     */
    public function isPerson(): bool
    {
        return $this->getType() == 'person';
    }

    protected function translateLabel($label): string
    {
        return self::LABEL_TRANSLATION_MAP[$label] ?? $label;
    }
}
