package ee.cyber.eid.net;

import java.io.BufferedInputStream;
import java.io.BufferedOutputStream;
import java.io.BufferedReader;
import java.io.IOException;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.io.UnsupportedEncodingException;
import java.math.BigInteger;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.List;

import ee.cyber.eid.EidException;
import ee.cyber.eid.util.Util;

/** A multipart/form-data HTTP POST request. */
public class HttpPOST {

    private static final byte[] CRLF = new byte[] { '\r', '\n' };

    private HttpURLConnection connection;
    private List<Part> parts = new ArrayList<Part>();
    private String boundary;

    /** Create a new HTTP POST request for the given url. */
    public HttpPOST(String url) throws IOException {
        boundary = generateBoundary();

        connection = (HttpURLConnection) new URL(url).openConnection();
        connection.setDoOutput(true);
        connection.setRequestMethod("POST");
        connection.setRequestProperty("Content-Type",
                "multipart/form-data; boundary=" + boundary);
    }

    /** Add a part to the multipart form. */
    public void addPart(Part part) {
        parts.add(part);
    }

    /** Add a part to the multipart form. */
    public void addPart(String field, byte[] content) {
        addPart(new Part(field, content));
    }

    /** Add a part to the multipart form. */
    public void addPart(String field, String type, String filename,
            byte[] content) {
        addPart(new Part(field, type, filename, content));
    }

    /**
     * Sends the parts to the URL and returns the response.
     * The caller needs to close the returned stream itself.
     */
    public InputStream send() throws EidException, IOException {
        /* Send our request. */
        OutputStream out = new BufferedOutputStream(connection.getOutputStream());
        writeTo(out);
        out.close();

        /* Check if the server responded with OK. */
        checkResponse();

        /* Return the response stream. */
        return new BufferedInputStream(connection.getInputStream());
    }

    /**
     * Checks if the current connection return HTTP 200 OK.
     * Else throw an Exception with the error message in the body.
     */
    private void checkResponse() throws IOException,
            UnsupportedEncodingException, EidException {
        int httpCode = connection.getResponseCode();
        if (httpCode != HttpURLConnection.HTTP_OK) {
            /* Check the encoding of the error message. */
            String charset = connection.getContentEncoding();
            if (charset == null) {
                charset = StandardCharsets.UTF_8.name();
            }

            /* Read the error message the server gave us. */
            BufferedReader in = new BufferedReader(new InputStreamReader(
                    connection.getErrorStream(), charset));
            StringBuilder buf = new StringBuilder();
            String err;
            while ((err = in.readLine()) != null) {
                buf.append(err);
            }

            throw new EidException("Server responded with HTTP " + httpCode
                    + ": " + buf.toString());
        }
    }

    /**
     * Writes the request's body.
     * Appends all the requested parts with multipart/form-data boundaries
     * to the body.
     * @param out The stream to write to.
     */
    private void writeTo(OutputStream out) throws IOException {
        if (parts.isEmpty()) {
            return;
        }
        /* Since we only have [0-9a-f\r\n] in the boundaries we can use
         * 7bit ASCII. */
        byte[] boundaryBytes = ("--" + boundary).getBytes(
                StandardCharsets.US_ASCII);

        for (Part part : parts) {
            out.write(boundaryBytes);
            out.write(CRLF);
            out.write(part.toByteArray());
            out.write(CRLF);
        }
        out.write(boundaryBytes);
        out.write(new byte[] { '-', '-' });
    }

    /** Generates a multipart boundary. */
    private String generateBoundary() {
        /* Boundary's max length is 70, so generate 35 random bytes and convert
         * them to hexadecimal. */
        byte[] rnd = Util.generateRandom(35);
        return String.format("%x", new BigInteger(rnd).abs());
    }

}
