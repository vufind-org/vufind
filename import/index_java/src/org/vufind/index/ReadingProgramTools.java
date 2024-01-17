package org.vufind.index;
/**
 * Reading program logic courtesy of Chanel Wheeler
 *
 * Example usage:
 *
 * #### In marc_local.properties, insert this:
 * arLvel = custom, getARLevel, (pattern_map.level)
 * rcLevel = custom, getRCLevel, (pattern_map.level)
 * pattern_map.level.pattern_0 = ([0-9]\\.[0-9]).*=>$1
 *
 * #### In solr/vufind/biblio/conf/schema.xml (I'm not aware of any way to localize this),
 * #### add this in the <types> section:
 * <fieldType name="tfloat" class="solr.TrieFloatField" precisionStep="8" positionIncrementGap="0"/>
 *
 * #### In solr/vufind/biblio/conf/schema.xml, add this in the <fields> section
 * <field name="arLevel" type="tfloat" indexed="true" stored="true"/>
 * <field name="rcLevel" type="tfloat" indexed="true" stored="true"/>
 *
 * Copyright (C) Villanova University 2017.
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
 */

import org.marc4j.marc.Record;
import org.marc4j.marc.DataField;
import org.marc4j.marc.VariableField;

/**
 * Reading program logic courtesy of Chanel Wheeler
 */
public class ReadingProgramTools
{
    /**
     * Get reading level for Accelerated Reader items
     *
     * @param  record
     * @return AR level
     */
     public String getARLevel(Record record) {
        for (VariableField variableField : record.getVariableFields("526")) {
            DataField rp = (DataField) variableField;
            if (rp.getSubfield('a') != null){
                if (rp.getSubfield('a').getData().toLowerCase().contains("accelerated reader")) {
                    return rp.getSubfield('c').getData();
                }
            }
        }
        return null;
     }

     /**
     * Get reading level for Reading Counts items
     *
     * @param  record
     * @return RC level
     */
     public String getRCLevel(Record record) {
        for (VariableField variableField : record.getVariableFields("526")) {
            DataField rp = (DataField) variableField;
            if (rp.getSubfield('a') != null){
                if (rp.getSubfield('a').getData().toLowerCase().contains("reading counts")) {
                    return rp.getSubfield('c').getData();
                }
            }
        }
        return null;
    }
}