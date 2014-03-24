#include <unistd.h>
#include "EstEIDConsolePinSigner.h"

using namespace digidoc;

std::string EstEIDConsolePinSigner::getPin(const PKCS11Cert &cert)
	throw (SignException)
{
        size_t pin_max = 16;
        char *pass;

        printf("Selected token %s.\n", cert.token.label.c_str());
        pass = getpass("Enter PIN or leave blank to cancel: ");
        if (pass == NULL) {
                SignException e(__FILE__, __LINE__, "Pin acquisition "
                                "failed.");
                e.setCode(Exception::PINFailed);
                throw e;
        }

        pass[pin_max - 1] = '\0';
        std::string pin(pass);
        memset(pass, 0, pin_max); // Delete pass from memory

        if (pin.empty()) {
                SignException e(__FILE__, __LINE__, "Pin acquisition "
                                "canceled.");
                e.setCode(Exception::PINCanceled);
                throw e;
        }
        return pin;
}
