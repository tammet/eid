#include "digidoc.h"

int initialize()
{
	initDigiDocLib();
	return initConfigStore(NULL);
}

void finalize()
{
	cleanupConfigStore(NULL);
	finalizeDigiDocLib();
}

char *get_error(int code)
{
	return getErrorString(code);
}

SignedDoc *new_signature_container()
{
	SignedDoc *sdoc = NULL;
	const char *format = ConfigItem_lookup("DIGIDOC_FORMAT");
	const char *version = ConfigItem_lookup("DIGIDOC_VERSION");
	int err = SignedDoc_new(&sdoc, format, version);
	if (err != ERR_OK) {
		fprintf(stderr, "new_signature_container: %s\n",
				get_error(err));
		return NULL;
	}
	return sdoc;
}

int add_data_file(SignedDoc *sdoc, const char *filename, const char *mimetype)
{
	DataFile *dfile = NULL;
	int err = DataFile_new(&dfile, sdoc, NULL, filename,
			CONTENT_EMBEDDED_BASE64, mimetype, 0, NULL,
			0, NULL, CHARSET_UTF_8);
	if (err != ERR_OK) {
		return err;
	}
	return calculateDataFileSizeAndDigest(sdoc, dfile->szId,
			filename, DIGEST_SHA1);
}

int sign_container(SignedDoc *sdoc, char *pin, const char *role, const char *city,
		const char *state, const char *zip, const char *country)
{
	SignatureInfo *sinfo = NULL;
	if (!pin) {
		pin = (char *) ConfigItem_lookup("AUTOSIGN_PIN");
	}
	return pin ? signDocument(sdoc, &sinfo, pin, role, city, state, zip, country)
		: ERR_PKCS_LOGIN;
}

int save_signed_document(SignedDoc *sdoc, const char *filename)
{
	return createSignedDoc(sdoc, NULL, filename);
}

void free_signature_container(SignedDoc *sdoc)
{
	SignedDoc_free(sdoc);
}

DEncEncryptedData *new_encryption_container()
{
	DEncEncryptedData *encd = NULL;
	int err = dencEncryptedData_new(&encd, DENC_XMLNS_XMLENC,
			DENC_ENC_METHOD_AES128, 0, 0, 0);
	if (err == ERR_OK) {
		err = dencMetaInfo_SetLibVersion(encd);
	}
	if (err == ERR_OK) {
		err = dencMetaInfo_SetFormatVersion(encd);
	}
	if (err != ERR_OK) {
		fprintf(stderr, "new_encryption_container: %s\n",
				get_error(err));
		return NULL;
	}
	return encd;
}

int encrypt_ddoc(DEncEncryptedData *encd, const char *filename)
{
	SignedDoc *sdoc = NULL;
	DigiDocMemBuf mbuf = { NULL, 0 };
	int err;

	err = ddocSaxReadSignedDocFromFile(&sdoc, filename, 0, 0);
	if (err == ERR_OK) {
		err = dencOrigContent_registerDigiDoc(encd, sdoc);
	}

	if (err == ERR_OK) {
		err = ddocReadFile(filename, &mbuf);
	}
	if (err == ERR_OK) {
		err = dencEncryptedData_AppendData(encd, mbuf.pMem, mbuf.nLen);
	}
	ddocMemBuf_free(&mbuf);

	if (err == ERR_OK) {
		int compress = ConfigItem_lookup_int("DENC_COMPRESS_MODE",
				DENC_COMPRESS_ALLWAYS);
		err = dencEncryptedData_encryptData(encd, compress);
	}
	return err;
}

int add_recipient(DEncEncryptedData *encd, const char *cert_pem, int cert_len)
{
	DEncEncryptedKey *enck = NULL;
	X509 *cert = NULL;
	DigiDocMemBuf mbuf = { NULL, 0 };
	int err;

	err = ddocDecodeX509PEMData(&cert, cert_pem, cert_len);
	if (err == ERR_OK) {
		err = ddocCertGetSubjectCN(cert, &mbuf);
	}
	if (err == ERR_OK) {
		err = dencEncryptedKey_new(encd, &enck, cert,
				DENC_ENC_METHOD_RSA1_5, NULL,
				(char *) mbuf.pMem, NULL, NULL);
	}
	ddocMemBuf_free(&mbuf);
	return err;
}

int save_encrypted_data(DEncEncryptedData *encd, const char *filename)
{
	return dencGenEncryptedData_writeToFile(encd, filename);
}

void free_encryption_container(DEncEncryptedData *encd)
{
	dencEncryptedData_free(encd);
}
