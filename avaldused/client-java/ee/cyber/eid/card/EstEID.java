package ee.cyber.eid.card;

import java.io.UnsupportedEncodingException;
import java.security.NoSuchAlgorithmException;
import java.util.concurrent.TimeoutException;

import javax.smartcardio.Card;
import javax.smartcardio.CardException;
import javax.smartcardio.CommandAPDU;

import ee.cyber.eid.EidException;

/** An EstEID token. */
public class EstEID {

    /** The command to select the master file. */
    static final CommandAPDU SELECT_FILE_MF = new CommandAPDU(
            new byte[] { 0x00, (byte) 0xa4, 0x00, 0x0c });

    /** The command to select the directory EEEE. */
    static final CommandAPDU SELECT_FILE_EEEE = new CommandAPDU(
            new byte[] { 0x00, (byte) 0xa4, 0x01, 0x0c, 0x02, (byte) 0xee,
                    (byte) 0xee });

    private Card card;
    private PersonalData personalData;

    /** Connect to the the token on the specified terminal index. */
    public EstEID(int terminalIndex) throws NoSuchAlgorithmException,
            CardException, TimeoutException {
        card = CardUtil.connectToCard(terminalIndex);
    }

    public Card getCard() {
        return card;
    }

    /** Retrieve personal data stored on the EstEID token. */
    public PersonalData getPersonalData() throws CardException, EidException,
            UnsupportedEncodingException {
        if (personalData == null) {
            personalData = new PersonalData(card.getBasicChannel());
        }
        return personalData;
    }

}
