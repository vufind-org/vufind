package org.vufind.index;
/**
 * Publisher indexing routines.
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
import org.marc4j.marc.VariableField;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Subfield;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.Set;

/**
 * Publisher indexing routines.
 */
public class PublisherTools
{
    /**
     * Get all available publishers from the record.
     *
     * @param  record MARC record
     * @return set of publishers
     */
    public Set<String> getPublishers(final Record record) {
        Set<String> publishers = new LinkedHashSet<String>();

        // First check old-style 260b name:
        List<VariableField> list260 = record.getVariableFields("260");
        for (VariableField vf : list260)
        {
            DataField df = (DataField) vf;
            String currentString = "";
            for (Subfield current : df.getSubfields('b')) {
                currentString = currentString.trim().concat(" " + current.getData()).trim();
            }
            if (currentString.length() > 0) {
                publishers.add(currentString);
            }
        }

        // Now track down relevant RDA-style 264b names; we only care about
        // copyright and publication names (and ignore copyright names if
        // publication names are present).
        Set<String> pubNames = new LinkedHashSet<String>();
        Set<String> copyNames = new LinkedHashSet<String>();
        List<VariableField> list264 = record.getVariableFields("264");
        for (VariableField vf : list264)
        {
            DataField df = (DataField) vf;
            String currentString = "";
            for (Subfield current : df.getSubfields('b')) {
                currentString = currentString.trim().concat(" " + current.getData()).trim();
            }
            if (currentString.length() > 0) {
                char ind2 = df.getIndicator2();
                switch (ind2)
                {
                    case '1':
                        pubNames.add(currentString);
                        break;
                    case '4':
                        copyNames.add(currentString);
                        break;
                }
            }
        }
        if (pubNames.size() > 0) {
            publishers.addAll(pubNames);
        } else if (copyNames.size() > 0) {
            publishers.addAll(copyNames);
        }

        return publishers;
    }
}