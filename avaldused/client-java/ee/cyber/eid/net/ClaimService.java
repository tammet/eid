package ee.cyber.eid.net;

import java.io.ByteArrayOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.security.cert.CertificateEncodingException;
import java.security.cert.X509Certificate;

import org.bouncycastle.util.encoders.Base64Encoder;

import ee.cyber.eid.Claim;
import ee.cyber.eid.EidException;
import ee.sk.digidoc.DigiDocException;
import ee.sk.utils.ConfigManager;
import ee.sk.xmlenc.EncryptedData;
import ee.sk.xmlenc.factory.EncryptedDataParser;

/** Communicates with the claim handling service. */
public class ClaimService {

    /**
     * Submit the given claim to the claim handling service.
     * @param url Location of the claim handling service.
     * @param claim The claim to submit.
     * @param cert The submitter's authentication certificate.
     * @return An encrypted response, decryptable by cert's owner.
     */
    public static EncryptedData submit(String url, Claim claim,
            X509Certificate cert) throws EidException, DigiDocException,
            CertificateEncodingException, IOException {
        byte[] pem = getPEM(cert);
        InputStream resp = send(url, claim, pem);

        EncryptedDataParser parser = ConfigManager.instance()
                .getEncryptedDataParser();
        EncryptedData data = parser.readEncryptedData(resp);
        resp.close();
        return data;
    }

    /**
     * Returns the base64 encoded DER encoding of the certificate. It is
     * actually not quite PEM, because it does not have the required headers,
     * footers or wrapping, but this is the format that our service expects.
     * @param cert The certificate to encode.
     * @return The certificate's DER encoding in base64.
     */
    private static byte[] getPEM(X509Certificate cert)
            throws CertificateEncodingException, IOException {
        ByteArrayOutputStream buf = new ByteArrayOutputStream();
        byte[] der = cert.getEncoded();
        new Base64Encoder().encode(der, 0, der.length, buf);
        return buf.toByteArray();
    }

    /** Sends the claim and certificate to the service and returns it's
     * response body. */
    private static InputStream send(String url, Claim claim, byte[] cert)
            throws EidException, DigiDocException, IOException {
        HttpPOST post = new HttpPOST(url);
        post.addPart("cert", cert);
        /* The filename (3rd argument) is never actually used - only needed so
         * PHP would handle the field correctly. */
        post.addPart("claim", claim.getMimeType(), "claim", claim.toByteArray());
        return post.send();
    }

}
