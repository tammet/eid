#ifndef ESTEID_CONSOLE_PING_SIGNER_H
#define ESTEID_CONSOLE_PING_SIGNER_H

#include <string.h>
#include <digidocpp/crypto/signer/EstEIDSigner.h>

using namespace digidoc;

/**
 * An implementation of EstEIDSigner that asks for the PIN through the console.
 * Requires unistd.h, so it only works on POSIX.1 compliant systems.
 */
class EstEIDConsolePinSigner : public EstEIDSigner
{
public:
        EstEIDConsolePinSigner(const std::string &driver) throw (SignException)
                : EstEIDSigner(driver) {}
protected:
        virtual std::string getPin(const PKCS11Cert &cert) throw (SignException);
};

#endif // ESTEID_CONSOLE_PIN_SIGNER_H
