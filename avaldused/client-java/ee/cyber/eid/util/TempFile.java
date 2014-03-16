package ee.cyber.eid.util;

import java.io.BufferedWriter;
import java.io.File;
import java.io.IOException;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;

/**
 * A helper for creating temporary files, since JDigiDoc insists on handling
 * everything as Files.
 * Uses java.nio.file package classes that require at least JDK 1.7.
 */
public class TempFile {

    public static final String MIME_TYPE = "text/plain";

    private static Path path;
    private Path file;

    public TempFile(String name) throws IOException {
        if (path == null) {
            path = Files.createTempDirectory("eid");
            path.toFile().deleteOnExit(); // FIXME doesn't seem to work
        }
        file = Files.createFile(Paths.get(path.toString(), name));
    }

    public TempFile(String name, String content) throws IOException {
        this(name);
        writeToFile(content);
    }

    public void writeToFile(String content) throws IOException {
        BufferedWriter out = Files.newBufferedWriter(file, StandardCharsets.UTF_8);
        out.write(content);
        out.close();
    }

    public File getFile() {
        return file.toFile();
    }

}
