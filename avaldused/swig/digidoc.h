/**
 * A simple interface to libdigidoc signing and encryption functions for use
 * in SWIG wrappers.
 */
#include <libdigidoc/DigiDocConfig.h>
#include <libdigidoc/DigiDocEnc.h>
#include <libdigidoc/DigiDocLib.h>

/**
 * Initialize the library.
 * @return An error code.
 * */
int initialize();

/** Finalize the library. */
void finalize();

/**
 * Get error string for code.
 * @param code The error code.
 * @return The error message for the given code.
 */
char *get_error(int code);

/**
 * Create a new signature container.
 * Format and version are read from the configuration file as the values of
 * DIGIDOC_FORMAT and DIGIDOC_VERSION respectively.
 * Since there were some limitations with PHP and SWIG, this function hides
 * the error codes given by the underlying library calls and instead returns
 * NULL on errors. While not great, we can accept this, as there are only a
 * few cases where errors are given on object creation.
 * @return A new instance of SignedDoc.
 */
SignedDoc *new_signature_container();

/**
 * Add data files to be signed.
 * @param sdoc     The container where to add the data file.
 * @param filename The file to be added.
 * @param mimetype The MIME type of this file.
 * @return An error code.
 */
int add_data_file(SignedDoc *sdoc, const char *filename, const char *mimetype);

/**
 * Signs the container with the given metadata.
 * @param sdoc    The container to sign.
 * @param pin     The PIN used to login to the smartcard. If this is NULL, then
 *                AUTOSIGN_PIN is read from the configuration file.
 * @param role    The role of the signer.
 * @param city    The city where the signing took place.
 * @param state   The state where the signing took place.
 * @param zip     The zip code where the signing took place.
 * @param country The country where the signing took place.
 * @return An error code.
 */
int sign_container(SignedDoc *sdoc, char *pin, const char *role, const char *city,
		const char *state, const char *zip, const char *country);

/**
 * Saves the container to disc.
 * @param sdoc     The container to save.
 * @param filename The name of the created file.
 * @return An error code.
 */
int save_signed_document(SignedDoc *sdoc, const char *filename);

/**
 * Free a SignedDoc.
 * @param sdoc The SignedDoc to free.
 */
void free_signature_container(SignedDoc *sdoc);

/**
 * Create a new encryption container.
 * Since there were some limitations with PHP and SWIG, this function hides
 * the error codes given by the underlying library calls and instead returns
 * NULL on errors. While not great, we can accept this, as there are only a
 * few cases where errors are given on object creation.
 * @return A new instance of DEncEncryptedData.
 */
DEncEncryptedData *new_encryption_container();

/**
 * Encrypt a (possibly signed) .ddoc container.
 * This must only be called once per encryption container. This is an accepted
 * limitation, since we only use it to encrypt one signed container.
 * @param encd     The container where to add the .ddoc.
 * @param filename The path to the .ddoc container to encrypt.
 * @return An error code.
 */
int encrypt_ddoc(DEncEncryptedData *encd, const char *filename);

/**
 * Add a recipient for the container.
 * Encrypts the transport key with the recipient's public key, so they have
 * access to it.
 * @param encd     The container, where to add a recipient.
 * @param cert_pem The recipient's certificate in PEM format.
 * @param cerT_len Length of the certificate.
 * @return An error code.
 */
int add_recipient(DEncEncryptedData *encd, const char *cert_pem, int cert_len);

/**
 * Saves the container to disc.
 * @param encd     The container to save.
 * @param filename The name of the created file.
 * @return An error code.
 */
int save_encrypted_data(DEncEncryptedData *encd, const char *filename);

/**
 * Free a DEncEncryptedData.
 * @param encd The DEncEncryptedData to free.
 */
void free_encryption_container(DEncEncryptedData *encd);
