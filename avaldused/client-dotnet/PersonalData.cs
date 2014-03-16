using System;
using System.Text;

/* This library is not Gemalto-specific, although the namespace would lead you to believe otherwise. */
using GemCard;

namespace EidClient
{
    /// <summary>
    /// Class for reading personal data from an ID-card.
    /// </summary>
    class PersonalData
    {
        private static int NUM_RECORDS = 16;
        private static string[] records = new string[NUM_RECORDS];

        /* Commands used to read the personal records. */
        static APDUCommand
            apduSelectMF = new APDUCommand(0, 0xA4, 0, 0x0C, null, 0),
            apduSelectEEEE = new APDUCommand(0, 0xA4, 1, 0x0C, new byte[] { 0xEE, 0xEE }, 2),
            apduSelect5044 = new APDUCommand(0, 0xA4, 2, 0x0C, new byte[] { 0x50, 0x44 }, 2),
            apduReadRecord = new APDUCommand(0, 0xB2, 0, 4, null, 0),
            apduGetResponse = new APDUCommand(0, 0xC0, 0, 0, null, 0);

        const ushort SC_OK = 0x9000;
        const byte SC_PENDING = 0x61;

        /// <summary>
        /// Reads personal records from the currently inserted ID-card into the <tt>records</tt> array.
        /// </summary>
        private static void readRecords()
        {
            CardNative iCard = new CardNative();
            APDUParam apduParam = new APDUParam();
            APDUResponse apduResp;

            string[] readers = iCard.ListReaders();

            /* Change this index to the one where your card is inserted. This is usually 0,
             * unless you have multiple readers. */
            string reader = readers[0];
            iCard.Connect(reader, SHARE.Shared, PROTOCOL.T0);
            Console.Write("Reading personal data from " + reader + "...");

            try
            {
                /* Select the Master File */
                apduResp = iCard.Transmit(apduSelectMF);
                if (apduResp.Status != SC_OK)
                    throw new Exception("Select MF failed: " + apduResp.ToString());

                /* Select EEEE */
                apduResp = iCard.Transmit(apduSelectEEEE);
                if (apduResp.Status != SC_OK)
                    throw new Exception("Select EEEE failed: " + apduResp.ToString());

                /* Select 5044 */
                apduResp = iCard.Transmit(apduSelect5044);
                if (apduResp.Status != SC_OK)
                    throw new Exception("Select 5044 failed: " + apduResp.ToString());

                /* Read the records */
                for (byte i = 1; i <= NUM_RECORDS; i++)
                {
                    /* Send the read command */
                    apduParam.Reset();
                    apduParam.P1 = i;
                    apduReadRecord.Update(apduParam);
                    apduResp = iCard.Transmit(apduReadRecord);
                    if (apduResp.SW1 != SC_PENDING)
                        throw new Exception("Read record failed: " + apduResp.ToString());

                    /* Read the response */
                    apduParam.Reset();
                    apduParam.Le = apduResp.SW2;
                    apduGetResponse.Update(apduParam);
                    apduResp = iCard.Transmit(apduGetResponse);
                    if (apduResp.Status != SC_OK)
                        throw new Exception("Get response failed: " + apduResp.ToString());
                    records[i - 1] = Encoding.UTF7.GetString(apduResp.Data).Trim();
                }
            }
            finally
            {
                iCard.Disconnect(DISCONNECT.Leave);
            }
        }

        /// <summary>
        /// Read personal records from the card and return a formatted string.
        /// </summary>
        /// <returns>Personal records on the currently inserted ID-card.</returns>
        public static new string ToString()
        {
            readRecords();
            return "Surname: " + records[0] + '\n'
                + "Given name 1: " + records[1] + '\n'
                + "Given name 2: " + records[2] + '\n'
                + "Gender: " + records[3] + '\n'
                + "Citizenship: " + records[4] + '\n'
                + "Date of birth: " + records[5] + '\n'
                + "Personal code: " + records[6] + '\n'
                + "Document number: " + records[7] + '\n'
                + "Date of expiry: " + records[8] + '\n'
                + "Place of birth: " + records[9] + '\n'
                + "Date of issue: " + records[10] + '\n'
                + "Residence permit type: " + records[11] + '\n'
                + "Remark 1: " + records[12] + '\n'
                + "Remark 2: " + records[13] + '\n'
                + "Remark 3: " + records[14] + '\n'
                + "Remark 4: " + records[15];
        }
    }
}
