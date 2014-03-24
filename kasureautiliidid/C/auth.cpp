/**
 * Small utilty for authenticating EstEID token owners.
 * A nonce is generated and signed using the token's authentication key. If the
 * signature verifies, then we can be sure that the user has access to the
 * private key of that token. We consider this to be proof enough that we
 * are dealing with the owner of the EstEID token.
 *
 * Uses libdigidocpp for signing and signature verification, but libdigidoc for
 * OCSP status checking.
 *
 * GCC example build instructions:
 * gcc auth.cpp common/EstEIDConsolePinSigner.cpp -ldigidocpp -ldigidoc
 */
#include <stdio.h>
#include <string.h>
#include <openssl/err.h>
#include <openssl/rand.h>

#include <digidocpp/ADoc.h>
#include <digidocpp/Conf.h>
#include <digidocpp/crypto/cert/X509Cert.h>
#include <libdigidoc/DigiDocConfig.h>
#include <libdigidoc/DigiDocError.h>

/*
 * Newest sources have an EstEIDConsolePinSigner which asks for the PIN, but
 * it is not available in the latest RIA distributables (as of 02.05.2012).
 * In case we do have it, define HAVE_ESTEID_CONSOLE_PIN_SIGNER to use it,
 * otherwise use the custom implementation (without Windows support).
 */
#ifdef HAVE_ESTEID_CONSOLE_PIN_SIGNER
#include <digidocpp/crypto/signer/EstEIDConsolePinSigner.h>
#else
#include "common/EstEIDConsolePinSigner.h"
#endif

using namespace digidoc;

/**
 * A _very_ slightly modified version of EstEIDSigner: we check for the
 * digital-signature (0th) bit instead of non-repudiation (1st) in key
 * usage.
 */
class EstEIDAuthSigner : public EstEIDConsolePinSigner
{
public:
	EstEIDAuthSigner(const std::string &driver) throw(SignException)
		: EstEIDConsolePinSigner(driver) {}
protected:
	virtual PKCS11Cert selectSigningCertificate(
			const std::vector<PKCS11Cert> &certificates)
		const throw(SignException)
	{
		if (certificates.empty()) {
			throw SignException(__FILE__, __LINE__, "Could not "
					"find certificate");
		}

		std::vector<PKCS11Cert>::const_iterator it;
		for (it = certificates.begin(); it < certificates.end(); it++) {
			// Get the key usage extension
			ASN1_BIT_STRING *usage = (ASN1_BIT_STRING*)
				X509_get_ext_d2i(it->cert, NID_key_usage, NULL,
						NULL);
			if (!usage) {
				continue;
			}
			// Check for digital-signature bit (0)
			if (ASN1_BIT_STRING_get_bit(usage, 0)) {
				ASN1_BIT_STRING_free(usage);
				return *it;
			}
			ASN1_BIT_STRING_free(usage);
		}
		throw SignException(__FILE__, __LINE__, "Could not find "
				"certificate");
	}
};

std::string traceException(const Exception &e)
{
	std::string msg = e.getMsg() + '\n';
	Exception::Causes causes = e.getCauses();
	Exception::Causes::iterator it;
	for (it = causes.begin(); it < causes.end(); it++) {
		msg += traceException(*it);
	}
	return msg;
}

/**
 * Initializes a digest for us to sign.
 * The digest is actually a random number which we pretend to be a SHA512 hash.
 * @param digest The digest to initialize.
 */
void generateNonce(Signer::Digest &digest) throw(const char *)
{
	digest.type = NID_sha512;
	digest.length = 64; // 512 bits = 64 bytes

	// digest.digest is const, so use a helper variable
	unsigned char *nonce = new unsigned char[digest.length];
	if (!RAND_bytes(nonce, digest.length)) {
		delete [] nonce;
		throw ERR_reason_error_string(ERR_get_error());
	}
	digest.digest = nonce;
}

/**
 * Verifies a signature.
 * @param nonce     The nonce that was signed.
 * @param signature The signature to verify.
 * @param cert      A certificate containing the public key used to verify the
 *                  signature.
 */
void verifySignature(const Signer::Digest &nonce,
		const Signer::Signature &signature, X509 *cert)
	throw(const char *)
{
	int err;
	if (!cert) {
		throw "Subject certificate is NULL";
	}

	/* Read the public key from the certificate. */
	EVP_PKEY *evp_key = X509_get_pubkey(cert);
	if (!evp_key || EVP_PKEY_type(evp_key->type) != EVP_PKEY_RSA) {
		EVP_PKEY_free(evp_key);
		throw ERR_reason_error_string(ERR_get_error());
	}

	/* Verify the signature with the RSA key. */
	RSA *rsa_key = EVP_PKEY_get1_RSA(evp_key);
	err = RSA_verify(nonce.type, nonce.digest, nonce.length,
			signature.signature, signature.length,
			rsa_key);

	RSA_free(rsa_key);
	EVP_PKEY_free(evp_key);

	if (err != 1) {
		throw ERR_reason_error_string(ERR_get_error());
	}
}

/* Check that the certificate is valid.
 * Asks a configured OCSP responder if the given certificate is still valid.
 * @param cert The certificate to check.
 */
void checkOCSPStatus(X509 *cert)
{
	OCSP_RESPONSE *resp = NULL;
	int err;
	if (!cert) {
		throw "Certificate to check is NULL";
	}

	/* Initialize the libdigidoc configuration, where the OCSP responder
	 * settings are kept. */
	err = initConfigStore(NULL);
	if (err) {
		throw getErrorString(err);
	}

	/* Verify the certificate via OCSP. */
	err = ddocVerifyCertByOCSP(cert, &resp);
	cleanupConfigStore(NULL);
	if (err) {
		throw getErrorString(err);
	}
}

int main(int argc, char **argv)
{
	initialize();

	EstEIDAuthSigner signer(Conf::getInstance()->getPKCS11DriverPath());
	Signer::Signature signature = { NULL, 0 };
	Signer::Digest digest;
	X509 *cert = NULL;
	/* We need the have_digest boolean to know if we have some memory to
	 * free later - digest.digest is constant, so we can't initialize it
	 * as NULL. */
	bool have_digest = false;

	try {
		printf("Generating nonce...");
		generateNonce(digest);
		have_digest = true;
		printf("Done\n");

		/* getCert() needs to be before signer.sign(), because it
		 * chooses the certificate to use for signing. */
		printf("Selecting a certificate...");
		cert = signer.getCert();
		printf("Done\n");

		/* Sign the generated nonce with the selected certificate. */
		printf("Signing the nonce...");
		signer.sign(digest, signature);
		printf("Done\n");

		/* Verify the signature we just created. */
		printf("Verifying the signature...");
		verifySignature(digest, signature, cert);
		printf("OK\n");

		/* Make sure that the signature is still valid. */
		printf("Checking certificate validity via OCSP...");
		checkOCSPStatus(cert);
		printf("OK\n");

		printf("Successfully authenticated %s.\n",
				X509Cert(cert).getSubject().c_str());
	} catch (const SignException &e) {
		fprintf(stderr, "Caught SignException: %s",
				traceException(e).c_str());
	} catch (const char *e) {
		fprintf(stderr, "Caught exception: %s\n", e);
	}

	if (signature.signature) {
		delete [] signature.signature;
	}
	if (have_digest) {
		delete [] digest.digest;
	}

	terminate();
	return 0;
}
