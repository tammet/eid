package ee.cyber.eid.util;

import java.io.Console;
import java.security.SecureRandom;
import java.security.cert.X509Certificate;

import javax.security.auth.x500.X500Principal;

import org.bouncycastle.asn1.x500.RDN;
import org.bouncycastle.asn1.x500.X500Name;
import org.bouncycastle.asn1.x500.style.BCStyle;
import org.bouncycastle.asn1.x500.style.IETFUtils;

/** Collection of general helper functions. */
public class Util {

    /**
     * Ask the user for PIN number <tt>num</tt> using a console.
     * @param num The PIN to ask for.
     * @return The user's input.
     */
    public static String getPin(int num) {
        Console cons = System.console();
        if (cons == null) {
            throw new RuntimeException("No console to enter PIN with.");
        }

        String pin = new String(cons.readPassword("Please enter PIN%d or leave "
                + "blank to cancel: ", num));
        if (pin == null || pin.trim().isEmpty()) {
            throw new RuntimeException("Canceled.");
        }

        return pin;
    }

    /**
     * Generate a random number.
     * @param length The length on the number in bytes.
     * @return The generated number.
     */
    public static byte[] generateRandom(int length) {
        SecureRandom prng = new SecureRandom();
        byte []nonce = new byte[length];
        prng.nextBytes(nonce);
        return nonce;
    }

    /**
     * Get the subject's common name from the certificate.
     * @param cert The subject's certificate.
     * @return The subject's CN.
     */
    public static String getSubjectCN(X509Certificate cert) {
        /* Get the subject's DN. */
        X500Principal principal = cert.getSubjectX500Principal();
        X500Name x500dn = new X500Name(principal.getName());

        /* Get the subject's CN from the DN. */
        RDN cn = x500dn.getRDNs(BCStyle.CN)[0];
        String escaped = IETFUtils.valueToString(cn.getFirst().getValue());

        /* The returned CN has extra escaping - remove it. */
        return escaped.replaceAll("\\\\", "");
    }

}
