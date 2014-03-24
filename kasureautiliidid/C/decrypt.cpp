/**
 * Small utility for decrypting document containers.
 * Uses the DigiDoc C-library, rather than the C++ one, which does not
 * support CDOC.
 *
 * GCC example build instructions:
 * g++ decrypt.cpp -ldigidoc
 */
#include <string>
#include <libdigidoc/DigiDocEnc.h>
#include <libdigidoc/DigiDocEncSAXParser.h>

/**
 * An encrypted document container.
 * The constructor and destructor also initialize and finalize the C-library -
 * because of this only a single instance of this class can exist at any given
 * time.
 */
class EncryptedData
{
private:
	DEncEncryptedData *enc_data;

public:
	/**
	 * Read in the encrypted document container at the given path.
	 * @param in The path to the container.
	 */
	EncryptedData(const std::string &in) throw(const std::string)
	{
		int err = 0;

		/* Initialize the C-library. */
		initDigiDocLib();
		err = initConfigStore(NULL);
		if (err) {
			throw std::string("initConfigStore failed: ")
					+ std::string(getErrorString(err));
		}

		/* Read in the encrypted container. */
		err = dencSaxReadEncryptedData(&enc_data, in.c_str());
		if (err) {
			throw std::string("dencSaxReadEncryptedData failed: ")
					+ std::string(getErrorString(err));
		}
	}

	/**
	 * Frees all the resources connected to the encrypted data.
	 */
	~EncryptedData()
	{
		if (enc_data) {
			dencEncryptedData_free(enc_data);
		}
		cleanupConfigStore(NULL);
		finalizeDigiDocLib();
	}

	/**
	 * Decrypts the document container.
	 * @param pin The pin for the smartcard to use in decryption.
	 */
	void decrypt(const std::string &pin) throw(const std::string)
	{
		DEncEncryptedKey *enc_key = NULL;
		int err = 0;

		/* Check that the data is in fact encrypted. */
		if (enc_data->nDataStatus != DENC_DATA_STATUS_ENCRYPTED_AND_NOT_COMPRESSED
				&& enc_data->nDataStatus != DENC_DATA_STATUS_ENCRYPTED_AND_COMPRESSED) {
			throw std::string("decrypt failed: Data is not encrypted");
		}

		/* Find the transport key for the inserted smartcard. If none
		 * exist, then that card owner is not a recipient and throw an
		 * error. */
		err = dencEncryptedData_findEncryptedKeyByPKCS11(enc_data, &enc_key);
		if (err) {
			throw std::string("dencEncryptedData_findEncryptedKeyByPKCS11 failed: ")
					+ std::string(getErrorString(err));
		}

		/* Decrypt the container using the found transport key. */
		err = dencEncryptedData_decrypt(enc_data, enc_key, pin.c_str());
		if (err) {
			throw std::string("dencEncryptedData_decrypt failed: ")
					+ std::string(getErrorString(err));
		}
	}

	/**
	 * Saves the decrypted data to an output file.
	 * @param out The path where to save the data.
	 */
	void saveTo(const std::string &out) const throw(const std::string)
	{
		FILE *fp = NULL;
		int err;

		/* Check that the data is decrypted. */
		if (enc_data->nDataStatus != DENC_DATA_STATUS_UNENCRYPTED_AND_NOT_COMPRESSED) {
			throw std::string("saveTo failed: Data is not decrypted");
		}

		/* Attempt to open the output path for writing. */
		fp = fopen(out.c_str(), "wb");
		if (fp == NULL) {
			throw std::string("saveTo failed: Could not create/open output file");
		}

		/* fwrite actually returns the number of elements written, which
		 * in our our case should be 1. If it is zero, then an error
		 * occured. */
		err = fwrite(enc_data->mbufEncryptedData.pMem,
				enc_data->mbufEncryptedData.nLen, 1, fp);
		fclose(fp);
		if (err == 0) {
			throw std::string("saveTo failed: Could not write to file");
		}
	}
};

int main(int argc, char **argv)
{
	if (argc < 4) {
		printf("Usage: %s infile pin outfile\n", argv[0]);
		return 0;
	}

	std::string in = argv[1];
	if (in.empty()) {
		fprintf(stderr, "ERROR: no infile given\n");
		return 1;
	}

	std::string pin = argv[2];
	if (in.empty()) {
		fprintf(stderr, "ERROR: no pin given\n");
		return 2;
	}

	std::string out = argv[3];
	if (in.empty()) {
		fprintf(stderr, "ERROR: no outfile given\n");
		return 3;
	}

	try {
		EncryptedData encd(in);
		encd.decrypt(pin);
		encd.saveTo(out);
	} catch (const std::string &e) {
		fprintf(stderr, "Exception caught: %s\n", e.c_str());
		return 4;
	}

	return 0;
}
