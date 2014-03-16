<?php

/**
 * DigiDoc PHP client
 *
 * (This file has been heavily cropped and modified. The comments are also
 *  translated. The original is available at
 *  https://www.openxades.org/ddservice/
 *  We only use the parser and file manager from there. - Tiit Pikma)
 *
 * [---]
 *
 * Minimum requirements
 * - <b>PHP 4.3.4</b> or newer
 *   - (since a lot has been cropped and altered, then the required version has
 *      most probably changed in some direction. -tiit)
 * - <i>CURL-extension for HTTPS connections</i>
 * - <i>sessions must be enabled</i>
 *
 * - <b>Browser</b>
 * - All the necessary drivers for ID-card and DigiDoc installed. These can be
 *   found at http://www.id.ee/installer
 *
 * [---]
 *
 * (Intentionally left untranslated. -tiit)
 * @package        DigiDoc
 * @author         Roomet Kirotarp <Roomet.Kirotarp@hot.ee>
 * @version        0.0.8 2004.05.20 : Esimene versioon.
 * @version        1.0.0 2004.06.01 : Beeta, kõik funktsioonid realiseeritud.
 * @version        1.1.0 2004.06.03 : Tükeldatud mitmesse klassi.
 * @version        2.0.0 2004.06.16 : Parandatud ja täiendatud versioon.
 * @version        2.0.1 2004.07.20 : Kõrvaldatud hash-i BASE64 mittekodeerimise viga, eemaldatud allalaetavatel failidel UTF8-ks kodeerimine.
 * @version        2.0.2 2004.07.21 : Suurte (>7MB) failide korral ei toiminud varasemalt failid BASE64 data lisamine DigiDoc-i.
 * @version        2.0.3 2004.07.21 : Eesti susisevate häälikute korrektne töötlemine UTF-8 kodeeringuga failinnimes ja allkirjastamisega seotud väljadel (func Parser_DigiDoc::getDigiDoc()).
 * @version        2.0.5 2005.12.07 : Hashi arvutamist muudetud, enne räsi arvutamist viiakse andmefailis reavahetus \n kujule
 * @version        2.0.6 2006.07.26 :
                                    - Uuendatud PEAR::SOAP pakette, kasutuses versioon 0.9.4
                                    - Lisatud teenuse sertifikaadi verifitseerimine, sertifikaatide fail on määratav config faili parameetriga DD_SERVER_CA_FILE
 */
require_once "conf.php";

/**
 * Static methods needed to create the DigiDocService class from the WSDL file.
 */
class DigiDoc_WSDL {

    /*
     * Load WebService_DigiDocService_DigiDocService's definition. This must be
     * called before starting a session with the service.
     */
    function load_WSDL() {
        if(is_readable( DD_WSDL_FILE ) && filesize( DD_WSDL_FILE ) > 32) {
            include_once DD_WSDL_FILE;
        } else {
            $wsdl = new SOAP_WSDL( DD_WSDL, DigiDoc_WSDL::getConnect() );
            $wcode = $wsdl->generateProxyCode();
            eval( $wcode );
            File::saveLocalFile( DD_WSDL_FILE, "<?php\n".$wcode."\n?".">");
        }
    }

    /**
     * @return array Connection and proxy parameters.
     */
    function getConnect() {
        $ret=array();

    /* These need to be before the curl array, since
     * SOAP_WSDL::generateProxyCode is buggy. -tiit */
        if (defined('DD_PROXY_HOST') && DD_PROXY_HOST) $ret['proxy_host'] = DD_PROXY_HOST;
        if (defined('DD_PROXY_PORT') && DD_PROXY_PORT) $ret['proxy_port'] = DD_PROXY_PORT;
        if (defined('DD_PROXY_USER') && DD_PROXY_USER) $ret['proxy_user'] = DD_PROXY_USER;
        if (defined('DD_PROXY_PASS') && DD_PROXY_PASS) $ret['proxy_pass'] = DD_PROXY_PASS;
        if (defined('DD_TIMEOUT') && DD_TIMEOUT) $ret['timeout'] = DD_TIMEOUT;

        // 2010.07, ahto, fix proposal by Anttix
        // (see http://curl.haxx.se/libcurl/c/curl_easy_setopt.html)
        $ret['curl'] = array(
            CURLOPT_SSL_VERIFYPEER => 1,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CAINFO => DD_SERVER_CA_FILE
        );

        return $ret;
    } // end func

}


/**
 * DigiDoc XML file parser
 *
 * Parses the components of a DigiDoc file. Also transforms from a version
 * with embedded files to one with only hashes, and reverse.
 * @access  public
 * @package DigiDoc
 */
class DigiDoc_Parser {

    /**
     * The DigiDoc XML being parsed
     * @var     string
     * @access  private
     */
    var $xml;

    /**
     * The parsed file's format
     * @var     array
     * @access  private
     */
    var $format;

    /**
     * The parsed file's version
     * @var     array
     * @access  private
     */
    var $version;

    /**
     * Path prefix for session files.
     * @var     string
     * @access  private
     */
    var $workPath;


    /**
     * Constructor.
     * @param   string  $xml The DDoc XML file to be parsed
     */
    function DigiDoc_Parser ($xml='') {
        $this->xml = $xml;
        $this->setDigiDocFormatAndVersion($xml);
        if (!is_dir(DD_FILES))
            if(File::DirMake(DD_FILES) != DIR_ERR_OK)
                die('Error accessing workpath: ' . DD_FILES);
        $this->workPath = File::getFilePrefix();
    } // end func

    /**
     * Determines the document's format and version from the XML
     *
     * @param   string  $xml
     * @access  public
     * @return  array
     */
    function setDigiDocFormatAndVersion($xml='') {
        if ($xml=='')
            $xml=$this->xml;
        if ($xml) {
            preg_match("'(\<SignedDoc.*\/SignedDoc\>)'Us", $xml, $match);
            $content = $match[1];
            preg_match("'format=\"(.*)\"'Us", $content, $match);    $this->format = $match[1];
            preg_match("'version=\"(.*)\"'Us", $content, $match);   $this->version = $match[1];
        } else {
            $this->format = "DIGIDOC-XML";
            $this->version = "1.3";
        }
    }

    /**
     * Returns the document being parsed, optionally converting it from a form
     * with embedded files to one with only hashes or reverse. -tiit
     *
     * @access  public
     * @return  string
     */
    function getDigiDoc($xml_has_files = FALSE) {
        $files = $this->_getFilesXML($this->xml);
        $nXML = $this->xml;
        $func = $xml_has_files ? "file2hash" : "hash2file";

        while(list(,$file) = each($files)) {
            $nXML = str_replace($file, $this->$func($file), $nXML);
        } //while

        return $nXML;
    } // end func

    /**
     * Returns all the datafile tags from the document being parsed.
     *
     * @param   string  $xml The XML to parse
     * @access  private
     * @return  array
     */
    function _getFilesXML($xml) {
        $x = array();
        $a = $b = -1;

        while(($a=strpos($xml, '<DataFile', $a+1))!==FALSE && ($b=strpos($xml, '/DataFile>', $b+1))!==FALSE) {
            $x[] = preg_replace("'/DataFile>.*$'s", "/DataFile>", substr($xml, $a, $b));
        } //while

        if(!count($x)) {
            $a = $b = -1;
            while(($a=strpos($xml, '<DataFileInfo', $a+1))!==FALSE && ($b=strpos($xml, '/DataFileInfo>', $b+1))!==FALSE) {
                $x[] = preg_replace("'/DataFileInfo>.*$'s", "/DataFileInfo>", substr($xml, $a, $b));
            } //while
        }
        return $x;
    } // end func

    /**
     * Replaces the hashcodes in datafile tags with the corresponding files.
     *
     * @param   string  $xml
     * @access  private
     * @return  string
     */
    function hash2file($xml) {
        if( preg_match("'ContentType\=\"HASHCODE\"'s", $xml) ) {
            preg_match("'Id=\"(.*)\"'Us", $xml, $match);
            $Id = $match[1];
            $nXML = File::readLocalFile($this->workPath . $Id);
            return $nXML;
        } else {
            return $xml;
        } //else
    } // end func

    /**
     * Transforms a datafile tag from a form with embedded files to one with
     * only hashes. The original file is saved to local disk so it can be
     * recovered later.
     *
     * @param   string  $xml
     * @access  private
     * @return  string
     */
    function file2hash($xml) {
        // 2010.04, ahto. for digidoc 1.0, 1.1 ja 1.2 we don't compute the hash,
        // keep it as it is.
        if ($this->format == 'SK-XML'
                || $this->format == 'DIGIDOC-XML'
                    && ($this->version == '1.1' || $this->version == '1.2')
                || preg_match("'ContentType\=\"HASHCODE\"'s", $xml)) {
            return $xml;
        }

        preg_match("'Id=\"(.*)\"'Us", $xml, $match);
        $Id = $match[1]; // Find out the file's id
        File::saveLocalFile( $this->workPath . $Id, $xml); // save the file

        // 2009.09.07, Ahto, improved finding a hashcode over a datafile tag
        return $this->getDataFileBlockHashCoded($xml);
    } // end func

    function getDataFileBlockHashCoded($xml) {
        // KNOWN_BUGS #1: If a datafile with accented letters in the filename is
        // given, the that letter is returned in a different format. When
        // getXMLDataFileElementCanonized() is called again below, then things
        // start breaking. Idea: the XML parser shouldn't write the XML to a
        // file, perhaps that breaks it. Or the parser has a bug involving
        // accented letters and should be changed.

        // find the canonized form of a datafile tag, ContentType = EMBEDDED_BASE64
        $base64_canonized = $this->getXMLDataFileElementCanonized($xml, 'file');
        if ($base64_canonized == "")
            return $xml;

        $hash = base64_encode(pack("H*", sha1(str_replace("\r\n", "\n", $base64_canonized))));

        //find a datafile tag, ContentType = HASHCODE
        $hashcode_canonized =
            $this->getXMLDataFileElementCanonized($base64_canonized, 'filesha1', $hash);
        if ($hashcode_canonized == "")
            return $xml;

        return $hashcode_canonized;
    }

    /**
     * Canonizes a XML <DataFile> tag.
     *
     * @param   string  $xml    <DataFile> tag, ContentType = "EMBEDDED_BASE64",
     *                          canonized or not
     * @param   string  $type   "file" or "filesha1", depending on if we want
     *                          the ContentType to be "EMBEDDED_BASE64" or
     *                          "HASHCODE"
     * @param   string  $hash   The hashcode to use when type is "filesha1"
     * @returns string  A <Datafile> tag or an empty string on error.
     */
    function getXMLDataFileElementCanonized($xml, $type, $hash = "") {
        // datafile content in base64
        $content = $type == "file" ? strip_tags($xml) : "";

        // find the datafile tag's attribute array
        $attributes = $this->getXMLElementAttributes($xml);
        if ($attributes == Array())
            return "";

        // Remove some attributes from the beginning - these are not added in
        // alphabetical order during canonization.
        $xmlns = $attributes["xmlns"];
        unset($attributes["xmlns"]);

        // ContentType
        switch($type) {
            case 'file':
                $attributes["ContentType"] = "EMBEDDED_BASE64";
                break;
            case 'filesha1':
                $attributes["ContentType"] = "HASHCODE";
                break;
            default:
        }

        // alphabetize the attributes
        ksort($attributes);

        // start with xmlns...
        $attributes_str = 'xmlns="' . $xmlns . '"';

        // ...followed by the alphabetized attributes...
        foreach ($attributes as $attr => $value) {
            $attributes_str .= " $attr=\"$value\"";
        }

        // ...and ending with DigestType and DigestValue, if $type is HASHCODE
        if ($type == 'filesha1') {
            $attributes_str .= ' DigestType="sha1"';
            $attributes_str .= " DigestValue=\"$hash\"";
        }

        return '<DataFile ' . $attributes_str . '>' . $content . '</DataFile>';
    } // end func

    // 2010.03, ahto
    // Returns an array of xml attributes, empty array on error.
    function getXMLElementAttributes($xml_element)
    {
        require_once 'xml_parser.php';

        $tmp_f_name = tempnam(getcwd() . "/data/", 'w');
        file_put_contents($tmp_f_name, $xml_element);
        $error = XMLParseFile($parser, $tmp_f_name, 1);
        unlink($tmp_f_name);

        return strlen($error) ? Array() : $parser->structure["0"]["Attributes"];
    }

    /**
     * Find the hash code for a file.
     *
     * Generates the XML tag for representing a file and hashes it. The file is
     * saved to disk before hashing.
     * @param   array   $file   An array representing the file to process
     * @param   string  $Id     The file's identificator
     * @access  public
     * @return  array
     */
    function getFileHash($file, $Id='D0') {
        //2009.08.18, Ahto, html encode the filename
        $xml = sprintf($this->getXMLTemplate(),
                htmlspecialchars($file['name']), $Id, $file['MIME'], $file['size'],
                chunk_split(base64_encode($file['content']), 64, "\n"));
        File::saveLocalFile($this->workPath . $Id, $xml);

        $sh = base64_encode(pack("H*", sha1(str_replace("\r\n", "\n", $xml))));
        $ret['Filename'] = $file['name'];
        $ret['MimeType'] = $file['MIME'];
        $ret['ContentType'] = 'HASHCODE';
        $ret['Size'] = $file['size'];
        $ret['DigestType'] = 'sha1';
        $ret['DigestValue'] = $sh;
        return $ret;
    } // end func

    /**
     * XML template for creating DataFile tags.
     *
     * @access  private
     * @return  string
     */
    // 2010.03, ahto, this method is only used for cases, where a DataFile
    // element has to be created from scratch.
    function getXMLTemplate() {
        return '<DataFile'.($this->version == '1.3'?' xmlns="http://www.sk.ee/DigiDoc/v1.3.0#"':'')
                . ' ContentType="EMBEDDED_BASE64" Filename="%s" Id="%s" MimeType="%s" Size="%s"'
                . ($this->format == 'SK-XML'?' DigestType="sha1" DigestValue="%s"':'') . '>%s</DataFile>';
    } // end func

} // end class


/** File::DirMake Status: OK */
DEFINE("DIR_ERR_OK", 0);

/* File::DirMake Status: Path exists but not as directory */
DEFINE("DIR_ERR_NOTDIR", 1);

/* File::DirMake Status: Syntax error in path */
DEFINE("DIR_ERR_SYNTAX", 2);

/* File::DirMake Status: "mkdir" error with no parent */
DEFINE("DIR_ERR_EMKDIR_1", 3);

/* File::DirMake Status: "mkdir" error and parent exists */
DEFINE("DIR_ERR_EMKDIR_2", 4);

/* File::DirMake Status: "mkdir" error after creating parent */
DEFINE("DIR_ERR_EMKDIR_3", 5);

/**
 * File functions
 *
 * This class contains functions related files, such as uploading, saving, name
 * generation, and directory creation.
 *
 * @package DigiDoc
 */
class File{


    /**
     * constructor
     */
    function File() {
        return true;
    } // end func

    /**
     * Directory/subdirectory creation.
     *
     * Creates the given directory, including all parents, provided that we
     * have the permissions.
     * @param   string  $strPath    Kausta nimi
     * @access  public
     * @return  integer Tegevuse staatus
     */
    function DirMake($strPath) {
        // If path exists nothing else can be done
        if ( file_exists($strPath) )
            return is_dir($strPath) ? DIR_ERR_OK : DIR_ERR_NOTDIR;

        // Backwards references are not allowed
        if (ereg("\.\.", $strPath) != 0)
            return DIR_ERR_SYNTAX;

        // If it can create the directory that's all. If not then either path
        // contains several dirs or error such as "permission denied" happened
        if (@mkdir($strPath))
            return DIR_ERR_OK;

        // Gets the parent path. If none then there was a severe error
        $nPos = strrpos($strPath, "/");
        if (!($nPos > 0))
            return DIR_ERR_EMKDIR_1;

        $strParent = substr($strPath,0,$nPos);
        // If parent exists then there was a severe error
        if (file_exists($strParent))
            return DIR_ERR_EMKDIR_2;

        // If it can make the parent
        $nRet = File::DirMake($strParent);
        if ($nRet == DIR_ERR_OK)
           return mkdir($strPath) ? DIR_ERR_OK : DIR_ERR_EMKDIR_3;
        return $nRet;
    }

    /**
     * Sends the given content to the browser for download. Forces the browser
     * to prompt for a file download, regardless of the MIME type.
     *
     * @param   string  $name       The name of the file to display to the user.
     * @param   mixed   $content    The content of the download.
     * @param   string  $MIME       The MIME type of the content.
     * @param   string  $charset    The character sheet to use.
     * @access  public
     * @return  boolean
     */
    function saveAs($name, $content, $MIME = 'text/plain', $charset = '') {

        $name = str_replace("\'", "'", $name); //2009.11.18, Ahto, replace "\'" -> "'"
        //ob_end_clean();
        ob_clean();

        if($charset) {
            header( 'Content-Type: ' . $MIME . '; charset='.$charset );
        } else {
            header( 'Content-Type: ' . $MIME );
        } //else

        // Always expired, to avoid using the cache.
        header( 'Expires:' . gmdate('D, d M Y H:i:s') . ' GMT' );

        /* Different behavior for IE.
         * (is this really needed? -tiit) */
        $browser = File::getBrowser();
        if (File::getBrowser() == 'IE') {
            $susisevad = array("š","ž","Š","Ž");
            $eisusise = array("sh","zh","Sh","Zh");
            $name = str_replace($susisevad, $eisusise, $name);
            $name = mb_convert_encoding($name, 'ISO-8859-1', 'UTF-8');

            header('Cache-Control:must-revalidate, post-check=0, pre-check=0');
            header('Pragma:public');
        } else {
            header('Pragma:no-cache');
        }
        header('Content-Disposition:attachment; filename="'.$name.'"');
        header("Content-Disposition-type: attachment");
        header("Content-Transfer-Encoding: binary");
        echo $content;
        //exit; (let's make the call to exit manually, so we can perform some
        //       cleanup. -tiit)
    } // end func


    /**
     * Find the user's browser and OS.
     * (Everything cropped except IE detection. -tiit)
     *
     * @return  string  "IE" if the user agent belongs to Internet Explorer,
     *                  "OTHER" otherwise.
     */
    function getBrowser() {
        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            $HTTP_USER_AGENT = $_SERVER['HTTP_USER_AGENT'];
        } else if (!isset($HTTP_USER_AGENT)) {
            $HTTP_USER_AGENT = '';
        }

        if (preg_match('@MSIE [0-9].[0-9]{1,2}@', $HTTP_USER_AGENT)) {
            return 'IE';
        }
        return 'OTHER';
    } //function

    /**
     * Reads content from a local file.
     *
     * @param   string  $name   The name of the file to read.
     * @access  public
     * @return  mixed The file's content, or FALSE on error.
     */
    function readLocalFile($name) {
        if(is_readable($name)) {
            $content = file_get_contents($name);
            return $content;
        } else {
            return FALSE;
        } //else
    } // end func


    /**
     * Save content to a local file.
     *
     * @param   string  $name       The name of the created file.
     * @param   string  $content    The content to save.
     * @access  public
     * @return  boolean TRUE if saving succeeded, FALSE otherwise
     */
    function saveLocalFile($name, $content) {
        if(touch($name)) {
            $fh = fopen($name, 'wb');
            fwrite($fh, $content);
            fclose($fh);
            return TRUE;
        } else {
            return FALSE;
        } //else
    } // end func

    /**
     * Returns the path prefix used for temporary files. This includes the
     * target directory and session id prefix.
     * @param  string $suffix Filename to be concatenated to the prefix.
     * @return string The prefix for a temporary file's path.
     */
    function getFilePrefix($suffix = "") {
        return DD_FILES . session_id() . "_$suffix";
    }

} // end class
?>
