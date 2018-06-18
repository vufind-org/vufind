package org.vufind.index;
/**
 * Format determination logic.
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
import org.marc4j.marc.ControlField;
import org.marc4j.marc.DataField;
import org.marc4j.marc.VariableField;
import java.util.Iterator;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.Set;

/**
 * Format determination logic.
 */
public class FormatCalculator
{
    /**
     * Determine Record Format(s)
     *
     * @param  record MARC record
     * @return set of record formats
     */
    public Set<String> getFormat(final Record record){
        Set<String> result = new LinkedHashSet<String>();
        String leader = record.getLeader().toString();
        char leaderBit;
        ControlField fixedField = (ControlField) record.getVariableField("008");
        DataField title = (DataField) record.getVariableField("245");
        String formatString;
        char formatCode = ' ';
        char formatCode2 = ' ';
        char formatCode4 = ' ';

        // check if there's an h in the 245
        if (title != null) {
            if (title.getSubfield('h') != null){
                if (title.getSubfield('h').getData().toLowerCase().contains("[electronic resource]")) {
                    result.add("Electronic");
                    return result;
                }
            }
        }

        // check the 007 - this is a repeating field
        List<VariableField> fields = record.getVariableFields("007");
        Iterator<VariableField> fieldsIter = fields.iterator();
        if (fields != null) {
            // TODO: update loop to for(:) syntax, but problem with type casting.
            ControlField formatField;
            while(fieldsIter.hasNext()) {
                formatField = (ControlField) fieldsIter.next();
                formatString = formatField.getData().toUpperCase();
                formatCode = formatString.length() > 0 ? formatString.charAt(0) : ' ';
                formatCode2 = formatString.length() > 1 ? formatString.charAt(1) : ' ';
                formatCode4 = formatString.length() > 4 ? formatString.charAt(4) : ' ';
                switch (formatCode) {
                    case 'A':
                        switch(formatCode2) {
                            case 'D':
                                result.add("Atlas");
                                break;
                            default:
                                result.add("Map");
                                break;
                        }
                        break;
                    case 'C':
                        switch(formatCode2) {
                            case 'A':
                                result.add("TapeCartridge");
                                break;
                            case 'B':
                                result.add("ChipCartridge");
                                break;
                            case 'C':
                                result.add("DiscCartridge");
                                break;
                            case 'F':
                                result.add("TapeCassette");
                                break;
                            case 'H':
                                result.add("TapeReel");
                                break;
                            case 'J':
                                result.add("FloppyDisk");
                                break;
                            case 'M':
                            case 'O':
                                result.add("CDROM");
                                break;
                            case 'R':
                                // Do not return - this will cause anything with an
                                // 856 field to be labeled as "Electronic"
                                break;
                            default:
                                result.add("Software");
                                break;
                        }
                        break;
                    case 'D':
                        result.add("Globe");
                        break;
                    case 'F':
                        result.add("Braille");
                        break;
                    case 'G':
                        switch(formatCode2) {
                            case 'C':
                            case 'D':
                                result.add("Filmstrip");
                                break;
                            case 'T':
                                result.add("Transparency");
                                break;
                            default:
                                result.add("Slide");
                                break;
                        }
                        break;
                    case 'H':
                        result.add("Microfilm");
                        break;
                    case 'K':
                        switch(formatCode2) {
                            case 'C':
                                result.add("Collage");
                                break;
                            case 'D':
                                result.add("Drawing");
                                break;
                            case 'E':
                                result.add("Painting");
                                break;
                            case 'F':
                                result.add("Print");
                                break;
                            case 'G':
                                result.add("Photonegative");
                                break;
                            case 'J':
                                result.add("Print");
                                break;
                            case 'L':
                                result.add("Drawing");
                                break;
                            case 'O':
                                result.add("FlashCard");
                                break;
                            case 'N':
                                result.add("Chart");
                                break;
                            default:
                                result.add("Photo");
                                break;
                        }
                        break;
                    case 'M':
                        switch(formatCode2) {
                            case 'F':
                                result.add("VideoCassette");
                                break;
                            case 'R':
                                result.add("Filmstrip");
                                break;
                            default:
                                result.add("MotionPicture");
                                break;
                        }
                        break;
                    case 'O':
                        result.add("Kit");
                        break;
                    case 'Q':
                        result.add("MusicalScore");
                        break;
                    case 'R':
                        result.add("SensorImage");
                        break;
                    case 'S':
                        switch(formatCode2) {
                            case 'D':
                                result.add("SoundDisc");
                                break;
                            case 'S':
                                result.add("SoundCassette");
                                break;
                            default:
                                result.add("SoundRecording");
                                break;
                        }
                        break;
                    case 'V':
                        switch(formatCode2) {
                            case 'C':
                                result.add("VideoCartridge");
                                break;
                            case 'D':
                                switch(formatCode4) {
                                    case 'S':
                                        result.add("BRDisc");
                                        break;
                                    case 'V':
                                    default:
                                        result.add("VideoDisc");
                                        break;
                                }
                                break;
                            case 'F':
                                result.add("VideoCassette");
                                break;
                            case 'R':
                                result.add("VideoReel");
                                break;
                            default:
                                result.add("Video");
                                break;
                        }
                        break;
                }
            }
            if (!result.isEmpty()) {
                return result;
            }
        }

        // check the Leader at position 6
        leaderBit = leader.charAt(6);
        switch (Character.toUpperCase(leaderBit)) {
            case 'C':
            case 'D':
                result.add("MusicalScore");
                break;
            case 'E':
            case 'F':
                result.add("Map");
                break;
            case 'G':
                result.add("Slide");
                break;
            case 'I':
                result.add("SoundRecording");
                break;
            case 'J':
                result.add("MusicRecording");
                break;
            case 'K':
                result.add("Photo");
                break;
            case 'M':
                result.add("Electronic");
                break;
            case 'O':
            case 'P':
                result.add("Kit");
                break;
            case 'R':
                result.add("PhysicalObject");
                break;
            case 'T':
                result.add("Manuscript");
                break;
        }
        if (!result.isEmpty()) {
            return result;
        }

        // check the Leader at position 7
        leaderBit = leader.charAt(7);
        switch (Character.toUpperCase(leaderBit)) {
            // Monograph
            case 'M':
                if (formatCode == 'C') {
                    result.add("eBook");
                } else {
                    result.add("Book");
                }
                break;
            // Component parts
            case 'A':
                result.add("BookComponentPart");
                break;
            case 'B':
                result.add("SerialComponentPart");
                break;
            // Serial
            case 'S':
                // Look in 008 to determine what type of Continuing Resource
                formatCode = fixedField.getData().toUpperCase().charAt(21);
                switch (formatCode) {
                    case 'N':
                        result.add("Newspaper");
                        break;
                    case 'P':
                        result.add("Journal");
                        break;
                    default:
                        result.add("Serial");
                        break;
                }
        }

        // Nothing worked!
        if (result.isEmpty()) {
            result.add("Unknown");
        }

        return result;
    }
}