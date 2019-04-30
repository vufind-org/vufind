package org.vufind.index;
/**
 * Alternate, multi-valued format determination logic.
 *
 * Copyright (C) Villanova University 2018.
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
import java.util.Iterator;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.Set;

/**
 * Alternate, multi-valued format determination logic.
 */
public class MultiFormatCalculator
{
    /**
     * Determine whether a record is electronic in format.
     *
     * @param Record record
     * @return boolean
     */
    protected boolean isElectronic(Record record) {
        /* Example from Villanova of how to use holdings locations to detect online status;
         * You can override this method in a subclass if you wish to use this approach.
        DataField holdingsField = (DataField) record.getVariableField("852");
        if (holdingsField != null) {
            if (holdingsField.getSubfield('b') != null) {
                String holdingsLocation = holdingsField.getSubfield('b').getData().toLowerCase();
                if (holdingsLocation.equals("www") || holdingsLocation.equals("e-ref")) {
                    return true;
                }
            }
        }
         */
        DataField title = (DataField) record.getVariableField("245");
        if (title != null) {
            if (title.getSubfield('h') != null){
                if (title.getSubfield('h').getData().toLowerCase().contains("[electronic resource]")) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Determine Record Format(s)
     *
     * @param  Record          record
     * @return Set     format of record
     */
    public Set<String> getFormats(Record record){
        Set<String> result = new LinkedHashSet<String>();
        String leader = record.getLeader().toString();
        char leaderBit;
        ControlField fixedField = (ControlField) record.getVariableField("008");
        String formatString;
        char formatCode = ' ';
        char formatCode2 = ' ';
        char formatCode5 = ' ';

        // This record could be a book... until we prove otherwise!
        boolean couldBeBook = true;

        // Is there a SuDoc number? If so, it's a government document.
        if (record.getVariableField("086") != null) {
            result.add("GovernmentDocument");
        }

        //---
        // Thesis
        //---
        DataField thesisField = (DataField) record.getVariableField("502");
        if (thesisField != null) {
            result.add("Thesis");
        }

        //---
        // Online (based on holdings location)
        //---
        if (isElectronic(record)) {
            result.add("Electronic");
        }

        //----
        // Conference Proceedings
        //----
        DataField conference1 = (DataField) record.getVariableField("111");
        // The 711 could possibly have more then one entry, although probably unlikely
        DataField conference2 = (DataField) null;
        List conferenceFields = record.getVariableFields("711");
        Iterator conferenceFieldsIter = conferenceFields.iterator();
        if (conferenceFields != null) {
            if (conferenceFieldsIter.hasNext()) {
            conference2 = (DataField) conferenceFieldsIter.next();
            }
        }
        if (conference1 != null || conference2 != null) {
            result.add("ConferenceProceeding");
        }

        // check the 007 - this is a repeating field
        List fields = record.getVariableFields("007");
        Iterator fieldsIter = fields.iterator();
        if (fields != null) {
            ControlField formatField;
            while(fieldsIter.hasNext()) {
                formatField = (ControlField) fieldsIter.next();
                formatString = formatField.getData().toUpperCase();
                formatCode = formatString.length() > 0 ? formatString.charAt(0) : ' ';
                formatCode2 = formatString.length() > 1 ? formatString.charAt(1) : ' ';
                formatCode5 = formatString.length() > 4 ? formatString.charAt(4) : ' ';
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
                        // Filmstrips / transparencies are not books.
                        couldBeBook = false;
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
                        // Pictures are not books
                        couldBeBook = false;
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
                        // Videos/films are not books!
                        couldBeBook = false;
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
                        // All video content should get flagged as video; we will also
                        // add a second value to distinguish different types of video.
                        result.add("Video");
                        switch(formatCode2) {
                            case 'C':
                                result.add("VideoCartridge");
                                break;
                            case 'D':
                                switch(formatCode5) {
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
                                // assume other video is online:
                                result.add("VideoOnline");
                                break;
                        }
                        // Videos are not books!
                        couldBeBook = false;
                        break;
                }
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
                // We're going to rely on the 007 for Projected Media
                //result.add("Slide");
                break;
            case 'I':
                result.add("SoundRecording");
                break;
            case 'J':
                result.add("MusicRecording");
                // Music recordings are not books.
                couldBeBook = false;
                break;
            case 'K':
                result.add("Photo");
                break;
            case 'M':
                // Check the 008 for the type of computer file:
                char typeOfComputerFile = ' ';
                try {
                    typeOfComputerFile = fixedField.getData().toUpperCase().charAt(26);
                } catch (java.lang.StringIndexOutOfBoundsException e) {
                    // ignore errors (leave the string blank if out of bounds)
                }
                // If this is a computer file containing numeric data, it is not a book:
                if (typeOfComputerFile == 'A') {
                    couldBeBook = false;
                }
                break;
            case 'O':
            case 'P':
                result.add("Kit");
                break;
            case 'R':
                result.add("PhysicalObject");
                // Physical objects are not books.
                couldBeBook = false;
                break;
            case 'T':
                if (thesisField == null) {
                    result.add("Manuscript");
                }
                break;
        }

        // check the Leader at position 7
        leaderBit = leader.charAt(7);
        switch (Character.toUpperCase(leaderBit)) {
            // Monograph
            case 'M':
                if (couldBeBook) {
                    if (formatCode == 'C') {
                        result.add("eBook");
                    } else {
                        result.add("Book");
                    }
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
                        if (conference1 == null && conference2 == null) {
                            result.add("Serial");
                        }
                        break;
                }
        }

        // Nothing worked!
        if (result.isEmpty()) {
            // If the leader bit indicates a "Collection," treat it as a kit for now;
            // this is a rare case but helps cut down on the number of unknowns.
            if (Character.toUpperCase(leaderBit) == 'C') {
                result.add("Kit");
            } else {
                result.add("Unknown");
            }
        }

        return result;
    }
}
