package ee.cyber.eid;

import java.security.NoSuchAlgorithmException;
import java.security.cert.CertificateExpiredException;
import java.security.cert.CertificateNotYetValidException;
import java.security.cert.X509Certificate;
import java.util.concurrent.TimeoutException;

import javax.smartcardio.CardException;

import ee.cyber.eid.card.CardUtil;
import ee.cyber.eid.util.Util;
import ee.sk.digidoc.DigiDocException;
import ee.sk.digidoc.factory.DigiDocVerifyFactory;
import ee.sk.digidoc.factory.SignatureFactory;
import ee.sk.utils.ConfigManager;

/**
 * Authenticates the user using an EstEID token.
 * The class generates a nonce and let's the user sign it with his or her
 * authentication certificate. If the signature verifies, then the class makes
 * an OCSP request to be sure that the certificate is still valid. If the
 * response comes back "GOOD", then the user is authenticated.
 */
public class Authenticator {

    /** Length of the nonce to sign. */
    private static int NONCE_LENGTH = 64;

    /**
     * Authenticate the user using the identification certificate on an
     * EstEID token.
     * @param tokenIndex The index of the token to use.
     * @return The authenticated user's certificate.
     */
    public static X509Certificate authenticate(int tokenIndex)
            throws EidException, DigiDocException, CertificateExpiredException,
            CertificateNotYetValidException, NoSuchAlgorithmException,
            CardException, TimeoutException {
        /* Get a signature factory instance. */
        SignatureFactory fac = ConfigManager.instance().getSignatureFactory();

        /* Retrieve the authentication certificate and run an initial, local
         * validity check based on the start and end dates on it.
         * For some reason jdigidoc wants the PIN to retrieve a certificate,
         * although it can be read without it. */
        CardUtil.waitForCard(tokenIndex);
        X509Certificate cert = fac.getAuthCertificate(tokenIndex, Util.getPin(1));
        cert.checkValidity();

        /* Generate and sign a nonce. Since we already logged in by asking for
         * the certificate, we don't need to provide the PIN anymore. */
        byte[] nonce = Util.generateRandom(NONCE_LENGTH);
        byte[] signature = fac.sign(nonce, tokenIndex, null, null);

        /* Verify the signature with the authentication certificate. */
        if (!DigiDocVerifyFactory.verify(nonce, signature, cert, false, null)) {
            throw new EidException("Signed nonce did not verify!");
        }

        /* Use OCSP to verify the certificate is valid. */
        ConfigManager.instance().getNotaryFactory().checkCertificate(cert);

        return cert;
    }

}
