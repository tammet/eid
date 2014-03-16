using System;
using System.Collections.Generic;
using System.Text;
using System.IO;
using System.Net;

namespace EidClient
{
    /// <summary>
    /// Class for submitting claims to the claim handling service.
    /// </summary>
    class ClaimService
    {
        /// <summary>
        /// Class for submitting multipart/form-data POST requests.
        /// Written with help from: http://www.briangrinstead.com/blog/multipart-form-post-in-c
        /// </summary>
        private class MultipartPOST
        {
            private static readonly Encoding ENCODING = Encoding.UTF8;

            /// <summary>
            /// A single part in the multipart request.
            /// </summary>
            public class Part
            {
                /// <summary>
                /// The name of the part.
                /// </summary>
                public string field { get; set; }

                /// <summary>
                /// The filename, if the part is a file; null otherwise.
                /// </summary>
                public string filename { get; set; }

                /// <summary>
                /// The content type of the file; null if the part isn't a file.
                /// </summary>
                public string type { get; set; }

                /// <summary>
                /// The contents of the part.
                /// </summary>
                public byte[] content { get; set; }

                /// <summary>
                /// Creates a non-file part.
                /// </summary>
                /// <param name="field">The name of the part.</param>
                /// <param name="content">The contents of the part.</param>
                public Part(string field, byte[] content)
                {
                    this.field = field;
                    this.filename = null;
                    this.type = null;
                    this.content = content;
                }

                /// <summary>
                /// Adds a part containing a file.
                /// </summary>
                /// <param name="field">The name of the part. Will also be used as the filename.</param>
                /// <param name="path">The path to the file to be read.</param>
                /// <param name="type">The MIME-type of the file.</param>
                public Part(string field, string path, string type)
                {
                    this.field = field;
                    this.filename = field;
                    this.type = type;

                    /* Read the file's contents. */
                    FileStream fs = new FileStream(path, FileMode.Open, FileAccess.Read);
                    content = new byte[fs.Length];
                    fs.Read(content, 0, content.Length);
                    fs.Close();
                }

                /// <summary>
                /// Converts the part into the byte array to be spent to the service.
                /// </summary>
                public byte[] toByteArray()
                {
                    /* Construct the header. */
                    StringBuilder header = new StringBuilder();
                    header.Append("Content-Disposition: form-data; name=\"" + field + '\"');
                    if (filename != null)
                        header.Append("; filename=\"" + filename + '\"');
                    header.Append("\r\n");

                    if (type != null)
                        header.Append("Content-Type: " + type + "\r\n");

                    header.Append("\r\n");

                    /* Assemble the whole part. */
                    MemoryStream buf = new MemoryStream();
                    byte[] headerBytes = ENCODING.GetBytes(header.ToString());
                    buf.Write(headerBytes, 0, headerBytes.Length);
                    buf.Write(content, 0, content.Length);

                    return buf.ToArray();
                }
            }

            /// <summary>
            /// Post a multipart/form-data POST request.
            /// </summary>
            /// <param name="url">The address of the service.</param>
            /// <param name="parts">A list of parts in the query.</param>
            /// <returns>The service's response.</returns>
            public static HttpWebResponse post(string url, List<Part> parts)
            {
                /* Generate a boundary and construct the request. */
                string boundary = String.Format("{0:N}", Guid.NewGuid());
                string contentType = "multipart/form-data; boundary=" + boundary;
                byte[] formData = getMultipartFormData(parts, boundary);

                HttpWebRequest request = WebRequest.Create(url) as HttpWebRequest;
                if (request == null)
                    throw new NullReferenceException("Created request is not an HTTP request.");

                /* Set up the request properties. */
                request.Method = "POST";
                request.ContentType = contentType;
                request.ContentLength = formData.Length;

                /* Send the form data to the request. */
                using (Stream requestStream = request.GetRequestStream())
                {
                    requestStream.Write(formData, 0, formData.Length);
                    requestStream.Close();
                }
                return request.GetResponse() as HttpWebResponse;
            }

            /// <summary>
            /// Constructs a multipart/form-data request from the given parts and boundary.
            /// </summary>
            /// <param name="parts">The parts of the request.</param>
            /// <param name="boundary">The boundary between the parts.</param>
            /// <returns>The constructed request.</returns>
            private static byte[] getMultipartFormData(List<Part> parts, string boundary)
            {
                MemoryStream buf = new MemoryStream();
                byte[] boundaryBytes = ENCODING.GetBytes("--" + boundary);
                byte[] crlf = ENCODING.GetBytes("\r\n");

                foreach (Part part in parts)
                {
                    buf.Write(boundaryBytes, 0, boundaryBytes.Length);
                    buf.Write(crlf, 0, crlf.Length);
                    byte[] partBytes = part.toByteArray();
                    buf.Write(partBytes, 0, partBytes.Length);
                    buf.Write(crlf, 0, crlf.Length);
                }
                buf.Write(boundaryBytes, 0, boundaryBytes.Length);
                buf.Write(ENCODING.GetBytes("--"), 0, 2);

                return buf.ToArray();
            }
        }

        /// <summary>
        /// Submit a claimto the claim handling service.
        /// </summary>
        /// <param name="url">The address of the service.</param>
        /// <param name="path">The path to the claim to send.</param>
        /// <param name="cert">The certificate for which to encrypt the response.</param>
        /// <returns>The service's response.</returns>
        public static byte[] submit(String url, string path)
        {
            List<MultipartPOST.Part> parts = new List<MultipartPOST.Part>();

            /* Since the COM-library does not support encryption, we ask the server
             * not to encrypt the response. */
            parts.Add(new MultipartPOST.Part("nocrypt", new byte[] { 1 }));
            parts.Add(new MultipartPOST.Part("claim", path, "application/x-ddoc"));

            HttpWebResponse resp;
            try
            {
                resp = MultipartPOST.post(url, parts);
            }
            catch (WebException e)
            {
                /* Status code will not be 200, so we re-throw the exception further down. */
                resp = e.Response as HttpWebResponse;
                if (resp == null)
                {
                    throw e;
                }
            }

            /* Read the response into memory. */
            Stream respStream = resp.GetResponseStream();
            MemoryStream bodyStream = new MemoryStream();
            byte[] block = new byte[4096];
            int read;

            while ((read = respStream.Read(block, 0, block.Length)) != 0)
            {
                bodyStream.Write(block, 0, read);
            };
            respStream.Close();
            byte[] body = bodyStream.ToArray();
            bodyStream.Close();

            /* Throw an exception if the server gave us an error. */
            if (resp.StatusCode != HttpStatusCode.OK)
                throw new Exception("HTTP Status: " + resp.StatusDescription + '\n' + Encoding.UTF8.GetString(body));

            return body;
        }
    }
}
