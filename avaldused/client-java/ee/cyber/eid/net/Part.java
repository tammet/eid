package ee.cyber.eid.net;

import java.io.ByteArrayOutputStream;
import java.io.IOException;
import java.nio.charset.StandardCharsets;

/**
 * A multipart/form-data part.
 * NB! Does NOT encode the values given to it, so please don't inject anything.
 */
public class Part {

    private String field;
    private String type;
    private String filename;
    private byte[] content;

    /**
     * Construct a new multipart/form-data part.
     * @param field The name of the form field.
     * @param content The contents of that field
     */
    public Part(String field, byte[] content) {
        this(field, null, null, content);
    }

    /**
     * Construct a new multipart/form-data part.
     * @param field The name of the form field.
     * @param type The field's content-type.
     * @param filename The name of the uploaded file (null if it's not a file).
     * @param content The contents of that field
     */
    public Part(String field, String type, String filename, byte[] content) {
        this.field = field;
        this.type = type;
        this.filename = filename;
        this.content = content;
    }

    /** Returns the multipart/form-data part as a byte array. */
    public byte[] toByteArray() throws IOException {
        StringBuilder header = new StringBuilder();
        header.append("Content-Disposition: form-data; name=\"" + field + '\"');
        if (filename != null) {
            header.append("; filename=\"" + filename + '\"');
        }
        header.append("\r\n");
        if (type != null) {
            header.append("Content-Type: " + type + "\r\n");
        }
        header.append("\r\n");

        /* Since the field names and content types we use in the header are all
         * 7bit ASCII encodable, then we don't need to worry about other
         * character sets. Very unportable, but suits our needs nicely. */
        ByteArrayOutputStream buf = new ByteArrayOutputStream();
        buf.write(header.toString().getBytes(StandardCharsets.US_ASCII));
        buf.write(content);
        return buf.toByteArray();
    }

}
