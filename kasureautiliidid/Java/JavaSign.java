// vim: set sw=4 et:
/**
 * Small utility for signing documents.
 *
 * Determines the format to use based on the extension of outfile. BDOC if it
 * ends with .bdoc, DIGIDOC-XML otherwise.
 *
 * Compilation:
 * javac -cp $JAVA_LIB/jdigidoc.jar JavaSign.java
 *
 * Execution:
 * java -cp .:$JAVA_LIB/* JavaSign
 */
import java.io.Console;
import java.io.File;
import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Paths;
import java.security.cert.X509Certificate;
import java.util.List;
import java.util.ArrayList;

import ee.sk.digidoc.DataFile;
import ee.sk.digidoc.DigiDocException;
import ee.sk.digidoc.Signature;
import ee.sk.digidoc.SignatureProductionPlace;
import ee.sk.digidoc.SignedDoc;
import ee.sk.digidoc.factory.DigiDocGenFactory;
import ee.sk.digidoc.factory.SignatureFactory;
import ee.sk.utils.ConfigManager;

public class JavaSign {

    /** Default place to look for the configuration file. */
    public static final String DEFAULT_CONF = "jdigidoc.cfg";

    private SignedDoc sdoc;

    /**
     * Constructor for a signer.
     */
    public JavaSign(String format) {
        try {
            sdoc = DigiDocGenFactory.createSignedDoc(format, null, null);
        } catch (DigiDocException e) {
            System.err.println("Error creating signature container:");
            e.printStackTrace();
        }
    }

    /**
     * Add a document for signing.
     * The MIME type of the file will be determined by the Files class.
     */
    public void addDocument(String path) {
        /* Do we have a container for the files. */
        if (!hasSignedDoc()) {
            System.err.println("Can't add document - no container found.");
            return;
        }

        /* Can we read the file. */
        File file = new File(path);
        if (!file.isFile() || !file.canRead()) {
            System.err.println("File not found: " + path);
            return;
        }

        /* Determine the MIME type. */
        String mime = "file";
        try {
            mime = Files.probeContentType(Paths.get(path)); // Since Java 1.7
        } catch (IOException e1) {
            System.err.println("An error occured trying to guess the MIME type "
                    + "for " + path + ". Using \"file\".");
        }

        /* Add it to the container. */
        try {
            sdoc.addDataFile(file, mime, DataFile.CONTENT_EMBEDDED_BASE64);
        } catch (DigiDocException e) {
            System.err.println("Error adding file to container:");
            e.printStackTrace();
        }
    }

    /** Add a list of documents for signing. */
    public void addDocuments(List<String> files) {
        for (String file : files) {
            addDocument(file);
        }
    }

    /**
     * Read the PIN from the console.
     */
    private String getPin() {
        Console cons = System.console();
        if (cons == null) {
            throw new RuntimeException("No console to enter PIN with.");
        }
        return new String(cons.readPassword("Please enter PIN2 or leave blank "
                + "to cancel: "));
    }

    /**
     * Sign the container.
     */
    public void sign() {
        if (!hasSignedDoc()) {
            System.err.println("No container to sign.");
            return;
        }

        try {
            /* Create a new signature factory. */
            SignatureFactory sigFac = ConfigManager.instance()
                    .getSignatureFactory();

            String pin = getPin();
            if (pin.isEmpty()) {
                return;
            }

            /* Read the certificate used for signing. */
            X509Certificate cert = sigFac.getCertificate(0, pin);

            /* Roles and SPP are optional, but add something for demonstration
             * purposes. */
            String[] roles = new String[] { "role" };
            SignatureProductionPlace spp = new SignatureProductionPlace(
                    "city", "state", "country", "postal");

            /* Get a signature object. */
            Signature sig = sdoc.prepareSignature(cert, roles, spp);

            /* Calculate the digest to sign and do it. */
            byte[] sigDigest = sig.calculateSignedInfoDigest();
            byte[] sigVal = sigFac.sign(sigDigest, 0, pin, sig);
            sig.setSignatureValue(sigVal);

            /* Get OCSP confirmation for the signature. */
            sig.getConfirmation();
        } catch (DigiDocException e) {
            System.err.println("Error signing the container:");
            e.printStackTrace();
        }
    }

    /**
     * Save the signed container to file.
     */
    public boolean saveTo(String path) {
        try {
            sdoc.writeToFile(new File(path));
            return true;
        } catch (DigiDocException e) {
            System.err.println("Error saving to file:");
            e.printStackTrace();
            return false;
        }
    }

    public boolean hasSignedDoc() {
        return sdoc != null;
    }

    public boolean hasDataFiles() {
        return sdoc.countDataFiles() > 0;
    }

    public boolean hasSignatures() {
        return sdoc.countSignatures() > 0;
    }

    /**
     * Verify all the signatures on the container.
     */
    @SuppressWarnings("unchecked")
    public void verifySignatures() {
        List<DigiDocException> err;
        for (int i = 0; i < sdoc.countSignatures(); i++) {
            Signature sig = sdoc.getSignature(i);
            if ((err = sig.verify(sdoc, false, true)).size() > 0) {
                /* This seems to give false errors for BDOC, so let's not
                 * handle them as fatal. */
                System.err.println("Could not verify signature " + sig.getId());
                for (DigiDocException e : err) {
                    System.err.println(e.getMessage());
                }
            }
        }
    }

    public static void printUsage() {
        System.out.println("Usage: java JavaSign [-cfg config] infile... "
                + "outfile");
    }

    public static void main(String[] argv) {
        if (argv.length < 2) {
            printUsage();
            return;
        }

        List<String> files = new ArrayList<String>();
        String cfg = null;
        for (int i = 0; i < argv.length; i++) {
            if ("-cfg".equals(argv[i])) {
                if (cfg != null || i + 1 >= argv.length) {
                    printUsage();
                    return;
                }
                cfg = argv[++i];
            } else {
                if (!argv[i].isEmpty()) {
                    files.add(argv[i]);
                }
            }
        }
        if (files.size() < 2) {
            printUsage();
            return;
        }

        /* Determine the outfile and its format. */
        String out = files.remove(files.size() - 1);
        String format = ".bdoc".equals(out.substring(out.lastIndexOf('.')))
                ? SignedDoc.FORMAT_BDOC : SignedDoc.FORMAT_DIGIDOC_XML;

        if (!ConfigManager.init(cfg != null ? cfg : DEFAULT_CONF)) {
            System.err.println("Add jdigidoc.cfg to the current directory or "
                    + "specify one with the '-cfg' argument.");
            return;
        }

        JavaSign js = new JavaSign(format);
        if (!js.hasSignedDoc()) {
            return;
        }

        js.addDocuments(files);
        if (!js.hasDataFiles()) {
            return;
        }

        js.sign();
        if (!js.hasSignatures()) {
            return;
        }

        js.verifySignatures();
        boolean ok = js.saveTo(out);
        if (ok) {
            System.out.println("Ok.");
        }
    }

}
