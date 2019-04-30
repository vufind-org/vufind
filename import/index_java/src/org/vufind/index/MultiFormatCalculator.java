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
     * Determine whether a record cannot be a book due to findings in 007.
     *
     * @param char formatCode
     * @return boolean
     */
    protected boolean definitelyNotBookBasedOn007(char formatCode) {
        switch (formatCode) {
            // Things that are not books: filmstrips/transparencies (G),
            // pictures (K) and videos/films (M, V):
            case 'G':
            case 'K':
            case 'M':
            case 'V':
                return true;
        }
        return false;
    }

    /**
     * Determine whether a record cannot be a book due to findings in leader
     * and fixed fields.
     *
     * @param char formatCode
     * @param ControlField fixedField
     * @return boolean
     */
    protected boolean definitelyNotBookBasedOnRecordType(char recordType, ControlField fixedField) {
        switch (recordType) {
            case 'M':
                // If this is a computer file containing numeric data, it is not a book:
                if (getTypeOfComputerFile(fixedField) == 'A') {
                    return true;
                }
                break;
            case 'J':
            case 'R':
                // Music recordings (J) and Physical objects (R) are not books.
                return true;
        }
        return false;
    }

    /**
     * Return the best format string based on codes extracted from 007; return
     * blank string for ambiguous/irrelevant results.
     *
     * @param char formatCode
     * @param char formatCode2
     * @param char formatCode5
     * @return String
     */
    protected String getFormatFrom007(char formatCode, char formatCode2, char formatCode5) {
        switch (formatCode) {
            case 'A':
                return formatCode2 == 'D' ? "Atlas" : "Map";
            case 'C':
                switch(formatCode2) {
                    case 'A':
                        return "TapeCartridge";
                    case 'B':
                        return "ChipCartridge";
                    case 'C':
                        return "DiscCartridge";
                    case 'F':
                        return "TapeCassette";
                    case 'H':
                        return "TapeReel";
                    case 'J':
                        return "FloppyDisk";
                    case 'M':
                    case 'O':
                        return "CDROM";
                    case 'R':
                        // Do not return anything - otherwise anything with an
                        // 856 field would be labeled as "Electronic"
                        return "";
                }
                return "Software";
            case 'D':
                return "Globe";
            case 'F':
                return "Braille";
            case 'G':
                switch(formatCode2) {
                    case 'C':
                    case 'D':
                        return "Filmstrip";
                    case 'T':
                        return "Transparency";
                }
                return "Slide";
            case 'H':
                return "Microfilm";
            case 'K':
                switch(formatCode2) {
                    case 'C':
                        return "Collage";
                    case 'D':
                        return "Drawing";
                    case 'E':
                        return "Painting";
                    case 'F':
                        return "Print";
                    case 'G':
                        return "Photonegative";
                    case 'J':
                        return "Print";
                    case 'L':
                        return "Drawing";
                    case 'O':
                        return "FlashCard";
                    case 'N':
                        return "Chart";
                }
                return "Photo";
            case 'M':
                switch(formatCode2) {
                    case 'F':
                        return "VideoCassette";
                    case 'R':
                        return "Filmstrip";
                }
                return "MotionPicture";
            case 'O':
                return "Kit";
            case 'Q':
                return "MusicalScore";
            case 'R':
                return "SensorImage";
            case 'S':
                switch(formatCode2) {
                    case 'D':
                        return "SoundDisc";
                    case 'S':
                        return "SoundCassette";
                }
                return "SoundRecording";
            case 'V':
                switch(formatCode2) {
                    case 'C':
                        return "VideoCartridge";
                    case 'D':
                        return formatCode5 == 'S' ? "BRDisc" : "VideoDisc";
                    case 'F':
                        return "VideoCassette";
                    case 'R':
                        return "VideoReel";
                }
                // assume other video is online:
                return "VideoOnline";
        }
        return "";
    }

    /**
     * Return the best format string based on bib level in leader; return
     * blank string for ambiguous/irrelevant results.
     *
     * @param Record record
     * @param char bibLevel
     * @param char formatCode
     * @param ControlField fixedField
     * @param boolean couldBeBook
     * @return String
     */
    protected String getFormatFromBibLevel(Record record, char bibLevel, char formatCode, ControlField fixedField, boolean couldBeBook) {
        switch (bibLevel) {
            // Monograph
            case 'M':
                if (couldBeBook) {
                    return (formatCode == 'C') ? "eBook" : "Book";
                }
                break;
            // Component parts
            case 'A':
                return "BookComponentPart";
            case 'B':
                return "SerialComponentPart";
            // Integrating resources (e.g. loose-leaf binders)
            case 'I':
                return "IntegratingResource";
            // Serial
            case 'S':
                // Look in 008 to determine what type of Continuing Resource
                switch (fixedField.getData().toUpperCase().charAt(21)) {
                    case 'N':
                        return "Newspaper";
                    case 'P':
                        return "Journal";
                    default:
                        if (!isConferenceProceeding(record)) {
                            return "Serial";
                        }
                }
                break;
        }
        return "";
    }

    /**
     * Return the best format string based on record type in leader; return
     * blank string for ambiguous/irrelevant results.
     *
     * @param Record record
     * @param char recordType
     * @return String
     */
    protected String getFormatFromRecordType(Record record, char recordType) {
        switch (recordType) {
            case 'C':
            case 'D':
                return "MusicalScore";
            case 'E':
            case 'F':
                return "Map";
            case 'G':
                // We're going to rely on the 007 instead for Projected Media
                //return "Slide";
                return "";
            case 'I':
                return "SoundRecording";
            case 'J':
                return "MusicRecording";
            case 'K':
                return "Photo";
            case 'O':
            case 'P':
                return "Kit";
            case 'R':
                return "PhysicalObject";
            case 'T':
                if (!isThesis(record)) {
                    return "Manuscript";
                }
                break;
        }
        return "";
    }

    protected char getTypeOfComputerFile(ControlField fixedField) {
        // Check the 008 for the type of computer file:
        try {
            return fixedField.getData().toUpperCase().charAt(26);
        } catch (java.lang.StringIndexOutOfBoundsException e) {
            // ignore errors (leave the string blank if out of bounds)
            return ' ';
        }
    }

    /**
     * Determine whether a record is a conference proceeding.
     *
     * @param Record record
     * @return boolean
     */
    protected boolean isConferenceProceeding(Record record) {
        // Is there a main entry meeting name?
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
        return (conference1 != null || conference2 != null);
    }

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
     * Determine whether a record is a government document.
     *
     * @param Record record
     * @return boolean
     */
    protected boolean isGovernmentDocument(Record record) {
        // Is there a SuDoc number? If so, it's a government document.
        return record.getVariableField("086") != null;
    }


    /**
     * Determine whether a record is a thesis.
     *
     * @param Record record
     * @return boolean
     */
    protected boolean isThesis(Record record) {
        // Is there a dissertation note? If so, it's a government document.
        return record.getVariableField("502") != null;
    }

    /**
     * Determine Record Format(s)
     *
     * @param  Record record
     * @return Set format(s) of record
     */
    public Set<String> getFormats(Record record){
        Set<String> result = new LinkedHashSet<String>();
        String leader = record.getLeader().toString();
        ControlField fixedField = (ControlField) record.getVariableField("008");
        String formatString;
        char formatCode = ' ';
        char formatCode2 = ' ';
        char formatCode5 = ' ';

        // This record could be a book... until we prove otherwise!
        boolean couldBeBook = true;

        // Some format-specific special cases:
        if (isGovernmentDocument(record)) {
            result.add("GovernmentDocument");
        }
        if (isThesis(record)) {
            result.add("Thesis");
        }
        if (isElectronic(record)) {
            result.add("Electronic");
        }
        if (isConferenceProceeding(record)) {
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
                if (definitelyNotBookBasedOn007(formatCode)) {
                    couldBeBook = false;
                }
                if (formatCode == 'V') {
                    // All video content should get flagged as video; we will also
                    // add a more detailed value in getFormatFrom007 to distinguish
                    // different types of video.
                    result.add("Video");
                }
                String formatFrom007 = getFormatFrom007(
                    formatCode, formatCode2, formatCode5
                );
                if (formatFrom007.length() > 0) {
                    result.add(formatFrom007);
                }
            }
        }

        // check the Leader at position 6
        char recordType = Character.toUpperCase(leader.charAt(6));
        if (definitelyNotBookBasedOnRecordType(recordType, fixedField)) {
            couldBeBook = false;
        }
        String formatFromRecordType = getFormatFromRecordType(record, recordType);
        if (formatFromRecordType.length() > 0) {
            result.add(formatFromRecordType);
        }

        // check the Leader at position 7
        char bibLevel = Character.toUpperCase(leader.charAt(7));
        String formatFromBibLevel = getFormatFromBibLevel(
            record, bibLevel, formatCode, fixedField, couldBeBook
        );
        if (formatFromBibLevel.length() > 0) {
            result.add(formatFromBibLevel);
        }

        // Nothing worked -- time to set up a value of last resort!
        if (result.isEmpty()) {
            // If the leader bit indicates a "Collection," treat it as a kit for now;
            // this is a rare case but helps cut down on the number of unknowns.
            result.add(bibLevel == 'C' ? "Kit" : "Unknown");
        }

        return result;
    }
}
