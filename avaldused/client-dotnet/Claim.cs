using System;
using System.IO;
using DIGIDOCLIBCOMLib;

namespace EidClient
{
    /// <summary>
    /// A helper class for creating signed claims.
    /// </summary>
    class Claim
    {
        private const string OCSP_URL = "http://www.openxades.org/cgi-bin/ocsp.cgi";

        private static ComErrorInfo ei = new ComErrorInfo();
        private ComSignedDoc sd;

        /// <summary>
        /// Creates a new helper class for creating claims.
        /// </summary>
        public Claim()
        {
            sd = new ComSignedDoc();
            sd.initialize(ComConstants.COM_DIGIDOC_XML_1_1_NAME, ComConstants.COM_DIGIDOC_XML_1_3_VER);
        }

        /// <summary>
        /// Add the given content to the container to be signed.
        /// It is first written to a temporary file and then added to the container.
        /// </summary>
        public void addContent(string content)
        {
            string filename = Path.GetTempFileName();
            using (StreamWriter outfile = new StreamWriter(filename))
            {
                outfile.Write(content);
            }
            addDataFile(filename, "text/plain");
        }

        /// <summary>Add the given file to the container to be signed.</summary>
        /// <param name="filename">Path to the file to add.</param>
        /// <param name="mimetype">The MIME type of the file.</param>
        /// <remark>Assumes UTF-8 charset.</remark>
        public void addDataFile(string filename, string mimetype)
        {
            addDataFile(filename, mimetype, ComConstants.COM_CHARSET_UTF_8);
        }

        /// <summary>Add the given file to the container to be signed.</summary>
        /// <param name="filename">Path to the file to add.</param>
        /// <param name="mimetype">The MIME type of the file.</param>
        /// <param name="charset">The character set the file is encoded in.</param>
        public void addDataFile(string filename, string mimetype, string charset)
        {
            ComDataFile df = new ComDataFile();
            sd.createDataFile(filename, ComConstants.COM_CONTENT_EMBEDDED_BASE64, mimetype, 0, null, 0, ComConstants.COM_DIGEST_SHA1_NAME, charset, df);
            sd.calculateDataFileSizeAndDigest(df.szId, filename, ComConstants.COM_DIGEST_SHA1);
        }

        /// <summary>
        /// Signs and OCSP confirms the container.
        /// </summary>
        public void sign()
        {
            int err;

            /* Calculate the signature. */
            ComSignatureInfo si = new ComSignatureInfo();
            sd.createSignatureInfo(si);
            sd.addAllDocInfos(si);
            err = si.calculateSignatureWithCSPEstID(sd, 0, "");
            if (err != 0)
                throw new Exception("Error signing the document: " + ei.getErrorStringByCode(err));

            /* Get OCSP confirmation. */
            err = sd.getConfirmation(si, null, null, OCSP_URL, null, null);
            if (err != 0)
                throw new Exception("Error getting confirmation: " + ei.getErrorStringByCode(err));

            /* Verify signature and OCSP response. */
            err = sd.verifySigDoc("");
            if (err != 0)
                throw new Exception ("Error verifying the signature: " + ei.getErrorStringByCode(err));
        }

        /// <summary>Saves the signed container to a file.</summary>
        /// <param name="filename">The path where to save the file.</param>
        public void saveToFile(string filename)
        {
            int err = sd.createSignedDoc(filename, "");
            if (err != 0)
                throw new Exception("Error saving the document: " + ei.getErrorStringByCode(err));
        }
    }
}
