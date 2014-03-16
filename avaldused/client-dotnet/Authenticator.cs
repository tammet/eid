using System;
using System.Security.Cryptography;
using System.Security.Cryptography.X509Certificates;

namespace EidClient
{
    /// <summary>
    /// Authenticates the user with their EstEID token using a Cryptographic Service Provider (CSP).
    /// </summary>
    class Authenticator
    {
        /// <summary>
        /// The distinguished name of the issuer, whose signed certificates are allowed for
        /// authentication use. Modify this as needed or change the
        /// X509FindType.FindByIssuerDistinguishedName parameter below if some other
        /// criteria is desired.
        /// </summary>
        private static string ISSUER = "E=pki@sk.ee, CN=TEST of ESTEID-SK 2011, O=AS Sertifitseerimiskeskus, C=EE";
        private static string HASH_ALG = "SHA1";
        private static int NONCE_LENGTH = 256;

        /// <summary>
        /// Authenticates the user by having them sign a nonce.
        /// </summary>
        /// <returns>The certificate used to authenticate the user.</returns>
        public static X509Certificate2 authenticate()
        {
            /* Have the user select their certificate. */
            X509Certificate2 authCert = selectCertificate();
            RSACryptoServiceProvider rsa = authCert.PrivateKey as RSACryptoServiceProvider;

            byte[] nonce = generateNonce();

            /* Here we have to trust the CSP to ask the user for the PIN. If there is a need to also
             * check that the CSP has behaved correctly, then we should additionally verify the
             * signature, either via the use of some third-party software (e.g OpenSSL) or by
             * implementing the RSA algorithm ourselves. */
            byte[] sig = rsa.SignData(nonce, HASH_ALG);

            verifyCertificate(authCert);
            return authCert;
        }

        /// <summary>
        /// Provides the user with a selection of all valid certificates for them to choose one.
        /// </summary>
        /// <returns>The certificate chosen.</returns>
        private static X509Certificate2 selectCertificate()
        {
            // Get the certificate store for the current user.
            X509Store store = new X509Store(StoreName.My, StoreLocation.CurrentUser);
            try
            {
                /* Place all certificates in an X509Certificate2Collection object. */
                store.Open(OpenFlags.ReadOnly | OpenFlags.OpenExistingOnly);
                X509Certificate2Collection certs = store.Certificates;

                /* Find all TEST-ESTEID 2011 certificates, i.e certificates for test-ID-cards. */
                certs = certs.Find(X509FindType.FindByIssuerDistinguishedName, ISSUER, true);

                /* Filter out the non-repudiation certificates and keep only the authentication (digital signature) ones. */
                certs = certs.Find(X509FindType.FindByKeyUsage, X509KeyUsageFlags.DigitalSignature, false);//true);
                if (certs.Count == 0)
                    throw new Exception("No suitable authentication certificates found.");

                certs = X509Certificate2UI.SelectFromCollection(
                    certs, "Select certificate", "Select a certificate for authentication", X509SelectionFlag.SingleSelection);
                if (certs.Count == 0)
                    throw new Exception("No authentication certificate selected.");

                return certs[0];
            }
            finally
            {
                store.Close();
            }
        }

        /// <summary>
        /// Generates a nonce of length NONCE_LENGTH.
        /// </summary>
        /// <returns>The generated nonce.</returns>
        private static byte[] generateNonce()
        {
            RNGCryptoServiceProvider rng = new RNGCryptoServiceProvider();
            byte[] nonce = new byte[NONCE_LENGTH];
            rng.GetBytes(nonce);
            return nonce;
        }

        /// <summary>
        /// Verifies the whole certification chain for the authentication certificate.
        /// This does NOT do an OCSP-verification, but does check the online CRLs.
        /// </summary>
        /// <param name="cert">The certificate to verify.</param>
        private static void verifyCertificate(X509Certificate2 cert)
        {
            X509Chain chain = new X509Chain();
            chain.ChainPolicy.RevocationMode = X509RevocationMode.Online;
            chain.ChainPolicy.RevocationFlag = X509RevocationFlag.EntireChain;
            if (!chain.Build(cert))
            {
                Console.WriteLine();
                foreach (X509ChainStatus status in chain.ChainStatus)
                    Console.WriteLine(status.StatusInformation);
                throw new Exception("Selected authentication certificate is not valid.");
            }
        }
    }
}
