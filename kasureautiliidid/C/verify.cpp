/**
 * Small utility for verifying signed document containers.
 *
 * GCC example build instructions:
 * gcc verify.cpp -ldigidocpp
 */
#include <stdio.h>
#include <string.h>
#include <digidocpp/WDoc.h>
#include <digidocpp/Signature.h>
#include <digidocpp/Document.h>

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

bool checkSignatures(ADoc &doc)
{
	unsigned int count = doc.signatureCount();
	const Signature *signature;
	bool ok = true;

	if (count == 0) {
		printf("No signatures found! Skipping verification.\n");
		return ok;
	}

	for (unsigned int i = 0; i < count; i++) {
		printf("Verifying signature %i of %i...", i + 1, count);
		try {
			signature = doc.getSignature(i);
			signature->validateOnline();
			printf("OK\n");
		} catch (const SignatureException &e) {
			printf("FAILED\n");
			fprintf(stderr, "Caught SignatureException: %s",
					traceException(e).c_str());
			ok = false;
		}
	}
	return ok;
}

bool extractDocuments(ADoc &doc)
{
	unsigned int count = doc.documentCount();
	std::string dest;
	bool ok = true;

	if (count == 0) {
		printf("Empty container! Nothing to extract.\n");
		return ok;
	}

	for (unsigned int i = 0; i < count; i++) {
		printf("Extracting %i of %i...", i + 1, count);
		dest.clear();
		try {
			Document document = doc.getDocument(i);
			dest = document.getFileName();
			printf("(%s) ", dest.c_str());
			document.saveAs(dest);
			printf("OK\n");
		} catch (const IOException &e) {
			printf("FAILED\n");
			fprintf(stderr, "Caught IOException: %s",
					traceException(e).c_str());
			ok = false;
		}
	}
	return ok;
}

int verify(const std::string &in)
{
	try {
		WDoc doc(in);
		if (!checkSignatures(doc)) {
			return 1;
		}
		if (!extractDocuments(doc)) {
			return 2;
		}
	} catch (const IOException &e) {
		fprintf(stderr, "Caught IOException: %s",
				traceException(e).c_str());
		return 3;
	} catch (const BDocException &e) {
		fprintf(stderr, "Caught BDocException: %s",
				traceException(e).c_str());
		return 4;
	}

	return 0;
}

int main(int argc, char **argv)
{
	if (argc < 2) {
		printf("Usage: %s infile\n", argv[0]);
		return 0;
	}

	std::string in = argv[1];
	if (in.empty()) {
		fprintf(stderr, "ERROR: no infiles given\n");
		return 1;
	}

	initialize();
	int rc = verify(in);
	terminate();

	return rc;
}
