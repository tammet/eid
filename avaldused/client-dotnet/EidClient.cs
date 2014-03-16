using System;
using System.IO;
using System.Security.Cryptography.X509Certificates;
using System.Text.RegularExpressions;

namespace EidClient
{
    /// <summary>
    /// Command line tool that authenticates the user, creates a claim and
    /// sends it to the service. The response's signature is checked and
    /// the response text is presented to the user.
    /// </summary>
    class EidClient
    {
        /// <summary>
        /// Default location of the clam handling service.
        /// </summary>
        private static string DEFAULT_URL = "https://localhost/eid/submit.php";

        [STAThread]
        static void Main(string[] args)
        {
            /* Take the location of the claim handling service as an optional parameter. */
            String url = DEFAULT_URL;
            if (args.Length > 0)
                url = args[0];

            Console.Write("Authenticating user...");
            X509Certificate2 cert = Authenticator.authenticate();
            Console.WriteLine("Ok.");

            Console.Write("Enter claim content: ");
            string content = Console.ReadLine();
            Claim claim = new Claim();
            claim.addContent(getSubject(cert) + '\n' + content);

            Console.Write("Reading personal data...");
            claim.addContent(PersonalData.ToString());
            Console.WriteLine("Ok.");

            Console.Write("Signing the claim...");
            claim.sign();
            Console.WriteLine("Ok.");

            Console.Write("Saving the document to disk...");
            string claimFile = Path.GetTempFileName();
            claim.saveToFile(claimFile);
            Console.WriteLine("Ok.");

            Console.Write("Submitting the claim...");
            byte[] responseBytes = ClaimService.submit(url, claimFile);
            Console.Write("Ok.");

            Response resp = new Response(responseBytes);
            resp.verify();
            Console.WriteLine("The service answered: " + resp.getContent());

            /* Pause the execution, so people have a chance to read the output. */
            Console.Write("Press Return to exit...");
            Console.ReadLine();
            return;
        }

        /// <summary>
        /// Returns the subject's name from the certificate in the form "GivenName Surname, Serial".
        /// </summary>
        /// <param name="cert">The subject's certificate.</param>
        /// <returns>The subject's formatted CN or the complete DN if pattern matching failed.</returns>
        private static string getSubject(X509Certificate2 cert)
        {
            /* Search for the Common Name and capture parts of it into groups. Alternatively you could
             * search for the values of SN, G and SERIALNUMBER.
             * For each match, group 0 will be the whole CN, group 1 the surname, group 2 the given name
             * and group 3 the serial number. */
            string pattern = "CN=\"(.+),(.+),(\\d+)\"";
            string DN = cert.Subject;
            foreach (Match match in Regex.Matches(DN, pattern))
                /* Return the first match. */
                return match.Groups[2].Value + ' ' + match.Groups[1].Value + ", " + match.Groups[3].Value;

            /* If no match found, return the whole DN. */
            return DN;
        }
    }
}
