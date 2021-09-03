<?php
/**
 * MARCXML format support class.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  MARC
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
namespace VuFind\Marc\Serialization;

/**
 * MARCXML format support class.
 *
 * @category VuFind
 * @package  MARC
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class MarcXml implements SerializationInterface
{
    /**
     * Check if this class can parse the given MARC string
     *
     * @param string $marc MARC
     *
     * @return bool
     */
    public static function canParse(string $marc): bool
    {
        // A pretty naïve check, but it's enough to tell the different formats apart
        return strncmp($marc, '<', 1) === 0;
    }

    /**
     * Parse MARCXML string
     *
     * @param string $marc MARCXML
     *
     * @throws Exception
     * @return array
     */
    public static function fromString(string $marc): array
    {
        $xmlHead = '<?xml version';
        if (strcasecmp(substr($marc, 0, strlen($xmlHead)), $xmlHead) === 0) {
            $decl = substr($marc, 0, strpos($marc, '?>'));
            if (strstr($decl, 'encoding') === false) {
                $marc = $decl . ' encoding="utf-8"' . substr($marc, strlen($decl));
            }
        } else {
            $marc = '<?xml version="1.0" encoding="utf-8"?>' . "\n\n$marc";
        }
        $xml = static::loadXML($marc);

        // Move to the record element if we were given a collection
        if ($xml->record) {
            $xml = $xml->record;
        }

        $leader = isset($xml->leader) ? (string)$xml->leader[0] : '';
        $fields = [];

        foreach ($xml->controlfield as $field) {
            $tag = (string)$field['tag'];
            $fields[$tag][] = (string)$field;
        }

        foreach ($xml->datafield as $field) {
            $newField = [
                'i1' => str_pad((string)$field['ind1'], 1),
                'i2' => str_pad((string)$field['ind2'], 1)
            ];
            foreach ($field->subfield as $subfield) {
                $newField['s'][] = [(string)$subfield['code'] => (string)$subfield];
            }
            $fields[(string)$field['tag']][] = $newField;
        }

        return [$leader, $fields];
    }

    /**
     * Convert record to a MARCXML string
     *
     * @param string $leader Leader
     * @param array  $fields Record fields
     *
     * @return string
     */
    public static function toString(string $leader, array $fields): string
    {
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElementNs(null, 'collection', "http://www.loc.gov/MARC21/slim");
        $xml->startElement('record');
        if ($leader) {
            $xml->writeElement('leader', $leader);
        }

        foreach ($fields as $tag => $fields) {
            foreach ($fields as $data) {
                if (!is_array($data)) {
                    $xml->startElement('controlfield');
                    $xml->writeAttribute('tag', $tag);
                    $xml->text($data);
                    $xml->endElement();
                } else {
                    $xml->startElement('datafield');
                    $xml->writeAttribute('tag', $tag);
                    $xml->writeAttribute('ind1', $data['i1']);
                    $xml->writeAttribute('ind2', $data['i2']);
                    if (isset($data['s'])) {
                        foreach ($data['s'] as $subfield) {
                            $subfieldData = current($subfield);
                            $subfieldCode = key($subfield);
                            if ($subfieldData == '') {
                                continue;
                            }
                            $xml->startElement('subfield');
                            $xml->writeAttribute('code', $subfieldCode);
                            $xml->text($subfieldData);
                            $xml->endElement();
                        }
                    }
                    $xml->endElement();
                }
            }
        }
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory(true);
    }

    /**
     * Load XML into SimpleXMLElement
     *
     * @param string $xml XML
     *
     * @throws Exception
     * @return \SimpleXMLElement
     */
    protected static function loadXML(string $xml): \SimpleXMLElement
    {
        $saveUseErrors = libxml_use_internal_errors(true);
        try {
            libxml_clear_errors();
            $doc = \simplexml_load_string(
                $xml,
                \SimpleXMLElement::class,
                LIBXML_COMPACT
            );
            if (false === $doc) {
                $errors = libxml_get_errors();
                $messageParts = [];
                foreach ($errors as $error) {
                    $messageParts[] = '[' . $error->line . ':' . $error->column
                        . '] Error ' . $error->code . ': ' . $error->message;
                }
                throw new \Exception(implode("\n", $messageParts));
            }
            libxml_use_internal_errors($saveUseErrors);
            return $doc;
        } catch (\Exception $e) {
            libxml_use_internal_errors($saveUseErrors);
            throw $e;
        }
    }
}
