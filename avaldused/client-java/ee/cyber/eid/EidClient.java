package ee.cyber.eid;

import java.security.cert.X509Certificate;
import java.util.List;
import java.util.Scanner;

import javax.smartcardio.CardException;

import ee.cyber.eid.card.CardUtil;
import ee.cyber.eid.card.EstEID;
import ee.cyber.eid.net.ClaimService;
import ee.cyber.eid.util.Util;
import ee.sk.digidoc.DigiDocException;
import ee.sk.utils.ConfigManager;
import ee.sk.xmlenc.EncryptedData;

/**
 * A desktop client for communicating with the claim handling service.
 * Usage: java EidClient [url] [-cfg config]
 */
public class EidClient {

    /** Default place to look for the configuration file. */
    private static final String DEFAULT_CONF = "jdigidoc.cfg";

    /** The default location of our claim handling service. */
    private static final String DEFAULT_URL = "https://localhost/eid/avaldused/submit.php";

    /** The default token index to use, i.e. 0 means we use the first card
     * reader we find. */
    private static final int TOKEN_INDEX = 0;

    public static void main(String[] argv) throws Exception {
        String cfg = DEFAULT_CONF;
        String url = DEFAULT_URL;

        /* Parse the arguments. */
        for (int i = 0; i < argv.length; i++) {
            if ("-cfg".equals(argv[i])) {
                if (++i < argv.length) {
                    cfg = argv[i];
                } else {
                    System.err.println("No parameter given after '-cfg'.");
                    return;
                }
            } else {
                url = argv[i];
            }
        }

        /* Check the configuration. */
        if (!ConfigManager.init(cfg)) {
            System.err.println("Add " + DEFAULT_CONF + " to the current "
                    + "directory or specify a configuration file as a "
                    + "command-line argument.");
            return;
        }

        /* Check if we have card terminals. */
        if (CardUtil.getTerminals().isEmpty()) {
            throw new CardException("No card terminals found");
        }

        /* Identify the user. */
        System.out.println("Identifying the user...");
        X509Certificate cert = Authenticator.authenticate(TOKEN_INDEX);
        System.out.println("Ok.");

        /* Find the subject's name.
         * We can't use ConvertUtils.getCommonName() from jdigidoc, because it
         * only returns the CN until the first ',' - on personal cards this
         * gives us only the surname. */
        String cn = Util.getSubjectCN(cert);
        String cnTokens[] = cn.split(",");
        String subject = (cnTokens.length != 3) ? cn
                : cnTokens[1] + ' ' + cnTokens[0] + ", " + cnTokens[2];

        /* Let the user insert something to submit. */
        System.out.print("Enter claim content: ");
        Scanner scanner = new Scanner(System.in);
        String content = scanner.nextLine();

        /* Create the claim, sign and verify it. */
        System.out.println("Reading personal data...");
        Claim claim = createClaim(subject + '\n' + content, TOKEN_INDEX);
        System.out.println("Signing the claim...");
        claim.sign(TOKEN_INDEX);
        if (claim.verify()) {
            System.out.println("Ok.");
        } else {
            System.err.println("Signing failed.");
            return;
        }

        /* Save the claim to disc for reference. */
        claim.saveTo("claim.ddoc");

        /* Submit the claim and get a response. */
        System.out.println("Submitting the claim...");
        EncryptedData encd = ClaimService.submit(url, claim, cert);
        if (validateEncryptedData(encd)) {
            System.out.println("Ok, got a valid response.");
        } else {
            System.err.println("Got an invalid response.");
            return;
        }

        /* Parse and verify the response. */
        System.out.println("Decrypting and processing the response...");
        Response resp = new Response(encd);
        resp.decrypt(cn, TOKEN_INDEX);
        try {
            resp.verify();
        } catch (Exception e) {
            System.err.println("Response signature verification failed: " + e.toString());
        }
        String responseContent = resp.getContent();
        if (responseContent == null) {
            System.err.println("The response is empty!");
            return;
        }
        System.out.println("Ok.");

        System.out.println("The service responded:");
        System.out.println(responseContent);
    }

    /** Create a new claim. */
    private static Claim createClaim(String content, int terminalIndex)
            throws Exception {
        /* Add the given content to a container. */
        Claim claim = new Claim();
        claim.addClaimFile(content);

        /* Read personal data from the card. */
        EstEID eid = new EstEID(terminalIndex);
        claim.addPersonalDataFile(eid.getPersonalData());

        return claim;
    }

    /** Validate an EncryptedData object. */
    @SuppressWarnings("unchecked")
    private static boolean validateEncryptedData(EncryptedData encd) {
        List<DigiDocException> errs = encd.validate();
        if (errs.size() > 0) {
            System.err.println("Could not validate response:");
            for (DigiDocException e : errs) {
                System.err.println(e.getMessage());
            }
            return false;
        }
        return true;
    }

}
