package ee.cyber.eid;

import java.io.ByteArrayInputStream;
import java.io.FileNotFoundException;
import java.io.FileOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.util.List;

import ee.cyber.eid.util.Util;
import ee.sk.digidoc.DataFile;
import ee.sk.digidoc.DigiDocException;
import ee.sk.digidoc.SignedDoc;
import ee.sk.digidoc.factory.DigiDocFactory;
import ee.sk.utils.ConfigManager;
import ee.sk.xmlenc.EncryptedData;
import ee.sk.xmlenc.EncryptedKey;

/** A response from the claim handling service. */
public class Response {

    /** The source data. */
    private EncryptedData encd;

    /** The signed document that was encrypted. */
    private SignedDoc sdoc;

    /** Is the source data still encrypted? */
    private boolean encrypted = true;

    /** Create a new response object from EncryptedData. */
    public Response(EncryptedData encd) {
        this.encd = encd;
    }

    /** Decrypts the response.
     * @param recpCN The CN of the recipient whose key we are using to decrypt
     *               the data.
     * @param tokenIndex The index of the token to use for decrypting the
     *                   transport key.
     */
    public void decrypt(String recpCN, int tokenIndex) throws EidException,
            DigiDocException {
        if (!encrypted) {
            return;
        }

        int keyIndex = findKeyIndexByRecipient(recpCN);
        if (keyIndex < 0) {
            throw new EidException("No key exists for " + recpCN + '.');
        }

        encd.decrypt(keyIndex, tokenIndex, Util.getPin(1));
        encrypted = false;
    }

    /** Find the index of the EncryptedKey we want to use. */
    private int findKeyIndexByRecipient(String recpCN) {
        for (int i = 0; i < encd.getNumKeys(); i++) {
            EncryptedKey key = encd.getEncryptedKey(i);
            String keyRecp = key.getRecipient();
            if (keyRecp != null && keyRecp.equals(recpCN)) {
                return i;
            }
        }
        return -1;
    }

    /** Verifies the container that was decrypted. */
    @SuppressWarnings("unchecked")
    public void verify() throws EidException, DigiDocException,
            FileNotFoundException, IOException {
        if (encrypted) {
            throw new EidException("The response is encrypted. Decrypt it first!");
        }

        /* Read the decrypted data, save it to disc for reference and parse it
         * into a signed document. */
        byte[] data = encd.getData();
        new FileOutputStream("response.ddoc").write(data);
        InputStream stream = new ByteArrayInputStream(data);
        DigiDocFactory fac = ConfigManager.instance().getDigiDocFactory();
        sdoc = fac.readSignedDocFromStreamOfType(stream, false);

        /* Validate first... */
        List<DigiDocException> errs = sdoc.validate(true);
        checkErrors(errs, "Decrypted document did not validate.");

        /* ...and then verify. */
        errs = sdoc.verify(true, true); // neither argument is actually ever used
        checkErrors(errs, "The signatures did not verify!");
    }

    /**
     * Checks the given list for errors.
     * If any are found, then they are written to stderr and an EidException
     * with the given message is thrown.
     */
    private void checkErrors(List<DigiDocException> errs, String message)
            throws EidException {
        if (errs.size() > 0) {
            for (DigiDocException e : errs) {
                System.err.println(e.getMessage());
            }
            throw new EidException(message);
        }
    }

    /** Returns the contents of the response. */
    public String getContent() throws EidException, DigiDocException {
        if (sdoc.countDataFiles() != 1) {
            throw new EidException("The response should only have 1 data file,"
                    + "but this one does not.");
        }
        DataFile df = sdoc.getDataFile(0);
        return df.getBodyAsString();
    }

}
