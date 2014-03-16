using System;
using System.Text;
using System.IO;
using DIGIDOCLIBCOMLib;

namespace EidClient
{
    /// <summary>
    /// A response from the claim handling service.
    /// </summary>
    class Response
    {
        private static ComDigiDocLib ddl = new ComDigiDocLib();
        private static ComErrorInfo ei = new ComErrorInfo();

        private ComSignedDoc sdoc = new ComSignedDoc();

        /// <summary>
        /// Create a response from the given data.
        /// </summary>
        /// <param name="data">The response bytes.</param>
        public Response(byte[] data)
        {
            /* Since we can only parse containers from disk, write the data to a tempfile. */
            string filename = Path.GetTempFileName();
            File.WriteAllBytes(filename, data);

            /* And now parse it. */
            int err = sdoc.readSignedDoc(filename, 1, 4096);
            if (err != 0)
                throw new Exception("Error reading the container: " + ei.getErrorStringByCode(err));
        }

        /// <summary>
        /// Check that the signed response verifies.
        /// </summary>
        public void verify()
        {
            int err = sdoc.verifySigDoc("");
            if (err != 0)
                throw new Exception("Error verifying the signed document: " + ei.getErrorStringByCode(err));
        }

        /// <summary>
        /// Read the contents of the first data file in the container. The server response should contain
        /// no more data files.
        /// </summary>
        /// <returns>The contents of the response.</returns>
        public string getContent()
        {
            if (sdoc.nDataFiles < 1)
                throw new Exception("No data files in the container");

            /* For our purposes, we can get away with reading only the first one. */
            ComDataFile df = new ComDataFile();
            sdoc.getDataFile(0, df);

            /* Since we have the container already read to memory, we do not need to read it from disk again.
             * But extractDataFile checks for the unused parameters anyway, so give some dummy values. */
            string filename = Path.GetTempFileName();
            int err = ddl.extractDataFile(sdoc, "not used", filename, df.szId, "not used");
            if (err != 0)
                throw new Exception("Error extracting data file: " + ei.getErrorStringByCode(err));

            /* Since the COM-library only supports extracting to file, then read it back in. */
            StringBuilder buf = new StringBuilder();
            using (StreamReader sr = new StreamReader(filename))
            {
                String line;
                while ((line = sr.ReadLine()) != null)
                    buf.AppendLine(line);
            }

            return buf.ToString();
        }
    }
}
