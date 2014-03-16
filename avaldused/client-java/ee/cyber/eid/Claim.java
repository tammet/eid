package ee.cyber.eid;

import java.io.ByteArrayOutputStream;
import java.io.File;
import java.io.IOException;
import java.security.cert.X509Certificate;
import java.util.List;

import ee.cyber.eid.card.PersonalData;
import ee.cyber.eid.util.TempFile;
import ee.cyber.eid.util.Util;
import ee.sk.digidoc.DataFile;
import ee.sk.digidoc.DigiDocException;
import ee.sk.digidoc.Signature;
import ee.sk.digidoc.SignatureProductionPlace;
import ee.sk.digidoc.SignedDoc;
import ee.sk.digidoc.factory.DigiDocGenFactory;
import ee.sk.digidoc.factory.SignatureFactory;
import ee.sk.utils.ConfigManager;

/** The claim to be built, signed and submitted. */
public class Claim {

    private SignedDoc sdoc;

    /** Creates a new signing container. */
    public Claim() throws DigiDocException {
        /* The claim handling service uses DigiDocService, and since that
         * supports only DDOC-formats, we use DIGIDOC-XML. */
        sdoc = DigiDocGenFactory.createSignedDoc(SignedDoc.FORMAT_DIGIDOC_XML,
                null, null);
    }

    /** Adds the given content into the container as the claim. */
    public void addClaimFile(String content) throws DigiDocException,
            IOException {
        addTempFile("claim", content);
    }

    /** Adds the given personal data into the container. */
    public void addPersonalDataFile(PersonalData data) throws DigiDocException,
            IOException {
        addTempFile("personal", data.toString());
    }

    /**
     * Writes a temporary file to disc and then adds it to the container.
     * This is necessary because JDigiDoc only operates on files.
     */
    private void addTempFile(String name, String content)
            throws DigiDocException, IOException {
        sdoc.addDataFile(new TempFile(name, content).getFile(),
                TempFile.MIME_TYPE, DataFile.CONTENT_EMBEDDED_BASE64);
    }

    /** Signs the container. */
    public void sign(int tokenIndex) throws DigiDocException {
        SignatureFactory sigFac = ConfigManager.instance()
                .getSignatureFactory();

        /* Get PIN2 from the user. */
        String pin = Util.getPin(2);
        if (pin.isEmpty()) {
            return;
        }

        /* Read the certificate used for signing. */
        X509Certificate cert = sigFac.getCertificate(tokenIndex, pin);

        /* Roles and SPP are optional, but add something for demonstration
         * purposes. These are usually asked from the signer. */
        String[] roles = new String[] { "role" };
        SignatureProductionPlace spp = new SignatureProductionPlace(
                "city", "state", "country", "postal");

        /* Get a signature object. */
        Signature sig = sdoc.prepareSignature(cert, roles, spp);

        /* Calculate the digest to sign and do it. */
        byte[] sigDigest = sig.calculateSignedInfoDigest();
        byte[] sigVal = sigFac.sign(sigDigest, tokenIndex, pin, sig);
        sig.setSignatureValue(sigVal);

        /* Get OCSP confirmation for the signature. */
        sig.getConfirmation();
    }

    /**
     * Verify all the signatures on the container.
     * Returns a boolean rather than throwing Exceptions - this gives us the
     * chance to find all errors and not just fail on the first one.
     */
    @SuppressWarnings("unchecked")
    public boolean verify() {
        List<DigiDocException> err = null;
        boolean ret = true;
        for (int i = 0; i < sdoc.countSignatures(); i++) {
            Signature sig = sdoc.getSignature(i);
            if ((err = sig.verify(sdoc, false, true)).size() > 0) {
                ret = false;
                System.err.println("Could not verify signature " + sig.getId());
                for (DigiDocException e : err) {
                    System.err.println(e.getMessage());
                }
            }
        }
        return ret;
    }

    /** Save the signed container to file. */
    public void saveTo(String path) throws DigiDocException {
        sdoc.writeToFile(new File(path));
    }

    /** Returns the MIME type for this claim. */
    public String getMimeType() {
        return SignedDoc.FORMAT_BDOC.equals(sdoc.getFormat())
                ? SignedDoc.FORMAT_BDOC_MIME + '-' + sdoc.getVersion()
                : "application/x-ddoc";
    }

    /** Returns the created claim as a byte array. */
    public byte[] toByteArray() throws DigiDocException {
        ByteArrayOutputStream buf = new ByteArrayOutputStream();
        sdoc.writeToStream(buf);
        return buf.toByteArray();
    }

}
