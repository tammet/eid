package ee.cyber.eid.card;

import java.security.NoSuchAlgorithmException;
import java.util.List;
import java.util.concurrent.TimeoutException;

import javax.smartcardio.Card;
import javax.smartcardio.CardChannel;
import javax.smartcardio.CardException;
import javax.smartcardio.CardTerminal;
import javax.smartcardio.CommandAPDU;
import javax.smartcardio.ResponseAPDU;
import javax.smartcardio.TerminalFactory;

import ee.cyber.eid.EidException;

/** A collection of helper functions for handling EstEID tokens. */
public class CardUtil {

    /** The encoding used for data stored on the tokens. */
    public static final String CARD_ENCODING = "ISO-8859-1";

    /** The protocol to use for communicating with the tokens. */
    private static final String PROTOCOL_T0 = "T=0";

    /** Get the card terminals connected. */
    public static List<CardTerminal> getTerminals() throws CardException,
            NoSuchAlgorithmException {
        return TerminalFactory.getInstance("PC/SC", null).terminals().list();
    }

    /** Wait for a card to be inserted on the given terminal. */
    public static void waitForCard(int terminalIndex)
            throws NoSuchAlgorithmException, CardException, TimeoutException {
        waitForCard(terminalIndex, 10000L);
    }

    /** Wait for a card to be inserted on the given terminal. */
    public static void waitForCard(int terminalIndex, long timeout)
            throws NoSuchAlgorithmException, CardException, TimeoutException {
        /* Get the terminal on the given index. */
        List<CardTerminal> terminals = getTerminals();
        if (terminalIndex < 0 || terminalIndex >= terminals.size()) {
            throw new IllegalArgumentException("Terminal index out of range");
        }
        CardTerminal terminal = terminals.get(terminalIndex);

        /* Wait for a card to be inserted. */
        System.out.print("Waiting for card in " + terminal.getName() + "...");
        if (terminal.waitForCardPresent(timeout)) {
            System.out.println("Found.");
        } else {
            System.out.println("Timeout: no card in terminal");
            throw new TimeoutException("No card inserted into selected "
                    + "terminal");
        }
    }

    /** Connect to the card inserted into the specified terminal. */
    static Card connectToCard(int terminalIndex)
            throws NoSuchAlgorithmException, CardException, TimeoutException {
        waitForCard(terminalIndex);

        /* Connect to the card using protocol T=0.
         * waitForCard() did all the sanity checking for terminalIndex, so no
         * need to check it again. */
        return getTerminals().get(terminalIndex).connect(PROTOCOL_T0);
    }

    /** Send a command APDU to the card. */
    static byte[] sendCommand(CardChannel channel, CommandAPDU cmd)
            throws CardException, EidException {
        ResponseAPDU resp = channel.transmit(cmd);
        int status = resp.getSW();
        if (status != 0x9000) { // 0x9000 is OK response
            throw new EidException("The card responded with status " + status);
        }
        return resp.getData();
    }

}
