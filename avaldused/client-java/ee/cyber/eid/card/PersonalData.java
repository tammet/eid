package ee.cyber.eid.card;

import java.io.UnsupportedEncodingException;

import javax.smartcardio.CardChannel;
import javax.smartcardio.CardException;
import javax.smartcardio.CommandAPDU;

import ee.cyber.eid.EidException;

/** The personal data stored on an ID-card. */
public class PersonalData {

    /** The number of personal data fields. */
    private static final int RECORDS_COUNT = 16;

    /** The command to select the file 5044. */
    private static final CommandAPDU SELECT_FILE_5044 = new CommandAPDU(
            new byte[] { 0x00, (byte) 0xa4, 0x02, 0x04, 0x02, 0x50, 0x44 });

    private String[] records = new String[RECORDS_COUNT];

    /** Read personal data from a card using the given channel. */
    public PersonalData(CardChannel channel) throws CardException,
            EidException, UnsupportedEncodingException {
        /* Navigate to the personal data file. */
        CardUtil.sendCommand(channel, EstEID.SELECT_FILE_MF);
        CardUtil.sendCommand(channel, EstEID.SELECT_FILE_EEEE);
        CardUtil.sendCommand(channel, SELECT_FILE_5044);

        /* Read the stored personal data into an array. */
        for (byte i = 1; i <= RECORDS_COUNT; i++) {
            records[i - 1] = new String(readRecord(channel, i),
                    CardUtil.CARD_ENCODING).trim();
        }
    }

    /** Read a record from the card using the given channel. */
    private byte[] readRecord(CardChannel channel, byte recordNumber)
            throws CardException, EidException {
        return CardUtil.sendCommand(channel, new CommandAPDU(new byte[] {
                0x00, (byte) 0xb2, recordNumber, 0x04, 0x00 }));
    }

    public String getSurname() {
        return records[0];
    }

    public String getGivenName1() {
        return records[1];
    }

    public String getGivenName2() {
        return records[2];
    }

    public String getGender() {
        return records[3];
    }

    public String getCitizenship() {
        return records[4];
    }

    public String getDateOfBirth() {
        return records[5];
    }

    public String getPersonalCode() {
        return records[6];
    }

    public String getDocumentNumber() {
        return records[7];
    }

    public String getDateOfExpiry() {
        return records[8];
    }

    public String getPlaceOfBirth() {
        return records[9];
    }

    public String getDateOfIssue() {
        return records[10];
    }

    public String getResidencePermitType() {
        return records[11];
    }

    public String getRemark1() {
        return records[12];
    }

    public String getRemark2() {
        return records[13];
    }

    public String getRemark3() {
        return records[14];
    }

    public String getRemark4() {
        return records[15];
    }

    @Override
    public String toString() {
        return "Surname: " + getSurname() + '\n'
                + "Given name 1: " + getGivenName1() + '\n'
                + "Given name 2: " + getGivenName2() + '\n'
                + "Gender: " + getGender() + '\n'
                + "Citizenship: " + getCitizenship() + '\n'
                + "Date of birth: " + getDateOfBirth() + '\n'
                + "Personal code: " + getPersonalCode() + '\n'
                + "Document number: " + getDocumentNumber() + '\n'
                + "Date of expiry: " + getDateOfExpiry() + '\n'
                + "Place of birth: " + getPlaceOfBirth() + '\n'
                + "Date of issue: " + getDateOfIssue() + '\n'
                + "Residence permit type: " + getResidencePermitType() + '\n'
                + "Remark 1: " + getRemark1() + '\n'
                + "Remark 2: " + getRemark2() + '\n'
                + "Remark 3: " + getRemark3() + '\n'
                + "Remark 4: " + getRemark4();
    }

}
