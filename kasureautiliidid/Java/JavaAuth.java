// vim: set sw=4 et:
/**
 * Small utility for authenticating EstEID token owners.
 *
 * A nonce is generated and signed using the authentication certificate. If the
 * signature verifies, then we can be sure that the user has access to the
 * private key of that certificate. We consider this to be proof enough that we
 * are dealing with the owner of the EstEID token.
 *
 * Compilation:
 * javac -cp $JAVA_LIB/jdigidoc.jar:$JAVA_LIB/bcprov-jdk16-146.jar JavaAuth.java
 *
 * Execution:
 * java -cp .:$JAVA_LIB/* JavaAuth
 */
import java.io.Console;
import java.security.SecureRandom;
import java.security.cert.CertificateExpiredException;
import java.security.cert.CertificateNotYetValidException;
import java.security.cert.X509Certificate;

import ee.sk.digidoc.DigiDocException;
import ee.sk.digidoc.factory.DigiDocVerifyFactory;
import ee.sk.digidoc.factory.SignatureFactory;
import ee.sk.utils.ConfigManager;

public class JavaAuth {

    /** Default place to look for the configuration file. */
    public static final String DEFAULT_CONF = "jdigidoc.cfg";

    /** Generate the random nonce to be signed. */
    private static byte[] generateNonce() {
        SecureRandom prng = new SecureRandom();
        byte[] nonce = new byte[64];
        prng.nextBytes(nonce);
        return nonce;
    }

    /** Read the PIN from the console. */
    private static String getPin() {
        Console cons = System.console();
        if (cons == null) {
            throw new RuntimeException("ERROR: No console to enter PIN with.");
        }
        return new String(cons.readPassword("Please enter PIN1 or leave blank "
                + "to cancel: "));
    }

    /** Check that the certificate is valid. */
    public static void validateCert(X509Certificate cert)
            throws CertificateExpiredException,
            CertificateNotYetValidException, DigiDocException {
        cert.checkValidity();
        ConfigManager.instance().getNotaryFactory().checkCertificate(cert);
    }

    public static void main(String[] argv) throws DigiDocException {
        String cfg = DEFAULT_CONF;
        if (argv.length == 2 && "-cfg".equals(argv[0])) {
            cfg = argv[1];
        }
        if (!ConfigManager.init(cfg)) {
            System.err.println("Add jdigidoc.cfg to the current directory or "
                    + "specify one with the '-cfg' argument.");
            return;
        }

        System.out.print("Generating nonce...");
        byte[] nonce = generateNonce();
        System.out.println("Done");

        /* Create a new signer. */
        SignatureFactory fac = ConfigManager.instance().getSignatureFactory();
        fac.init();

        /* Because we open a session first by reading the certificate, we can
         * pass null as the pin for sign() */
        X509Certificate cert = fac.getAuthCertificate(0, getPin());
        try {
            validateCert(cert);
        } catch (Exception e) {
            System.err.println("ERROR: Certificate is not valid");
            System.err.println(e.getMessage());
            if (e instanceof DigiDocException) {
                Throwable t = ((DigiDocException) e).getNestedException();
                if (t != null) {
                    t.printStackTrace();
                }
            } else {
                e.printStackTrace();
            }
            return;
        }

        /* Sign the nonce and verify the signature. The session is already open,
         * so no need for the pin. */
        byte[] signature = fac.sign(nonce, 0, null, null);
        if (DigiDocVerifyFactory.verify(nonce, signature, cert, false, null)) {
            System.out.println("Authentication successful");
        } else {
            System.err.println("ERROR: Could not verify signature");
        }
    }

}
