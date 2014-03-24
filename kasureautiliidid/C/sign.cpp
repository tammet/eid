/**
 * Small utility for creating signed document containers.
 * The type of the container created will be determined by the output file's
 * extension.
 *
 * GCC example build instructions:
 * gcc sign.cpp common/EstEIDConsolePinSigner.cpp -ldigidocpp
 */
#include <algorithm>
#include <stdio.h>
#include <string.h>

#include <digidocpp/WDoc.h>
#include <digidocpp/Conf.h>
#include <digidocpp/Document.h>

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

ADoc::DocumentType getDocumentType(const std::string &out)
{
	size_t pos = out.rfind('.');
	if (pos != std::string::npos) {
		std::string ext = out.substr(pos + 1);
		std::transform(ext.begin(), ext.end(), ext.begin(), tolower);
		if (ext == "bdoc") {
			return ADoc::BDocType;
		}
		if (ext == "ddoc") {
			return ADoc::DDocType;
		}
	}
	/* Use DDOC as the default. */
	return ADoc::DDocType;
}

int sign(const std::vector<std::string> &in, const std::string &out)
{
	std::string file, mime;
	size_t pos;
	try {
		/* Create a new signer with the PKCS11 module specified in the
		 * configuration file. */
		EstEIDConsolePinSigner signer(
				Conf::getInstance()->getPKCS11DriverPath());
		WDoc doc(getDocumentType(out));

		/* Iterate through the input files and add them to the
		 * container with the specified MIME type (or default
		 * "file"). */
		std::vector<std::string>::const_iterator it;
		for (it = in.begin(); it < in.end(); it++) {
			pos = it->find_first_of(':');
			file = it->substr(0, pos);
			mime = (pos != std::string::npos) ?
				it->substr(pos + 1) : "application/octet-stream";
			doc.addDocument(Document(file, mime));
		}

		/* SPP and SR are optional, but let's add something here
		 * just to demonstrate using them. */
		SignatureProductionPlace spp(
				"city", "state", "postal", "country");
		signer.setSignatureProductionPlace(spp);

		SignerRole role("role");
		signer.setSignerRole(role);

		/* Sign the container with the signer. */
		doc.sign(&signer);

		/* Save the container to file. */
		doc.saveTo(out);
	} catch (const SignException &e) {
		fprintf(stderr, "Caught SignException: %s",
				traceException(e).c_str());
		return 1;
	} catch (const BDocException &e) {
		fprintf(stderr, "Caught BDocException: %s",
				traceException(e).c_str());
		return 1;
	} catch (const SignatureException &e) {
		fprintf(stderr, "Caught SignatureException: %s",
				traceException(e).c_str());
		return 1;
	} catch (const IOException &e) {
		fprintf(stderr, "Caught IOException: %s",
				traceException(e).c_str());
		return 1;
	}

	return 0;
}

int main(int argc, char **argv)
{
	if (argc < 3) {
		printf("Usage: %s infile[:mimetype]... outfile\n", argv[0]);
		return 0;
	}

	std::vector<std::string> in;
	std::string out = argv[argc - 1];
	for (int i = 1; i < argc - 1; i++) {
		std::string file = argv[i];
		if (!file.empty()) {
			in.push_back(file);
		}
	}

	if (in.empty()) {
		fprintf(stderr, "ERROR: no infiles given\n");
		return 1;
	}
	if (out.empty()) {
		fprintf(stderr, "ERROR: no outfile given\n");
		return 1;
	}

	initialize();
	int rc = sign(in, out);
	terminate();

	return rc;
}
