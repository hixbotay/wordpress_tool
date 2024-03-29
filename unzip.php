<?php

/**
 * Akeeba Kickstart
 * A JSON-powered archive extraction tool
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / AkeebaBackup.com
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

/*
    Akeeba Kickstart - The server-side archive extraction wizard
    Copyright (C) 2008-2013  Nicholas K. Dionysopoulos / AkeebaBackup.com

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

define('KICKSTART',1);
define('VERSION', '4.0.0');
define('KICKSTARTPRO', '0');
// Uncomment the following line to enable Kickstart's debug mode
//define('KSDEBUG', 1);

// Used during development
if (!defined('KSDEBUG') && isset($_SERVER) && isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'local.web') !== false))
{
	define('KSDEBUG', 1);
}

define('KSWINDOWS', substr(PHP_OS, 0, 3) == 'WIN');

if(!defined('KSROOTDIR'))
{
	define('KSROOTDIR', dirname(__FILE__));
}

if(defined('KSDEBUG')) {
	ini_set('error_log', KSROOTDIR.'/kickstart_error_log' );
	if(file_exists(KSROOTDIR.'/kickstart_error_log')) {
		@unlink(KSROOTDIR.'/kickstart_error_log');
	}
	error_reporting(E_ALL | E_STRICT);
} else {
	@error_reporting(E_NONE);
}

// ==========================================================================================
// IIS missing REQUEST_URI workaround
// ==========================================================================================

/*
 * Based REQUEST_URI for IIS Servers 1.0 by NeoSmart Technologies
 * The proper method to solve IIS problems is to take a look at this:
 * http://neosmart.net/dl.php?id=7
 */

//This file should be located in the same directory as php.exe or php5isapi.dll

if (!isset($_SERVER['REQUEST_URI']))
{
	if (isset($_SERVER['HTTP_REQUEST_URI']))
	{
		$_SERVER['REQUEST_URI'] = $_SERVER['HTTP_REQUEST_URI'];
		//Good to go!
	}
	else
	{
		//Someone didn't follow the instructions!
		if(isset($_SERVER['SCRIPT_NAME']))
		$_SERVER['HTTP_REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
		else
		$_SERVER['HTTP_REQUEST_URI'] = $_SERVER['PHP_SELF'];
		if($_SERVER['QUERY_STRING']){
			$_SERVER['HTTP_REQUEST_URI'] .=  '?' . $_SERVER['QUERY_STRING'];
		}
		//WARNING: This is a workaround!
		//For guaranteed compatibility, HTTP_REQUEST_URI *MUST* be defined!
		//See product documentation for instructions!
		$_SERVER['REQUEST_URI'] = $_SERVER['HTTP_REQUEST_URI'];
	}
}

// Define the cacert.pem location, if it exists
$cacertpem = KSROOTDIR . '/cacert.pem';
if(is_file($cacertpem)) {
	if(is_readable($cacertpem)) {
		define('AKEEBA_CACERT_PEM', $cacertpem);
	}
}
unset($cacertpem);

// Loads other PHP files containing extra Kickstart features
$dh = @opendir(KSROOTDIR);
if($dh === false) return;
while($filename = readdir($dh)) {
	if(!is_file($filename)) continue;
	if(substr($filename, 0, 10) != 'kickstart.') continue;
	if(substr($filename, -4) != '.php') continue;
	include_once $filename;
}



/**
 * Akeeba Restore
 * A JSON-powered JPA, JPS and ZIP archive extraction library
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / Akeeba Ltd.
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

define('_AKEEBA_RESTORATION', 1);
defined('DS') or define('DS', DIRECTORY_SEPARATOR);

// Unarchiver run states
define('AK_STATE_NOFILE', 0); // File header not read yet
define('AK_STATE_HEADER', 1); // File header read; ready to process data
define('AK_STATE_DATA', 2); // Processing file data
define('AK_STATE_DATAREAD', 3); // Finished processing file data; ready to post-process
define('AK_STATE_POSTPROC', 4); // Post-processing
define('AK_STATE_DONE', 5); // Done with post-processing

/* Windows system detection */
if (!defined('_AKEEBA_IS_WINDOWS'))
{
	if (function_exists('php_uname'))
	{
		define('_AKEEBA_IS_WINDOWS', stristr(php_uname(), 'windows'));
	}
	else
	{
		define('_AKEEBA_IS_WINDOWS', DIRECTORY_SEPARATOR == '\\');
	}
}

// Get the file's root
if (!defined('KSROOTDIR'))
{
	define('KSROOTDIR', dirname(__FILE__));
}
if (!defined('KSLANGDIR'))
{
	define('KSLANGDIR', KSROOTDIR);
}

// Make sure the locale is correct for basename() to work
if (function_exists('setlocale'))
{
	@setlocale(LC_ALL, 'en_US.UTF8');
}

// fnmatch not available on non-POSIX systems
// Thanks to soywiz@php.net for this usefull alternative function [http://gr2.php.net/fnmatch]
if (!function_exists('fnmatch'))
{
	function fnmatch($pattern, $string)
	{
		return @preg_match(
			'/^' . strtr(addcslashes($pattern, '/\\.+^$(){}=!<>|'),
				array('*' => '.*', '?' => '.?')) . '$/i', $string
		);
	}
}

// Unicode-safe binary data length function
if (!function_exists('akstringlen'))
{
	if (function_exists('mb_strlen'))
	{
		function akstringlen($string)
		{
			return mb_strlen($string, '8bit');
		}
	}
	else
	{
		function akstringlen($string)
		{
			return strlen($string);
		}
	}
}

/**
 * Gets a query parameter from GET or POST data
 *
 * @param $key
 * @param $default
 */
function getQueryParam($key, $default = null)
{
	$value = $default;

	if (array_key_exists($key, $_REQUEST))
	{
		$value = $_REQUEST[$key];
	}

	if (get_magic_quotes_gpc() && !is_null($value))
	{
		$value = stripslashes($value);
	}

	return $value;
}

// Debugging function
function debugMsg($msg)
{
	if (!defined('KSDEBUG'))
	{
		return;
	}

	$fp = fopen('debug.txt', 'at');

	fwrite($fp, $msg . "\n");
	fclose($fp);
}

/**
 * Akeeba Restore
 * A JSON-powered JPA, JPS and ZIP archive extraction library
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / Akeeba Ltd.
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

/**
 * Akeeba Backup's JSON compatibility layer
 *
 * On systems where json_encode and json_decode are not available, Akeeba
 * Backup will attempt to use PEAR's Services_JSON library to emulate them.
 * A copy of this library is included in this file and will be used if and
 * only if it isn't already loaded, e.g. due to PEAR's auto-loading, or a
 * 3PD extension loading it for its own purposes.
 */

/**
 * Converts to and from JSON format.
 *
 * JSON (JavaScript Object Notation) is a lightweight data-interchange
 * format. It is easy for humans to read and write. It is easy for machines
 * to parse and generate. It is based on a subset of the JavaScript
 * Programming Language, Standard ECMA-262 3rd Edition - December 1999.
 * This feature can also be found in  Python. JSON is a text format that is
 * completely language independent but uses conventions that are familiar
 * to programmers of the C-family of languages, including C, C++, C#, Java,
 * JavaScript, Perl, TCL, and many others. These properties make JSON an
 * ideal data-interchange language.
 *
 * This package provides a simple encoder and decoder for JSON notation. It
 * is intended for use with client-side Javascript applications that make
 * use of HTTPRequest to perform server communication functions - data can
 * be encoded into JSON notation for use in a client-side javascript, or
 * decoded from incoming Javascript requests. JSON format is native to
 * Javascript, and can be directly eval()'ed with no further parsing
 * overhead
 *
 * All strings should be in ASCII or UTF-8 format!
 *
 * LICENSE: Redistribution and use in source and binary forms, with or
 * without modification, are permitted provided that the following
 * conditions are met: Redistributions of source code must retain the
 * above copyright notice, this list of conditions and the following
 * disclaimer. Redistributions in binary form must reproduce the above
 * copyright notice, this list of conditions and the following disclaimer
 * in the documentation and/or other materials provided with the
 * distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN
 * NO EVENT SHALL CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS
 * OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR
 * TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE
 * USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE.
 *
 * @category
 * @package     Services_JSON
 * @author      Michal Migurski <mike-json@teczno.com>
 * @author      Matt Knapp <mdknapp[at]gmail[dot]com>
 * @author      Brett Stimmerman <brettstimmerman[at]gmail[dot]com>
 * @copyright   2005 Michal Migurski
 * @version     CVS: $Id: restore.php 612 2011-05-19 08:26:26Z nikosdion $
 * @license     http://www.opensource.org/licenses/bsd-license.php
 * @link        http://pear.php.net/pepr/pepr-proposal-show.php?id=198
 */

if(!defined('JSON_FORCE_OBJECT'))
{
	define('JSON_FORCE_OBJECT', 1);
}

if(!defined('SERVICES_JSON_SLICE'))
{
	/**
	 * Marker constant for Services_JSON::decode(), used to flag stack state
	 */
	define('SERVICES_JSON_SLICE',   1);

	/**
	 * Marker constant for Services_JSON::decode(), used to flag stack state
	 */
	define('SERVICES_JSON_IN_STR',  2);

	/**
	 * Marker constant for Services_JSON::decode(), used to flag stack state
	 */
	define('SERVICES_JSON_IN_ARR',  3);

	/**
	 * Marker constant for Services_JSON::decode(), used to flag stack state
	 */
	define('SERVICES_JSON_IN_OBJ',  4);

	/**
	 * Marker constant for Services_JSON::decode(), used to flag stack state
	 */
	define('SERVICES_JSON_IN_CMT', 5);

	/**
	 * Behavior switch for Services_JSON::decode()
	 */
	define('SERVICES_JSON_LOOSE_TYPE', 16);

	/**
	 * Behavior switch for Services_JSON::decode()
	 */
	define('SERVICES_JSON_SUPPRESS_ERRORS', 32);
}

/**
 * Converts to and from JSON format.
 *
 * Brief example of use:
 *
 * <code>
 * // create a new instance of Services_JSON
 * $json = new Services_JSON();
 *
 * // convert a complexe value to JSON notation, and send it to the browser
 * $value = array('foo', 'bar', array(1, 2, 'baz'), array(3, array(4)));
 * $output = $json->encode($value);
 *
 * print($output);
 * // prints: ["foo","bar",[1,2,"baz"],[3,[4]]]
 *
 * // accept incoming POST data, assumed to be in JSON notation
 * $input = file_get_contents('php://input', 1000000);
 * $value = $json->decode($input);
 * </code>
 */
if(!class_exists('Akeeba_Services_JSON'))
{
	class Akeeba_Services_JSON
	{
	   /**
	    * constructs a new JSON instance
	    *
	    * @param    int     $use    object behavior flags; combine with boolean-OR
	    *
	    *                           possible values:
	    *                           - SERVICES_JSON_LOOSE_TYPE:  loose typing.
	    *                                   "{...}" syntax creates associative arrays
	    *                                   instead of objects in decode().
	    *                           - SERVICES_JSON_SUPPRESS_ERRORS:  error suppression.
	    *                                   Values which can't be encoded (e.g. resources)
	    *                                   appear as NULL instead of throwing errors.
	    *                                   By default, a deeply-nested resource will
	    *                                   bubble up with an error, so all return values
	    *                                   from encode() should be checked with isError()
	    */
	    function Akeeba_Services_JSON($use = 0)
	    {
	        $this->use = $use;
	    }

	   /**
	    * convert a string from one UTF-16 char to one UTF-8 char
	    *
	    * Normally should be handled by mb_convert_encoding, but
	    * provides a slower PHP-only method for installations
	    * that lack the multibye string extension.
	    *
	    * @param    string  $utf16  UTF-16 character
	    * @return   string  UTF-8 character
	    * @access   private
	    */
	    function utf162utf8($utf16)
	    {
	        // oh please oh please oh please oh please oh please
	        if(function_exists('mb_convert_encoding')) {
	            return mb_convert_encoding($utf16, 'UTF-8', 'UTF-16');
	        }

	        $bytes = (ord($utf16{0}) << 8) | ord($utf16{1});

	        switch(true) {
	            case ((0x7F & $bytes) == $bytes):
	                // this case should never be reached, because we are in ASCII range
	                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                return chr(0x7F & $bytes);

	            case (0x07FF & $bytes) == $bytes:
	                // return a 2-byte UTF-8 character
	                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                return chr(0xC0 | (($bytes >> 6) & 0x1F))
	                     . chr(0x80 | ($bytes & 0x3F));

	            case (0xFFFF & $bytes) == $bytes:
	                // return a 3-byte UTF-8 character
	                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                return chr(0xE0 | (($bytes >> 12) & 0x0F))
	                     . chr(0x80 | (($bytes >> 6) & 0x3F))
	                     . chr(0x80 | ($bytes & 0x3F));
	        }

	        // ignoring UTF-32 for now, sorry
	        return '';
	    }

	   /**
	    * convert a string from one UTF-8 char to one UTF-16 char
	    *
	    * Normally should be handled by mb_convert_encoding, but
	    * provides a slower PHP-only method for installations
	    * that lack the multibye string extension.
	    *
	    * @param    string  $utf8   UTF-8 character
	    * @return   string  UTF-16 character
	    * @access   private
	    */
	    function utf82utf16($utf8)
	    {
	        // oh please oh please oh please oh please oh please
	        if(function_exists('mb_convert_encoding')) {
	            return mb_convert_encoding($utf8, 'UTF-16', 'UTF-8');
	        }

	        switch(strlen($utf8)) {
	            case 1:
	                // this case should never be reached, because we are in ASCII range
	                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                return $utf8;

	            case 2:
	                // return a UTF-16 character from a 2-byte UTF-8 char
	                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                return chr(0x07 & (ord($utf8{0}) >> 2))
	                     . chr((0xC0 & (ord($utf8{0}) << 6))
	                         | (0x3F & ord($utf8{1})));

	            case 3:
	                // return a UTF-16 character from a 3-byte UTF-8 char
	                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                return chr((0xF0 & (ord($utf8{0}) << 4))
	                         | (0x0F & (ord($utf8{1}) >> 2)))
	                     . chr((0xC0 & (ord($utf8{1}) << 6))
	                         | (0x7F & ord($utf8{2})));
	        }

	        // ignoring UTF-32 for now, sorry
	        return '';
	    }

	   /**
	    * encodes an arbitrary variable into JSON format
	    *
	    * @param    mixed   $var    any number, boolean, string, array, or object to be encoded.
	    *                           see argument 1 to Services_JSON() above for array-parsing behavior.
	    *                           if var is a strng, note that encode() always expects it
	    *                           to be in ASCII or UTF-8 format!
	    *
	    * @return   mixed   JSON string representation of input var or an error if a problem occurs
	    * @access   public
	    */
	    function encode($var)
	    {
	        switch (gettype($var)) {
	            case 'boolean':
	                return $var ? 'true' : 'false';

	            case 'NULL':
	                return 'null';

	            case 'integer':
	                return (int) $var;

	            case 'double':
	            case 'float':
	                return (float) $var;

	            case 'string':
	                // STRINGS ARE EXPECTED TO BE IN ASCII OR UTF-8 FORMAT
	                $ascii = '';
	                $strlen_var = strlen($var);

	               /*
	                * Iterate over every character in the string,
	                * escaping with a slash or encoding to UTF-8 where necessary
	                */
	                for ($c = 0; $c < $strlen_var; ++$c) {

	                    $ord_var_c = ord($var{$c});

	                    switch (true) {
	                        case $ord_var_c == 0x08:
	                            $ascii .= '\b';
	                            break;
	                        case $ord_var_c == 0x09:
	                            $ascii .= '\t';
	                            break;
	                        case $ord_var_c == 0x0A:
	                            $ascii .= '\n';
	                            break;
	                        case $ord_var_c == 0x0C:
	                            $ascii .= '\f';
	                            break;
	                        case $ord_var_c == 0x0D:
	                            $ascii .= '\r';
	                            break;

	                        case $ord_var_c == 0x22:
	                        case $ord_var_c == 0x2F:
	                        case $ord_var_c == 0x5C:
	                            // double quote, slash, slosh
	                            $ascii .= '\\'.$var{$c};
	                            break;

	                        case (($ord_var_c >= 0x20) && ($ord_var_c <= 0x7F)):
	                            // characters U-00000000 - U-0000007F (same as ASCII)
	                            $ascii .= $var{$c};
	                            break;

	                        case (($ord_var_c & 0xE0) == 0xC0):
	                            // characters U-00000080 - U-000007FF, mask 110XXXXX
	                            // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                            $char = pack('C*', $ord_var_c, ord($var{$c + 1}));
	                            $c += 1;
	                            $utf16 = $this->utf82utf16($char);
	                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
	                            break;

	                        case (($ord_var_c & 0xF0) == 0xE0):
	                            // characters U-00000800 - U-0000FFFF, mask 1110XXXX
	                            // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                            $char = pack('C*', $ord_var_c,
	                                         ord($var{$c + 1}),
	                                         ord($var{$c + 2}));
	                            $c += 2;
	                            $utf16 = $this->utf82utf16($char);
	                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
	                            break;

	                        case (($ord_var_c & 0xF8) == 0xF0):
	                            // characters U-00010000 - U-001FFFFF, mask 11110XXX
	                            // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                            $char = pack('C*', $ord_var_c,
	                                         ord($var{$c + 1}),
	                                         ord($var{$c + 2}),
	                                         ord($var{$c + 3}));
	                            $c += 3;
	                            $utf16 = $this->utf82utf16($char);
	                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
	                            break;

	                        case (($ord_var_c & 0xFC) == 0xF8):
	                            // characters U-00200000 - U-03FFFFFF, mask 111110XX
	                            // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                            $char = pack('C*', $ord_var_c,
	                                         ord($var{$c + 1}),
	                                         ord($var{$c + 2}),
	                                         ord($var{$c + 3}),
	                                         ord($var{$c + 4}));
	                            $c += 4;
	                            $utf16 = $this->utf82utf16($char);
	                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
	                            break;

	                        case (($ord_var_c & 0xFE) == 0xFC):
	                            // characters U-04000000 - U-7FFFFFFF, mask 1111110X
	                            // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                            $char = pack('C*', $ord_var_c,
	                                         ord($var{$c + 1}),
	                                         ord($var{$c + 2}),
	                                         ord($var{$c + 3}),
	                                         ord($var{$c + 4}),
	                                         ord($var{$c + 5}));
	                            $c += 5;
	                            $utf16 = $this->utf82utf16($char);
	                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
	                            break;
	                    }
	                }

	                return '"'.$ascii.'"';

	            case 'array':
	               /*
	                * As per JSON spec if any array key is not an integer
	                * we must treat the the whole array as an object. We
	                * also try to catch a sparsely populated associative
	                * array with numeric keys here because some JS engines
	                * will create an array with empty indexes up to
	                * max_index which can cause memory issues and because
	                * the keys, which may be relevant, will be remapped
	                * otherwise.
	                *
	                * As per the ECMA and JSON specification an object may
	                * have any string as a property. Unfortunately due to
	                * a hole in the ECMA specification if the key is a
	                * ECMA reserved word or starts with a digit the
	                * parameter is only accessible using ECMAScript's
	                * bracket notation.
	                */

	                // treat as a JSON object
	                if (is_array($var) && count($var) && (array_keys($var) !== range(0, sizeof($var) - 1))) {
	                    $properties = array_map(array($this, 'name_value'),
	                                            array_keys($var),
	                                            array_values($var));

	                    foreach($properties as $property) {
	                        if(Akeeba_Services_JSON::isError($property)) {
	                            return $property;
	                        }
	                    }

	                    return '{' . join(',', $properties) . '}';
	                }

	                // treat it like a regular array
	                $elements = array_map(array($this, 'encode'), $var);

	                foreach($elements as $element) {
	                    if(Akeeba_Services_JSON::isError($element)) {
	                        return $element;
	                    }
	                }

	                return '[' . join(',', $elements) . ']';

	            case 'object':
	                $vars = get_object_vars($var);

	                $properties = array_map(array($this, 'name_value'),
	                                        array_keys($vars),
	                                        array_values($vars));

	                foreach($properties as $property) {
	                    if(Akeeba_Services_JSON::isError($property)) {
	                        return $property;
	                    }
	                }

	                return '{' . join(',', $properties) . '}';

	            default:
	                return ($this->use & SERVICES_JSON_SUPPRESS_ERRORS)
	                    ? 'null'
	                    : new Akeeba_Services_JSON_Error(gettype($var)." can not be encoded as JSON string");
	        }
	    }

	   /**
	    * array-walking function for use in generating JSON-formatted name-value pairs
	    *
	    * @param    string  $name   name of key to use
	    * @param    mixed   $value  reference to an array element to be encoded
	    *
	    * @return   string  JSON-formatted name-value pair, like '"name":value'
	    * @access   private
	    */
	    function name_value($name, $value)
	    {
	        $encoded_value = $this->encode($value);

	        if(Akeeba_Services_JSON::isError($encoded_value)) {
	            return $encoded_value;
	        }

	        return $this->encode(strval($name)) . ':' . $encoded_value;
	    }

	   /**
	    * reduce a string by removing leading and trailing comments and whitespace
	    *
	    * @param    $str    string      string value to strip of comments and whitespace
	    *
	    * @return   string  string value stripped of comments and whitespace
	    * @access   private
	    */
	    function reduce_string($str)
	    {
	        $str = preg_replace(array(

	                // eliminate single line comments in '// ...' form
	                '#^\s*//(.+)$#m',

	                // eliminate multi-line comments in '/* ... */' form, at start of string
	                '#^\s*/\*(.+)\*/#Us',

	                // eliminate multi-line comments in '/* ... */' form, at end of string
	                '#/\*(.+)\*/\s*$#Us'

	            ), '', $str);

	        // eliminate extraneous space
	        return trim($str);
	    }

	   /**
	    * decodes a JSON string into appropriate variable
	    *
	    * @param    string  $str    JSON-formatted string
	    *
	    * @return   mixed   number, boolean, string, array, or object
	    *                   corresponding to given JSON input string.
	    *                   See argument 1 to Akeeba_Services_JSON() above for object-output behavior.
	    *                   Note that decode() always returns strings
	    *                   in ASCII or UTF-8 format!
	    * @access   public
	    */
	    function decode($str)
	    {
	        $str = $this->reduce_string($str);

	        switch (strtolower($str)) {
	            case 'true':
	                return true;

	            case 'false':
	                return false;

	            case 'null':
	                return null;

	            default:
	                $m = array();

	                if (is_numeric($str)) {
	                    // Lookie-loo, it's a number

	                    // This would work on its own, but I'm trying to be
	                    // good about returning integers where appropriate:
	                    // return (float)$str;

	                    // Return float or int, as appropriate
	                    return ((float)$str == (integer)$str)
	                        ? (integer)$str
	                        : (float)$str;

	                } elseif (preg_match('/^("|\').*(\1)$/s', $str, $m) && $m[1] == $m[2]) {
	                    // STRINGS RETURNED IN UTF-8 FORMAT
	                    $delim = substr($str, 0, 1);
	                    $chrs = substr($str, 1, -1);
	                    $utf8 = '';
	                    $strlen_chrs = strlen($chrs);

	                    for ($c = 0; $c < $strlen_chrs; ++$c) {

	                        $substr_chrs_c_2 = substr($chrs, $c, 2);
	                        $ord_chrs_c = ord($chrs{$c});

	                        switch (true) {
	                            case $substr_chrs_c_2 == '\b':
	                                $utf8 .= chr(0x08);
	                                ++$c;
	                                break;
	                            case $substr_chrs_c_2 == '\t':
	                                $utf8 .= chr(0x09);
	                                ++$c;
	                                break;
	                            case $substr_chrs_c_2 == '\n':
	                                $utf8 .= chr(0x0A);
	                                ++$c;
	                                break;
	                            case $substr_chrs_c_2 == '\f':
	                                $utf8 .= chr(0x0C);
	                                ++$c;
	                                break;
	                            case $substr_chrs_c_2 == '\r':
	                                $utf8 .= chr(0x0D);
	                                ++$c;
	                                break;

	                            case $substr_chrs_c_2 == '\\"':
	                            case $substr_chrs_c_2 == '\\\'':
	                            case $substr_chrs_c_2 == '\\\\':
	                            case $substr_chrs_c_2 == '\\/':
	                                if (($delim == '"' && $substr_chrs_c_2 != '\\\'') ||
	                                   ($delim == "'" && $substr_chrs_c_2 != '\\"')) {
	                                    $utf8 .= $chrs{++$c};
	                                }
	                                break;

	                            case preg_match('/\\\u[0-9A-F]{4}/i', substr($chrs, $c, 6)):
	                                // single, escaped unicode character
	                                $utf16 = chr(hexdec(substr($chrs, ($c + 2), 2)))
	                                       . chr(hexdec(substr($chrs, ($c + 4), 2)));
	                                $utf8 .= $this->utf162utf8($utf16);
	                                $c += 5;
	                                break;

	                            case ($ord_chrs_c >= 0x20) && ($ord_chrs_c <= 0x7F):
	                                $utf8 .= $chrs{$c};
	                                break;

	                            case ($ord_chrs_c & 0xE0) == 0xC0:
	                                // characters U-00000080 - U-000007FF, mask 110XXXXX
	                                //see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                                $utf8 .= substr($chrs, $c, 2);
	                                ++$c;
	                                break;

	                            case ($ord_chrs_c & 0xF0) == 0xE0:
	                                // characters U-00000800 - U-0000FFFF, mask 1110XXXX
	                                // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                                $utf8 .= substr($chrs, $c, 3);
	                                $c += 2;
	                                break;

	                            case ($ord_chrs_c & 0xF8) == 0xF0:
	                                // characters U-00010000 - U-001FFFFF, mask 11110XXX
	                                // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                                $utf8 .= substr($chrs, $c, 4);
	                                $c += 3;
	                                break;

	                            case ($ord_chrs_c & 0xFC) == 0xF8:
	                                // characters U-00200000 - U-03FFFFFF, mask 111110XX
	                                // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                                $utf8 .= substr($chrs, $c, 5);
	                                $c += 4;
	                                break;

	                            case ($ord_chrs_c & 0xFE) == 0xFC:
	                                // characters U-04000000 - U-7FFFFFFF, mask 1111110X
	                                // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
	                                $utf8 .= substr($chrs, $c, 6);
	                                $c += 5;
	                                break;

	                        }

	                    }

	                    return $utf8;

	                } elseif (preg_match('/^\[.*\]$/s', $str) || preg_match('/^\{.*\}$/s', $str)) {
	                    // array, or object notation

	                    if ($str{0} == '[') {
	                        $stk = array(SERVICES_JSON_IN_ARR);
	                        $arr = array();
	                    } else {
	                        if ($this->use & SERVICES_JSON_LOOSE_TYPE) {
	                            $stk = array(SERVICES_JSON_IN_OBJ);
	                            $obj = array();
	                        } else {
	                            $stk = array(SERVICES_JSON_IN_OBJ);
	                            $obj = new stdClass();
	                        }
	                    }

	                    array_push($stk, array('what'  => SERVICES_JSON_SLICE,
	                                           'where' => 0,
	                                           'delim' => false));

	                    $chrs = substr($str, 1, -1);
	                    $chrs = $this->reduce_string($chrs);

	                    if ($chrs == '') {
	                        if (reset($stk) == SERVICES_JSON_IN_ARR) {
	                            return $arr;

	                        } else {
	                            return $obj;

	                        }
	                    }

	                    //print("\nparsing {$chrs}\n");

	                    $strlen_chrs = strlen($chrs);

	                    for ($c = 0; $c <= $strlen_chrs; ++$c) {

	                        $top = end($stk);
	                        $substr_chrs_c_2 = substr($chrs, $c, 2);

	                        if (($c == $strlen_chrs) || (($chrs{$c} == ',') && ($top['what'] == SERVICES_JSON_SLICE))) {
	                            // found a comma that is not inside a string, array, etc.,
	                            // OR we've reached the end of the character list
	                            $slice = substr($chrs, $top['where'], ($c - $top['where']));
	                            array_push($stk, array('what' => SERVICES_JSON_SLICE, 'where' => ($c + 1), 'delim' => false));
	                            //print("Found split at {$c}: ".substr($chrs, $top['where'], (1 + $c - $top['where']))."\n");

	                            if (reset($stk) == SERVICES_JSON_IN_ARR) {
	                                // we are in an array, so just push an element onto the stack
	                                array_push($arr, $this->decode($slice));

	                            } elseif (reset($stk) == SERVICES_JSON_IN_OBJ) {
	                                // we are in an object, so figure
	                                // out the property name and set an
	                                // element in an associative array,
	                                // for now
	                                $parts = array();

	                                if (preg_match('/^\s*(["\'].*[^\\\]["\'])\s*:\s*(\S.*),?$/Uis', $slice, $parts)) {
	                                    // "name":value pair
	                                    $key = $this->decode($parts[1]);
	                                    $val = $this->decode($parts[2]);

	                                    if ($this->use & SERVICES_JSON_LOOSE_TYPE) {
	                                        $obj[$key] = $val;
	                                    } else {
	                                        $obj->$key = $val;
	                                    }
	                                } elseif (preg_match('/^\s*(\w+)\s*:\s*(\S.*),?$/Uis', $slice, $parts)) {
	                                    // name:value pair, where name is unquoted
	                                    $key = $parts[1];
	                                    $val = $this->decode($parts[2]);

	                                    if ($this->use & SERVICES_JSON_LOOSE_TYPE) {
	                                        $obj[$key] = $val;
	                                    } else {
	                                        $obj->$key = $val;
	                                    }
	                                }

	                            }

	                        } elseif ((($chrs{$c} == '"') || ($chrs{$c} == "'")) && ($top['what'] != SERVICES_JSON_IN_STR)) {
	                            // found a quote, and we are not inside a string
	                            array_push($stk, array('what' => SERVICES_JSON_IN_STR, 'where' => $c, 'delim' => $chrs{$c}));
	                            //print("Found start of string at {$c}\n");

	                        } elseif (($chrs{$c} == $top['delim']) &&
	                                 ($top['what'] == SERVICES_JSON_IN_STR) &&
	                                 ((strlen(substr($chrs, 0, $c)) - strlen(rtrim(substr($chrs, 0, $c), '\\'))) % 2 != 1)) {
	                            // found a quote, we're in a string, and it's not escaped
	                            // we know that it's not escaped becase there is _not_ an
	                            // odd number of backslashes at the end of the string so far
	                            array_pop($stk);
	                            //print("Found end of string at {$c}: ".substr($chrs, $top['where'], (1 + 1 + $c - $top['where']))."\n");

	                        } elseif (($chrs{$c} == '[') &&
	                                 in_array($top['what'], array(SERVICES_JSON_SLICE, SERVICES_JSON_IN_ARR, SERVICES_JSON_IN_OBJ))) {
	                            // found a left-bracket, and we are in an array, object, or slice
	                            array_push($stk, array('what' => SERVICES_JSON_IN_ARR, 'where' => $c, 'delim' => false));
	                            //print("Found start of array at {$c}\n");

	                        } elseif (($chrs{$c} == ']') && ($top['what'] == SERVICES_JSON_IN_ARR)) {
	                            // found a right-bracket, and we're in an array
	                            array_pop($stk);
	                            //print("Found end of array at {$c}: ".substr($chrs, $top['where'], (1 + $c - $top['where']))."\n");

	                        } elseif (($chrs{$c} == '{') &&
	                                 in_array($top['what'], array(SERVICES_JSON_SLICE, SERVICES_JSON_IN_ARR, SERVICES_JSON_IN_OBJ))) {
	                            // found a left-brace, and we are in an array, object, or slice
	                            array_push($stk, array('what' => SERVICES_JSON_IN_OBJ, 'where' => $c, 'delim' => false));
	                            //print("Found start of object at {$c}\n");

	                        } elseif (($chrs{$c} == '}') && ($top['what'] == SERVICES_JSON_IN_OBJ)) {
	                            // found a right-brace, and we're in an object
	                            array_pop($stk);
	                            //print("Found end of object at {$c}: ".substr($chrs, $top['where'], (1 + $c - $top['where']))."\n");

	                        } elseif (($substr_chrs_c_2 == '/*') &&
	                                 in_array($top['what'], array(SERVICES_JSON_SLICE, SERVICES_JSON_IN_ARR, SERVICES_JSON_IN_OBJ))) {
	                            // found a comment start, and we are in an array, object, or slice
	                            array_push($stk, array('what' => SERVICES_JSON_IN_CMT, 'where' => $c, 'delim' => false));
	                            $c++;
	                            //print("Found start of comment at {$c}\n");

	                        } elseif (($substr_chrs_c_2 == '*/') && ($top['what'] == SERVICES_JSON_IN_CMT)) {
	                            // found a comment end, and we're in one now
	                            array_pop($stk);
	                            $c++;

	                            for ($i = $top['where']; $i <= $c; ++$i)
	                                $chrs = substr_replace($chrs, ' ', $i, 1);

	                            //print("Found end of comment at {$c}: ".substr($chrs, $top['where'], (1 + $c - $top['where']))."\n");

	                        }

	                    }

	                    if (reset($stk) == SERVICES_JSON_IN_ARR) {
	                        return $arr;

	                    } elseif (reset($stk) == SERVICES_JSON_IN_OBJ) {
	                        return $obj;

	                    }

	                }
	        }
	    }

	    function isError($data, $code = null)
	    {
	        if (class_exists('pear')) {
	            return PEAR::isError($data, $code);
	        } elseif (is_object($data) && (get_class($data) == 'services_json_error' ||
	                                 is_subclass_of($data, 'services_json_error'))) {
	            return true;
	        }

	        return false;
	    }
	}

    class Akeeba_Services_JSON_Error
    {
        function Akeeba_Services_JSON_Error($message = 'unknown error', $code = null,
                                     $mode = null, $options = null, $userinfo = null)
        {

        }
    }
}

if(!function_exists('json_encode'))
{
	function json_encode($value, $options = 0) {
		$flags = SERVICES_JSON_LOOSE_TYPE;
		if( $options & JSON_FORCE_OBJECT ) $flags = 0;
		$encoder = new Akeeba_Services_JSON($flags);
		return $encoder->encode($value);
	}
}

if(!function_exists('json_decode'))
{
	function json_decode($value, $assoc = false)
	{
		$flags = 0;
		if($assoc) $flags = SERVICES_JSON_LOOSE_TYPE;
		$decoder = new Akeeba_Services_JSON($flags);
		return $decoder->decode($value);
	}
}

/**
 * Akeeba Restore
 * A JSON-powered JPA, JPS and ZIP archive extraction library
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / Akeeba Ltd.
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

/**
 * The base class of Akeeba Engine objects. Allows for error and warnings logging
 * and propagation. Largely based on the Joomla! 1.5 JObject class.
 */
abstract class AKAbstractObject
{
	/** @var	array	An array of errors */
	private $_errors = array();

	/** @var	array	The queue size of the $_errors array. Set to 0 for infinite size. */
	protected $_errors_queue_size = 0;

	/** @var	array	An array of warnings */
	private $_warnings = array();

	/** @var	array	The queue size of the $_warnings array. Set to 0 for infinite size. */
	protected $_warnings_queue_size = 0;

	/**
	 * Public constructor, makes sure we are instanciated only by the factory class
	 */
	public function __construct()
	{
		/*
		// Assisted Singleton pattern
		if(function_exists('debug_backtrace'))
		{
			$caller=debug_backtrace();
			if(
				($caller[1]['class'] != 'AKFactory') &&
				($caller[2]['class'] != 'AKFactory') &&
				($caller[3]['class'] != 'AKFactory') &&
				($caller[4]['class'] != 'AKFactory')
			) {
				var_dump(debug_backtrace());
				trigger_error("You can't create direct descendants of ".__CLASS__, E_USER_ERROR);
			}
		}
		*/
	}

	/**
	 * Get the most recent error message
	 * @param	integer	$i Optional error index
	 * @return	string	Error message
	 */
	public function getError($i = null)
	{
		return $this->getItemFromArray($this->_errors, $i);
	}

	/**
	 * Return all errors, if any
	 * @return	array	Array of error messages
	 */
	public function getErrors()
	{
		return $this->_errors;
	}

	/**
	 * Add an error message
	 * @param	string $error Error message
	 */
	public function setError($error)
	{
		if($this->_errors_queue_size > 0)
		{
			if(count($this->_errors) >= $this->_errors_queue_size)
			{
				array_shift($this->_errors);
			}
		}
		array_push($this->_errors, $error);
	}

	/**
	 * Resets all error messages
	 */
	public function resetErrors()
	{
		$this->_errors = array();
	}

	/**
	 * Get the most recent warning message
	 * @param	integer	$i Optional warning index
	 * @return	string	Error message
	 */
	public function getWarning($i = null)
	{
		return $this->getItemFromArray($this->_warnings, $i);
	}

	/**
	 * Return all warnings, if any
	 * @return	array	Array of error messages
	 */
	public function getWarnings()
	{
		return $this->_warnings;
	}

	/**
	 * Add an error message
	 * @param	string $error Error message
	 */
	public function setWarning($warning)
	{
		if($this->_warnings_queue_size > 0)
		{
			if(count($this->_warnings) >= $this->_warnings_queue_size)
			{
				array_shift($this->_warnings);
			}
		}

		array_push($this->_warnings, $warning);
	}

	/**
	 * Resets all warning messages
	 */
	public function resetWarnings()
	{
		$this->_warnings = array();
	}

	/**
	 * Propagates errors and warnings to a foreign object. The foreign object SHOULD
	 * implement the setError() and/or setWarning() methods but DOESN'T HAVE TO be of
	 * AKAbstractObject type. For example, this can even be used to propagate to a
	 * JObject instance in Joomla!. Propagated items will be removed from ourself.
	 * @param object $object The object to propagate errors and warnings to.
	 */
	public function propagateToObject(&$object)
	{
		// Skip non-objects
		if(!is_object($object)) return;

		if( method_exists($object,'setError') )
		{
			if(!empty($this->_errors))
			{
				foreach($this->_errors as $error)
				{
					$object->setError($error);
				}
				$this->_errors = array();
			}
		}

		if( method_exists($object,'setWarning') )
		{
			if(!empty($this->_warnings))
			{
				foreach($this->_warnings as $warning)
				{
					$object->setWarning($warning);
				}
				$this->_warnings = array();
			}
		}
	}

	/**
	 * Propagates errors and warnings from a foreign object. Each propagated list is
	 * then cleared on the foreign object, as long as it implements resetErrors() and/or
	 * resetWarnings() methods.
	 * @param object $object The object to propagate errors and warnings from
	 */
	public function propagateFromObject(&$object)
	{
		if( method_exists($object,'getErrors') )
		{
			$errors = $object->getErrors();
			if(!empty($errors))
			{
				foreach($errors as $error)
				{
					$this->setError($error);
				}
			}
			if(method_exists($object,'resetErrors'))
			{
				$object->resetErrors();
			}
		}

		if( method_exists($object,'getWarnings') )
		{
			$warnings = $object->getWarnings();
			if(!empty($warnings))
			{
				foreach($warnings as $warning)
				{
					$this->setWarning($warning);
				}
			}
			if(method_exists($object,'resetWarnings'))
			{
				$object->resetWarnings();
			}
		}
	}

	/**
	 * Sets the size of the error queue (acts like a LIFO buffer)
	 * @param int $newSize The new queue size. Set to 0 for infinite length.
	 */
	protected function setErrorsQueueSize($newSize = 0)
	{
		$this->_errors_queue_size = (int)$newSize;
	}

	/**
	 * Sets the size of the warnings queue (acts like a LIFO buffer)
	 * @param int $newSize The new queue size. Set to 0 for infinite length.
	 */
	protected function setWarningsQueueSize($newSize = 0)
	{
		$this->_warnings_queue_size = (int)$newSize;
	}

	/**
	 * Returns the last item of a LIFO string message queue, or a specific item
	 * if so specified.
	 * @param array $array An array of strings, holding messages
	 * @param int $i Optional message index
	 * @return mixed The message string, or false if the key doesn't exist
	 */
	private function getItemFromArray($array, $i = null)
	{
		// Find the item
		if ( $i === null) {
			// Default, return the last item
			$item = end($array);
		}
		else
		if ( ! array_key_exists($i, $array) ) {
			// If $i has been specified but does not exist, return false
			return false;
		}
		else
		{
			$item	= $array[$i];
		}

		return $item;
	}

}

/**
 * Akeeba Restore
 * A JSON-powered JPA, JPS and ZIP archive extraction library
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / Akeeba Ltd.
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

/**
 * The superclass of all Akeeba Kickstart parts. The "parts" are intelligent stateful
 * classes which perform a single procedure and have preparation, running and
 * finalization phases. The transition between phases is handled automatically by
 * this superclass' tick() final public method, which should be the ONLY public API
 * exposed to the rest of the Akeeba Engine.
 */
abstract class AKAbstractPart extends AKAbstractObject
{
	/**
	 * Indicates whether this part has finished its initialisation cycle
	 * @var boolean
	 */
	protected $isPrepared = false;

	/**
	 * Indicates whether this part has more work to do (it's in running state)
	 * @var boolean
	 */
	protected $isRunning = false;

	/**
	 * Indicates whether this part has finished its finalization cycle
	 * @var boolean
	 */
	protected $isFinished = false;

	/**
	 * Indicates whether this part has finished its run cycle
	 * @var boolean
	 */
	protected $hasRan = false;

	/**
	 * The name of the engine part (a.k.a. Domain), used in return table
	 * generation.
	 * @var string
	 */
	protected $active_domain = "";

	/**
	 * The step this engine part is in. Used verbatim in return table and
	 * should be set by the code in the _run() method.
	 * @var string
	 */
	protected $active_step = "";

	/**
	 * A more detailed description of the step this engine part is in. Used
	 * verbatim in return table and should be set by the code in the _run()
	 * method.
	 * @var string
	 */
	protected $active_substep = "";

	/**
	 * Any configuration variables, in the form of an array.
	 * @var array
	 */
	protected $_parametersArray = array();

	/** @var string The database root key */
	protected $databaseRoot = array();

	/** @var int Last reported warnings's position in array */
	private $warnings_pointer = -1;

	/** @var array An array of observers */
	protected $observers = array();

	/**
	 * Runs the preparation for this part. Should set _isPrepared
	 * to true
	 */
	abstract protected function _prepare();

	/**
	 * Runs the finalisation process for this part. Should set
	 * _isFinished to true.
	 */
	abstract protected function _finalize();

	/**
	 * Runs the main functionality loop for this part. Upon calling,
	 * should set the _isRunning to true. When it finished, should set
	 * the _hasRan to true. If an error is encountered, setError should
	 * be used.
	 */
	abstract protected function _run();

	/**
	 * Sets the BREAKFLAG, which instructs this engine part that the current step must break immediately,
	 * in fear of timing out.
	 */
	protected function setBreakFlag()
	{
		AKFactory::set('volatile.breakflag', true);
	}

	/**
	 * Sets the engine part's internal state, in an easy to use manner
	 *
	 * @param	string	$state			One of init, prepared, running, postrun, finished, error
	 * @param	string	$errorMessage	The reported error message, should the state be set to error
	 */
	protected function setState($state = 'init', $errorMessage='Invalid setState argument')
	{
		switch($state)
		{
			case 'init':
				$this->isPrepared = false;
				$this->isRunning  = false;
				$this->isFinished = false;
				$this->hasRun     = false;
				break;

			case 'prepared':
				$this->isPrepared = true;
				$this->isRunning  = false;
				$this->isFinished = false;
				$this->hasRun     = false;
				break;

			case 'running':
				$this->isPrepared = true;
				$this->isRunning  = true;
				$this->isFinished = false;
				$this->hasRun     = false;
				break;

			case 'postrun':
				$this->isPrepared = true;
				$this->isRunning  = false;
				$this->isFinished = false;
				$this->hasRun     = true;
				break;

			case 'finished':
				$this->isPrepared = true;
				$this->isRunning  = false;
				$this->isFinished = true;
				$this->hasRun     = false;
				break;

			case 'error':
			default:
				$this->setError($errorMessage);
				break;
		}
	}

	/**
	 * The public interface to an engine part. This method takes care for
	 * calling the correct method in order to perform the initialisation -
	 * run - finalisation cycle of operation and return a proper reponse array.
	 * @return	array	A Reponse Array
	 */
	final public function tick()
	{
		// Call the right action method, depending on engine part state
		switch( $this->getState() )
		{
			case "init":
				$this->_prepare();
				break;
			case "prepared":
				$this->_run();
				break;
			case "running":
				$this->_run();
				break;
			case "postrun":
				$this->_finalize();
				break;
		}

		// Send a Return Table back to the caller
		$out = $this->_makeReturnTable();
		return $out;
	}

	/**
	 * Returns a copy of the class's status array
	 * @return array
	 */
	public function getStatusArray()
	{
		return $this->_makeReturnTable();
	}

	/**
	 * Sends any kind of setup information to the engine part. Using this,
	 * we avoid passing parameters to the constructor of the class. These
	 * parameters should be passed as an indexed array and should be taken
	 * into account during the preparation process only. This function will
	 * set the error flag if it's called after the engine part is prepared.
	 *
	 * @param array $parametersArray The parameters to be passed to the
	 * engine part.
	 */
	final public function setup( $parametersArray )
	{
		if( $this->isPrepared )
		{
			$this->setState('error', "Can't modify configuration after the preparation of " . $this->active_domain);
		}
		else
		{
			$this->_parametersArray = $parametersArray;
			if(array_key_exists('root', $parametersArray))
			{
				$this->databaseRoot = $parametersArray['root'];
			}
		}
	}

	/**
	 * Returns the state of this engine part.
	 *
	 * @return string The state of this engine part. It can be one of
	 * error, init, prepared, running, postrun, finished.
	 */
	final public function getState()
	{
		if( $this->getError() )
		{
			return "error";
		}

		if( !($this->isPrepared) )
		{
			return "init";
		}

		if( !($this->isFinished) && !($this->isRunning) && !( $this->hasRun ) && ($this->isPrepared) )
		{
			return "prepared";
		}

		if ( !($this->isFinished) && $this->isRunning && !( $this->hasRun ) )
		{
			return "running";
		}

		if ( !($this->isFinished) && !($this->isRunning) && $this->hasRun )
		{
			return "postrun";
		}

		if ( $this->isFinished )
		{
			return "finished";
		}
	}

	/**
	 * Constructs a Response Array based on the engine part's state.
	 * @return array The Response Array for the current state
	 */
	final protected function _makeReturnTable()
	{
		// Get a list of warnings
		$warnings = $this->getWarnings();
		// Report only new warnings if there is no warnings queue size
		if( $this->_warnings_queue_size == 0 )
		{
			if( ($this->warnings_pointer > 0) && ($this->warnings_pointer < (count($warnings)) ) )
			{
				$warnings = array_slice($warnings, $this->warnings_pointer + 1);
				$this->warnings_pointer += count($warnings);
			}
			else
			{
				$this->warnings_pointer = count($warnings);
			}
		}

		$out =  array(
			'HasRun'	=> (!($this->isFinished)),
			'Domain'	=> $this->active_domain,
			'Step'		=> $this->active_step,
			'Substep'	=> $this->active_substep,
			'Error'		=> $this->getError(),
			'Warnings'	=> $warnings
		);

		return $out;
	}

	final protected function setDomain($new_domain)
	{
		$this->active_domain = $new_domain;
	}

	final public function getDomain()
	{
		return $this->active_domain;
	}

	final protected function setStep($new_step)
	{
		$this->active_step = $new_step;
	}

	final public function getStep()
	{
		return $this->active_step;
	}

	final protected function setSubstep($new_substep)
	{
		$this->active_substep = $new_substep;
	}

	final public function getSubstep()
	{
		return $this->active_substep;
	}

	/**
	 * Attaches an observer object
	 * @param AKAbstractPartObserver $obs
	 */
	function attach(AKAbstractPartObserver $obs) {
        $this->observers["$obs"] = $obs;
    }

	/**
	 * Dettaches an observer object
	 * @param AKAbstractPartObserver $obs
	 */
    function detach(AKAbstractPartObserver $obs) {
        delete($this->observers["$obs"]);
    }

    /**
     * Notifies observers each time something interesting happened to the part
     * @param mixed $message The event object
     */
	protected function notify($message) {
        foreach ($this->observers as $obs) {
            $obs->update($this, $message);
        }
    }
}

/**
 * Akeeba Restore
 * A JSON-powered JPA, JPS and ZIP archive extraction library
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / Akeeba Ltd.
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

/**
 * The base class of unarchiver classes
 */
abstract class AKAbstractUnarchiver extends AKAbstractPart
{
	/** @var string Archive filename */
	protected $filename = null;

	/** @var array List of the names of all archive parts */
	public $archiveList = array();

	/** @var int The total size of all archive parts */
	public $totalSize = array();

	/** @var integer Current archive part number */
	protected $currentPartNumber = -1;

	/** @var integer The offset inside the current part */
	protected $currentPartOffset = 0;

	/** @var bool Should I restore permissions? */
	protected $flagRestorePermissions = false;

	/** @var AKAbstractPostproc Post processing class */
	protected $postProcEngine = null;

	/** @var string Absolute path to prepend to extracted files */
	protected $addPath = '';

	/** @var array Which files to rename */
	public $renameFiles = array();

	/** @var array Which directories to rename */
	public $renameDirs = array();

	/** @var array Which files to skip */
	public $skipFiles = array();

	/** @var integer Chunk size for processing */
	protected $chunkSize = 524288;

	/** @var resource File pointer to the current archive part file */
	protected $fp = null;

	/** @var int Run state when processing the current archive file */
	protected $runState = null;

	/** @var stdClass File header data, as read by the readFileHeader() method */
	protected $fileHeader = null;

	/** @var int How much of the uncompressed data we've read so far */
	protected $dataReadLength = 0;

	/** @var array Unwriteable files in these directories are always ignored and do not cause errors when not extracted */
	protected $ignoreDirectories = array();

	/**
	 * Public constructor
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Wakeup function, called whenever the class is unserialized
	 */
	public function __wakeup()
	{
		if($this->currentPartNumber >= 0)
		{
			$this->fp = @fopen($this->archiveList[$this->currentPartNumber], 'rb');
			if( (is_resource($this->fp)) && ($this->currentPartOffset > 0) )
			{
				@fseek($this->fp, $this->currentPartOffset);
			}
		}
	}

	/**
	 * Sleep function, called whenever the class is serialized
	 */
	public function shutdown()
	{
		if(is_resource($this->fp))
		{
			$this->currentPartOffset = @ftell($this->fp);
			@fclose($this->fp);
		}
	}

	/**
	 * Implements the abstract _prepare() method
	 */
	final protected function _prepare()
	{
		parent::__construct();

		if( count($this->_parametersArray) > 0 )
		{
			foreach($this->_parametersArray as $key => $value)
			{
				switch($key)
				{
					// Archive's absolute filename
					case 'filename':
						$this->filename = $value;

						// Sanity check
						if (!empty($value))
						{
							$value = strtolower($value);

							if (strlen($value) > 6)
							{
								if (
									(substr($value, 0, 7) == 'http://')
									|| (substr($value, 0, 8) == 'https://')
									|| (substr($value, 0, 6) == 'ftp://')
									|| (substr($value, 0, 7) == 'ssh2://')
									|| (substr($value, 0, 6) == 'ssl://')
								)
								{
									$this->setState('error', 'Invalid archive location');
								}
							}
						}



						break;

					// Should I restore permissions?
					case 'restore_permissions':
						$this->flagRestorePermissions = $value;
						break;

					// Should I use FTP?
					case 'post_proc':
						$this->postProcEngine = AKFactory::getpostProc($value);
						break;

					// Path to add in the beginning
					case 'add_path':
						$this->addPath = $value;
						$this->addPath = str_replace('\\','/',$this->addPath);
						$this->addPath = rtrim($this->addPath,'/');
						if(!empty($this->addPath)) $this->addPath .= '/';
						break;

					// Which files to rename (hash array)
					case 'rename_files':
						$this->renameFiles = $value;
						break;

					// Which files to rename (hash array)
					case 'rename_dirs':
						$this->renameDirs = $value;
						break;

					// Which files to skip (indexed array)
					case 'skip_files':
						$this->skipFiles = $value;
						break;

					// Which directories to ignore when we can't write files in them (indexed array)
					case 'ignoredirectories':
						$this->ignoreDirectories = $value;
						break;
				}
			}
		}

		$this->scanArchives();

		$this->readArchiveHeader();
		$errMessage = $this->getError();
		if(!empty($errMessage))
		{
			$this->setState('error', $errMessage);
		}
		else
		{
			$this->runState = AK_STATE_NOFILE;
			$this->setState('prepared');
		}
	}

	protected function _run()
	{
		if($this->getState() == 'postrun') return;

		$this->setState('running');

		$timer = AKFactory::getTimer();

		$status = true;
		while( $status && ($timer->getTimeLeft() > 0) )
		{
			switch( $this->runState )
			{
				case AK_STATE_NOFILE:
					debugMsg(__CLASS__.'::_run() - Reading file header');
					$status = $this->readFileHeader();
					if($status)
					{
						debugMsg(__CLASS__.'::_run() - Preparing to extract '.$this->fileHeader->realFile);
						// Send start of file notification
						$message = new stdClass;
						$message->type = 'startfile';
						$message->content = new stdClass;
						if( array_key_exists('realfile', get_object_vars($this->fileHeader)) ) {
							$message->content->realfile = $this->fileHeader->realFile;
						} else {
							$message->content->realfile = $this->fileHeader->file;
						}
						$message->content->file = $this->fileHeader->file;
						if( array_key_exists('compressed', get_object_vars($this->fileHeader)) ) {
							$message->content->compressed = $this->fileHeader->compressed;
						} else {
							$message->content->compressed = 0;
						}
						$message->content->uncompressed = $this->fileHeader->uncompressed;
						$this->notify($message);
					} else {
						debugMsg(__CLASS__.'::_run() - Could not read file header');
					}
					break;

				case AK_STATE_HEADER:
				case AK_STATE_DATA:
					debugMsg(__CLASS__.'::_run() - Processing file data');
					$status = $this->processFileData();
					break;

				case AK_STATE_DATAREAD:
				case AK_STATE_POSTPROC:
					debugMsg(__CLASS__.'::_run() - Calling post-processing class');
					$this->postProcEngine->timestamp = $this->fileHeader->timestamp;
					$status = $this->postProcEngine->process();
					$this->propagateFromObject( $this->postProcEngine );
					$this->runState = AK_STATE_DONE;
					break;

				case AK_STATE_DONE:
				default:
					if($status)
					{
						debugMsg(__CLASS__.'::_run() - Finished extracting file');
						// Send end of file notification
						$message = new stdClass;
						$message->type = 'endfile';
						$message->content = new stdClass;
						if( array_key_exists('realfile', get_object_vars($this->fileHeader)) ) {
							$message->content->realfile = $this->fileHeader->realFile;
						} else {
							$message->content->realfile = $this->fileHeader->file;
						}
						$message->content->file = $this->fileHeader->file;
						if( array_key_exists('compressed', get_object_vars($this->fileHeader)) ) {
							$message->content->compressed = $this->fileHeader->compressed;
						} else {
							$message->content->compressed = 0;
						}
						$message->content->uncompressed = $this->fileHeader->uncompressed;
						$this->notify($message);
					}
					$this->runState = AK_STATE_NOFILE;
					continue;
			}
		}

		$error = $this->getError();
		if( !$status && ($this->runState == AK_STATE_NOFILE) && empty( $error ) )
		{
			debugMsg(__CLASS__.'::_run() - Just finished');
			// We just finished
			$this->setState('postrun');
		}
		elseif( !empty($error) )
		{
			debugMsg(__CLASS__.'::_run() - Halted with an error:');
			debugMsg($error);
			$this->setState( 'error', $error );
		}
	}

	protected function _finalize()
	{
		// Nothing to do
		$this->setState('finished');
	}

	/**
	 * Returns the base extension of the file, e.g. '.jpa'
	 * @return string
	 */
	private function getBaseExtension()
	{
		static $baseextension;

		if(empty($baseextension))
		{
			$basename = basename($this->filename);
			$lastdot = strrpos($basename,'.');
			$baseextension = substr($basename, $lastdot);
		}

		return $baseextension;
	}

	/**
	 * Scans for archive parts
	 */
	private function scanArchives()
	{
		if(defined('KSDEBUG')) {
			@unlink('debug.txt');
		}
		debugMsg('Preparing to scan archives');

		$privateArchiveList = array();

		// Get the components of the archive filename
		$dirname = dirname($this->filename);
		$base_extension = $this->getBaseExtension();
		$basename = basename($this->filename, $base_extension);
		$this->totalSize = 0;

		// Scan for multiple parts until we don't find any more of them
		$count = 0;
		$found = true;
		$this->archiveList = array();
		while($found)
		{
			++$count;
			$extension = substr($base_extension, 0, 2).sprintf('%02d', $count);
			$filename = $dirname.DIRECTORY_SEPARATOR.$basename.$extension;
			$found = file_exists($filename);
			if($found)
			{
				debugMsg('- Found archive '.$filename);
				// Add yet another part, with a numeric-appended filename
				$this->archiveList[] = $filename;

				$filesize = @filesize($filename);
				$this->totalSize += $filesize;

				$privateArchiveList[] = array($filename, $filesize);
			}
			else
			{
				debugMsg('- Found archive '.$this->filename);
				// Add the last part, with the regular extension
				$this->archiveList[] = $this->filename;

				$filename = $this->filename;
				$filesize = @filesize($filename);
				$this->totalSize += $filesize;

				$privateArchiveList[] = array($filename, $filesize);
			}
		}
		debugMsg('Total archive parts: '.$count);

		$this->currentPartNumber = -1;
		$this->currentPartOffset = 0;
		$this->runState = AK_STATE_NOFILE;

		// Send start of file notification
		$message = new stdClass;
		$message->type = 'totalsize';
		$message->content = new stdClass;
		$message->content->totalsize = $this->totalSize;
		$message->content->filelist = $privateArchiveList;
		$this->notify($message);
	}

	/**
	 * Opens the next part file for reading
	 */
	protected function nextFile()
	{
		debugMsg('Current part is '.$this->currentPartNumber.'; opening the next part');
		++$this->currentPartNumber;

		if( $this->currentPartNumber > (count($this->archiveList) - 1) )
		{
			$this->setState('postrun');
			return false;
		}
		else
		{
			if( is_resource($this->fp) ) @fclose($this->fp);
			debugMsg('Opening file '.$this->archiveList[$this->currentPartNumber]);
			$this->fp = @fopen( $this->archiveList[$this->currentPartNumber], 'rb' );
			if($this->fp === false) {
				debugMsg('Could not open file - crash imminent');
			}
			fseek($this->fp, 0);
			$this->currentPartOffset = 0;
			return true;
		}
	}

	/**
	 * Returns true if we have reached the end of file
	 * @param $local bool True to return EOF of the local file, false (default) to return if we have reached the end of the archive set
	 * @return bool True if we have reached End Of File
	 */
	protected function isEOF($local = false)
	{
		$eof = @feof($this->fp);

		if(!$eof)
		{
			// Border case: right at the part's end (eeeek!!!). For the life of me, I don't understand why
			// feof() doesn't report true. It expects the fp to be positioned *beyond* the EOF to report
			// true. Incredible! :(
			$position = @ftell($this->fp);
			$filesize = @filesize( $this->archiveList[$this->currentPartNumber] );
			if($filesize <= 0) {
				// 2Gb or more files on a 32 bit version of PHP tend to get screwed up. Meh.
				$eof = false;
			} elseif( $position >= $filesize  ) {
				$eof = true;
			}
		}

		if($local)
		{
			return $eof;
		}
		else
		{
			return $eof && ($this->currentPartNumber >= (count($this->archiveList)-1) );
		}
	}

	/**
	 * Tries to make a directory user-writable so that we can write a file to it
	 * @param $path string A path to a file
	 */
	protected function setCorrectPermissions($path)
	{
		static $rootDir = null;

		if(is_null($rootDir)) {
			$rootDir = rtrim(AKFactory::get('kickstart.setup.destdir',''),'/\\');
		}

		$directory = rtrim(dirname($path),'/\\');
		if($directory != $rootDir) {
			// Is this an unwritable directory?
			if(!is_writeable($directory)) {
				$this->postProcEngine->chmod( $directory, 0755 );
			}
		}
		$this->postProcEngine->chmod( $path, 0644 );
	}

	/**
	 * Concrete classes are supposed to use this method in order to read the archive's header and
	 * prepare themselves to the point of being ready to extract the first file.
	 */
	protected abstract function readArchiveHeader();

	/**
	 * Concrete classes must use this method to read the file header
	 * @return bool True if reading the file was successful, false if an error occured or we reached end of archive
	 */
	protected abstract function readFileHeader();

	/**
	 * Concrete classes must use this method to process file data. It must set $runState to AK_STATE_DATAREAD when
	 * it's finished processing the file data.
	 * @return bool True if processing the file data was successful, false if an error occured
	 */
	protected abstract function processFileData();

	/**
	 * Reads data from the archive and notifies the observer with the 'reading' message
	 * @param $fp
	 * @param $length
	 */
	protected function fread($fp, $length = null)
	{
		if(is_numeric($length))
		{
			if($length > 0) {
				$data = fread($fp, $length);
			} else {
				$data = fread($fp, PHP_INT_MAX);
			}
		}
		else
		{
			$data = fread($fp, PHP_INT_MAX);
		}
		if($data === false) $data = '';

		// Send start of file notification
		$message = new stdClass;
		$message->type = 'reading';
		$message->content = new stdClass;
		$message->content->length = strlen($data);
		$this->notify($message);

		return $data;
	}

	/**
	 * Is this file or directory contained in a directory we've decided to ignore
	 * write errors for? This is useful to let the extraction work despite write
	 * errors in the log, logs and tmp directories which MIGHT be used by the system
	 * on some low quality hosts and Plesk-powered hosts.
	 *
	 * @param   string  $shortFilename  The relative path of the file/directory in the package
	 *
	 * @return  boolean  True if it belongs in an ignored directory
	 */
	public function isIgnoredDirectory($shortFilename)
	{
		return false;
		if (substr($shortFilename, -1) == '/')
		{
			$check = substr($shortFilename, 0, -1);
		}
		else
		{
			$check = dirname($shortFilename);
		}

		return in_array($check, $this->ignoreDirectories);
	}
}

/**
 * Akeeba Restore
 * A JSON-powered JPA, JPS and ZIP archive extraction library
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / Akeeba Ltd.
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

/**
 * File post processor engines base class
 */
abstract class AKAbstractPostproc extends AKAbstractObject
{
	/** @var string The current (real) file path we'll have to process */
	protected $filename = null;

	/** @var int The requested permissions */
	protected $perms = 0755;

	/** @var string The temporary file path we gave to the unarchiver engine */
	protected $tempFilename = null;

	/** @var int The UNIX timestamp of the file's desired modification date */
	public $timestamp = 0;

	/**
	 * Processes the current file, e.g. moves it from temp to final location by FTP
	 */
	abstract public function process();

	/**
	 * The unarchiver tells us the path to the filename it wants to extract and we give it
	 * a different path instead.
	 * @param string $filename The path to the real file
	 * @param int $perms The permissions we need the file to have
	 * @return string The path to the temporary file
	 */
	abstract public function processFilename($filename, $perms = 0755);

	/**
	 * Recursively creates a directory if it doesn't exist
	 * @param string $dirName The directory to create
	 * @param int $perms The permissions to give to that directory
	 */
	abstract public function createDirRecursive( $dirName, $perms );

	abstract public function chmod( $file, $perms );

	abstract public function unlink( $file );

	abstract public function rmdir( $directory );

	abstract public function rename( $from, $to );
}


/**
 * Akeeba Restore
 * A JSON-powered JPA, JPS and ZIP archive extraction library
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / Akeeba Ltd.
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

/**
 * Descendants of this class can be used in the unarchiver's observer methods (attach, detach and notify)
 * @author Nicholas
 *
 */
abstract class AKAbstractPartObserver
{
	abstract public function update($object, $message);
}


/**
 * Akeeba Restore
 * A JSON-powered JPA, JPS and ZIP archive extraction library
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / Akeeba Ltd.
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

/**
 * Direct file writer
 */
class AKPostprocDirect extends AKAbstractPostproc
{
	public function process()
	{
		$restorePerms = AKFactory::get('kickstart.setup.restoreperms', false);
		if($restorePerms)
		{
			@chmod($this->filename, $this->perms);
		}
		else
		{
			if(@is_file($this->filename))
			{
				@chmod($this->filename, 0644);
			}
			else
			{
				@chmod($this->filename, 0755);
			}
		}
		if($this->timestamp > 0)
		{
			@touch($this->filename, $this->timestamp);
		}
		return true;
	}

	public function processFilename($filename, $perms = 0755)
	{
		$this->perms = $perms;
		$this->filename = $filename;
		return $filename;
	}

	public function createDirRecursive( $dirName, $perms )
	{
		if( AKFactory::get('kickstart.setup.dryrun','0') ) return true;
		if (@mkdir($dirName, 0755, true)) {
			@chmod($dirName, 0755);
			return true;
		}

		$root = AKFactory::get('kickstart.setup.destdir');
		$root = rtrim(str_replace('\\','/',$root),'/');
		$dir = rtrim(str_replace('\\','/',$dirName),'/');
		if(strpos($dir, $root) === 0) {
			$dir = ltrim(substr($dir, strlen($root)), '/');
			$root .= '/';
		} else {
			$root = '';
		}

		if(empty($dir)) return true;

		$dirArray = explode('/', $dir);
		$path = '';
		foreach( $dirArray as $dir )
		{
			$path .= $dir . '/';
			$ret = is_dir($root.$path) ? true : @mkdir($root.$path);
			if( !$ret ) {
				// Is this a file instead of a directory?
				if(is_file($root.$path) )
				{
					@unlink($root.$path);
					$ret = @mkdir($root.$path);
				}
				if( !$ret ) {
					$this->setError( AKText::sprintf('COULDNT_CREATE_DIR',$path) );
					return false;
				}
			}
			// Try to set new directory permissions to 0755
			@chmod($root.$path, $perms);
		}
		return true;
	}

	public function chmod( $file, $perms )
	{
		if( AKFactory::get('kickstart.setup.dryrun','0') ) return true;

		return @chmod( $file, $perms );
	}

	public function unlink( $file )
	{
		return @unlink( $file );
	}

	public function rmdir( $directory )
	{
		return @rmdir( $directory );
	}

	public function rename( $from, $to )
	{
		return @rename($from, $to);
	}

}

/**
 * Akeeba Restore
 * A JSON-powered JPA, JPS and ZIP archive extraction library
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / Akeeba Ltd.
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

/**
 * FTP file writer
 */
class AKPostprocFTP extends AKAbstractPostproc
{
	/** @var bool Should I use FTP over implicit SSL? */
	public $useSSL = false;
	/** @var bool use Passive mode? */
	public $passive = true;
	/** @var string FTP host name */
	public $host = '';
	/** @var int FTP port */
	public $port = 21;
	/** @var string FTP user name */
	public $user = '';
	/** @var string FTP password */
	public $pass = '';
	/** @var string FTP initial directory */
	public $dir = '';
	/** @var resource The FTP handle */
	private $handle = null;
	/** @var string The temporary directory where the data will be stored */
	private $tempDir = '';

	public function __construct()
	{
		parent::__construct();

		$this->useSSL = AKFactory::get('kickstart.ftp.ssl', false);
		$this->passive = AKFactory::get('kickstart.ftp.passive', true);
		$this->host = AKFactory::get('kickstart.ftp.host', '');
		$this->port = AKFactory::get('kickstart.ftp.port', 21);
		if(trim($this->port) == '') $this->port = 21;
		$this->user = AKFactory::get('kickstart.ftp.user', '');
		$this->pass = AKFactory::get('kickstart.ftp.pass', '');
		$this->dir = AKFactory::get('kickstart.ftp.dir', '');
		$this->tempDir = AKFactory::get('kickstart.ftp.tempdir', '');

		$connected = $this->connect();

		if($connected)
		{
			if(!empty($this->tempDir))
			{
				$tempDir = rtrim($this->tempDir, '/\\').'/';
				$writable = $this->isDirWritable($tempDir);
			}
			else
			{
				$tempDir = '';
				$writable = false;
			}

			if(!$writable) {
				// Default temporary directory is the current root
				$tempDir = KSROOTDIR;
				if(empty($tempDir))
				{
					// Oh, we have no directory reported!
					$tempDir = '.';
				}
				$absoluteDirToHere = $tempDir;
				$tempDir = rtrim(str_replace('\\','/',$tempDir),'/');
				if(!empty($tempDir)) $tempDir .= '/';
				$this->tempDir = $tempDir;
				// Is this directory writable?
				$writable = $this->isDirWritable($tempDir);
			}

			if(!$writable)
			{
				// Nope. Let's try creating a temporary directory in the site's root.
				$tempDir = $absoluteDirToHere.'/kicktemp';
				$this->createDirRecursive($tempDir, 0777);
				// Try making it writable...
				$this->fixPermissions($tempDir);
				$writable = $this->isDirWritable($tempDir);
			}

			// Was the new directory writable?
			if(!$writable)
			{
				// Let's see if the user has specified one
				$userdir = AKFactory::get('kickstart.ftp.tempdir', '');
				if(!empty($userdir))
				{
					// Is it an absolute or a relative directory?
					$absolute = false;
					$absolute = $absolute || ( substr($userdir,0,1) == '/' );
					$absolute = $absolute || ( substr($userdir,1,1) == ':' );
					$absolute = $absolute || ( substr($userdir,2,1) == ':' );
					if(!$absolute)
					{
						// Make absolute
						$tempDir = $absoluteDirToHere.$userdir;
					}
					else
					{
						// it's already absolute
						$tempDir = $userdir;
					}
					// Does the directory exist?
					if( is_dir($tempDir) )
					{
						// Yeah. Is it writable?
						$writable = $this->isDirWritable($tempDir);
					}
				}
			}
			$this->tempDir = $tempDir;

			if(!$writable)
			{
				// No writable directory found!!!
				$this->setError(AKText::_('FTP_TEMPDIR_NOT_WRITABLE'));
			}
			else
			{
				AKFactory::set('kickstart.ftp.tempdir', $tempDir);
				$this->tempDir = $tempDir;
			}
		}
	}

	function __wakeup()
	{
		$this->connect();
	}

	public function connect()
	{
		// Connect to server, using SSL if so required
		if($this->useSSL) {
			$this->handle = @ftp_ssl_connect($this->host, $this->port);
		} else {
			$this->handle = @ftp_connect($this->host, $this->port);
		}
		if($this->handle === false)
		{
			$this->setError(AKText::_('WRONG_FTP_HOST'));
			return false;
		}

		// Login
		if(! @ftp_login($this->handle, $this->user, $this->pass))
		{
			$this->setError(AKText::_('WRONG_FTP_USER'));
			@ftp_close($this->handle);
			return false;
		}

		// Change to initial directory
		if(! @ftp_chdir($this->handle, $this->dir))
		{
			$this->setError(AKText::_('WRONG_FTP_PATH1'));
			@ftp_close($this->handle);
			return false;
		}

		// Enable passive mode if the user requested it
		if( $this->passive )
		{
			@ftp_pasv($this->handle, true);
		}
		else
		{
			@ftp_pasv($this->handle, false);
		}

		// Try to download ourselves
		$testFilename = defined('KSSELFNAME') ? KSSELFNAME : basename(__FILE__);
		$tempHandle = fopen('php://temp', 'r+');
		if (@ftp_fget($this->handle, $tempHandle, $testFilename, FTP_ASCII, 0) === false)
		{
			$this->setError(AKText::_('WRONG_FTP_PATH2'));
			@ftp_close($this->handle);
			fclose($tempHandle);

			return false;
		}
		fclose($tempHandle);

		return true;
	}

	public function process()
	{
		if( is_null($this->tempFilename) )
		{
			// If an empty filename is passed, it means that we shouldn't do any post processing, i.e.
			// the entity was a directory or symlink
			return true;
		}

		$remotePath = dirname($this->filename);
		$removePath = AKFactory::get('kickstart.setup.destdir','');
		if(!empty($removePath))
		{
			$removePath = ltrim($removePath, "/");
			$remotePath = ltrim($remotePath, "/");
			$left = substr($remotePath, 0, strlen($removePath));
			if($left == $removePath)
			{
				$remotePath = substr($remotePath, strlen($removePath));
			}
		}

		$absoluteFSPath = dirname($this->filename);
		$relativeFTPPath = trim($remotePath, '/');
		$absoluteFTPPath = '/'.trim( $this->dir, '/' ).'/'.trim($remotePath, '/');
		$onlyFilename = basename($this->filename);

		$remoteName = $absoluteFTPPath.'/'.$onlyFilename;

		$ret = @ftp_chdir($this->handle, $absoluteFTPPath);
		if($ret === false)
		{
			$ret = $this->createDirRecursive( $absoluteFSPath, 0755);
			if($ret === false) {
				$this->setError(AKText::sprintf('FTP_COULDNT_UPLOAD', $this->filename));
				return false;
			}
			$ret = @ftp_chdir($this->handle, $absoluteFTPPath);
			if($ret === false) {
				$this->setError(AKText::sprintf('FTP_COULDNT_UPLOAD', $this->filename));
				return false;
			}
		}

		$ret = @ftp_put($this->handle, $remoteName, $this->tempFilename, FTP_BINARY);
		if($ret === false)
		{
			// If we couldn't create the file, attempt to fix the permissions in the PHP level and retry!
			$this->fixPermissions($this->filename);
			$this->unlink($this->filename);

			$fp = @fopen($this->tempFilename, 'rb');
			if($fp !== false)
			{
				$ret = @ftp_fput($this->handle, $remoteName, $fp, FTP_BINARY);
				@fclose($fp);
			}
			else
			{
				$ret = false;
			}
		}
		@unlink($this->tempFilename);

		if($ret === false)
		{
			$this->setError(AKText::sprintf('FTP_COULDNT_UPLOAD', $this->filename));
			return false;
		}
		$restorePerms = AKFactory::get('kickstart.setup.restoreperms', false);
		if($restorePerms)
		{
			@ftp_chmod($this->_handle, $this->perms, $remoteName);
		}
		else
		{
			@ftp_chmod($this->_handle, 0644, $remoteName);
		}
		return true;
	}

	public function processFilename($filename, $perms = 0755)
	{
		// Catch some error conditions...
		if($this->getError())
		{
			return false;
		}

		// If a null filename is passed, it means that we shouldn't do any post processing, i.e.
		// the entity was a directory or symlink
		if(is_null($filename))
		{
			$this->filename = null;
			$this->tempFilename = null;
			return null;
		}

		// Strip absolute filesystem path to website's root
		$removePath = AKFactory::get('kickstart.setup.destdir','');
		if(!empty($removePath))
		{
			$left = substr($filename, 0, strlen($removePath));
			if($left == $removePath)
			{
				$filename = substr($filename, strlen($removePath));
			}
		}

		// Trim slash on the left
		$filename = ltrim($filename, '/');

		$this->filename = $filename;
		$this->tempFilename = tempnam($this->tempDir, 'kickstart-');
		$this->perms = $perms;

		if( empty($this->tempFilename) )
		{
			// Oops! Let's try something different
			$this->tempFilename = $this->tempDir.'/kickstart-'.time().'.dat';
		}

		return $this->tempFilename;
	}

	private function isDirWritable($dir)
	{
		$fp = @fopen($dir.'/kickstart.dat', 'wb');
		if($fp === false)
		{
			return false;
		}
		else
		{
			@fclose($fp);
			unlink($dir.'/kickstart.dat');
			return true;
		}
	}

	public function createDirRecursive( $dirName, $perms )
	{
		// Strip absolute filesystem path to website's root
		$removePath = AKFactory::get('kickstart.setup.destdir','');
		if(!empty($removePath))
		{
			// UNIXize the paths
			$removePath = str_replace('\\','/',$removePath);
			$dirName = str_replace('\\','/',$dirName);
			// Make sure they both end in a slash
			$removePath = rtrim($removePath,'/\\').'/';
			$dirName = rtrim($dirName,'/\\').'/';
			// Process the path removal
			$left = substr($dirName, 0, strlen($removePath));
			if($left == $removePath)
			{
				$dirName = substr($dirName, strlen($removePath));
			}
		}
		if(empty($dirName)) $dirName = ''; // 'cause the substr() above may return FALSE.

		$check = '/'.trim($this->dir,'/').'/'.trim($dirName, '/');
		if($this->is_dir($check)) return true;

		$alldirs = explode('/', $dirName);
		$previousDir = '/'.trim($this->dir);
		foreach($alldirs as $curdir)
		{
			$check = $previousDir.'/'.$curdir;
			if(!$this->is_dir($check))
			{
				// Proactively try to delete a file by the same name
				@ftp_delete($this->handle, $check);

				if(@ftp_mkdir($this->handle, $check) === false)
				{
					// If we couldn't create the directory, attempt to fix the permissions in the PHP level and retry!
					$this->fixPermissions($removePath.$check);
					if(@ftp_mkdir($this->handle, $check) === false)
					{
						// Can we fall back to pure PHP mode, sire?
						if(!@mkdir($check))
						{
							$this->setError(AKText::sprintf('FTP_CANT_CREATE_DIR', $check));
							return false;
						}
						else
						{
							// Since the directory was built by PHP, change its permissions
							@chmod($check, "0777");
							return true;
						}
					}
				}
				@ftp_chmod($this->handle, $perms, $check);
			}
			$previousDir = $check;
		}

		return true;
	}

	public function close()
	{
		@ftp_close($this->handle);
	}

	/*
	 * Tries to fix directory/file permissions in the PHP level, so that
	 * the FTP operation doesn't fail.
	 * @param $path string The full path to a directory or file
	 */
	private function fixPermissions( $path )
	{
		// Turn off error reporting
		if(!defined('KSDEBUG')) {
			$oldErrorReporting = @error_reporting(E_NONE);
		}

		// Get UNIX style paths
		$relPath = str_replace('\\','/',$path);
		$basePath = rtrim(str_replace('\\','/',KSROOTDIR),'/');
		$basePath = rtrim($basePath,'/');
		if(!empty($basePath)) $basePath .= '/';
		// Remove the leading relative root
		if( substr($relPath,0,strlen($basePath)) == $basePath )
			$relPath = substr($relPath,strlen($basePath));
		$dirArray = explode('/', $relPath);
		$pathBuilt = rtrim($basePath,'/');
		foreach( $dirArray as $dir )
		{
			if(empty($dir)) continue;
			$oldPath = $pathBuilt;
			$pathBuilt .= '/'.$dir;
			if(is_dir($oldPath.$dir))
			{
				@chmod($oldPath.$dir, 0777);
			}
			else
			{
				if(@chmod($oldPath.$dir, 0777) === false)
				{
					@unlink($oldPath.$dir);
				}
			}
		}

		// Restore error reporting
		if(!defined('KSDEBUG')) {
			@error_reporting($oldErrorReporting);
		}
	}

	public function chmod( $file, $perms )
	{
		return @ftp_chmod($this->handle, $perms, $file);
	}

	private function is_dir( $dir )
	{
		return @ftp_chdir( $this->handle, $dir );
	}

	public function unlink( $file )
	{
		$removePath = AKFactory::get('kickstart.setup.destdir','');
		if(!empty($removePath))
		{
			$left = substr($file, 0, strlen($removePath));
			if($left == $removePath)
			{
				$file = substr($file, strlen($removePath));
			}
		}

		$check = '/'.trim($this->dir,'/').'/'.trim($file, '/');

		return @ftp_delete( $this->handle, $check );
	}

	public function rmdir( $directory )
	{
		$removePath = AKFactory::get('kickstart.setup.destdir','');
		if(!empty($removePath))
		{
			$left = substr($directory, 0, strlen($removePath));
			if($left == $removePath)
			{
				$directory = substr($directory, strlen($removePath));
			}
		}

		$check = '/'.trim($this->dir,'/').'/'.trim($directory, '/');

		return @ftp_rmdir( $this->handle, $check );
	}

	public function rename( $from, $to )
	{
		$originalFrom = $from;
		$originalTo = $to;

		$removePath = AKFactory::get('kickstart.setup.destdir','');
		if(!empty($removePath))
		{
			$left = substr($from, 0, strlen($removePath));
			if($left == $removePath)
			{
				$from = substr($from, strlen($removePath));
			}
		}
		$from = '/'.trim($this->dir,'/').'/'.trim($from, '/');

		if(!empty($removePath))
		{
			$left = substr($to, 0, strlen($removePath));
			if($left == $removePath)
			{
				$to = substr($to, strlen($removePath));
			}
		}
		$to = '/'.trim($this->dir,'/').'/'.trim($to, '/');

		$result = @ftp_rename( $this->handle, $from, $to );
		if($result !== true)
		{
			return @rename($from, $to);
		}
		else
		{
			return true;
		}
	}

}


/**
 * Akeeba Restore
 * A JSON-powered JPA, JPS and ZIP archive extraction library
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / Akeeba Ltd.
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

/**
 * FTP file writer
 */
class AKPostprocSFTP extends AKAbstractPostproc
{
	/** @var bool Should I use FTP over implicit SSL? */
	public $useSSL = false;
	/** @var bool use Passive mode? */
	public $passive = true;
	/** @var string FTP host name */
	public $host = '';
	/** @var int FTP port */
	public $port = 21;
	/** @var string FTP user name */
	public $user = '';
	/** @var string FTP password */
	public $pass = '';
	/** @var string FTP initial directory */
	public $dir = '';

    /** @var resource SFTP resource handle */
	private $handle = null;

    /** @var resource SSH2 connection resource handle */
    private $_connection = null;

    /** @var string Current remote directory, including the remote directory string */
    private $_currentdir;

	/** @var string The temporary directory where the data will be stored */
	private $tempDir = '';

	public function __construct()
	{
		parent::__construct();

		$this->host     = AKFactory::get('kickstart.ftp.host', '');
		$this->port     = AKFactory::get('kickstart.ftp.port', 22);

		if(trim($this->port) == '') $this->port = 22;

		$this->user     = AKFactory::get('kickstart.ftp.user', '');
		$this->pass     = AKFactory::get('kickstart.ftp.pass', '');
		$this->dir      = AKFactory::get('kickstart.ftp.dir', '');
		$this->tempDir  = AKFactory::get('kickstart.ftp.tempdir', '');

		$connected = $this->connect();

		if($connected)
		{
			if(!empty($this->tempDir))
			{
				$tempDir = rtrim($this->tempDir, '/\\').'/';
				$writable = $this->isDirWritable($tempDir);
			}
			else
			{
				$tempDir = '';
				$writable = false;
			}

			if(!$writable) {
				// Default temporary directory is the current root
				$tempDir = KSROOTDIR;
				if(empty($tempDir))
				{
					// Oh, we have no directory reported!
					$tempDir = '.';
				}
				$absoluteDirToHere = $tempDir;
				$tempDir = rtrim(str_replace('\\','/',$tempDir),'/');
				if(!empty($tempDir)) $tempDir .= '/';
				$this->tempDir = $tempDir;
				// Is this directory writable?
				$writable = $this->isDirWritable($tempDir);
			}

			if(!$writable)
			{
				// Nope. Let's try creating a temporary directory in the site's root.
				$tempDir = $absoluteDirToHere.'/kicktemp';
				$this->createDirRecursive($tempDir, 0777);
				// Try making it writable...
				$this->fixPermissions($tempDir);
				$writable = $this->isDirWritable($tempDir);
			}

			// Was the new directory writable?
			if(!$writable)
			{
				// Let's see if the user has specified one
				$userdir = AKFactory::get('kickstart.ftp.tempdir', '');
				if(!empty($userdir))
				{
					// Is it an absolute or a relative directory?
					$absolute = false;
					$absolute = $absolute || ( substr($userdir,0,1) == '/' );
					$absolute = $absolute || ( substr($userdir,1,1) == ':' );
					$absolute = $absolute || ( substr($userdir,2,1) == ':' );
					if(!$absolute)
					{
						// Make absolute
						$tempDir = $absoluteDirToHere.$userdir;
					}
					else
					{
						// it's already absolute
						$tempDir = $userdir;
					}
					// Does the directory exist?
					if( is_dir($tempDir) )
					{
						// Yeah. Is it writable?
						$writable = $this->isDirWritable($tempDir);
					}
				}
			}
			$this->tempDir = $tempDir;

			if(!$writable)
			{
				// No writable directory found!!!
				$this->setError(AKText::_('SFTP_TEMPDIR_NOT_WRITABLE'));
			}
			else
			{
				AKFactory::set('kickstart.ftp.tempdir', $tempDir);
				$this->tempDir = $tempDir;
			}
		}
	}

	function __wakeup()
	{
		$this->connect();
	}

	public function connect()
	{
        $this->_connection = false;

        if(!function_exists('ssh2_connect'))
        {
            $this->setError(AKText::_('SFTP_NO_SSH2'));
            return false;
        }

        $this->_connection = @ssh2_connect($this->host, $this->port);

        if (!@ssh2_auth_password($this->_connection, $this->user, $this->pass))
        {
            $this->setError(AKText::_('SFTP_WRONG_USER'));

            $this->_connection = false;

            return false;
        }

        $this->handle = @ssh2_sftp($this->_connection);

        // I must have an absolute directory
        if(!$this->dir)
        {
            $this->setError(AKText::_('SFTP_WRONG_STARTING_DIR'));
            return false;
        }

        // Change to initial directory
        if(!$this->sftp_chdir('/'))
        {
            $this->setError(AKText::_('SFTP_WRONG_STARTING_DIR'));

            unset($this->_connection);
            unset($this->handle);

            return false;
        }

        // Try to download ourselves
        $testFilename = defined('KSSELFNAME') ? KSSELFNAME : basename(__FILE__);
        $basePath     = '/'.trim($this->dir, '/');

        if(@fopen("ssh2.sftp://{$this->handle}$basePath/$testFilename",'r+') === false)
        {
            $this->setError(AKText::_('SFTP_WRONG_STARTING_DIR'));

            unset($this->_connection);
            unset($this->handle);

            return false;
        }

        return true;
	}

	public function process()
	{
		if( is_null($this->tempFilename) )
		{
			// If an empty filename is passed, it means that we shouldn't do any post processing, i.e.
			// the entity was a directory or symlink
			return true;
		}

		$remotePath      = dirname($this->filename);
		$absoluteFSPath  = dirname($this->filename);
		$absoluteFTPPath = '/'.trim( $this->dir, '/' ).'/'.trim($remotePath, '/');
		$onlyFilename    = basename($this->filename);

		$remoteName = $absoluteFTPPath.'/'.$onlyFilename;

        $ret = $this->sftp_chdir($absoluteFTPPath);

		if($ret === false)
		{
			$ret = $this->createDirRecursive( $absoluteFSPath, 0755);

			if($ret === false)
            {
				$this->setError(AKText::sprintf('SFTP_COULDNT_UPLOAD', $this->filename));
				return false;
			}

			$ret = $this->sftp_chdir($absoluteFTPPath);

			if($ret === false)
            {
				$this->setError(AKText::sprintf('SFTP_COULDNT_UPLOAD', $this->filename));
				return false;
			}
		}

        // Create the file
        $ret = $this->write($this->tempFilename, $remoteName);

        // If I got a -1 it means that I wasn't able to open the file, so I have to stop here
        if($ret === -1)
        {
            $this->setError(AKText::sprintf('SFTP_COULDNT_UPLOAD', $this->filename));
            return false;
        }

		if($ret === false)
		{
			// If we couldn't create the file, attempt to fix the permissions in the PHP level and retry!
			$this->fixPermissions($this->filename);
			$this->unlink($this->filename);

            $ret = $this->write($this->tempFilename, $remoteName);
		}

		@unlink($this->tempFilename);

		if($ret === false)
		{
			$this->setError(AKText::sprintf('SFTP_COULDNT_UPLOAD', $this->filename));
			return false;
		}
		$restorePerms = AKFactory::get('kickstart.setup.restoreperms', false);

		if($restorePerms)
		{
            $this->chmod($remoteName, $this->perms);
		}
		else
		{
            $this->chmod($remoteName, 0644);
		}
		return true;
	}

	public function processFilename($filename, $perms = 0755)
	{
		// Catch some error conditions...
		if($this->getError())
		{
			return false;
		}

		// If a null filename is passed, it means that we shouldn't do any post processing, i.e.
		// the entity was a directory or symlink
		if(is_null($filename))
		{
			$this->filename = null;
			$this->tempFilename = null;
			return null;
		}

        // Strip absolute filesystem path to website's root
        $removePath = AKFactory::get('kickstart.setup.destdir','');
        if(!empty($removePath))
        {
            $left = substr($filename, 0, strlen($removePath));
            if($left == $removePath)
            {
                $filename = substr($filename, strlen($removePath));
            }
        }

        // Trim slash on the left
        $filename = ltrim($filename, '/');

		$this->filename = $filename;
		$this->tempFilename = tempnam($this->tempDir, 'kickstart-');
		$this->perms = $perms;

		if( empty($this->tempFilename) )
		{
			// Oops! Let's try something different
			$this->tempFilename = $this->tempDir.'/kickstart-'.time().'.dat';
		}

		return $this->tempFilename;
	}

	private function isDirWritable($dir)
	{
		if(@fopen("ssh2.sftp://{$this->handle}$dir/kickstart.dat",'wb') === false)
		{
			return false;
		}
		else
		{
            @ssh2_sftp_unlink($this->handle, $dir.'/kickstart.dat');
			return true;
		}
	}

	public function createDirRecursive( $dirName, $perms )
	{
        // Strip absolute filesystem path to website's root
        $removePath = AKFactory::get('kickstart.setup.destdir','');
        if(!empty($removePath))
        {
            // UNIXize the paths
            $removePath = str_replace('\\','/',$removePath);
            $dirName = str_replace('\\','/',$dirName);
            // Make sure they both end in a slash
            $removePath = rtrim($removePath,'/\\').'/';
            $dirName = rtrim($dirName,'/\\').'/';
            // Process the path removal
            $left = substr($dirName, 0, strlen($removePath));
            if($left == $removePath)
            {
                $dirName = substr($dirName, strlen($removePath));
            }
        }
        if(empty($dirName)) $dirName = ''; // 'cause the substr() above may return FALSE.

		$check = '/'.trim($this->dir,'/ ').'/'.trim($dirName, '/');

		if($this->is_dir($check))
        {
            return true;
        }

		$alldirs = explode('/', $dirName);
		$previousDir = '/'.trim($this->dir, '/ ');

		foreach($alldirs as $curdir)
		{
            if(!$curdir)
            {
                continue;
            }

			$check = $previousDir.'/'.$curdir;

			if(!$this->is_dir($check))
			{
				// Proactively try to delete a file by the same name
                @ssh2_sftp_unlink($this->handle, $check);

				if(@ssh2_sftp_mkdir($this->handle, $check) === false)
				{
					// If we couldn't create the directory, attempt to fix the permissions in the PHP level and retry!
					$this->fixPermissions($check);

					if(@ssh2_sftp_mkdir($this->handle, $check) === false)
					{
						// Can we fall back to pure PHP mode, sire?
						if(!@mkdir($check))
						{
							$this->setError(AKText::sprintf('FTP_CANT_CREATE_DIR', $check));
							return false;
						}
						else
						{
							// Since the directory was built by PHP, change its permissions
							@chmod($check, "0777");
							return true;
						}
					}
				}

				@ssh2_sftp_chmod($this->handle, $check, $perms);
			}

			$previousDir = $check;
		}

		return true;
	}

	public function close()
	{
		unset($this->_connection);
		unset($this->handle);
	}

	/*
	 * Tries to fix directory/file permissions in the PHP level, so that
	 * the FTP operation doesn't fail.
	 * @param $path string The full path to a directory or file
	 */
	private function fixPermissions( $path )
	{
		// Turn off error reporting
		if(!defined('KSDEBUG')) {
			$oldErrorReporting = @error_reporting(E_NONE);
		}

		// Get UNIX style paths
		$relPath  = str_replace('\\','/',$path);
		$basePath = rtrim(str_replace('\\','/',KSROOTDIR),'/');
		$basePath = rtrim($basePath,'/');

		if(!empty($basePath))
        {
            $basePath .= '/';
        }

		// Remove the leading relative root
		if( substr($relPath,0,strlen($basePath)) == $basePath )
        {
            $relPath = substr($relPath,strlen($basePath));
        }

		$dirArray  = explode('/', $relPath);
		$pathBuilt = rtrim($basePath,'/');

		foreach( $dirArray as $dir )
		{
			if(empty($dir))
            {
                continue;
            }

			$oldPath = $pathBuilt;
			$pathBuilt .= '/'.$dir;

			if(is_dir($oldPath.'/'.$dir))
			{
				@chmod($oldPath.'/'.$dir, 0777);
			}
			else
			{
				if(@chmod($oldPath.'/'.$dir, 0777) === false)
				{
					@unlink($oldPath.$dir);
				}
			}
		}

		// Restore error reporting
		if(!defined('KSDEBUG')) {
			@error_reporting($oldErrorReporting);
		}
	}

	public function chmod( $file, $perms )
	{
        return @ssh2_sftp_chmod($this->handle, $file, $perms);
	}

	private function is_dir( $dir )
	{
        return $this->sftp_chdir($dir);
	}

    private function write($local, $remote)
    {
        $fp      = @fopen("ssh2.sftp://{$this->handle}$remote",'w');
        $localfp = @fopen($local,'rb');

        if($fp === false)
        {
            return -1;
        }

        if($localfp === false)
        {
            @fclose($fp);
            return -1;
        }

        $res = true;

        while(!feof($localfp) && ($res !== false))
        {
            $buffer = @fread($localfp, 65567);
            $res    = @fwrite($fp, $buffer);
        }

        @fclose($fp);
        @fclose($localfp);

        return $res;
    }

	public function unlink( $file )
	{
		$check    = '/'.trim($this->dir,'/').'/'.trim($file, '/');

        return @ssh2_sftp_unlink($this->handle, $check);
	}

	public function rmdir( $directory )
	{
		$check    = '/'.trim($this->dir,'/').'/'.trim($directory, '/');

		return @ssh2_sftp_rmdir( $this->handle, $check);
	}

	public function rename( $from, $to )
	{
        $from     = '/'.trim($this->dir,'/').'/'.trim($from, '/');
        $to       = '/'.trim($this->dir,'/').'/'.trim($to, '/');

        $result =  @ssh2_sftp_rename($this->handle, $from, $to);

		if($result !== true)
		{
			return @rename($from, $to);
		}
		else
		{
			return true;
		}
	}

    /**
     * Changes to the requested directory in the remote server. You give only the
     * path relative to the initial directory and it does all the rest by itself,
     * including doing nothing if the remote directory is the one we want.
     *
     * @param   string  $dir    The (realtive) remote directory
     *
     * @return  bool True if successful, false otherwise.
     */
    private function sftp_chdir($dir)
    {
        // Strip absolute filesystem path to website's root
        $removePath = AKFactory::get('kickstart.setup.destdir','');
        if(!empty($removePath))
        {
            // UNIXize the paths
            $removePath = str_replace('\\','/',$removePath);
            $dir        = str_replace('\\','/',$dir);

            // Make sure they both end in a slash
            $removePath = rtrim($removePath,'/\\').'/';
            $dir        = rtrim($dir,'/\\').'/';

            // Process the path removal
            $left = substr($dir, 0, strlen($removePath));

            if($left == $removePath)
            {
                $dir = substr($dir, strlen($removePath));
            }
        }

        if(empty($dir))
        {
            // Because the substr() above may return FALSE.
            $dir = '';
        }

        // Calculate "real" (absolute) SFTP path
        $realdir = substr($this->dir, -1) == '/' ? substr($this->dir, 0, strlen($this->dir) - 1) : $this->dir;
        $realdir .= '/'.$dir;
        $realdir = substr($realdir, 0, 1) == '/' ? $realdir : '/'.$realdir;

        if($this->_currentdir == $realdir)
        {
            // Already there, do nothing
            return true;
        }

        $result = @ssh2_sftp_stat($this->handle, $realdir);

        if($result === false)
        {
            return false;
        }
        else
        {
            // Update the private "current remote directory" variable
            $this->_currentdir = $realdir;

            return true;
        }
    }

}


/**
 * Akeeba Restore
 * A JSON-powered JPA, JPS and ZIP archive extraction library
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / Akeeba Ltd.
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

/**
 * Hybrid direct / FTP mode file writer
 */
class AKPostprocHybrid extends AKAbstractPostproc
{

	/** @var bool Should I use the FTP layer? */
	public $useFTP = false;

	/** @var bool Should I use FTP over implicit SSL? */
	public $useSSL = false;

	/** @var bool use Passive mode? */
	public $passive = true;

	/** @var string FTP host name */
	public $host = '';

	/** @var int FTP port */
	public $port = 21;

	/** @var string FTP user name */
	public $user = '';

	/** @var string FTP password */
	public $pass = '';

	/** @var string FTP initial directory */
	public $dir = '';

	/** @var resource The FTP handle */
	private $handle = null;

	/** @var string The temporary directory where the data will be stored */
	private $tempDir = '';

	/** @var null The FTP connection handle */
	private $_handle = null;

	/**
	 * Public constructor. Tries to connect to the FTP server.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->useFTP = true;
		$this->useSSL = AKFactory::get('kickstart.ftp.ssl', false);
		$this->passive = AKFactory::get('kickstart.ftp.passive', true);
		$this->host = AKFactory::get('kickstart.ftp.host', '');
		$this->port = AKFactory::get('kickstart.ftp.port', 21);
		$this->user = AKFactory::get('kickstart.ftp.user', '');
		$this->pass = AKFactory::get('kickstart.ftp.pass', '');
		$this->dir = AKFactory::get('kickstart.ftp.dir', '');
		$this->tempDir = AKFactory::get('kickstart.ftp.tempdir', '');

		if (trim($this->port) == '')
		{
			$this->port = 21;
		}

		// If FTP is not configured, skip it altogether
		if (empty($this->host) || empty($this->user) || empty($this->pass))
		{
			$this->useFTP = false;
		}

		// Try to connect to the FTP server
		$connected = $this->connect();

		// If the connection fails, skip FTP altogether
		if (!$connected)
		{
			$this->useFTP = false;
		}

		if ($connected)
		{
			if (!empty($this->tempDir))
			{
				$tempDir = rtrim($this->tempDir, '/\\') . '/';
				$writable = $this->isDirWritable($tempDir);
			}
			else
			{
				$tempDir = '';
				$writable = false;
			}

			if (!$writable)
			{
				// Default temporary directory is the current root
				$tempDir = KSROOTDIR;
				if (empty($tempDir))
				{
					// Oh, we have no directory reported!
					$tempDir = '.';
				}
				$absoluteDirToHere = $tempDir;
				$tempDir = rtrim(str_replace('\\', '/', $tempDir), '/');
				if (!empty($tempDir))
				{
					$tempDir .= '/';
				}
				$this->tempDir = $tempDir;
				// Is this directory writable?
				$writable = $this->isDirWritable($tempDir);
			}

			if (!$writable)
			{
				// Nope. Let's try creating a temporary directory in the site's root.
				$tempDir = $absoluteDirToHere . '/kicktemp';
				$this->createDirRecursive($tempDir, 0777);
				// Try making it writable...
				$this->fixPermissions($tempDir);
				$writable = $this->isDirWritable($tempDir);
			}

			// Was the new directory writable?
			if (!$writable)
			{
				// Let's see if the user has specified one
				$userdir = AKFactory::get('kickstart.ftp.tempdir', '');
				if (!empty($userdir))
				{
					// Is it an absolute or a relative directory?
					$absolute = false;
					$absolute = $absolute || (substr($userdir, 0, 1) == '/');
					$absolute = $absolute || (substr($userdir, 1, 1) == ':');
					$absolute = $absolute || (substr($userdir, 2, 1) == ':');
					if (!$absolute)
					{
						// Make absolute
						$tempDir = $absoluteDirToHere . $userdir;
					}
					else
					{
						// it's already absolute
						$tempDir = $userdir;
					}
					// Does the directory exist?
					if (is_dir($tempDir))
					{
						// Yeah. Is it writable?
						$writable = $this->isDirWritable($tempDir);
					}
				}
			}
			$this->tempDir = $tempDir;

			if (!$writable)
			{
				// No writable directory found!!!
				$this->setError(AKText::_('FTP_TEMPDIR_NOT_WRITABLE'));
			}
			else
			{
				AKFactory::set('kickstart.ftp.tempdir', $tempDir);
				$this->tempDir = $tempDir;
			}
		}
	}

	/**
	 * Called after unserialisation, tries to reconnect to FTP
	 */
	function __wakeup()
	{
		if ($this->useFTP)
		{
			$this->connect();
		}
	}

	function __destruct()
	{
		if (!$this->useFTP)
		{
			@ftp_close($this->handle);
		}
	}

	/**
	 * Tries to connect to the FTP server
	 *
	 * @return bool
	 */
	public function connect()
	{
		if (!$this->useFTP)
		{
			return false;
		}

		// Connect to server, using SSL if so required
		if ($this->useSSL)
		{
			$this->handle = @ftp_ssl_connect($this->host, $this->port);
		}
		else
		{
			$this->handle = @ftp_connect($this->host, $this->port);
		}
		if ($this->handle === false)
		{
			$this->setError(AKText::_('WRONG_FTP_HOST'));

			return false;
		}

		// Login
		if (!@ftp_login($this->handle, $this->user, $this->pass))
		{
			$this->setError(AKText::_('WRONG_FTP_USER'));
			@ftp_close($this->handle);

			return false;
		}

		// Change to initial directory
		if (!@ftp_chdir($this->handle, $this->dir))
		{
			$this->setError(AKText::_('WRONG_FTP_PATH1'));
			@ftp_close($this->handle);

			return false;
		}

		// Enable passive mode if the user requested it
		if ($this->passive)
		{
			@ftp_pasv($this->handle, true);
		}
		else
		{
			@ftp_pasv($this->handle, false);
		}

		// Try to download ourselves
		$testFilename = defined('KSSELFNAME') ? KSSELFNAME : basename(__FILE__);
		$tempHandle = fopen('php://temp', 'r+');

		if (@ftp_fget($this->handle, $tempHandle, $testFilename, FTP_ASCII, 0) === false)
		{
			$this->setError(AKText::_('WRONG_FTP_PATH2'));
			@ftp_close($this->handle);
			fclose($tempHandle);

			return false;
		}

		fclose($tempHandle);

		return true;
	}

	/**
	 * Post-process an extracted file, using FTP or direct file writes to move it
	 *
	 * @return bool
	 */
	public function process()
	{
		if (is_null($this->tempFilename))
		{
			// If an empty filename is passed, it means that we shouldn't do any post processing, i.e.
			// the entity was a directory or symlink
			return true;
		}

		$remotePath = dirname($this->filename);
		$removePath = AKFactory::get('kickstart.setup.destdir', '');
		$root = rtrim($removePath, '/\\');

		if (!empty($removePath))
		{
			$removePath = ltrim($removePath, "/");
			$remotePath = ltrim($remotePath, "/");
			$left = substr($remotePath, 0, strlen($removePath));

			if ($left == $removePath)
			{
				$remotePath = substr($remotePath, strlen($removePath));
			}
		}

		$absoluteFSPath = dirname($this->filename);
		$relativeFTPPath = trim($remotePath, '/');
		$absoluteFTPPath = '/' . trim($this->dir, '/') . '/' . trim($remotePath, '/');
		$onlyFilename = basename($this->filename);

		$remoteName = $absoluteFTPPath . '/' . $onlyFilename;

		// Does the directory exist?
		if (!is_dir($root . '/' . $absoluteFSPath))
		{
			$ret = $this->createDirRecursive($absoluteFSPath, 0755);

			if (($ret === false) && ($this->useFTP))
			{
				$ret = @ftp_chdir($this->handle, $absoluteFTPPath);
			}

			if ($ret === false)
			{
				$this->setError(AKText::sprintf('FTP_COULDNT_UPLOAD', $this->filename));

				return false;
			}
		}

		if ($this->useFTP)
		{
			$ret = @ftp_chdir($this->handle, $absoluteFTPPath);
		}

		// Try copying directly
		$ret = @copy($this->tempFilename, $root . '/' . $this->filename);

		if ($ret === false)
		{
			$this->fixPermissions($this->filename);
			$this->unlink($this->filename);

			$ret = @copy($this->tempFilename, $root . '/' . $this->filename);
		}

		if ($this->useFTP && ($ret === false))
		{
			$ret = @ftp_put($this->handle, $remoteName, $this->tempFilename, FTP_BINARY);

			if ($ret === false)
			{
				// If we couldn't create the file, attempt to fix the permissions in the PHP level and retry!
				$this->fixPermissions($this->filename);
				$this->unlink($this->filename);

				$fp = @fopen($this->tempFilename, 'rb');
				if ($fp !== false)
				{
					$ret = @ftp_fput($this->handle, $remoteName, $fp, FTP_BINARY);
					@fclose($fp);
				}
				else
				{
					$ret = false;
				}
			}
		}

		@unlink($this->tempFilename);

		if ($ret === false)
		{
			$this->setError(AKText::sprintf('FTP_COULDNT_UPLOAD', $this->filename));

			return false;
		}

		$restorePerms = AKFactory::get('kickstart.setup.restoreperms', false);
		$perms = $restorePerms ? $this->perms : 0644;

		$ret = @chmod($root . '/' . $this->filename, $perms);

		if ($this->useFTP && ($ret === false))
		{
			@ftp_chmod($this->_handle, $perms, $remoteName);
		}

		return true;
	}

	/**
	 * Create a temporary filename
	 *
	 * @param string $filename The original filename
	 * @param int    $perms    The file permissions
	 *
	 * @return string
	 */
	public function processFilename($filename, $perms = 0755)
	{
		// Catch some error conditions...
		if ($this->getError())
		{
			return false;
		}

		// If a null filename is passed, it means that we shouldn't do any post processing, i.e.
		// the entity was a directory or symlink
		if (is_null($filename))
		{
			$this->filename = null;
			$this->tempFilename = null;

			return null;
		}

		// Strip absolute filesystem path to website's root
		$removePath = AKFactory::get('kickstart.setup.destdir', '');

		if (!empty($removePath))
		{
			$left = substr($filename, 0, strlen($removePath));

			if ($left == $removePath)
			{
				$filename = substr($filename, strlen($removePath));
			}
		}

		// Trim slash on the left
		$filename = ltrim($filename, '/');

		$this->filename = $filename;
		$this->tempFilename = tempnam($this->tempDir, 'kickstart-');
		$this->perms = $perms;

		if (empty($this->tempFilename))
		{
			// Oops! Let's try something different
			$this->tempFilename = $this->tempDir . '/kickstart-' . time() . '.dat';
		}

		return $this->tempFilename;
	}

	/**
	 * Is the directory writeable?
	 *
	 * @param string $dir The directory ti check
	 *
	 * @return bool
	 */
	private function isDirWritable($dir)
	{
		$fp = @fopen($dir . '/kickstart.dat', 'wb');

		if ($fp === false)
		{
			return false;
		}

		@fclose($fp);
		unlink($dir . '/kickstart.dat');

		return true;
	}

	/**
	 * Create a directory, recursively
	 *
	 * @param string $dirName The directory to create
	 * @param int    $perms   The permissions to give to the directory
	 *
	 * @return bool
	 */
	public function createDirRecursive($dirName, $perms)
	{
		// Strip absolute filesystem path to website's root
		$removePath = AKFactory::get('kickstart.setup.destdir', '');

		if (!empty($removePath))
		{
			// UNIXize the paths
			$removePath = str_replace('\\', '/', $removePath);
			$dirName = str_replace('\\', '/', $dirName);
			// Make sure they both end in a slash
			$removePath = rtrim($removePath, '/\\') . '/';
			$dirName = rtrim($dirName, '/\\') . '/';
			// Process the path removal
			$left = substr($dirName, 0, strlen($removePath));

			if ($left == $removePath)
			{
				$dirName = substr($dirName, strlen($removePath));
			}
		}

		// 'cause the substr() above may return FALSE.
		if (empty($dirName))
		{
			$dirName = '';
		}

		$check = '/' . trim($this->dir, '/') . '/' . trim($dirName, '/');
		$checkFS = $removePath . trim($dirName, '/');

		if ($this->is_dir($check))
		{
			return true;
		}

		$alldirs = explode('/', $dirName);
		$previousDir = '/' . trim($this->dir);
		$previousDirFS = rtrim($removePath, '/\\');

		foreach ($alldirs as $curdir)
		{
			$check = $previousDir . '/' . $curdir;
			$checkFS = $previousDirFS . '/' . $curdir;

			if (!is_dir($checkFS) && !$this->is_dir($check))
			{
				// Proactively try to delete a file by the same name
				if (!@unlink($checkFS) && $this->useFTP)
				{
					@ftp_delete($this->handle, $check);
				}

				$createdDir = @mkdir($checkFS, 0755);

				if (!$createdDir && $this->useFTP)
				{
					$createdDir = @ftp_mkdir($this->handle, $check);
				}

				if ($createdDir === false)
				{
					// If we couldn't create the directory, attempt to fix the permissions in the PHP level and retry!
					$this->fixPermissions($checkFS);

					$createdDir = @mkdir($checkFS, 0755);
					if (!$createdDir && $this->useFTP)
					{
						$createdDir = @ftp_mkdir($this->handle, $check);
					}

					if ($createdDir === false)
					{
						$this->setError(AKText::sprintf('FTP_CANT_CREATE_DIR', $check));

						return false;
					}
				}

				if (!@chmod($checkFS, $perms) && $this->useFTP)
				{
					@ftp_chmod($this->handle, $perms, $check);
				}
			}

			$previousDir = $check;
			$previousDirFS = $checkFS;
		}

		return true;
	}

	/**
	 * Closes the FTP connection
	 */
	public function close()
	{
		if (!$this->useFTP)
		{
			@ftp_close($this->handle);
		}
	}

	/**
	 * Tries to fix directory/file permissions in the PHP level, so that
	 * the FTP operation doesn't fail.
	 *
	 * @param $path string The full path to a directory or file
	 */
	private function fixPermissions($path)
	{
		// Turn off error reporting
		if (!defined('KSDEBUG'))
		{
			$oldErrorReporting = @error_reporting(E_NONE);
		}

		// Get UNIX style paths
		$relPath = str_replace('\\', '/', $path);
		$basePath = rtrim(str_replace('\\', '/', KSROOTDIR), '/');
		$basePath = rtrim($basePath, '/');

		if (!empty($basePath))
		{
			$basePath .= '/';
		}

		// Remove the leading relative root
		if (substr($relPath, 0, strlen($basePath)) == $basePath)
		{
			$relPath = substr($relPath, strlen($basePath));
		}

		$dirArray = explode('/', $relPath);
		$pathBuilt = rtrim($basePath, '/');

		foreach ($dirArray as $dir)
		{
			if (empty($dir))
			{
				continue;
			}

			$oldPath = $pathBuilt;
			$pathBuilt .= '/' . $dir;

			if (is_dir($oldPath . $dir))
			{
				@chmod($oldPath . $dir, 0777);
			}
			else
			{
				if (@chmod($oldPath . $dir, 0777) === false)
				{
					@unlink($oldPath . $dir);
				}
			}
		}

		// Restore error reporting
		if (!defined('KSDEBUG'))
		{
			@error_reporting($oldErrorReporting);
		}
	}

	public function chmod($file, $perms)
	{
		if (AKFactory::get('kickstart.setup.dryrun', '0'))
		{
			return true;
		}

		$ret = @chmod($file, $perms);

		if (!$ret && $this->useFTP)
		{
			// Strip absolute filesystem path to website's root
			$removePath = AKFactory::get('kickstart.setup.destdir', '');

			if (!empty($removePath))
			{
				$left = substr($file, 0, strlen($removePath));

				if ($left == $removePath)
				{
					$file = substr($file, strlen($removePath));
				}
			}

			// Trim slash on the left
			$file = ltrim($file, '/');

			$ret = @ftp_chmod($this->handle, $perms, $file);
		}

		return $ret;
	}

	private function is_dir($dir)
	{
		if ($this->useFTP)
		{
			return @ftp_chdir($this->handle, $dir);
		}

		return false;
	}

	public function unlink($file)
	{
		$ret = @unlink($file);

		if (!$ret && $this->useFTP)
		{
			$removePath = AKFactory::get('kickstart.setup.destdir', '');
			if (!empty($removePath))
			{
				$left = substr($file, 0, strlen($removePath));
				if ($left == $removePath)
				{
					$file = substr($file, strlen($removePath));
				}
			}

			$check = '/' . trim($this->dir, '/') . '/' . trim($file, '/');

			$ret = @ftp_delete($this->handle, $check);
		}

		return $ret;
	}

	public function rmdir($directory)
	{
		$ret = @rmdir($directory);

		if (!$ret && $this->useFTP)
		{
			$removePath = AKFactory::get('kickstart.setup.destdir', '');
			if (!empty($removePath))
			{
				$left = substr($directory, 0, strlen($removePath));
				if ($left == $removePath)
				{
					$directory = substr($directory, strlen($removePath));
				}
			}

			$check = '/' . trim($this->dir, '/') . '/' . trim($directory, '/');

			$ret = @ftp_rmdir($this->handle, $check);
		}

		return $ret;
	}

	public function rename($from, $to)
	{
		$ret = @rename($from, $to);

		if (!$ret && $this->useFTP)
		{
			$originalFrom = $from;
			$originalTo = $to;

			$removePath = AKFactory::get('kickstart.setup.destdir', '');
			if (!empty($removePath))
			{
				$left = substr($from, 0, strlen($removePath));
				if ($left == $removePath)
				{
					$from = substr($from, strlen($removePath));
				}
			}
			$from = '/' . trim($this->dir, '/') . '/' . trim($from, '/');

			if (!empty($removePath))
			{
				$left = substr($to, 0, strlen($removePath));
				if ($left == $removePath)
				{
					$to = substr($to, strlen($removePath));
				}
			}
			$to = '/' . trim($this->dir, '/') . '/' . trim($to, '/');

			$ret = @ftp_rename($this->handle, $from, $to);
		}

		return $ret;
	}
}

/**
 * Akeeba Restore
 * A JSON-powered JPA, JPS and ZIP archive extraction library
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / Akeeba Ltd.
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

/**
 * JPA archive extraction class
 */
class AKUnarchiverJPA extends AKAbstractUnarchiver
{
	protected $archiveHeaderData = array();

	protected function readArchiveHeader()
	{
		debugMsg('Preparing to read archive header');
		// Initialize header data array
		$this->archiveHeaderData = new stdClass();

		// Open the first part
		debugMsg('Opening the first part');
		$this->nextFile();

		// Fail for unreadable files
		if( $this->fp === false ) {
			debugMsg('Could not open the first part');
			return false;
		}

		// Read the signature
		$sig = fread( $this->fp, 3 );

		if ($sig != 'JPA')
		{
			// Not a JPA file
			debugMsg('Invalid archive signature');
			$this->setError( AKText::_('ERR_NOT_A_JPA_FILE') );
			return false;
		}

		// Read and parse header length
		$header_length_array = unpack( 'v', fread( $this->fp, 2 ) );
		$header_length = $header_length_array[1];

		// Read and parse the known portion of header data (14 bytes)
		$bin_data = fread($this->fp, 14);
		$header_data = unpack('Cmajor/Cminor/Vcount/Vuncsize/Vcsize', $bin_data);

		// Load any remaining header data (forward compatibility)
		$rest_length = $header_length - 19;
		if( $rest_length > 0 )
			$junk = fread($this->fp, $rest_length);
		else
			$junk = '';

		// Temporary array with all the data we read
		$temp = array(
			'signature' => 			$sig,
			'length' => 			$header_length,
			'major' => 				$header_data['major'],
			'minor' => 				$header_data['minor'],
			'filecount' => 			$header_data['count'],
			'uncompressedsize' => 	$header_data['uncsize'],
			'compressedsize' => 	$header_data['csize'],
			'unknowndata' => 		$junk
		);
		// Array-to-object conversion
		foreach($temp as $key => $value)
		{
			$this->archiveHeaderData->{$key} = $value;
		}

		debugMsg('Header data:');
		debugMsg('Length              : '.$header_length);
		debugMsg('Major               : '.$header_data['major']);
		debugMsg('Minor               : '.$header_data['minor']);
		debugMsg('File count          : '.$header_data['count']);
		debugMsg('Uncompressed size   : '.$header_data['uncsize']);
		debugMsg('Compressed size	  : '.$header_data['csize']);

		$this->currentPartOffset = @ftell($this->fp);

		$this->dataReadLength = 0;

		return true;
	}

	/**
	 * Concrete classes must use this method to read the file header
	 * @return bool True if reading the file was successful, false if an error occured or we reached end of archive
	 */
	protected function readFileHeader()
	{
		// If the current part is over, proceed to the next part please
		if( $this->isEOF(true) ) {
			debugMsg('Archive part EOF; moving to next file');
			$this->nextFile();
		}

		debugMsg('Reading file signature');
		// Get and decode Entity Description Block
		$signature = fread($this->fp, 3);

		$this->fileHeader = new stdClass();
		$this->fileHeader->timestamp = 0;

		// Check signature
		if( $signature != 'JPF' )
		{
			if($this->isEOF(true))
			{
				// This file is finished; make sure it's the last one
				$this->nextFile();
				if(!$this->isEOF(false))
				{
					debugMsg('Invalid file signature before end of archive encountered');
					$this->setError(AKText::sprintf('INVALID_FILE_HEADER', $this->currentPartNumber, $this->currentPartOffset));
					return false;
				}
				// We're just finished
				return false;
			}
			else
			{
				$screwed = true;
				if(AKFactory::get('kickstart.setup.ignoreerrors', false)) {
					debugMsg('Invalid file block signature; launching heuristic file block signature scanner');
					$screwed = !$this->heuristicFileHeaderLocator();
					if(!$screwed) {
						$signature = 'JPF';
					} else {
						debugMsg('Heuristics failed. Brace yourself for the imminent crash.');
					}
				}
				if($screwed) {
					debugMsg('Invalid file block signature');
					// This is not a file block! The archive is corrupt.
					$this->setError(AKText::sprintf('INVALID_FILE_HEADER', $this->currentPartNumber, $this->currentPartOffset));
					return false;
				}
			}
		}
		// This a JPA Entity Block. Process the header.

		$isBannedFile = false;

		// Read length of EDB and of the Entity Path Data
		$length_array = unpack('vblocksize/vpathsize', fread($this->fp, 4));
		// Read the path data
		if($length_array['pathsize'] > 0) {
			$file = fread( $this->fp, $length_array['pathsize'] );
		} else {
			$file = '';
		}

		// Handle file renaming
		$isRenamed = false;
		if(is_array($this->renameFiles) && (count($this->renameFiles) > 0) )
		{
			if(array_key_exists($file, $this->renameFiles))
			{
				$file = $this->renameFiles[$file];
				$isRenamed = true;
			}
		}

		// Handle directory renaming
		$isDirRenamed = false;
		if(is_array($this->renameDirs) && (count($this->renameDirs) > 0)) {
			if(array_key_exists(dirname($file), $this->renameDirs)) {
				$file = rtrim($this->renameDirs[dirname($file)],'/').'/'.basename($file);
				$isRenamed = true;
				$isDirRenamed = true;
			}
		}

		// Read and parse the known data portion
		$bin_data = fread( $this->fp, 14 );
		$header_data = unpack('Ctype/Ccompression/Vcompsize/Vuncompsize/Vperms', $bin_data);
		// Read any unknown data
		$restBytes = $length_array['blocksize'] - (21 + $length_array['pathsize']);
		if( $restBytes > 0 )
		{
			// Start reading the extra fields
			while($restBytes >= 4)
			{
				$extra_header_data = fread($this->fp, 4);
				$extra_header = unpack('vsignature/vlength', $extra_header_data);
				$restBytes -= 4;
				$extra_header['length'] -= 4;
				switch($extra_header['signature'])
				{
					case 256:
						// File modified timestamp
						if($extra_header['length'] > 0)
						{
							$bindata = fread($this->fp, $extra_header['length']);
							$restBytes -= $extra_header['length'];
							$timestamps = unpack('Vmodified', substr($bindata,0,4));
							$filectime = $timestamps['modified'];
							$this->fileHeader->timestamp = $filectime;
						}
						break;

					default:
						// Unknown field
						if($extra_header['length']>0) {
							$junk = fread($this->fp, $extra_header['length']);
							$restBytes -= $extra_header['length'];
						}
						break;
				}
			}
			if($restBytes > 0) $junk = fread($this->fp, $restBytes);
		}

		$compressionType = $header_data['compression'];

		// Populate the return array
		$this->fileHeader->file = $file;
		$this->fileHeader->compressed = $header_data['compsize'];
		$this->fileHeader->uncompressed = $header_data['uncompsize'];
		switch($header_data['type'])
		{
			case 0:
				$this->fileHeader->type = 'dir';
				break;

			case 1:
				$this->fileHeader->type = 'file';
				break;

			case 2:
				$this->fileHeader->type = 'link';
				break;
		}
		switch( $compressionType )
		{
			case 0:
				$this->fileHeader->compression = 'none';
				break;
			case 1:
				$this->fileHeader->compression = 'gzip';
				break;
			case 2:
				$this->fileHeader->compression = 'bzip2';
				break;
		}
		$this->fileHeader->permissions = $header_data['perms'];

		// Find hard-coded banned files
		if( (basename($this->fileHeader->file) == ".") || (basename($this->fileHeader->file) == "..") )
		{
			$isBannedFile = true;
		}

		// Also try to find banned files passed in class configuration
		if((count($this->skipFiles) > 0) && (!$isRenamed) )
		{
			if(in_array($this->fileHeader->file, $this->skipFiles))
			{
				$isBannedFile = true;
			}
		}

		// If we have a banned file, let's skip it
		if($isBannedFile)
		{
			debugMsg('Skipping file '.$this->fileHeader->file);
			// Advance the file pointer, skipping exactly the size of the compressed data
			$seekleft = $this->fileHeader->compressed;
			while($seekleft > 0)
			{
				// Ensure that we can seek past archive part boundaries
				$curSize = @filesize($this->archiveList[$this->currentPartNumber]);
				$curPos = @ftell($this->fp);
				$canSeek = $curSize - $curPos;
				if($canSeek > $seekleft) $canSeek = $seekleft;
				@fseek( $this->fp, $canSeek, SEEK_CUR );
				$seekleft -= $canSeek;
				if($seekleft) $this->nextFile();
			}

			$this->currentPartOffset = @ftell($this->fp);
			$this->runState = AK_STATE_DONE;
			return true;
		}

		// Last chance to prepend a path to the filename
		if(!empty($this->addPath) && !$isDirRenamed)
		{
			$this->fileHeader->file = $this->addPath.$this->fileHeader->file;
		}

		// Get the translated path name
		$restorePerms = AKFactory::get('kickstart.setup.restoreperms', false);
		if($this->fileHeader->type == 'file')
		{
			// Regular file; ask the postproc engine to process its filename
			if($restorePerms)
			{
				$this->fileHeader->realFile = $this->postProcEngine->processFilename( $this->fileHeader->file, $this->fileHeader->permissions );
			}
			else
			{
				$this->fileHeader->realFile = $this->postProcEngine->processFilename( $this->fileHeader->file );
			}
		}
		elseif($this->fileHeader->type == 'dir')
		{
			$dir = $this->fileHeader->file;

			// Directory; just create it
			if($restorePerms)
			{
				$this->postProcEngine->createDirRecursive( $this->fileHeader->file, $this->fileHeader->permissions );
			}
			else
			{
				$this->postProcEngine->createDirRecursive( $this->fileHeader->file, 0755 );
			}
			$this->postProcEngine->processFilename(null);
		}
		else
		{
			// Symlink; do not post-process
			$this->postProcEngine->processFilename(null);
		}

		$this->createDirectory();

		// Header is read
		$this->runState = AK_STATE_HEADER;

		$this->dataReadLength = 0;

		return true;
	}

	/**
	 * Concrete classes must use this method to process file data. It must set $runState to AK_STATE_DATAREAD when
	 * it's finished processing the file data.
	 * @return bool True if processing the file data was successful, false if an error occured
	 */
	protected function processFileData()
	{
		switch( $this->fileHeader->type )
		{
			case 'dir':
				return $this->processTypeDir();
				break;

			case 'link':
				return $this->processTypeLink();
				break;

			case 'file':
				switch($this->fileHeader->compression)
				{
					case 'none':
						return $this->processTypeFileUncompressed();
						break;

					case 'gzip':
					case 'bzip2':
						return $this->processTypeFileCompressedSimple();
						break;

				}
				break;

			default:
				debugMsg('Unknown file type '.$this->fileHeader->type);
				break;
		}
	}

	private function processTypeFileUncompressed()
	{
		// Uncompressed files are being processed in small chunks, to avoid timeouts
		if( ($this->dataReadLength == 0) && !AKFactory::get('kickstart.setup.dryrun','0') )
		{
			// Before processing file data, ensure permissions are adequate
			$this->setCorrectPermissions( $this->fileHeader->file );
		}

		// Open the output file
		if( !AKFactory::get('kickstart.setup.dryrun','0') )
		{
			$ignore = AKFactory::get('kickstart.setup.ignoreerrors', false) || $this->isIgnoredDirectory($this->fileHeader->file);
			if ($this->dataReadLength == 0) {
				$outfp = @fopen( $this->fileHeader->realFile, 'wb' );
			} else {
				$outfp = @fopen( $this->fileHeader->realFile, 'ab' );
			}

			// Can we write to the file?
			if( ($outfp === false) && (!$ignore) ) {
				// An error occured
				debugMsg('Could not write to output file');
				$this->setError( AKText::sprintf('COULDNT_WRITE_FILE', $this->fileHeader->realFile) );
				return false;
			}
		}

		// Does the file have any data, at all?
		if( $this->fileHeader->compressed == 0 )
		{
			// No file data!
			if( !AKFactory::get('kickstart.setup.dryrun','0') && is_resource($outfp) ) @fclose($outfp);
			$this->runState = AK_STATE_DATAREAD;
			return true;
		}

		// Reference to the global timer
		$timer = AKFactory::getTimer();

		$toReadBytes = 0;
		$leftBytes = $this->fileHeader->compressed - $this->dataReadLength;

		// Loop while there's data to read and enough time to do it
		while( ($leftBytes > 0) && ($timer->getTimeLeft() > 0) )
		{
			$toReadBytes = ($leftBytes > $this->chunkSize) ? $this->chunkSize : $leftBytes;
			$data = $this->fread( $this->fp, $toReadBytes );
			$reallyReadBytes = akstringlen($data);
			$leftBytes -= $reallyReadBytes;
			$this->dataReadLength += $reallyReadBytes;
			if($reallyReadBytes < $toReadBytes)
			{
				// We read less than requested! Why? Did we hit local EOF?
				if( $this->isEOF(true) && !$this->isEOF(false) )
				{
					// Yeap. Let's go to the next file
					$this->nextFile();
				}
				else
				{
					// Nope. The archive is corrupt
					debugMsg('Not enough data in file. The archive is truncated or corrupt.');
					$this->setError( AKText::_('ERR_CORRUPT_ARCHIVE') );
					return false;
				}
			}
			if( !AKFactory::get('kickstart.setup.dryrun','0') )
				if(is_resource($outfp)) @fwrite( $outfp, $data );
		}

		// Close the file pointer
		if( !AKFactory::get('kickstart.setup.dryrun','0') )
			if(is_resource($outfp)) @fclose($outfp);

		// Was this a pre-timeout bail out?
		if( $leftBytes > 0 )
		{
			$this->runState = AK_STATE_DATA;
		}
		else
		{
			// Oh! We just finished!
			$this->runState = AK_STATE_DATAREAD;
			$this->dataReadLength = 0;
		}

		return true;
	}

	private function processTypeFileCompressedSimple()
	{
		if( !AKFactory::get('kickstart.setup.dryrun','0') )
		{
			// Before processing file data, ensure permissions are adequate
			$this->setCorrectPermissions( $this->fileHeader->file );

			// Open the output file
			$outfp = @fopen( $this->fileHeader->realFile, 'wb' );

			// Can we write to the file?
			$ignore = AKFactory::get('kickstart.setup.ignoreerrors', false) || $this->isIgnoredDirectory($this->fileHeader->file);
			if( ($outfp === false) && (!$ignore) ) {
				// An error occured
				debugMsg('Could not write to output file');
				$this->setError( AKText::sprintf('COULDNT_WRITE_FILE', $this->fileHeader->realFile) );
				return false;
			}
		}

		// Does the file have any data, at all?
		if( $this->fileHeader->compressed == 0 )
		{
			// No file data!
			if( !AKFactory::get('kickstart.setup.dryrun','0') )
				if(is_resource($outfp)) @fclose($outfp);
			$this->runState = AK_STATE_DATAREAD;
			return true;
		}

		// Simple compressed files are processed as a whole; we can't do chunk processing
		$zipData = $this->fread( $this->fp, $this->fileHeader->compressed );
		while( akstringlen($zipData) < $this->fileHeader->compressed )
		{
			// End of local file before reading all data, but have more archive parts?
			if($this->isEOF(true) && !$this->isEOF(false))
			{
				// Yeap. Read from the next file
				$this->nextFile();
				$bytes_left = $this->fileHeader->compressed - akstringlen($zipData);
				$zipData .= $this->fread( $this->fp, $bytes_left );
			}
			else
			{
				debugMsg('End of local file before reading all data with no more parts left. The archive is corrupt or truncated.');
				$this->setError( AKText::_('ERR_CORRUPT_ARCHIVE') );
				return false;
			}
		}

		if($this->fileHeader->compression == 'gzip')
		{
			$unzipData = gzinflate( $zipData );
		}
		elseif($this->fileHeader->compression == 'bzip2')
		{
			$unzipData = bzdecompress( $zipData );
		}
		unset($zipData);

		// Write to the file.
		if( !AKFactory::get('kickstart.setup.dryrun','0') && is_resource($outfp) )
		{
			@fwrite( $outfp, $unzipData, $this->fileHeader->uncompressed );
			@fclose( $outfp );
		}
		unset($unzipData);

		$this->runState = AK_STATE_DATAREAD;
		return true;
	}

	/**
	 * Process the file data of a link entry
	 * @return bool
	 */
	private function processTypeLink()
	{
		$readBytes = 0;
		$toReadBytes = 0;
		$leftBytes = $this->fileHeader->compressed;
		$data = '';

		while( $leftBytes > 0)
		{
			$toReadBytes = ($leftBytes > $this->chunkSize) ? $this->chunkSize : $leftBytes;
			$mydata = $this->fread( $this->fp, $toReadBytes );
			$reallyReadBytes = akstringlen($mydata);
			$data .= $mydata;
			$leftBytes -= $reallyReadBytes;
			if($reallyReadBytes < $toReadBytes)
			{
				// We read less than requested! Why? Did we hit local EOF?
				if( $this->isEOF(true) && !$this->isEOF(false) )
				{
					// Yeap. Let's go to the next file
					$this->nextFile();
				}
				else
				{
					debugMsg('End of local file before reading all data with no more parts left. The archive is corrupt or truncated.');
					// Nope. The archive is corrupt
					$this->setError( AKText::_('ERR_CORRUPT_ARCHIVE') );
					return false;
				}
			}
		}

		// Try to remove an existing file or directory by the same name
		if(file_exists($this->fileHeader->realFile)) { @unlink($this->fileHeader->realFile); @rmdir($this->fileHeader->realFile); }
		// Remove any trailing slash
		if(substr($this->fileHeader->realFile, -1) == '/') $this->fileHeader->realFile = substr($this->fileHeader->realFile, 0, -1);
		// Create the symlink - only possible within PHP context. There's no support built in the FTP protocol, so no postproc use is possible here :(
		if( !AKFactory::get('kickstart.setup.dryrun','0') )
			@symlink($data, $this->fileHeader->realFile);

		$this->runState = AK_STATE_DATAREAD;

		return true; // No matter if the link was created!
	}

	/**
	 * Process the file data of a directory entry
	 * @return bool
	 */
	private function processTypeDir()
	{
		// Directory entries in the JPA do not have file data, therefore we're done processing the entry
		$this->runState = AK_STATE_DATAREAD;
		return true;
	}

	/**
	 * Creates the directory this file points to
	 */
	protected function createDirectory()
	{
		if( AKFactory::get('kickstart.setup.dryrun','0') ) return true;

		// Do we need to create a directory?
		if(empty($this->fileHeader->realFile)) $this->fileHeader->realFile = $this->fileHeader->file;
		$lastSlash = strrpos($this->fileHeader->realFile, '/');
		$dirName = substr( $this->fileHeader->realFile, 0, $lastSlash);
		$perms = $this->flagRestorePermissions ? $this->fileHeader->permissions : 0755;
		$ignore = AKFactory::get('kickstart.setup.ignoreerrors', false) || $this->isIgnoredDirectory($dirName);
		if( ($this->postProcEngine->createDirRecursive($dirName, $perms) == false) && (!$ignore) ) {
			$this->setError( AKText::sprintf('COULDNT_CREATE_DIR', $dirName) );
			return false;
		}
		else
		{
			return true;
		}
	}

	protected function heuristicFileHeaderLocator()
	{
		$ret = false;
		$fullEOF = false;

		while(!$ret && !$fullEOF) {
			$this->currentPartOffset = @ftell($this->fp);
			if($this->isEOF(true)) {
				$this->nextFile();
			}

			if($this->isEOF(false)) {
				$fullEOF = true;
				continue;
			}

			// Read 512Kb
			$chunk = fread($this->fp, 524288);
			$size_read = mb_strlen($chunk,'8bit');
			//$pos = strpos($chunk, 'JPF');
			$pos = mb_strpos($chunk, 'JPF', 0, '8bit');
			if($pos !== false) {
				// We found it!
				$this->currentPartOffset += $pos + 3;
				@fseek($this->fp, $this->currentPartOffset, SEEK_SET);
				$ret = true;
			} else {
				// Not yet found :(
				$this->currentPartOffset = @ftell($this->fp);
			}
		}

		return $ret;
	}
}

/**
 * Akeeba Restore
 * A JSON-powered JPA, JPS and ZIP archive extraction library
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / Akeeba Ltd.
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

/**
 * ZIP archive extraction class
 *
 * Since the file data portion of ZIP and JPA are similarly structured (it's empty for dirs,
 * linked node name for symlinks, dumped binary data for no compressions and dumped gzipped
 * binary data for gzip compression) we just have to subclass AKUnarchiverJPA and change the
 * header reading bits. Reusable code ;)
 */
class AKUnarchiverZIP extends AKUnarchiverJPA
{
	var $expectDataDescriptor = false;

	protected function readArchiveHeader()
	{
		debugMsg('Preparing to read archive header');
		// Initialize header data array
		$this->archiveHeaderData = new stdClass();

		// Open the first part
		debugMsg('Opening the first part');
		$this->nextFile();

		// Fail for unreadable files
		if( $this->fp === false ) {
			debugMsg('The first part is not readable');
			return false;
		}

		// Read a possible multipart signature
		$sigBinary = fread( $this->fp, 4 );
		$headerData = unpack('Vsig', $sigBinary);

		// Roll back if it's not a multipart archive
		if( $headerData['sig'] == 0x04034b50 ) {
			debugMsg('The archive is not multipart');
			fseek($this->fp, -4, SEEK_CUR);
		} else {
			debugMsg('The archive is multipart');
		}

		$multiPartSigs = array(
			0x08074b50,		// Multi-part ZIP
			0x30304b50,		// Multi-part ZIP (alternate)
			0x04034b50		// Single file
		);
		if( !in_array($headerData['sig'], $multiPartSigs) )
		{
			debugMsg('Invalid header signature '.dechex($headerData['sig']));
			$this->setError(AKText::_('ERR_CORRUPT_ARCHIVE'));
			return false;
		}

		$this->currentPartOffset = @ftell($this->fp);
		debugMsg('Current part offset after reading header: '.$this->currentPartOffset);

		$this->dataReadLength = 0;

		return true;
	}

	/**
	 * Concrete classes must use this method to read the file header
	 * @return bool True if reading the file was successful, false if an error occured or we reached end of archive
	 */
	protected function readFileHeader()
	{
		// If the current part is over, proceed to the next part please
		if( $this->isEOF(true) ) {
			debugMsg('Opening next archive part');
			$this->nextFile();
		}

		if($this->expectDataDescriptor)
		{
			// The last file had bit 3 of the general purpose bit flag set. This means that we have a
			// 12 byte data descriptor we need to skip. To make things worse, there might also be a 4
			// byte optional data descriptor header (0x08074b50).
			$junk = @fread($this->fp, 4);
			$junk = unpack('Vsig', $junk);
			if($junk['sig'] == 0x08074b50) {
				// Yes, there was a signature
				$junk = @fread($this->fp, 12);
				debugMsg('Data descriptor (w/ header) skipped at '.(ftell($this->fp)-12));
			} else {
				// No, there was no signature, just read another 8 bytes
				$junk = @fread($this->fp, 8);
				debugMsg('Data descriptor (w/out header) skipped at '.(ftell($this->fp)-8));
			}

			// And check for EOF, too
			if( $this->isEOF(true) ) {
				debugMsg('EOF before reading header');

				$this->nextFile();
			}
		}

		// Get and decode Local File Header
		$headerBinary = fread($this->fp, 30);
		$headerData = unpack('Vsig/C2ver/vbitflag/vcompmethod/vlastmodtime/vlastmoddate/Vcrc/Vcompsize/Vuncomp/vfnamelen/veflen', $headerBinary);

		// Check signature
		if(!( $headerData['sig'] == 0x04034b50 ))
		{
			debugMsg('Not a file signature at '.(ftell($this->fp)-4));

			// The signature is not the one used for files. Is this a central directory record (i.e. we're done)?
			if($headerData['sig'] == 0x02014b50)
			{
				debugMsg('EOCD signature at '.(ftell($this->fp)-4));
				// End of ZIP file detected. We'll just skip to the end of file...
				while( $this->nextFile() ) {};
				@fseek($this->fp, 0, SEEK_END); // Go to EOF
				return false;
			}
			else
			{
				debugMsg( 'Invalid signature ' . dechex($headerData['sig']) . ' at '.ftell($this->fp) );
				$this->setError(AKText::_('ERR_CORRUPT_ARCHIVE'));
				return false;
			}
		}

		// If bit 3 of the bitflag is set, expectDataDescriptor is true
		$this->expectDataDescriptor = ($headerData['bitflag'] & 4) == 4;

		$this->fileHeader = new stdClass();
		$this->fileHeader->timestamp = 0;

		// Read the last modified data and time
		$lastmodtime = $headerData['lastmodtime'];
		$lastmoddate = $headerData['lastmoddate'];

		if($lastmoddate && $lastmodtime)
		{
			// ----- Extract time
			$v_hour = ($lastmodtime & 0xF800) >> 11;
			$v_minute = ($lastmodtime & 0x07E0) >> 5;
			$v_seconde = ($lastmodtime & 0x001F)*2;

			// ----- Extract date
			$v_year = (($lastmoddate & 0xFE00) >> 9) + 1980;
			$v_month = ($lastmoddate & 0x01E0) >> 5;
			$v_day = $lastmoddate & 0x001F;

			// ----- Get UNIX date format
			$this->fileHeader->timestamp = @mktime($v_hour, $v_minute, $v_seconde, $v_month, $v_day, $v_year);
		}

		$isBannedFile = false;

		$this->fileHeader->compressed	= $headerData['compsize'];
		$this->fileHeader->uncompressed	= $headerData['uncomp'];
		$nameFieldLength				= $headerData['fnamelen'];
		$extraFieldLength				= $headerData['eflen'];

		// Read filename field
		$this->fileHeader->file			= fread( $this->fp, $nameFieldLength );

		// Handle file renaming
		$isRenamed = false;
		if(is_array($this->renameFiles) && (count($this->renameFiles) > 0) )
		{
			if(array_key_exists($this->fileHeader->file, $this->renameFiles))
			{
				$this->fileHeader->file = $this->renameFiles[$this->fileHeader->file];
				$isRenamed = true;
			}
		}

		// Handle directory renaming
		$isDirRenamed = false;
		if(is_array($this->renameDirs) && (count($this->renameDirs) > 0)) {
			if(array_key_exists(dirname($this->fileHeader->file), $this->renameDirs)) {
				$file = rtrim($this->renameDirs[dirname($this->fileHeader->file)],'/').'/'.basename($this->fileHeader->file);
				$isRenamed = true;
				$isDirRenamed = true;
			}
		}

		// Read extra field if present
		if($extraFieldLength > 0) {
			$extrafield = fread( $this->fp, $extraFieldLength );
		}

		debugMsg( '*'.ftell($this->fp).' IS START OF '.$this->fileHeader->file. ' ('.$this->fileHeader->compressed.' bytes)' );


		// Decide filetype -- Check for directories
		$this->fileHeader->type = 'file';
		if( strrpos($this->fileHeader->file, '/') == strlen($this->fileHeader->file) - 1 ) $this->fileHeader->type = 'dir';
		// Decide filetype -- Check for symbolic links
		if( ($headerData['ver1'] == 10) && ($headerData['ver2'] == 3) )$this->fileHeader->type = 'link';

		switch( $headerData['compmethod'] )
		{
			case 0:
				$this->fileHeader->compression = 'none';
				break;
			case 8:
				$this->fileHeader->compression = 'gzip';
				break;
		}

		// Find hard-coded banned files
		if( (basename($this->fileHeader->file) == ".") || (basename($this->fileHeader->file) == "..") )
		{
			$isBannedFile = true;
		}

		// Also try to find banned files passed in class configuration
		if((count($this->skipFiles) > 0) && (!$isRenamed))
		{
			if(in_array($this->fileHeader->file, $this->skipFiles))
			{
				$isBannedFile = true;
			}
		}

		// If we have a banned file, let's skip it
		if($isBannedFile)
		{
			// Advance the file pointer, skipping exactly the size of the compressed data
			$seekleft = $this->fileHeader->compressed;
			while($seekleft > 0)
			{
				// Ensure that we can seek past archive part boundaries
				$curSize = @filesize($this->archiveList[$this->currentPartNumber]);
				$curPos = @ftell($this->fp);
				$canSeek = $curSize - $curPos;
				if($canSeek > $seekleft) $canSeek = $seekleft;
				@fseek( $this->fp, $canSeek, SEEK_CUR );
				$seekleft -= $canSeek;
				if($seekleft) $this->nextFile();
			}

			$this->currentPartOffset = @ftell($this->fp);
			$this->runState = AK_STATE_DONE;
			return true;
		}

		// Last chance to prepend a path to the filename
		if(!empty($this->addPath) && !$isDirRenamed)
		{
			$this->fileHeader->file = $this->addPath.$this->fileHeader->file;
		}

		// Get the translated path name
		if($this->fileHeader->type == 'file')
		{
			$this->fileHeader->realFile = $this->postProcEngine->processFilename( $this->fileHeader->file );
		}
		elseif($this->fileHeader->type == 'dir')
		{
			$this->fileHeader->timestamp = 0;

			$dir = $this->fileHeader->file;

			$this->postProcEngine->createDirRecursive( $this->fileHeader->file, 0755 );
			$this->postProcEngine->processFilename(null);
		}
		else
		{
			// Symlink; do not post-process
			$this->fileHeader->timestamp = 0;
			$this->postProcEngine->processFilename(null);
		}

		$this->createDirectory();

		// Header is read
		$this->runState = AK_STATE_HEADER;

		return true;
	}

}

/**
 * Akeeba Restore
 * A JSON-powered JPA, JPS and ZIP archive extraction library
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / Akeeba Ltd.
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

/**
 * JPS archive extraction class
 */
class AKUnarchiverJPS extends AKUnarchiverJPA
{
	protected $archiveHeaderData = array();

	protected $password = '';

	public function __construct()
	{
		parent::__construct();

		$this->password = AKFactory::get('kickstart.jps.password','');
	}

	protected function readArchiveHeader()
	{
		// Initialize header data array
		$this->archiveHeaderData = new stdClass();

		// Open the first part
		$this->nextFile();

		// Fail for unreadable files
		if( $this->fp === false ) return false;

		// Read the signature
		$sig = fread( $this->fp, 3 );

		if ($sig != 'JPS')
		{
			// Not a JPA file
			$this->setError( AKText::_('ERR_NOT_A_JPS_FILE') );
			return false;
		}

		// Read and parse the known portion of header data (5 bytes)
		$bin_data = fread($this->fp, 5);
		$header_data = unpack('Cmajor/Cminor/cspanned/vextra', $bin_data);

		// Load any remaining header data (forward compatibility)
		$rest_length = $header_data['extra'];
		if( $rest_length > 0 )
			$junk = fread($this->fp, $rest_length);
		else
			$junk = '';

		// Temporary array with all the data we read
		$temp = array(
			'signature' => 			$sig,
			'major' => 				$header_data['major'],
			'minor' => 				$header_data['minor'],
			'spanned' => 			$header_data['spanned']
		);
		// Array-to-object conversion
		foreach($temp as $key => $value)
		{
			$this->archiveHeaderData->{$key} = $value;
		}

		$this->currentPartOffset = @ftell($this->fp);

		$this->dataReadLength = 0;

		return true;
	}

	/**
	 * Concrete classes must use this method to read the file header
	 * @return bool True if reading the file was successful, false if an error occured or we reached end of archive
	 */
	protected function readFileHeader()
	{
		// If the current part is over, proceed to the next part please
		if( $this->isEOF(true) ) {
			$this->nextFile();
		}

		// Get and decode Entity Description Block
		$signature = fread($this->fp, 3);

		// Check for end-of-archive siganture
		if($signature == 'JPE')
		{
			$this->setState('postrun');
			return true;
		}

		$this->fileHeader = new stdClass();
		$this->fileHeader->timestamp = 0;

		// Check signature
		if( $signature != 'JPF' )
		{
			if($this->isEOF(true))
			{
				// This file is finished; make sure it's the last one
				$this->nextFile();
				if(!$this->isEOF(false))
				{
					$this->setError(AKText::sprintf('INVALID_FILE_HEADER', $this->currentPartNumber, $this->currentPartOffset));
					return false;
				}
				// We're just finished
				return false;
			}
			else
			{
				fseek($this->fp, -6, SEEK_CUR);
				$signature = fread($this->fp, 3);
				if($signature == 'JPE')
				{
					return false;
				}

				$this->setError(AKText::sprintf('INVALID_FILE_HEADER', $this->currentPartNumber, $this->currentPartOffset));
				return false;
			}
		}
		// This a JPA Entity Block. Process the header.

		$isBannedFile = false;

		// Read and decrypt the header
		$edbhData = fread($this->fp, 4);
		$edbh = unpack('vencsize/vdecsize', $edbhData);
		$bin_data = fread($this->fp, $edbh['encsize']);

		// Decrypt and truncate
		$bin_data = AKEncryptionAES::AESDecryptCBC($bin_data, $this->password, 128);
		$bin_data = substr($bin_data,0,$edbh['decsize']);

		// Read length of EDB and of the Entity Path Data
		$length_array = unpack('vpathsize', substr($bin_data,0,2) );
		// Read the path data
		$file = substr($bin_data,2,$length_array['pathsize']);

		// Handle file renaming
		$isRenamed = false;
		if(is_array($this->renameFiles) && (count($this->renameFiles) > 0) )
		{
			if(array_key_exists($file, $this->renameFiles))
			{
				$file = $this->renameFiles[$file];
				$isRenamed = true;
			}
		}

		// Handle directory renaming
		$isDirRenamed = false;
		if(is_array($this->renameDirs) && (count($this->renameDirs) > 0)) {
			if(array_key_exists(dirname($file), $this->renameDirs)) {
				$file = rtrim($this->renameDirs[dirname($file)],'/').'/'.basename($file);
				$isRenamed = true;
				$isDirRenamed = true;
			}
		}

		// Read and parse the known data portion
		$bin_data = substr($bin_data, 2 + $length_array['pathsize']);
		$header_data = unpack('Ctype/Ccompression/Vuncompsize/Vperms/Vfilectime', $bin_data);

		$this->fileHeader->timestamp = $header_data['filectime'];
		$compressionType = $header_data['compression'];

		// Populate the return array
		$this->fileHeader->file = $file;
		$this->fileHeader->uncompressed = $header_data['uncompsize'];
		switch($header_data['type'])
		{
			case 0:
				$this->fileHeader->type = 'dir';
				break;

			case 1:
				$this->fileHeader->type = 'file';
				break;

			case 2:
				$this->fileHeader->type = 'link';
				break;
		}
		switch( $compressionType )
		{
			case 0:
				$this->fileHeader->compression = 'none';
				break;
			case 1:
				$this->fileHeader->compression = 'gzip';
				break;
			case 2:
				$this->fileHeader->compression = 'bzip2';
				break;
		}
		$this->fileHeader->permissions = $header_data['perms'];

		// Find hard-coded banned files
		if( (basename($this->fileHeader->file) == ".") || (basename($this->fileHeader->file) == "..") )
		{
			$isBannedFile = true;
		}

		// Also try to find banned files passed in class configuration
		if((count($this->skipFiles) > 0) && (!$isRenamed) )
		{
			if(in_array($this->fileHeader->file, $this->skipFiles))
			{
				$isBannedFile = true;
			}
		}

		// If we have a banned file, let's skip it
		if($isBannedFile)
		{
			$done = false;
			while(!$done)
			{
				// Read the Data Chunk Block header
				$binMiniHead = fread($this->fp, 8);
				if( in_array( substr($binMiniHead,0,3), array('JPF','JPE') ) )
				{
					// Not a Data Chunk Block header, I am done skipping the file
					@fseek($this->fp,-8,SEEK_CUR); // Roll back the file pointer
					$done = true; // Mark as done
					continue; // Exit loop
				}
				else
				{
					// Skip forward by the amount of compressed data
					$miniHead = unpack('Vencsize/Vdecsize', $binMiniHead);
					@fseek($this->fp, $miniHead['encsize'], SEEK_CUR);
				}
			}

			$this->currentPartOffset = @ftell($this->fp);
			$this->runState = AK_STATE_DONE;
			return true;
		}

		// Last chance to prepend a path to the filename
		if(!empty($this->addPath) && !$isDirRenamed)
		{
			$this->fileHeader->file = $this->addPath.$this->fileHeader->file;
		}

		// Get the translated path name
		$restorePerms = AKFactory::get('kickstart.setup.restoreperms', false);
		if($this->fileHeader->type == 'file')
		{
			// Regular file; ask the postproc engine to process its filename
			if($restorePerms)
			{
				$this->fileHeader->realFile = $this->postProcEngine->processFilename( $this->fileHeader->file, $this->fileHeader->permissions );
			}
			else
			{
				$this->fileHeader->realFile = $this->postProcEngine->processFilename( $this->fileHeader->file );
			}
		}
		elseif($this->fileHeader->type == 'dir')
		{
			$dir = $this->fileHeader->file;
			$this->fileHeader->realFile = $dir;

			// Directory; just create it
			if($restorePerms)
			{
				$this->postProcEngine->createDirRecursive( $this->fileHeader->file, $this->fileHeader->permissions );
			}
			else
			{
				$this->postProcEngine->createDirRecursive( $this->fileHeader->file, 0755 );
			}
			$this->postProcEngine->processFilename(null);
		}
		else
		{
			// Symlink; do not post-process
			$this->postProcEngine->processFilename(null);
		}

		$this->createDirectory();

		// Header is read
		$this->runState = AK_STATE_HEADER;

		$this->dataReadLength = 0;

		return true;
	}

	/**
	 * Concrete classes must use this method to process file data. It must set $runState to AK_STATE_DATAREAD when
	 * it's finished processing the file data.
	 * @return bool True if processing the file data was successful, false if an error occured
	 */
	protected function processFileData()
	{
		switch( $this->fileHeader->type )
		{
			case 'dir':
				return $this->processTypeDir();
				break;

			case 'link':
				return $this->processTypeLink();
				break;

			case 'file':
				switch($this->fileHeader->compression)
				{
					case 'none':
						return $this->processTypeFileUncompressed();
						break;

					case 'gzip':
					case 'bzip2':
						return $this->processTypeFileCompressedSimple();
						break;

				}
				break;
		}
	}

	private function processTypeFileUncompressed()
	{
		// Uncompressed files are being processed in small chunks, to avoid timeouts
		if( ($this->dataReadLength == 0) && !AKFactory::get('kickstart.setup.dryrun','0') )
		{
			// Before processing file data, ensure permissions are adequate
			$this->setCorrectPermissions( $this->fileHeader->file );
		}

		// Open the output file
		if( !AKFactory::get('kickstart.setup.dryrun','0') )
		{
			$ignore = AKFactory::get('kickstart.setup.ignoreerrors', false) || $this->isIgnoredDirectory($this->fileHeader->file);
			if ($this->dataReadLength == 0) {
				$outfp = @fopen( $this->fileHeader->realFile, 'wb' );
			} else {
				$outfp = @fopen( $this->fileHeader->realFile, 'ab' );
			}

			// Can we write to the file?
			if( ($outfp === false) && (!$ignore) ) {
				// An error occured
				$this->setError( AKText::sprintf('COULDNT_WRITE_FILE', $this->fileHeader->realFile) );
				return false;
			}
		}

		// Does the file have any data, at all?
		if( $this->fileHeader->uncompressed == 0 )
		{
			// No file data!
			if( !AKFactory::get('kickstart.setup.dryrun','0') && is_resource($outfp) ) @fclose($outfp);
			$this->runState = AK_STATE_DATAREAD;
			return true;
		}
		else
		{
			$this->setError('An uncompressed file was detected; this is not supported by this archive extraction utility');
			return false;
		}

		return true;
	}

	private function processTypeFileCompressedSimple()
	{
		$timer = AKFactory::getTimer();

		// Files are being processed in small chunks, to avoid timeouts
		if( ($this->dataReadLength == 0) && !AKFactory::get('kickstart.setup.dryrun','0') )
		{
			// Before processing file data, ensure permissions are adequate
			$this->setCorrectPermissions( $this->fileHeader->file );
		}

		// Open the output file
		if( !AKFactory::get('kickstart.setup.dryrun','0') )
		{
			// Open the output file
			$outfp = @fopen( $this->fileHeader->realFile, 'wb' );

			// Can we write to the file?
			$ignore = AKFactory::get('kickstart.setup.ignoreerrors', false) || $this->isIgnoredDirectory($this->fileHeader->file);
			if( ($outfp === false) && (!$ignore) ) {
				// An error occured
				$this->setError( AKText::sprintf('COULDNT_WRITE_FILE', $this->fileHeader->realFile) );
				return false;
			}
		}

		// Does the file have any data, at all?
		if( $this->fileHeader->uncompressed == 0 )
		{
			// No file data!
			if( !AKFactory::get('kickstart.setup.dryrun','0') )
				if(is_resource($outfp)) @fclose($outfp);
			$this->runState = AK_STATE_DATAREAD;
			return true;
		}

		$leftBytes = $this->fileHeader->uncompressed - $this->dataReadLength;

		// Loop while there's data to write and enough time to do it
		while( ($leftBytes > 0) && ($timer->getTimeLeft() > 0) )
		{
			// Read the mini header
			$binMiniHeader = fread($this->fp, 8);
			$reallyReadBytes = akstringlen($binMiniHeader);
			if($reallyReadBytes < 8)
			{
				// We read less than requested! Why? Did we hit local EOF?
				if( $this->isEOF(true) && !$this->isEOF(false) )
				{
					// Yeap. Let's go to the next file
					$this->nextFile();
					// Retry reading the header
					$binMiniHeader = fread($this->fp, 8);
					$reallyReadBytes = akstringlen($binMiniHeader);
					// Still not enough data? If so, the archive is corrupt or missing parts.
					if($reallyReadBytes < 8) {
						$this->setError( AKText::_('ERR_CORRUPT_ARCHIVE') );
						return false;
					}
				}
				else
				{
					// Nope. The archive is corrupt
					$this->setError( AKText::_('ERR_CORRUPT_ARCHIVE') );
					return false;
				}
			}

			// Read the encrypted data
			$miniHeader = unpack('Vencsize/Vdecsize', $binMiniHeader);
			$toReadBytes = $miniHeader['encsize'];
			$data = $this->fread( $this->fp, $toReadBytes );
			$reallyReadBytes = akstringlen($data);
			if($reallyReadBytes < $toReadBytes)
			{
				// We read less than requested! Why? Did we hit local EOF?
				if( $this->isEOF(true) && !$this->isEOF(false) )
				{
					// Yeap. Let's go to the next file
					$this->nextFile();
					// Read the rest of the data
					$toReadBytes -= $reallyReadBytes;
					$restData = $this->fread( $this->fp, $toReadBytes );
					$reallyReadBytes = akstringlen($restData);
					if($reallyReadBytes < $toReadBytes) {
						$this->setError( AKText::_('ERR_CORRUPT_ARCHIVE') );
						return false;
					}
					if(akstringlen($data) == 0) {
						$data = $restData;
					} else {
						$data .= $restData;
					}
				}
				else
				{
					// Nope. The archive is corrupt
					$this->setError( AKText::_('ERR_CORRUPT_ARCHIVE') );
					return false;
				}
			}

			// Decrypt the data
			$data = AKEncryptionAES::AESDecryptCBC($data, $this->password, 128);

			// Is the length of the decrypted data less than expected?
			$data_length = akstringlen($data);
			if($data_length < $miniHeader['decsize']) {
				$this->setError(AKText::_('ERR_INVALID_JPS_PASSWORD'));
				return false;
			}

			// Trim the data
			$data = substr($data,0,$miniHeader['decsize']);

			// Decompress
			$data = gzinflate($data);
			$unc_len = akstringlen($data);

			// Write the decrypted data
			if( !AKFactory::get('kickstart.setup.dryrun','0') )
				if(is_resource($outfp)) @fwrite( $outfp, $data, akstringlen($data) );

			// Update the read length
			$this->dataReadLength += $unc_len;
			$leftBytes = $this->fileHeader->uncompressed - $this->dataReadLength;
		}

		// Close the file pointer
		if( !AKFactory::get('kickstart.setup.dryrun','0') )
			if(is_resource($outfp)) @fclose($outfp);

		// Was this a pre-timeout bail out?
		if( $leftBytes > 0 )
		{
			$this->runState = AK_STATE_DATA;
		}
		else
		{
			// Oh! We just finished!
			$this->runState = AK_STATE_DATAREAD;
			$this->dataReadLength = 0;
		}

		return true;
	}

	/**
	 * Process the file data of a link entry
	 * @return bool
	 */
	private function processTypeLink()
	{

		// Does the file have any data, at all?
		if( $this->fileHeader->uncompressed == 0 )
		{
			// No file data!
			$this->runState = AK_STATE_DATAREAD;
			return true;
		}

		// Read the mini header
		$binMiniHeader = fread($this->fp, 8);
		$reallyReadBytes = akstringlen($binMiniHeader);
		if($reallyReadBytes < 8)
		{
			// We read less than requested! Why? Did we hit local EOF?
			if( $this->isEOF(true) && !$this->isEOF(false) )
			{
				// Yeap. Let's go to the next file
				$this->nextFile();
				// Retry reading the header
				$binMiniHeader = fread($this->fp, 8);
				$reallyReadBytes = akstringlen($binMiniHeader);
				// Still not enough data? If so, the archive is corrupt or missing parts.
				if($reallyReadBytes < 8) {
					$this->setError( AKText::_('ERR_CORRUPT_ARCHIVE') );
					return false;
				}
			}
			else
			{
				// Nope. The archive is corrupt
				$this->setError( AKText::_('ERR_CORRUPT_ARCHIVE') );
				return false;
			}
		}

		// Read the encrypted data
		$miniHeader = unpack('Vencsize/Vdecsize', $binMiniHeader);
		$toReadBytes = $miniHeader['encsize'];
		$data = $this->fread( $this->fp, $toReadBytes );
		$reallyReadBytes = akstringlen($data);
		if($reallyReadBytes < $toReadBytes)
		{
			// We read less than requested! Why? Did we hit local EOF?
			if( $this->isEOF(true) && !$this->isEOF(false) )
			{
				// Yeap. Let's go to the next file
				$this->nextFile();
				// Read the rest of the data
				$toReadBytes -= $reallyReadBytes;
				$restData = $this->fread( $this->fp, $toReadBytes );
				$reallyReadBytes = akstringlen($data);
				if($reallyReadBytes < $toReadBytes) {
					$this->setError( AKText::_('ERR_CORRUPT_ARCHIVE') );
					return false;
				}
				$data .= $restData;
			}
			else
			{
				// Nope. The archive is corrupt
				$this->setError( AKText::_('ERR_CORRUPT_ARCHIVE') );
				return false;
			}
		}

		// Decrypt the data
		$data = AKEncryptionAES::AESDecryptCBC($data, $this->password, 128);

		// Is the length of the decrypted data less than expected?
		$data_length = akstringlen($data);
		if($data_length < $miniHeader['decsize']) {
			$this->setError(AKText::_('ERR_INVALID_JPS_PASSWORD'));
			return false;
		}

		// Trim the data
		$data = substr($data,0,$miniHeader['decsize']);

		// Try to remove an existing file or directory by the same name
		if(file_exists($this->fileHeader->file)) { @unlink($this->fileHeader->file); @rmdir($this->fileHeader->file); }
		// Remove any trailing slash
		if(substr($this->fileHeader->file, -1) == '/') $this->fileHeader->file = substr($this->fileHeader->file, 0, -1);
		// Create the symlink - only possible within PHP context. There's no support built in the FTP protocol, so no postproc use is possible here :(

		if( !AKFactory::get('kickstart.setup.dryrun','0') )
		{
			@symlink($data, $this->fileHeader->file);
		}

		$this->runState = AK_STATE_DATAREAD;

		return true; // No matter if the link was created!
	}

	/**
	 * Process the file data of a directory entry
	 * @return bool
	 */
	private function processTypeDir()
	{
		// Directory entries in the JPA do not have file data, therefore we're done processing the entry
		$this->runState = AK_STATE_DATAREAD;
		return true;
	}

	/**
	 * Creates the directory this file points to
	 */
	protected function createDirectory()
	{
		if( AKFactory::get('kickstart.setup.dryrun','0') ) return true;

		// Do we need to create a directory?
		$lastSlash = strrpos($this->fileHeader->realFile, '/');
		$dirName = substr( $this->fileHeader->realFile, 0, $lastSlash);
		$perms = $this->flagRestorePermissions ? $retArray['permissions'] : 0755;
		$ignore = AKFactory::get('kickstart.setup.ignoreerrors', false) || $this->isIgnoredDirectory($dirName);
		if( ($this->postProcEngine->createDirRecursive($dirName, $perms) == false) && (!$ignore) ) {
			$this->setError( AKText::sprintf('COULDNT_CREATE_DIR', $dirName) );
			return false;
		}
		else
		{
			return true;
		}
	}
}

/**
 * Akeeba Restore
 * A JSON-powered JPA, JPS and ZIP archive extraction library
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / Akeeba Ltd.
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

/**
 * Timer class
 */
class AKCoreTimer extends AKAbstractObject
{
	/** @var int Maximum execution time allowance per step */
	private $max_exec_time = null;

	/** @var int Timestamp of execution start */
	private $start_time = null;

	/**
	 * Public constructor, creates the timer object and calculates the execution time limits
	 * @return AECoreTimer
	 */
	public function __construct()
	{
		parent::__construct();

		// Initialize start time
		$this->start_time = $this->microtime_float();

		// Get configured max time per step and bias
		$config_max_exec_time	= AKFactory::get('kickstart.tuning.max_exec_time', 14);
		$bias					= AKFactory::get('kickstart.tuning.run_time_bias', 75)/100;

		// Get PHP's maximum execution time (our upper limit)
		if(@function_exists('ini_get'))
		{
			$php_max_exec_time = @ini_get("maximum_execution_time");
			if ( (!is_numeric($php_max_exec_time)) || ($php_max_exec_time == 0) ) {
				// If we have no time limit, set a hard limit of about 10 seconds
				// (safe for Apache and IIS timeouts, verbose enough for users)
				$php_max_exec_time = 14;
			}
		}
		else
		{
			// If ini_get is not available, use a rough default
			$php_max_exec_time = 14;
		}

		// Apply an arbitrary correction to counter CMS load time
		$php_max_exec_time--;

		// Apply bias
		$php_max_exec_time = $php_max_exec_time * $bias;
		$config_max_exec_time = $config_max_exec_time * $bias;

		// Use the most appropriate time limit value
		if( $config_max_exec_time > $php_max_exec_time )
		{
			$this->max_exec_time = $php_max_exec_time;
		}
		else
		{
			$this->max_exec_time = $config_max_exec_time;
		}
	}

	/**
	 * Wake-up function to reset internal timer when we get unserialized
	 */
	public function __wakeup()
	{
		// Re-initialize start time on wake-up
		$this->start_time = $this->microtime_float();
	}

	/**
	 * Gets the number of seconds left, before we hit the "must break" threshold
	 * @return float
	 */
	public function getTimeLeft()
	{
		return $this->max_exec_time - $this->getRunningTime();
	}

	/**
	 * Gets the time elapsed since object creation/unserialization, effectively how
	 * long Akeeba Engine has been processing data
	 * @return float
	 */
	public function getRunningTime()
	{
		return $this->microtime_float() - $this->start_time;
	}

	/**
	 * Returns the current timestampt in decimal seconds
	 */
	private function microtime_float()
	{
		list($usec, $sec) = explode(" ", microtime());
		return ((float)$usec + (float)$sec);
	}

	/**
	 * Enforce the minimum execution time
	 */
	public function enforce_min_exec_time()
	{
		// Try to get a sane value for PHP's maximum_execution_time INI parameter
		if(@function_exists('ini_get'))
		{
			$php_max_exec = @ini_get("maximum_execution_time");
		}
		else
		{
			$php_max_exec = 10;
		}
		if ( ($php_max_exec == "") || ($php_max_exec == 0) ) {
			$php_max_exec = 10;
		}
		// Decrease $php_max_exec time by 500 msec we need (approx.) to tear down
		// the application, as well as another 500msec added for rounding
		// error purposes. Also make sure this is never gonna be less than 0.
		$php_max_exec = max($php_max_exec * 1000 - 1000, 0);

		// Get the "minimum execution time per step" Akeeba Backup configuration variable
		$minexectime = AKFactory::get('kickstart.tuning.min_exec_time',0);
		if(!is_numeric($minexectime)) $minexectime = 0;

		// Make sure we are not over PHP's time limit!
		if($minexectime > $php_max_exec) $minexectime = $php_max_exec;

		// Get current running time
		$elapsed_time = $this->getRunningTime() * 1000;

			// Only run a sleep delay if we haven't reached the minexectime execution time
		if( ($minexectime > $elapsed_time) && ($elapsed_time > 0) )
		{
			$sleep_msec = $minexectime - $elapsed_time;
			if(function_exists('usleep'))
			{
				usleep(1000 * $sleep_msec);
			}
			elseif(function_exists('time_nanosleep'))
			{
				$sleep_sec = floor($sleep_msec / 1000);
				$sleep_nsec = 1000000 * ($sleep_msec - ($sleep_sec * 1000));
				time_nanosleep($sleep_sec, $sleep_nsec);
			}
			elseif(function_exists('time_sleep_until'))
			{
				$until_timestamp = time() + $sleep_msec / 1000;
				time_sleep_until($until_timestamp);
			}
			elseif(function_exists('sleep'))
			{
				$sleep_sec = ceil($sleep_msec/1000);
				sleep( $sleep_sec );
			}
		}
		elseif( $elapsed_time > 0 )
		{
			// No sleep required, even if user configured us to be able to do so.
		}
	}

	/**
	 * Reset the timer. It should only be used in CLI mode!
	 */
	public function resetTime()
	{
		$this->start_time = $this->microtime_float();
	}
}

/**
 * Akeeba Restore
 * A JSON-powered JPA, JPS and ZIP archive extraction library
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / Akeeba Ltd.
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

/**
 * A filesystem scanner which uses opendir()
 */
class AKUtilsLister extends AKAbstractObject
{
	public function &getFiles($folder, $pattern = '*')
	{
		// Initialize variables
		$arr = array();
		$false = false;

		if(!is_dir($folder)) return $false;

		$handle = @opendir($folder);
		// If directory is not accessible, just return FALSE
		if ($handle === FALSE) {
			$this->setWarning( 'Unreadable directory '.$folder);
			return $false;
		}

		while (($file = @readdir($handle)) !== false)
		{
			if( !fnmatch($pattern, $file) ) continue;

			if (($file != '.') && ($file != '..'))
			{
				$ds = ($folder == '') || ($folder == '/') || (@substr($folder, -1) == '/') || (@substr($folder, -1) == DIRECTORY_SEPARATOR) ? '' : DIRECTORY_SEPARATOR;
				$dir = $folder . $ds . $file;
				$isDir = is_dir($dir);
				if (!$isDir) {
					$arr[] = $dir;
				}
			}
		}
		@closedir($handle);

		return $arr;
	}

	public function &getFolders($folder, $pattern = '*')
	{
		// Initialize variables
		$arr = array();
		$false = false;

		if(!is_dir($folder)) return $false;

		$handle = @opendir($folder);
		// If directory is not accessible, just return FALSE
		if ($handle === FALSE) {
			$this->setWarning( 'Unreadable directory '.$folder);
			return $false;
		}

		while (($file = @readdir($handle)) !== false)
		{
			if( !fnmatch($pattern, $file) ) continue;

			if (($file != '.') && ($file != '..'))
			{
				$ds = ($folder == '') || ($folder == '/') || (@substr($folder, -1) == '/') || (@substr($folder, -1) == DIRECTORY_SEPARATOR) ? '' : DIRECTORY_SEPARATOR;
				$dir = $folder . $ds . $file;
				$isDir = is_dir($dir);
				if ($isDir) {
					$arr[] = $dir;
				}
			}
		}
		@closedir($handle);

		return $arr;
	}
}

/**
 * Akeeba Restore
 * A JSON-powered JPA, JPS and ZIP archive extraction library
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / Akeeba Ltd.
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

/**
 * A simple INI-based i18n engine
 */

class AKText extends AKAbstractObject
{
	/**
	 * The default (en_GB) translation used when no other translation is available
	 * @var array
	 */
	private $default_translation = array(
		'AUTOMODEON' => 'Auto-mode enabled',
		'ERR_NOT_A_JPA_FILE' => 'The file is not a JPA archive',
		'ERR_CORRUPT_ARCHIVE' => 'The archive file is corrupt, truncated or archive parts are missing',
		'ERR_INVALID_LOGIN' => 'Invalid login',
		'COULDNT_CREATE_DIR' => 'Could not create %s folder',
		'COULDNT_WRITE_FILE' => 'Could not open %s for writing.',
		'WRONG_FTP_HOST' => 'Wrong FTP host or port',
		'WRONG_FTP_USER' => 'Wrong FTP username or password',
		'WRONG_FTP_PATH1' => 'Wrong FTP initial directory - the directory doesn\'t exist',
		'FTP_CANT_CREATE_DIR' => 'Could not create directory %s',
		'FTP_TEMPDIR_NOT_WRITABLE' => 'Could not find or create a writable temporary directory',
		'SFTP_TEMPDIR_NOT_WRITABLE' => 'Could not find or create a writable temporary directory',
		'FTP_COULDNT_UPLOAD' => 'Could not upload %s',
		'THINGS_HEADER' => 'Things you should know about Akeeba Kickstart',
		'THINGS_01' => 'Kickstart is not an installer. It is an archive extraction tool. The actual installer was put inside the archive file at backup time.',
		'THINGS_02' => 'Kickstart is not the only way to extract the backup archive. You can use Akeeba eXtract Wizard and upload the extracted files using FTP instead.',
		'THINGS_03' => 'Kickstart is bound by your server\'s configuration. As such, it may not work at all.',
		'THINGS_04' => 'You should download and upload your archive files using FTP in Binary transfer mode. Any other method could lead to a corrupt backup archive and restoration failure.',
		'THINGS_05' => 'Post-restoration site load errors are usually caused by .htaccess or php.ini directives. You should understand that blank pages, 404 and 500 errors can usually be worked around by editing the aforementioned files. It is not our job to mess with your configuration files, because this could be dangerous for your site.',
		'THINGS_06' => 'Kickstart overwrites files without a warning. If you are not sure that you are OK with that do not continue.',
		'THINGS_07' => 'Trying to restore to the temporary URL of a cPanel host (e.g. http://1.2.3.4/~username) will lead to restoration failure and your site will appear to be not working. This is normal and it\'s just how your server and CMS software work.',
		'THINGS_08' => 'You are supposed to read the documentation before using this software. Most issues can be avoided, or easily worked around, by understanding how this software works.',
		'THINGS_09' => 'This text does not imply that there is a problem detected. It is standard text displayed every time you launch Kickstart.',
		'CLOSE_LIGHTBOX' => 'Click here or press ESC to close this message',
		'SELECT_ARCHIVE' => 'Select a backup archive',
		'ARCHIVE_FILE' => 'Archive file:',
		'SELECT_EXTRACTION' => 'Select an extraction method',
		'WRITE_TO_FILES' => 'Write to files:',
		'WRITE_HYBRID' => 'Hybrid (use FTP only if needed)',
		'WRITE_DIRECTLY' => 'Directly',
		'WRITE_FTP' => 'Use FTP for all files',
        'WRITE_SFTP' => 'Use SFTP for all files',
		'FTP_HOST' => '(S)FTP host name:',
		'FTP_PORT' => '(S)FTP port:',
		'FTP_FTPS' => 'Use FTP over SSL (FTPS)',
		'FTP_PASSIVE' => 'Use FTP Passive Mode',
		'FTP_USER' => '(S)FTP user name:',
		'FTP_PASS' => '(S)FTP password:',
		'FTP_DIR' => '(S)FTP directory:',
		'FTP_TEMPDIR' => 'Temporary directory:',
		'FTP_CONNECTION_OK' => 'FTP Connection Established',
		'SFTP_CONNECTION_OK' => 'SFTP Connection Established',
		'FTP_CONNECTION_FAILURE' => 'The FTP Connection Failed',
		'SFTP_CONNECTION_FAILURE' => 'The SFTP Connection Failed',
		'FTP_TEMPDIR_WRITABLE' => 'The temporary directory is writable.',
		'FTP_TEMPDIR_UNWRITABLE' => 'The temporary directory is not writable. Please check the permissions.',
        'FTPBROWSER_ERROR_HOSTNAME' => "Invalid FTP host or port",
        'FTPBROWSER_ERROR_USERPASS' => "Invalid FTP username or password",
        'FTPBROWSER_ERROR_NOACCESS' => "Directory doesn't exist or you don't have enough permissions to access it",
        'FTPBROWSER_ERROR_UNSUPPORTED' => "Sorry, your FTP server doesn't support our FTP directory browser.",
        'FTPBROWSER_LBL_GOPARENT' => "&lt;up one level&gt;",
        'FTPBROWSER_LBL_INSTRUCTIONS' => 'Click on a directory to navigate into it. Click on OK to select that directory, Cancel to abort the procedure.',
        'FTPBROWSER_LBL_ERROR' => 'An error occurred',
        'SFTP_NO_SSH2' => 'Your web server does not have the SSH2 PHP module, therefore can not connect to SFTP servers.',
        'SFTP_NO_FTP_SUPPORT' => 'Your SSH server does not allow SFTP connections',
        'SFTP_WRONG_USER' => 'Wrong SFTP username or password',
        'SFTP_WRONG_STARTING_DIR' => 'You must supply a valid absolute path',
        'SFTPBROWSER_ERROR_NOACCESS' => "Directory doesn't exist or you don't have enough permissions to access it",
        'SFTP_COULDNT_UPLOAD' => 'Could not upload %s',
        'SFTP_CANT_CREATE_DIR' => 'Could not create directory %s',
        'UI-ROOT' => '&lt;root&gt;',
        'CONFIG_UI_FTPBROWSER_TITLE' => 'FTP Directory Browser',
        'FTP_BROWSE' => 'Browse',
		'BTN_CHECK' => 'Check',
		'BTN_RESET' => 'Reset',
		'BTN_TESTFTPCON' => 'Test FTP connection',
		'BTN_TESTSFTPCON' => 'Test SFTP connection',
		'BTN_GOTOSTART' => 'Start over',
		'FINE_TUNE' => 'Fine tune',
		'MIN_EXEC_TIME' => 'Minimum execution time:',
		'MAX_EXEC_TIME' => 'Maximum execution time:',
		'SECONDS_PER_STEP' => 'seconds per step',
		'EXTRACT_FILES' => 'Extract files',
		'BTN_START' => 'Start',
		'EXTRACTING' => 'Extracting',
		'DO_NOT_CLOSE_EXTRACT' => 'Do not close this window while the extraction is in progress',
		'RESTACLEANUP' => 'Restoration and Clean Up',
		'BTN_RUNINSTALLER' => 'Run the Installer',
		'BTN_CLEANUP' => 'Clean Up',
		'BTN_SITEFE' => 'Visit your site\'s front-end',
		'BTN_SITEBE' => 'Visit your site\'s back-end',
		'WARNINGS' => 'Extraction Warnings',
		'ERROR_OCCURED' => 'An error occured',
		'STEALTH_MODE' => 'Stealth mode',
		'STEALTH_URL' => 'HTML file to show to web visitors',
		'ERR_NOT_A_JPS_FILE' => 'The file is not a JPA archive',
		'ERR_INVALID_JPS_PASSWORD' => 'The password you gave is wrong or the archive is corrupt',
		'JPS_PASSWORD' => 'Archive Password (for JPS files)',
		'INVALID_FILE_HEADER' => 'Invalid header in archive file, part %s, offset %s',
		'NEEDSOMEHELPKS' => 'Want some help to use this tool? Read this first:',
		'QUICKSTART' => 'Quick Start Guide',
		'CANTGETITTOWORK' => 'Can\'t get it to work? Click me!',
		'NOARCHIVESCLICKHERE' => 'No archives detected. Click here for troubleshooting instructions.',
		'POSTRESTORATIONTROUBLESHOOTING' => 'Something not working after the restoration? Click here for troubleshooting instructions.',
		'UPDATE_HEADER' => 'An updated version of Akeeba Kickstart (<span id="update-version">unknown</span>) is available!',
		'UPDATE_NOTICE' => 'You are advised to always use the latest version of Akeeba Kickstart available. Older versions may be subject to bugs and will not be supported.',
		'UPDATE_DLNOW' => 'Download now',
		'UPDATE_MOREINFO' => 'More information',
		'IGNORE_MOST_ERRORS' => 'Ignore most errors',
		'WRONG_FTP_PATH2' => 'Wrong FTP initial directory - the directory doesn\'t correspond to your site\'s web root',
		'ARCHIVE_DIRECTORY' => 'Archive directory:',
		'RELOAD_ARCHIVES'	=> 'Reload',
		'CONFIG_UI_SFTPBROWSER_TITLE'	=> 'SFTP Directory Browser',
	);

	/**
	 * The array holding the translation keys
	 * @var array
	 */
	private $strings;

	/**
	 * The currently detected language (ISO code)
	 * @var string
	 */
	private $language;

	/*
	 * Initializes the translation engine
	 * @return AKText
	 */
	public function __construct()
	{
		// Start with the default translation
		$this->strings = $this->default_translation;
		// Try loading the translation file in English, if it exists
		$this->loadTranslation('en-GB');
		// Try loading the translation file in the browser's preferred language, if it exists
		$this->getBrowserLanguage();
		if(!is_null($this->language))
		{
			$this->loadTranslation();
		}
	}

	/**
	 * Singleton pattern for Language
	 * @return AKText The global AKText instance
	 */
	public static function &getInstance()
	{
		static $instance;

		if(!is_object($instance))
		{
			$instance = new AKText();
		}

		return $instance;
	}

	public static function _($string)
	{
		$text = self::getInstance();

		$key = strtoupper($string);
		$key = substr($key, 0, 1) == '_' ? substr($key, 1) : $key;

		if (isset ($text->strings[$key]))
		{
			$string = $text->strings[$key];
		}
		else
		{
			if (defined($string))
			{
				$string = constant($string);
			}
		}

		return $string;
	}

	public static function sprintf($key)
	{
		$text = self::getInstance();
		$args = func_get_args();
		if (count($args) > 0) {
			$args[0] = $text->_($args[0]);
			return @call_user_func_array('sprintf', $args);
		}
		return '';
	}

	public function dumpLanguage()
	{
		$out = '';
		foreach($this->strings as $key => $value)
		{
			$out .= "$key=$value\n";
		}
		return $out;
	}

	public function asJavascript()
	{
		$out = '';
		foreach($this->strings as $key => $value)
		{
			$key = addcslashes($key, '\\\'"');
			$value = addcslashes($value, '\\\'"');
			if(!empty($out)) $out .= ",\n";
			$out .= "'$key':\t'$value'";
		}
		return $out;
	}

	public function resetTranslation()
	{
		$this->strings = $this->default_translation;
	}

	public function getBrowserLanguage()
	{
		// Detection code from Full Operating system language detection, by Harald Hope
		// Retrieved from http://techpatterns.com/downloads/php_language_detection.php
		$user_languages = array();
		//check to see if language is set
		if ( isset( $_SERVER["HTTP_ACCEPT_LANGUAGE"] ) )
		{
			$languages = strtolower( $_SERVER["HTTP_ACCEPT_LANGUAGE"] );
			// $languages = ' fr-ch;q=0.3, da, en-us;q=0.8, en;q=0.5, fr;q=0.3';
			// need to remove spaces from strings to avoid error
			$languages = str_replace( ' ', '', $languages );
			$languages = explode( ",", $languages );

			foreach ( $languages as $language_list )
			{
				// pull out the language, place languages into array of full and primary
				// string structure:
				$temp_array = array();
				// slice out the part before ; on first step, the part before - on second, place into array
				$temp_array[0] = substr( $language_list, 0, strcspn( $language_list, ';' ) );//full language
				$temp_array[1] = substr( $language_list, 0, 2 );// cut out primary language
				if( (strlen($temp_array[0]) == 5) && ( (substr($temp_array[0],2,1) == '-') || (substr($temp_array[0],2,1) == '_') ) )
				{
					$langLocation = strtoupper(substr($temp_array[0],3,2));
					$temp_array[0] = $temp_array[1].'-'.$langLocation;
				}
				//place this array into main $user_languages language array
				$user_languages[] = $temp_array;
			}
		}
		else// if no languages found
		{
			$user_languages[0] = array( '','' ); //return blank array.
		}

		$this->language = null;
		$basename=basename(__FILE__, '.php') . '.ini';

		// Try to match main language part of the filename, irrespective of the location, e.g. de_DE will do if de_CH doesn't exist.
		if (class_exists('AKUtilsLister'))
		{
			$fs = new AKUtilsLister();
			$iniFiles = $fs->getFiles(KSROOTDIR, '*.'.$basename );
			if(empty($iniFiles) && ($basename != 'kickstart.ini')) {
				$basename = 'kickstart.ini';
				$iniFiles = $fs->getFiles(KSROOTDIR, '*.'.$basename );
			}
		}
		else
		{
			$iniFiles = null;
		}

		if (is_array($iniFiles)) {
			foreach($user_languages as $languageStruct)
			{
				if(is_null($this->language))
				{
					// Get files matching the main lang part
					$iniFiles = $fs->getFiles(KSROOTDIR, $languageStruct[1].'-??.'.$basename );
					if (count($iniFiles) > 0) {
						$filename = $iniFiles[0];
						$filename = substr($filename, strlen(KSROOTDIR)+1);
						$this->language = substr($filename, 0, 5);
					} else {
						$this->language = null;
					}
				}
			}
		}

		if(is_null($this->language)) {
			// Try to find a full language match
			foreach($user_languages as $languageStruct)
			{
				if (@file_exists($languageStruct[0].'.'.$basename) && is_null($this->language)) {
					$this->language = $languageStruct[0];
				} else {

				}
			}
		} else {
			// Do we have an exact match?
			foreach($user_languages as $languageStruct)
			{
				if(substr($this->language,0,strlen($languageStruct[1])) == $languageStruct[1]) {
					if(file_exists($languageStruct[0].'.'.$basename)) {
						$this->language = $languageStruct[0];
					}
				}
			}
		}

		// Now, scan for full language based on the partial match

	}

	private function loadTranslation( $lang = null )
	{
		if (defined('KSLANGDIR'))
		{
			$dirname = KSLANGDIR;
		}
		else
		{
			$dirname = KSROOTDIR;
		}
		$basename = basename(__FILE__, '.php') . '.ini';
		if( empty($lang) ) $lang = $this->language;

		$translationFilename = $dirname.DIRECTORY_SEPARATOR.$lang.'.'.$basename;
		if(!@file_exists($translationFilename) && ($basename != 'kickstart.ini')) {
			$basename = 'kickstart.ini';
			$translationFilename = $dirname.DIRECTORY_SEPARATOR.$lang.'.'.$basename;
		}
		if(!@file_exists($translationFilename)) return;
		$temp = self::parse_ini_file($translationFilename, false);

		if(!is_array($this->strings)) $this->strings = array();
		if(empty($temp)) {
			$this->strings = array_merge($this->default_translation, $this->strings);
		} else {
			$this->strings = array_merge($this->strings, $temp);
		}
	}

	public function addDefaultLanguageStrings($stringList = array())
	{
		if(!is_array($stringList)) return;
		if(empty($stringList)) return;

		$this->strings = array_merge($stringList, $this->strings);
	}

	/**
	 * A PHP based INI file parser.
	 *
	 * Thanks to asohn ~at~ aircanopy ~dot~ net for posting this handy function on
	 * the parse_ini_file page on http://gr.php.net/parse_ini_file
	 *
	 * @param string $file Filename to process
	 * @param bool $process_sections True to also process INI sections
	 * @return array An associative array of sections, keys and values
	 * @access private
	 */
	public static function parse_ini_file($file, $process_sections = false, $raw_data = false)
	{
		$process_sections = ($process_sections !== true) ? false : true;

		if(!$raw_data)
		{
			$ini = @file($file);
		}
		else
		{
			$ini = $file;
		}
		if (count($ini) == 0) {return array();}

		$sections = array();
		$values = array();
		$result = array();
		$globals = array();
		$i = 0;
		if(!empty($ini)) foreach ($ini as $line) {
			$line = trim($line);
			$line = str_replace("\t", " ", $line);

			// Comments
			if (!preg_match('/^[a-zA-Z0-9[]/', $line)) {continue;}

			// Sections
			if ($line{0} == '[') {
				$tmp = explode(']', $line);
				$sections[] = trim(substr($tmp[0], 1));
				$i++;
				continue;
			}

			// Key-value pair
			list($key, $value) = explode('=', $line, 2);
			$key = trim($key);
			$value = trim($value);
			if (strstr($value, ";")) {
				$tmp = explode(';', $value);
				if (count($tmp) == 2) {
					if ((($value{0} != '"') && ($value{0} != "'")) ||
					preg_match('/^".*"\s*;/', $value) || preg_match('/^".*;[^"]*$/', $value) ||
					preg_match("/^'.*'\s*;/", $value) || preg_match("/^'.*;[^']*$/", $value) ){
						$value = $tmp[0];
					}
				} else {
					if ($value{0} == '"') {
						$value = preg_replace('/^"(.*)".*/', '$1', $value);
					} elseif ($value{0} == "'") {
						$value = preg_replace("/^'(.*)'.*/", '$1', $value);
					} else {
						$value = $tmp[0];
					}
				}
			}
			$value = trim($value);
			$value = trim($value, "'\"");

			if ($i == 0) {
				if (substr($line, -1, 2) == '[]') {
					$globals[$key][] = $value;
				} else {
					$globals[$key] = $value;
				}
			} else {
				if (substr($line, -1, 2) == '[]') {
					$values[$i-1][$key][] = $value;
				} else {
					$values[$i-1][$key] = $value;
				}
			}
		}

		for($j = 0; $j < $i; $j++) {
			if ($process_sections === true) {
				$result[$sections[$j]] = $values[$j];
			} else {
				$result[] = $values[$j];
			}
		}

		return $result + $globals;
	}
}

/**
 * Akeeba Restore
 * A JSON-powered JPA, JPS and ZIP archive extraction library
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / Akeeba Ltd.
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

/**
 * The Akeeba Kickstart Factory class
 * This class is reponssible for instanciating all Akeeba Kicsktart classes
 */
class AKFactory {
	/** @var array A list of instanciated objects */
	private $objectlist = array();

	/** @var array Simple hash data storage */
	private $varlist = array();

	/** Private constructor makes sure we can't directly instanciate the class */
	private function __construct() {}

	/**
	 * Gets a single, internally used instance of the Factory
	 * @param string $serialized_data [optional] Serialized data to spawn the instance from
	 * @return AKFactory A reference to the unique Factory object instance
	 */
	protected static function &getInstance( $serialized_data = null ) {
		static $myInstance;
		if(!is_object($myInstance) || !is_null($serialized_data))
			if(!is_null($serialized_data))
			{
				$myInstance = unserialize($serialized_data);
			}
			else
			{
				$myInstance = new self();
			}
		return $myInstance;
	}

	/**
	 * Internal function which instanciates a class named $class_name.
	 * The autoloader
	 * @param object $class_name
	 * @return
	 */
	protected static function &getClassInstance($class_name) {
		$self = self::getInstance();
		if(!isset($self->objectlist[$class_name]))
		{
			$self->objectlist[$class_name] = new $class_name;
		}
		return $self->objectlist[$class_name];
	}

	// ========================================================================
	// Public factory interface
	// ========================================================================

	/**
	 * Gets a serialized snapshot of the Factory for safekeeping (hibernate)
	 * @return string The serialized snapshot of the Factory
	 */
	public static function serialize() {
		$engine = self::getUnarchiver();
		$engine->shutdown();
		$serialized = serialize(self::getInstance());

		if(function_exists('base64_encode') && function_exists('base64_decode'))
		{
			$serialized = base64_encode($serialized);
		}
		return $serialized;
	}

	/**
	 * Regenerates the full Factory state from a serialized snapshot (resume)
	 * @param string $serialized_data The serialized snapshot to resume from
	 */
	public static function unserialize($serialized_data) {
		if(function_exists('base64_encode') && function_exists('base64_decode'))
		{
			$serialized_data = base64_decode($serialized_data);
		}
		self::getInstance($serialized_data);
	}

	/**
	 * Reset the internal factory state, freeing all previously created objects
	 */
	public static function nuke()
	{
		$self = self::getInstance();
		foreach($self->objectlist as $key => $object)
		{
			$self->objectlist[$key] = null;
		}
		$self->objectlist = array();
	}

	// ========================================================================
	// Public hash data storage interface
	// ========================================================================

	public static function set($key, $value)
	{
		$self = self::getInstance();
		$self->varlist[$key] = $value;
	}

	public static function get($key, $default = null)
	{
		$self = self::getInstance();
		if( array_key_exists($key, $self->varlist) )
		{
			return $self->varlist[$key];
		}
		else
		{
			return $default;
		}
	}

	// ========================================================================
	// Akeeba Kickstart classes
	// ========================================================================

	/**
	 * Gets the post processing engine
	 * @param string $proc_engine
	 */
	public static function &getPostProc($proc_engine = null)
	{
		static $class_name;
		if( empty($class_name) )
		{
			if(empty($proc_engine))
			{
				$proc_engine = self::get('kickstart.procengine','direct');
			}
			$class_name = 'AKPostproc'.ucfirst($proc_engine);
		}
		return self::getClassInstance($class_name);
	}

	/**
	 * Gets the unarchiver engine
	 */
	public static function &getUnarchiver( $configOverride = null )
	{
		static $class_name;

		if(!empty($configOverride))
		{
			if($configOverride['reset']) {
				$class_name = null;
			}
		}

		if( empty($class_name) )
		{
			$filetype = self::get('kickstart.setup.filetype', null);

			if(empty($filetype))
			{
				$filename = self::get('kickstart.setup.sourcefile', null);
				$basename = basename($filename);
				$baseextension = strtoupper(substr($basename,-3));
				switch($baseextension)
				{
					case 'JPA':
						$filetype = 'JPA';
						break;

					case 'JPS':
						$filetype = 'JPS';
						break;

					case 'ZIP':
						$filetype = 'ZIP';
						break;

					default:
						die('Invalid archive type or extension in file '.$filename);
						break;
				}
			}

			$class_name = 'AKUnarchiver'.ucfirst($filetype);
		}

		$destdir = self::get('kickstart.setup.destdir', null);
		if(empty($destdir))
		{
			$destdir = KSROOTDIR;
		}

		$object = self::getClassInstance($class_name);
		if( $object->getState() == 'init')
		{
			$sourcePath = self::get('kickstart.setup.sourcepath', '');
			$sourceFile = self::get('kickstart.setup.sourcefile', '');

			if (!empty($sourcePath))
			{
				$sourceFile = rtrim($sourcePath, '/\\') . '/' . $sourceFile;
			}

			// Initialize the object
			$config = array(
				'filename'				=> $sourceFile,
				'restore_permissions'	=> self::get('kickstart.setup.restoreperms', 0),
				'post_proc'				=> self::get('kickstart.procengine', 'direct'),
				'add_path'				=> self::get('kickstart.setup.targetpath', $destdir),
				'rename_files'			=> array('.htaccess' => 'htaccess.bak', 'php.ini' => 'php.ini.bak', 'web.config' => 'web.config.bak'),
				'skip_files'			=> array(basename(__FILE__), 'kickstart.php', 'abiautomation.ini', 'htaccess.bak', 'php.ini.bak', 'cacert.pem'),
				'ignoredirectories'		=> array('tmp', 'log', 'logs'),
			);

			if(!defined('KICKSTART'))
			{
				// In restore.php mode we have to exclude some more files
				$config['skip_files'][] = 'administrator/components/com_akeeba/restore.php';
				$config['skip_files'][] = 'administrator/components/com_akeeba/restoration.php';
			}

			if(!empty($configOverride))
			{
				foreach($configOverride as $key => $value)
				{
					$config[$key] = $value;
				}
			}

			$object->setup($config);
		}

		return $object;
	}

	/**
	 * Get the a reference to the Akeeba Engine's timer
	 * @return AKCoreTimer
	 */
	public static function &getTimer()
	{
		return self::getClassInstance('AKCoreTimer');
	}

}

/**
 * Akeeba Restore
 * A JSON-powered JPA, JPS and ZIP archive extraction library
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / Akeeba Ltd.
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

/**
 * AES implementation in PHP (c) Chris Veness 2005-2013.
 * Right to use and adapt is granted for under a simple creative commons attribution
 * licence. No warranty of any form is offered.
 *
 * Modified for Akeeba Backup by Nicholas K. Dionysopoulos
 */
class AKEncryptionAES
{
	// Sbox is pre-computed multiplicative inverse in GF(2^8) used in SubBytes and KeyExpansion [�5.1.1]
	protected static $Sbox =
			 array(0x63,0x7c,0x77,0x7b,0xf2,0x6b,0x6f,0xc5,0x30,0x01,0x67,0x2b,0xfe,0xd7,0xab,0x76,
	               0xca,0x82,0xc9,0x7d,0xfa,0x59,0x47,0xf0,0xad,0xd4,0xa2,0xaf,0x9c,0xa4,0x72,0xc0,
	               0xb7,0xfd,0x93,0x26,0x36,0x3f,0xf7,0xcc,0x34,0xa5,0xe5,0xf1,0x71,0xd8,0x31,0x15,
	               0x04,0xc7,0x23,0xc3,0x18,0x96,0x05,0x9a,0x07,0x12,0x80,0xe2,0xeb,0x27,0xb2,0x75,
	               0x09,0x83,0x2c,0x1a,0x1b,0x6e,0x5a,0xa0,0x52,0x3b,0xd6,0xb3,0x29,0xe3,0x2f,0x84,
	               0x53,0xd1,0x00,0xed,0x20,0xfc,0xb1,0x5b,0x6a,0xcb,0xbe,0x39,0x4a,0x4c,0x58,0xcf,
	               0xd0,0xef,0xaa,0xfb,0x43,0x4d,0x33,0x85,0x45,0xf9,0x02,0x7f,0x50,0x3c,0x9f,0xa8,
	               0x51,0xa3,0x40,0x8f,0x92,0x9d,0x38,0xf5,0xbc,0xb6,0xda,0x21,0x10,0xff,0xf3,0xd2,
	               0xcd,0x0c,0x13,0xec,0x5f,0x97,0x44,0x17,0xc4,0xa7,0x7e,0x3d,0x64,0x5d,0x19,0x73,
	               0x60,0x81,0x4f,0xdc,0x22,0x2a,0x90,0x88,0x46,0xee,0xb8,0x14,0xde,0x5e,0x0b,0xdb,
	               0xe0,0x32,0x3a,0x0a,0x49,0x06,0x24,0x5c,0xc2,0xd3,0xac,0x62,0x91,0x95,0xe4,0x79,
	               0xe7,0xc8,0x37,0x6d,0x8d,0xd5,0x4e,0xa9,0x6c,0x56,0xf4,0xea,0x65,0x7a,0xae,0x08,
	               0xba,0x78,0x25,0x2e,0x1c,0xa6,0xb4,0xc6,0xe8,0xdd,0x74,0x1f,0x4b,0xbd,0x8b,0x8a,
	               0x70,0x3e,0xb5,0x66,0x48,0x03,0xf6,0x0e,0x61,0x35,0x57,0xb9,0x86,0xc1,0x1d,0x9e,
	               0xe1,0xf8,0x98,0x11,0x69,0xd9,0x8e,0x94,0x9b,0x1e,0x87,0xe9,0xce,0x55,0x28,0xdf,
	               0x8c,0xa1,0x89,0x0d,0xbf,0xe6,0x42,0x68,0x41,0x99,0x2d,0x0f,0xb0,0x54,0xbb,0x16);

	// Rcon is Round Constant used for the Key Expansion [1st col is 2^(r-1) in GF(2^8)] [�5.2]
	protected static $Rcon = array(
				   array(0x00, 0x00, 0x00, 0x00),
	               array(0x01, 0x00, 0x00, 0x00),
	               array(0x02, 0x00, 0x00, 0x00),
	               array(0x04, 0x00, 0x00, 0x00),
	               array(0x08, 0x00, 0x00, 0x00),
	               array(0x10, 0x00, 0x00, 0x00),
	               array(0x20, 0x00, 0x00, 0x00),
	               array(0x40, 0x00, 0x00, 0x00),
	               array(0x80, 0x00, 0x00, 0x00),
	               array(0x1b, 0x00, 0x00, 0x00),
	               array(0x36, 0x00, 0x00, 0x00) );

	protected static $passwords = array();

	/**
	 * AES Cipher function: encrypt 'input' with Rijndael algorithm
	 *
	 * @param input message as byte-array (16 bytes)
	 * @param w     key schedule as 2D byte-array (Nr+1 x Nb bytes) -
	 *              generated from the cipher key by KeyExpansion()
	 * @return      ciphertext as byte-array (16 bytes)
	 */
	protected static function Cipher($input, $w) {    // main Cipher function [�5.1]
	  $Nb = 4;                 // block size (in words): no of columns in state (fixed at 4 for AES)
	  $Nr = count($w)/$Nb - 1; // no of rounds: 10/12/14 for 128/192/256-bit keys

	  $state = array();  // initialise 4xNb byte-array 'state' with input [�3.4]
	  for ($i=0; $i<4*$Nb; $i++) $state[$i%4][floor($i/4)] = $input[$i];

	  $state = self::AddRoundKey($state, $w, 0, $Nb);

	  for ($round=1; $round<$Nr; $round++) {  // apply Nr rounds
	    $state = self::SubBytes($state, $Nb);
	    $state = self::ShiftRows($state, $Nb);
	    $state = self::MixColumns($state, $Nb);
	    $state = self::AddRoundKey($state, $w, $round, $Nb);
	  }

	  $state = self::SubBytes($state, $Nb);
	  $state = self::ShiftRows($state, $Nb);
	  $state = self::AddRoundKey($state, $w, $Nr, $Nb);

	  $output = array(4*$Nb);  // convert state to 1-d array before returning [�3.4]
	  for ($i=0; $i<4*$Nb; $i++) $output[$i] = $state[$i%4][floor($i/4)];
	  return $output;
	}

	protected static function AddRoundKey($state, $w, $rnd, $Nb) {  // xor Round Key into state S [�5.1.4]
	  for ($r=0; $r<4; $r++) {
	    for ($c=0; $c<$Nb; $c++) $state[$r][$c] ^= $w[$rnd*4+$c][$r];
	  }
	  return $state;
	}

	protected static function SubBytes($s, $Nb) {    // apply SBox to state S [�5.1.1]
	  for ($r=0; $r<4; $r++) {
	    for ($c=0; $c<$Nb; $c++) $s[$r][$c] = self::$Sbox[$s[$r][$c]];
	  }
	  return $s;
	}

	protected static function ShiftRows($s, $Nb) {    // shift row r of state S left by r bytes [�5.1.2]
	  $t = array(4);
	  for ($r=1; $r<4; $r++) {
	    for ($c=0; $c<4; $c++) $t[$c] = $s[$r][($c+$r)%$Nb];  // shift into temp copy
	    for ($c=0; $c<4; $c++) $s[$r][$c] = $t[$c];         // and copy back
	  }          // note that this will work for Nb=4,5,6, but not 7,8 (always 4 for AES):
	  return $s;  // see fp.gladman.plus.com/cryptography_technology/rijndael/aes.spec.311.pdf
	}

	protected static function MixColumns($s, $Nb) {   // combine bytes of each col of state S [�5.1.3]
	  for ($c=0; $c<4; $c++) {
	    $a = array(4);  // 'a' is a copy of the current column from 's'
	    $b = array(4);  // 'b' is a�{02} in GF(2^8)
	    for ($i=0; $i<4; $i++) {
	      $a[$i] = $s[$i][$c];
	      $b[$i] = $s[$i][$c]&0x80 ? $s[$i][$c]<<1 ^ 0x011b : $s[$i][$c]<<1;
	    }
	    // a[n] ^ b[n] is a�{03} in GF(2^8)
	    $s[0][$c] = $b[0] ^ $a[1] ^ $b[1] ^ $a[2] ^ $a[3]; // 2*a0 + 3*a1 + a2 + a3
	    $s[1][$c] = $a[0] ^ $b[1] ^ $a[2] ^ $b[2] ^ $a[3]; // a0 * 2*a1 + 3*a2 + a3
	    $s[2][$c] = $a[0] ^ $a[1] ^ $b[2] ^ $a[3] ^ $b[3]; // a0 + a1 + 2*a2 + 3*a3
	    $s[3][$c] = $a[0] ^ $b[0] ^ $a[1] ^ $a[2] ^ $b[3]; // 3*a0 + a1 + a2 + 2*a3
	  }
	  return $s;
	}

	/**
	 * Key expansion for Rijndael Cipher(): performs key expansion on cipher key
	 * to generate a key schedule
	 *
	 * @param key cipher key byte-array (16 bytes)
	 * @return    key schedule as 2D byte-array (Nr+1 x Nb bytes)
	 */
	protected static function KeyExpansion($key) {  // generate Key Schedule from Cipher Key [�5.2]
	  $Nb = 4;              // block size (in words): no of columns in state (fixed at 4 for AES)
	  $Nk = count($key)/4;  // key length (in words): 4/6/8 for 128/192/256-bit keys
	  $Nr = $Nk + 6;        // no of rounds: 10/12/14 for 128/192/256-bit keys

	  $w = array();
	  $temp = array();

	  for ($i=0; $i<$Nk; $i++) {
	    $r = array($key[4*$i], $key[4*$i+1], $key[4*$i+2], $key[4*$i+3]);
	    $w[$i] = $r;
	  }

	  for ($i=$Nk; $i<($Nb*($Nr+1)); $i++) {
	    $w[$i] = array();
	    for ($t=0; $t<4; $t++) $temp[$t] = $w[$i-1][$t];
	    if ($i % $Nk == 0) {
	      $temp = self::SubWord(self::RotWord($temp));
	      for ($t=0; $t<4; $t++) $temp[$t] ^= self::$Rcon[$i/$Nk][$t];
	    } else if ($Nk > 6 && $i%$Nk == 4) {
	      $temp = self::SubWord($temp);
	    }
	    for ($t=0; $t<4; $t++) $w[$i][$t] = $w[$i-$Nk][$t] ^ $temp[$t];
	  }
	  return $w;
	}

	protected static function SubWord($w) {    // apply SBox to 4-byte word w
	  for ($i=0; $i<4; $i++) $w[$i] = self::$Sbox[$w[$i]];
	  return $w;
	}

	protected static function RotWord($w) {    // rotate 4-byte word w left by one byte
	  $tmp = $w[0];
	  for ($i=0; $i<3; $i++) $w[$i] = $w[$i+1];
	  $w[3] = $tmp;
	  return $w;
	}

	/*
	 * Unsigned right shift function, since PHP has neither >>> operator nor unsigned ints
	 *
	 * @param a  number to be shifted (32-bit integer)
	 * @param b  number of bits to shift a to the right (0..31)
	 * @return   a right-shifted and zero-filled by b bits
	 */
	protected static function urs($a, $b) {
	  $a &= 0xffffffff; $b &= 0x1f;  // (bounds check)
	  if ($a&0x80000000 && $b>0) {   // if left-most bit set
	    $a = ($a>>1) & 0x7fffffff;   //   right-shift one bit & clear left-most bit
	    $a = $a >> ($b-1);           //   remaining right-shifts
	  } else {                       // otherwise
	    $a = ($a>>$b);               //   use normal right-shift
	  }
	  return $a;
	}

	/**
	 * Encrypt a text using AES encryption in Counter mode of operation
	 *  - see http://csrc.nist.gov/publications/nistpubs/800-38a/sp800-38a.pdf
	 *
	 * Unicode multi-byte character safe
	 *
	 * @param plaintext source text to be encrypted
	 * @param password  the password to use to generate a key
	 * @param nBits     number of bits to be used in the key (128, 192, or 256)
	 * @return          encrypted text
	 */
	public static function AESEncryptCtr($plaintext, $password, $nBits) {
	  $blockSize = 16;  // block size fixed at 16 bytes / 128 bits (Nb=4) for AES
	  if (!($nBits==128 || $nBits==192 || $nBits==256)) return '';  // standard allows 128/192/256 bit keys
	  // note PHP (5) gives us plaintext and password in UTF8 encoding!

	  // use AES itself to encrypt password to get cipher key (using plain password as source for
	  // key expansion) - gives us well encrypted key
	  $nBytes = $nBits/8;  // no bytes in key
	  $pwBytes = array();
	  for ($i=0; $i<$nBytes; $i++) $pwBytes[$i] = ord(substr($password,$i,1)) & 0xff;
	  $key = self::Cipher($pwBytes, self::KeyExpansion($pwBytes));
	  $key = array_merge($key, array_slice($key, 0, $nBytes-16));  // expand key to 16/24/32 bytes long

	  // initialise counter block (NIST SP800-38A �B.2): millisecond time-stamp for nonce in
	  // 1st 8 bytes, block counter in 2nd 8 bytes
	  $counterBlock = array();
	  $nonce = floor(microtime(true)*1000);   // timestamp: milliseconds since 1-Jan-1970
	  $nonceSec = floor($nonce/1000);
	  $nonceMs = $nonce%1000;
	  // encode nonce with seconds in 1st 4 bytes, and (repeated) ms part filling 2nd 4 bytes
	  for ($i=0; $i<4; $i++) $counterBlock[$i] = self::urs($nonceSec, $i*8) & 0xff;
	  for ($i=0; $i<4; $i++) $counterBlock[$i+4] = $nonceMs & 0xff;
	  // and convert it to a string to go on the front of the ciphertext
	  $ctrTxt = '';
	  for ($i=0; $i<8; $i++) $ctrTxt .= chr($counterBlock[$i]);

	  // generate key schedule - an expansion of the key into distinct Key Rounds for each round
	  $keySchedule = self::KeyExpansion($key);

	  $blockCount = ceil(strlen($plaintext)/$blockSize);
	  $ciphertxt = array();  // ciphertext as array of strings

	  for ($b=0; $b<$blockCount; $b++) {
	    // set counter (block #) in last 8 bytes of counter block (leaving nonce in 1st 8 bytes)
	    // done in two stages for 32-bit ops: using two words allows us to go past 2^32 blocks (68GB)
	    for ($c=0; $c<4; $c++) $counterBlock[15-$c] = self::urs($b, $c*8) & 0xff;
	    for ($c=0; $c<4; $c++) $counterBlock[15-$c-4] = self::urs($b/0x100000000, $c*8);

	    $cipherCntr = self::Cipher($counterBlock, $keySchedule);  // -- encrypt counter block --

	    // block size is reduced on final block
	    $blockLength = $b<$blockCount-1 ? $blockSize : (strlen($plaintext)-1)%$blockSize+1;
	    $cipherByte = array();

	    for ($i=0; $i<$blockLength; $i++) {  // -- xor plaintext with ciphered counter byte-by-byte --
	      $cipherByte[$i] = $cipherCntr[$i] ^ ord(substr($plaintext, $b*$blockSize+$i, 1));
	      $cipherByte[$i] = chr($cipherByte[$i]);
	    }
	    $ciphertxt[$b] = implode('', $cipherByte);  // escape troublesome characters in ciphertext
	  }

	  // implode is more efficient than repeated string concatenation
	  $ciphertext = $ctrTxt . implode('', $ciphertxt);
	  $ciphertext = base64_encode($ciphertext);
	  return $ciphertext;
	}

	/**
	 * Decrypt a text encrypted by AES in counter mode of operation
	 *
	 * @param ciphertext source text to be decrypted
	 * @param password   the password to use to generate a key
	 * @param nBits      number of bits to be used in the key (128, 192, or 256)
	 * @return           decrypted text
	 */
	public static function AESDecryptCtr($ciphertext, $password, $nBits) {
	  $blockSize = 16;  // block size fixed at 16 bytes / 128 bits (Nb=4) for AES
	  if (!($nBits==128 || $nBits==192 || $nBits==256)) return '';  // standard allows 128/192/256 bit keys
	  $ciphertext = base64_decode($ciphertext);

	  // use AES to encrypt password (mirroring encrypt routine)
	  $nBytes = $nBits/8;  // no bytes in key
	  $pwBytes = array();
	  for ($i=0; $i<$nBytes; $i++) $pwBytes[$i] = ord(substr($password,$i,1)) & 0xff;
	  $key = self::Cipher($pwBytes, self::KeyExpansion($pwBytes));
	  $key = array_merge($key, array_slice($key, 0, $nBytes-16));  // expand key to 16/24/32 bytes long

	  // recover nonce from 1st element of ciphertext
	  $counterBlock = array();
	  $ctrTxt = substr($ciphertext, 0, 8);
	  for ($i=0; $i<8; $i++) $counterBlock[$i] = ord(substr($ctrTxt,$i,1));

	  // generate key schedule
	  $keySchedule = self::KeyExpansion($key);

	  // separate ciphertext into blocks (skipping past initial 8 bytes)
	  $nBlocks = ceil((strlen($ciphertext)-8) / $blockSize);
	  $ct = array();
	  for ($b=0; $b<$nBlocks; $b++) $ct[$b] = substr($ciphertext, 8+$b*$blockSize, 16);
	  $ciphertext = $ct;  // ciphertext is now array of block-length strings

	  // plaintext will get generated block-by-block into array of block-length strings
	  $plaintxt = array();

	  for ($b=0; $b<$nBlocks; $b++) {
	    // set counter (block #) in last 8 bytes of counter block (leaving nonce in 1st 8 bytes)
	    for ($c=0; $c<4; $c++) $counterBlock[15-$c] = self::urs($b, $c*8) & 0xff;
	    for ($c=0; $c<4; $c++) $counterBlock[15-$c-4] = self::urs(($b+1)/0x100000000-1, $c*8) & 0xff;

	    $cipherCntr = self::Cipher($counterBlock, $keySchedule);  // encrypt counter block

	    $plaintxtByte = array();
	    for ($i=0; $i<strlen($ciphertext[$b]); $i++) {
	      // -- xor plaintext with ciphered counter byte-by-byte --
	      $plaintxtByte[$i] = $cipherCntr[$i] ^ ord(substr($ciphertext[$b],$i,1));
	      $plaintxtByte[$i] = chr($plaintxtByte[$i]);

	    }
	    $plaintxt[$b] = implode('', $plaintxtByte);
	  }

	  // join array of blocks into single plaintext string
	  $plaintext = implode('',$plaintxt);

	  return $plaintext;
	}

	/**
	 * AES decryption in CBC mode. This is the standard mode (the CTR methods
	 * actually use Rijndael-128 in CTR mode, which - technically - isn't AES).
	 *
	 * Supports AES-128, AES-192 and AES-256. It supposes that the last 4 bytes
	 * contained a little-endian unsigned long integer representing the unpadded
	 * data length.
	 *
	 * @since 3.0.1
	 * @author Nicholas K. Dionysopoulos
	 *
	 * @param string $ciphertext The data to encrypt
	 * @param string $password Encryption password
	 * @param int $nBits Encryption key size. Can be 128, 192 or 256
	 * @return string The plaintext
	 */
	public static function AESDecryptCBC($ciphertext, $password, $nBits = 128)
	{
		if (!($nBits==128 || $nBits==192 || $nBits==256)) return false;  // standard allows 128/192/256 bit keys
		if(!function_exists('mcrypt_module_open')) return false;

		// Try to fetch cached key/iv or create them if they do not exist
		$lookupKey = $password.'-'.$nBits;
		if(array_key_exists($lookupKey, self::$passwords))
		{
			$key	= self::$passwords[$lookupKey]['key'];
			$iv		= self::$passwords[$lookupKey]['iv'];
		}
		else
		{
			// use AES itself to encrypt password to get cipher key (using plain password as source for
			// key expansion) - gives us well encrypted key
			$nBytes = $nBits/8;  // no bytes in key
			$pwBytes = array();
			for ($i=0; $i<$nBytes; $i++) $pwBytes[$i] = ord(substr($password,$i,1)) & 0xff;
			$key = self::Cipher($pwBytes, self::KeyExpansion($pwBytes));
			$key = array_merge($key, array_slice($key, 0, $nBytes-16));  // expand key to 16/24/32 bytes long
			$newKey = '';
			foreach($key as $int) { $newKey .= chr($int); }
			$key = $newKey;

			// Create an Initialization Vector (IV) based on the password, using the same technique as for the key
			$nBytes = 16;  // AES uses a 128 -bit (16 byte) block size, hence the IV size is always 16 bytes
			$pwBytes = array();
			for ($i=0; $i<$nBytes; $i++) $pwBytes[$i] = ord(substr($password,$i,1)) & 0xff;
			$iv = self::Cipher($pwBytes, self::KeyExpansion($pwBytes));
			$newIV = '';
			foreach($iv as $int) { $newIV .= chr($int); }
			$iv = $newIV;

			self::$passwords[$lookupKey]['key'] = $key;
			self::$passwords[$lookupKey]['iv'] = $iv;
		}

		// Read the data size
		$data_size = unpack('V', substr($ciphertext,-4) );

		// Decrypt
		$td = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');
		mcrypt_generic_init($td, $key, $iv);
		$plaintext = mdecrypt_generic($td, substr($ciphertext,0,-4));
		mcrypt_generic_deinit($td);

		// Trim padding, if necessary
		if(strlen($plaintext) > $data_size)
		{
			$plaintext = substr($plaintext, 0, $data_size);
		}

		return $plaintext;
	}
}

/**
 * Akeeba Restore
 * A JSON-powered JPA, JPS and ZIP archive extraction library
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / Akeeba Ltd.
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

/**
 * The Master Setup will read the configuration parameters from restoration.php or
 * the JSON-encoded "configuration" input variable and return the status.
 *
 * @return bool True if the master configuration was applied to the Factory object
 */
function masterSetup()
{
	// ------------------------------------------------------------
	// 1. Import basic setup parameters
	// ------------------------------------------------------------

	$ini_data = null;

	// In restore.php mode, require restoration.php or fail
	if (!defined('KICKSTART'))
	{
		// This is the standalone mode, used by Akeeba Backup Professional. It looks for a restoration.php
		// file to perform its magic. If the file is not there, we will abort.
		$setupFile = 'restoration.php';

		if (!file_exists($setupFile))
		{
			AKFactory::set('kickstart.enabled', false);

			return false;
		}

		// Load restoration.php. It creates a global variable named $restoration_setup
		require_once $setupFile;

		$ini_data = $restoration_setup;

		if (empty($ini_data))
		{
			// No parameters fetched. Darn, how am I supposed to work like that?!
			AKFactory::set('kickstart.enabled', false);

			return false;
		}

		AKFactory::set('kickstart.enabled', true);
	}
	else
	{
		// Maybe we have $restoration_setup defined in the head of kickstart.php
		global $restoration_setup;

		if (!empty($restoration_setup) && !is_array($restoration_setup))
		{
			$ini_data = AKText::parse_ini_file($restoration_setup, false, true);
		}
		elseif (is_array($restoration_setup))
		{
			$ini_data = $restoration_setup;
		}
	}

	// Import any data from $restoration_setup
	if (!empty($ini_data))
	{
		foreach ($ini_data as $key => $value)
		{
			AKFactory::set($key, $value);
		}
		AKFactory::set('kickstart.enabled', true);
	}

	// Reinitialize $ini_data
	$ini_data = null;

	// ------------------------------------------------------------
	// 2. Explode JSON parameters into $_REQUEST scope
	// ------------------------------------------------------------

	// Detect a JSON string in the request variable and store it.
	$json = getQueryParam('json', null);

	// Remove everything from the request, post and get arrays
	if (!empty($_REQUEST))
	{
		foreach ($_REQUEST as $key => $value)
		{
			unset($_REQUEST[$key]);
		}
	}

	if (!empty($_POST))
	{
		foreach ($_POST as $key => $value)
		{
			unset($_POST[$key]);
		}
	}

	if (!empty($_GET))
	{
		foreach ($_GET as $key => $value)
		{
			unset($_GET[$key]);
		}
	}

	// Decrypt a possibly encrypted JSON string
	$password = AKFactory::get('kickstart.security.password', null);

	if (!empty($json))
	{
		if (!empty($password))
		{
			$json = AKEncryptionAES::AESDecryptCtr($json, $password, 128);

			if (empty($json))
			{
				die('###{"status":false,"message":"Invalid login"}###');
			}
		}

		// Get the raw data
		$raw = json_decode($json, true);

		if (!empty($password) && (empty($password) || !isset($raw['factory'])))
		{
			die('###{"status":false,"message":"Invalid login"}###');
		}

		// Pass all JSON data to the request array
		if (!empty($raw))
		{
			foreach ($raw as $key => $value)
			{
				$_REQUEST[$key] = $value;
			}
		}
	}
	elseif (!empty($password))
	{
		die('###{"status":false,"message":"Invalid login"}###');
	}

	// ------------------------------------------------------------
	// 3. Try the "factory" variable
	// ------------------------------------------------------------
	// A "factory" variable will override all other settings.
	$serialized = getQueryParam('factory', null);

	if (!is_null($serialized))
	{
		// Get the serialized factory
		AKFactory::unserialize($serialized);
		AKFactory::set('kickstart.enabled', true);

		return true;
	}

	// ------------------------------------------------------------
	// 4. Try the configuration variable for Kickstart
	// ------------------------------------------------------------
	if (defined('KICKSTART'))
	{
		$configuration = getQueryParam('configuration');

		if (!is_null($configuration))
		{
			// Let's decode the configuration from JSON to array
			$ini_data = json_decode($configuration, true);
		}
		else
		{
			// Neither exists. Enable Kickstart's interface anyway.
			$ini_data = array('kickstart.enabled' => true);
		}

		// Import any INI data we might have from other sources
		if (!empty($ini_data))
		{
			foreach ($ini_data as $key => $value)
			{
				AKFactory::set($key, $value);
			}

			AKFactory::set('kickstart.enabled', true);

			return true;
		}
	}
}

/**
 * Akeeba Restore
 * A JSON-powered JPA, JPS and ZIP archive extraction library
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / Akeeba Ltd.
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

// Mini-controller for restore.php
if(!defined('KICKSTART'))
{
	// The observer class, used to report number of files and bytes processed
	class RestorationObserver extends AKAbstractPartObserver
	{
		public $compressedTotal = 0;
		public $uncompressedTotal = 0;
		public $filesProcessed = 0;

		public function update($object, $message)
		{
			if(!is_object($message)) return;

			if( !array_key_exists('type', get_object_vars($message)) ) return;

			if( $message->type == 'startfile' )
			{
				$this->filesProcessed++;
				$this->compressedTotal += $message->content->compressed;
				$this->uncompressedTotal += $message->content->uncompressed;
			}
		}

		public function __toString()
		{
			return __CLASS__;
		}

	}

	// Import configuration
	masterSetup();

	$retArray = array(
		'status'	=> true,
		'message'	=> null
	);

	$enabled = AKFactory::get('kickstart.enabled', false);

	if($enabled)
	{
		$task = getQueryParam('task');

		switch($task)
		{
			case 'ping':
				// ping task - realy does nothing!
				$timer = AKFactory::getTimer();
				$timer->enforce_min_exec_time();
				break;

			case 'startRestore':
				AKFactory::nuke(); // Reset the factory

				// Let the control flow to the next step (the rest of the code is common!!)

			case 'stepRestore':
				$engine = AKFactory::getUnarchiver(); // Get the engine
				$observer = new RestorationObserver(); // Create a new observer
				$engine->attach($observer); // Attach the observer
				$engine->tick();
				$ret = $engine->getStatusArray();

				if( $ret['Error'] != '' )
				{
					$retArray['status'] = false;
					$retArray['done'] = true;
					$retArray['message'] = $ret['Error'];
				}
				elseif( !$ret['HasRun'] )
				{
					$retArray['files'] = $observer->filesProcessed;
					$retArray['bytesIn'] = $observer->compressedTotal;
					$retArray['bytesOut'] = $observer->uncompressedTotal;
					$retArray['status'] = true;
					$retArray['done'] = true;
				}
				else
				{
					$retArray['files'] = $observer->filesProcessed;
					$retArray['bytesIn'] = $observer->compressedTotal;
					$retArray['bytesOut'] = $observer->uncompressedTotal;
					$retArray['status'] = true;
					$retArray['done'] = false;
					$retArray['factory'] = AKFactory::serialize();
				}
				break;

			case 'finalizeRestore':
				$root = AKFactory::get('kickstart.setup.destdir');
				// Remove the installation directory
				recursive_remove_directory( $root.'/installation' );

				$postproc = AKFactory::getPostProc();

				// Rename htaccess.bak to .htaccess
				if(file_exists($root.'/htaccess.bak'))
				{
					if( file_exists($root.'/.htaccess')  )
					{
						$postproc->unlink($root.'/.htaccess');
					}
					$postproc->rename( $root.'/htaccess.bak', $root.'/.htaccess' );
				}

				// Rename htaccess.bak to .htaccess
				if(file_exists($root.'/web.config.bak'))
				{
					if( file_exists($root.'/web.config')  )
					{
						$postproc->unlink($root.'/web.config');
					}
					$postproc->rename( $root.'/web.config.bak', $root.'/web.config' );
				}

				// Remove restoration.php
				$basepath = KSROOTDIR;
				$basepath = rtrim( str_replace('\\','/',$basepath), '/' );
				if(!empty($basepath)) $basepath .= '/';
				$postproc->unlink( $basepath.'restoration.php' );

				// Import a custom finalisation file
				if (file_exists(__DIR__ . '/restore_finalisation.php'))
				{
					include_once __DIR__ . '/restore_finalisation.php';
				}

				// Run a custom finalisation script
				if (function_exists('finalizeRestore'))
				{
					finalizeRestore($root, $basepath);
				}
				break;

			default:
				// Invalid task!
				$enabled = false;
				break;
		}
	}

	// Maybe we weren't authorized or the task was invalid?
	if(!$enabled)
	{
		// Maybe the user failed to enter any information
		$retArray['status'] = false;
		$retArray['message'] = AKText::_('ERR_INVALID_LOGIN');
	}

	// JSON encode the message
	$json = json_encode($retArray);
	// Do I have to encrypt?
	$password = AKFactory::get('kickstart.security.password', null);
	if(!empty($password))
	{
		$json = AKEncryptionAES::AESEncryptCtr($json, $password, 128);
	}

	// Return the message
	echo "###$json###";

}

// ------------ lixlpixel recursive PHP functions -------------
// recursive_remove_directory( directory to delete, empty )
// expects path to directory and optional TRUE / FALSE to empty
// of course PHP has to have the rights to delete the directory
// you specify and all files and folders inside the directory
// ------------------------------------------------------------
function recursive_remove_directory($directory)
{
	// if the path has a slash at the end we remove it here
	if(substr($directory,-1) == '/')
	{
		$directory = substr($directory,0,-1);
	}
	// if the path is not valid or is not a directory ...
	if(!file_exists($directory) || !is_dir($directory))
	{
		// ... we return false and exit the function
		return FALSE;
	// ... if the path is not readable
	}elseif(!is_readable($directory))
	{
		// ... we return false and exit the function
		return FALSE;
	// ... else if the path is readable
	}else{
		// we open the directory
		$handle = opendir($directory);
		$postproc = AKFactory::getPostProc();
		// and scan through the items inside
		while (FALSE !== ($item = readdir($handle)))
		{
			// if the filepointer is not the current directory
			// or the parent directory
			if($item != '.' && $item != '..')
			{
				// we build the new path to delete
				$path = $directory.'/'.$item;
				// if the new path is a directory
				if(is_dir($path))
				{
					// we call this function with the new path
					recursive_remove_directory($path);
				// if the new path is a file
				}else{
					// we remove the file
					$postproc->unlink($path);
				}
			}
		}
		// close the directory
		closedir($handle);
		// try to delete the now empty directory
		if(!$postproc->rmdir($directory))
		{
			// return false if not possible
			return FALSE;
		}
		// return success
		return TRUE;
	}
}


/**
 * Akeeba Kickstart
 * A JSON-powered archive extraction tool
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / AkeebaBackup.com
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */
class AKKickstartUtils
{
	/**
	 * Guess the best path containing backup archives. The default strategy is check in the current directory first,
	 * then attempt to find an Akeeba Backup for Joomla!, Akeeba Solo or Akeeba Backup for WordPress default backup
	 * output directory under the current root. The first one containing backup archives wins.
	 *
	 * @return string The path to get archives from
	 */
	public static function getBestArchivePath()
	{
		$basePath = self::getPath();
		$basePathSlash = (empty($basePath) ? '.' : rtrim($basePath, '/\\')) . '/';

		$paths = array(
			$basePath,
			$basePathSlash . 'administrator/components/com_akeeba/backup',
			$basePathSlash . 'backups',
			$basePathSlash . 'wp-content/plugins/akeebabackupwp/app/backups',
		);

		foreach ($paths as $path)
		{
			$archives = self::findArchives($path);

			if (!empty($archives))
			{
				return $path;
			}
		}

		return $basePath;
	}

	/**
	 * Gets the directory the file is in
	 *
	 * @return string
	 */
	public static function getPath()
	{
		$path = KSROOTDIR;
		$path = rtrim(str_replace('\\', '/', $path), '/');
		if (!empty($path))
		{
			$path .= '/';
		}

		return $path;
	}

	/**
	 * Scans the current directory for archive files (JPA, JPS and ZIP format)
	 *
	 * @param string $path The path to look for archives. null for automatic path
	 *
	 * @return array
	 */
	public static function findArchives($path)
	{
		$ret = array();

		if (empty($path))
		{
			$path = self::getPath();
		}

		if (empty($path))
		{
			$path = '.';
		}

		$dh = @opendir($path);

		if ($dh === false)
		{
			return $ret;
		}

		while (false !== $file = @readdir($dh))
		{
			$dotpos = strrpos($file, '.');

			if ($dotpos === false)
			{
				continue;
			}

			if ($dotpos == strlen($file))
			{
				continue;
			}

			$extension = strtolower(substr($file, $dotpos + 1));

			if (in_array($extension, array('jpa', 'zip', 'jps')))
			{
				$ret[] = $file;
			}
		}

		closedir($dh);

		if (!empty($ret))
		{
			return $ret;
		}

		// On some hosts using opendir doesn't work. Let's try Dir instead
		$d = dir($path);

		while (false != ($file = $d->read()))
		{
			$dotpos = strrpos($file, '.');

			if ($dotpos === false)
			{
				continue;
			}

			if ($dotpos == strlen($file))
			{
				continue;
			}

			$extension = strtolower(substr($file, $dotpos + 1));

			if (in_array($extension, array('jpa', 'zip', 'jps')))
			{
				$ret[] = $file;
			}
		}

		return $ret;
	}

	/**
	 * Scans the current directory for archive files and returns them as <OPTION> tags
	 *
	 * @param string $path The path to look for archives. null for automatic path
	 *
	 * @return string
	 */
	public static function getArchivesAsOptions($path = null)
	{
		$ret = '';

		$archives = self::findArchives($path);

		if (empty($archives))
		{
			return $ret;
		}

		foreach ($archives as $file)
		{
			//$file = htmlentities($file);
			$ret .= '<option value="' . $file . '">' . $file . '</option>' . "\n";
		}

		return $ret;
	}
}

/**
 * Akeeba Kickstart
 * A JSON-powered archive extraction tool
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / AkeebaBackup.com
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

class ExtractionObserver extends AKAbstractPartObserver
{
	public $compressedTotal = 0;
	public $uncompressedTotal = 0;
	public $filesProcessed = 0;
	public $totalSize = null;
	public $fileList = null;
	public $lastFile = '';

	public function update($object, $message)
	{
		if(!is_object($message)) return;

		if( !array_key_exists('type', get_object_vars($message)) ) return;

		if( $message->type == 'startfile' )
		{
			$this->lastFile = $message->content->file;
			$this->filesProcessed++;
			$this->compressedTotal += $message->content->compressed;
			$this->uncompressedTotal += $message->content->uncompressed;
		}
		elseif( $message->type == 'totalsize' )
		{
			$this->totalSize = $message->content->totalsize;
			$this->fileList = $message->content->filelist;
		}
	}

	public function __toString()
	{
		return __CLASS__;
	}

}

/**
 * Akeeba Kickstart
 * A JSON-powered archive extraction tool
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / AkeebaBackup.com
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

function callExtraFeature($method = null, array $params = array())
{
	static $extraFeatureObjects = null;

	if(!is_array($extraFeatureObjects)) {
		$extraFeatureObjects = array();
		$allClasses = get_declared_classes();
		foreach($allClasses as $class) {
			if(substr($class, 0, 9) == 'AKFeature') {
				$extraFeatureObjects[] = new $class;
			}
		}
	}

	if(is_null($method)) {
		return;
	}

	if(empty($extraFeatureObjects)) {
		return;
	}

	$result = null;
	foreach($extraFeatureObjects as $o) {
		if(!method_exists($o, $method)) {
			continue;
		}
		$result = call_user_func(array($o, $method), $params);
	}

	return $result;
}

/**
 * Akeeba Kickstart
 * A JSON-powered archive extraction tool
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / AkeebaBackup.com
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

function TranslateWinPath($p_path)
{
	$is_unc = false;

	if (KSWINDOWS)
	{
		// Is this a UNC path?
		$is_unc = (substr($p_path, 0, 2) == '\\\\') || (substr($p_path, 0, 2) == '//');
		// Change potential windows directory separator
		if ((strpos($p_path, '\\') > 0) || (substr($p_path, 0, 1) == '\\'))
		{
			$p_path = strtr($p_path, '\\', '/');
		}
	}

	// Remove multiple slashes
	$p_path = str_replace('///', '/', $p_path);
	$p_path = str_replace('//', '/', $p_path);

	// Fix UNC paths
	if ($is_unc)
	{
		$p_path = '//' . ltrim($p_path, '/');
	}

	return $p_path;
}


/**
 * FTP Functions
 */
function getListing($directory, $host, $port, $username, $password, $passive, $ssl)
{
	$directory = resolvePath($directory);
	$dir       = $directory;

	// Parse directory to parts
	$parsed_dir = trim($dir,'/');
	$parts = empty($parsed_dir) ? array() : explode('/', $parsed_dir);

	// Find the path to the parent directory
	if(!empty($parts)) {
		$copy_of_parts = $parts;
		array_pop($copy_of_parts);
		if(!empty($copy_of_parts)) {
			$parent_directory = '/' . implode('/', $copy_of_parts);
		} else {
			$parent_directory = '/';
		}
	} else {
		$parent_directory = '';
	}

	// Connect to the server
	if($ssl) {
		$con = @ftp_ssl_connect($host, $port);
	} else {
		$con = @ftp_connect($host, $port);
	}
	if($con === false) {
		return array(
			'error' => 'FTPBROWSER_ERROR_HOSTNAME'
		);
	}

	// Login
	$result = @ftp_login($con, $username, $password);
	if($result === false) {
		return array(
			'error' => 'FTPBROWSER_ERROR_USERPASS'
		);
	}

	// Set the passive mode -- don't care if it fails, though!
	@ftp_pasv($con, $passive);

	// Try to chdir to the specified directory
	if(!empty($dir)) {
		$result = @ftp_chdir($con, $dir);
		if($result === false) {
			return array(
				'error' => 'FTPBROWSER_ERROR_NOACCESS'
			);
		}
	}

	// Get a raw directory listing (hoping it's a UNIX server!)
	$list = @ftp_rawlist($con,'.');
	ftp_close($con);

	if($list === false) {
		return array(
			'error' => 'FTPBROWSER_ERROR_UNSUPPORTED'
		);
	}

	// Parse the raw listing into an array
	$folders = parse_rawlist($list);

	return array(
		'error'			=> '',
		'list'			=> $folders,
		'breadcrumbs'	=> $parts,
		'directory'		=> $directory,
		'parent'		=> $parent_directory
	);
}

function parse_rawlist($list)
{
	$folders = array();
	foreach($list as $v)
	{
		$info = array();
		$vinfo = preg_split("/[\s]+/", $v, 9);
		if ($vinfo[0] !== "total") {
			$perms = $vinfo[0];
			if(substr($perms,0,1) == 'd') {
				$folders[] = $vinfo[8];
			}
		}
	}

	asort($folders);
	return $folders;
}

function getSftpListing($directory, $host, $port, $username, $password)
{
	$directory = resolvePath($directory);
	$dir       = $directory;

	// Parse directory to parts
	$parsed_dir = trim($dir,'/');
	$parts = empty($parsed_dir) ? array() : explode('/', $parsed_dir);

	// Find the path to the parent directory
	if(!empty($parts)) {
		$copy_of_parts = $parts;
		array_pop($copy_of_parts);
		if(!empty($copy_of_parts)) {
			$parent_directory = '/' . implode('/', $copy_of_parts);
		} else {
			$parent_directory = '/';
		}
	} else {
		$parent_directory = '';
	}

	// Initialise
	$connection = null;
	$sftphandle = null;

	// Open a connection
	if(!function_exists('ssh2_connect'))
	{
		return array(
			'error' => AKText::_('SFTP_NO_SSH2')
		);
	}

	$connection = ssh2_connect($host, $port);

	if ($connection === false)
	{
		return array(
			'error' => AKText::_('SFTP_WRONG_USER')
		);
	}

	if(!ssh2_auth_password($connection, $username, $password))
	{
		return array(
			'error' => AKText::_('SFTP_WRONG_USER')
		);
	}

	$sftphandle = ssh2_sftp($connection);

	if($sftphandle === false)
	{
		return array(
			'error' => AKText::_('SFTP_NO_FTP_SUPPORT')
		);
	}

	// Get a raw directory listing (hoping it's a UNIX server!)
	$list = array();
	$dir  = ltrim($dir, '/');

	$handle = opendir("ssh2.sftp://$sftphandle/$dir");

	if (!is_resource($handle))
	{
		return array(
			'error' => AKText::_('SFTPBROWSER_ERROR_NOACCESS')
		);
	}

	while (($entry = readdir($handle)) !== false)
	{
		if (!is_dir("ssh2.sftp://$sftphandle/$dir/$entry"))
		{
			continue;
		}

		$list[] = $entry;
	}

	closedir($handle);

	if (!empty($list))
	{
		asort($list);
	}

	return array(
		'error'			=> '',
		'list'			=> $list,
		'breadcrumbs'	=> $parts,
		'directory'		=> $directory,
		'parent'		=> $parent_directory
	);
}

/**
 * Simple function to resolve relative paths.
 * Note that it is unable to resolve pathnames any higher than the present working directory.
 * I.E. It doesn't know about any directory names that you don't tell it about; hence: ../../foo becomes foo.
 *
 * @param $filename
 *
 * @return string
 */
function resolvePath($filename)
{
	$filename = str_replace('//', '/', $filename);
	$parts = explode('/', $filename);
	$out = array();
	foreach ($parts as $part){
		if ($part == '.') continue;
		if ($part == '..') {
			array_pop($out);
			continue;
		}
		$out[] = $part;
	}
	return implode('/', $out);
}

function createStealthURL()
{
	$filename = AKFactory::get('kickstart.stealth.url', '');
	// We need an HTML file!
	if(empty($filename)) return;
	// Make sure it ends in .html or .htm
	$filename = basename($filename);
	if( (strtolower(substr($filename,-5)) != '.html') && (strtolower(substr($filename,-4)) != '.htm') ) return;

	$filename_quoted = str_replace('.','\\.',$filename);
	$rewrite_base = trim(dirname(AKFactory::get('kickstart.stealth.url', '')),'/');

	// Get the IP
	$userIP = $_SERVER['REMOTE_ADDR'];
	$userIP = str_replace('.', '\.', $userIP);

	// Get the .htaccess contents
	$stealthHtaccess = <<<ENDHTACCESS
RewriteEngine On
RewriteBase /$rewrite_base
RewriteCond %{REMOTE_HOST}		!$userIP
RewriteCond %{REQUEST_URI}		!$filename_quoted
RewriteCond %{REQUEST_URI}		!(\.png|\.jpg|\.gif|\.jpeg|\.bmp|\.swf|\.css|\.js)$
RewriteRule (.*)				$filename	[R=307,L]

ENDHTACCESS;

	// Write the new .htaccess, removing the old one first
	$postproc = AKFactory::getpostProc();
	$postproc->unlink('.htaccess');
	$tempfile = $postproc->processFilename('.htaccess');
	@file_put_contents($tempfile, $stealthHtaccess);
	$postproc->process();
}

/**
 * Akeeba Kickstart
 * A JSON-powered archive extraction tool
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / AkeebaBackup.com
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

function echoCSS() {
	echo <<<CSS
html {
    background: #e9e9e9;
    font-size: 62.5%;
}
body {
	font-size: 14px;
    font-size: 1.4rem;
    font-family: "Helvetica Neue",Helvetica,Arial,sans-serif;
	text-rendering: optimizelegibility;
	background: transparent;
	color:#555;
	width:90%;
	max-width:980px;
	margin: 0 auto;
}

#page-container {
	position:relative;
	margin:5% 0;
	background: #f9f9f9;
	border: 1px solid #777;
	border: 1px solid rgba(0,0,0,.2);
	-webkit-box-shadow: 0px 0px 10px rgba(0,0,0,.1);
    -moz-box-shadow: 0px 0px 10px rgba(0,0,0,.1);
    box-shadow: 0px 0px 10px rgba(0,0,0,.1);
}

#header {
	color: #555;
	text-shadow: 0 1px #fff;
	background: #f2f5f6;
	background: -moz-linear-gradient(top, #f2f5f6 0%, #e3eaed 37%, #c8d7dc 100%);
	background: -webkit-gradient(linear, left top, left bottom, color-stop(0%,#f2f5f6), color-stop(37%,#e3eaed), color-stop(100%,#c8d7dc));
	background: -webkit-linear-gradient(top, #f2f5f6 0%,#e3eaed 37%,#c8d7dc 100%);
	background: -o-linear-gradient(top, #f2f5f6 0%,#e3eaed 37%,#c8d7dc 100%);
	background: -ms-linear-gradient(top, #f2f5f6 0%,#e3eaed 37%,#c8d7dc 100%);
	background: linear-gradient(top, #f2f5f6 0%,#e3eaed 37%,#c8d7dc 100%);
	-moz-background-clip: padding;
	-webkit-background-clip: padding-box;
	background-clip: padding-box;
	margin-bottom: 0.7em;
	border-bottom: 1px solid #ddd;
	border-bottom: 1px solid rgba(0,0,0,.2);
	padding:.25em;
    font-size: 32px;
    font-size: 3.2rem;
	line-height: 1.2;
	text-align: center;
}

#footer {
	font-size: 8pt;
	color: #233b53;
	text-align: center;
	border-top: 1px solid #ddd;
	border-top: 1px solid rgba(0,0,0,.05);
	padding: 1em 2em;
	background: #deefff;
	background: -moz-linear-gradient(top, #deefff 0%, #98bede 100%);
	background: -webkit-gradient(linear, left top, left bottom, color-stop(0%,#deefff), color-stop(100%,#98bede));
	background: -webkit-linear-gradient(top, #deefff 0%,#98bede 100%);
	background: -o-linear-gradient(top, #deefff 0%,#98bede 100%);
	background: -ms-linear-gradient(top, #deefff 0%,#98bede 100%);
	background: linear-gradient(top, #deefff 0%,#98bede 100%);
	clear: both;
}

#error, .error {
	display: none;
	border: solid #cc0000;
	border-width: 2px 0;
	background: rgb(255,255,136);
	background: -moz-linear-gradient(top, rgba(255,255,136,1) 0%, rgba(255,255,136,1) 100%);
	background: -webkit-gradient(linear, left top, left bottom, color-stop(0%,rgba(255,255,136,1)), color-stop(100%,rgba(255,255,136,1)));
	background: -webkit-linear-gradient(top, rgba(255,255,136,1) 0%,rgba(255,255,136,1) 100%);
	background: -o-linear-gradient(top, rgba(255,255,136,1) 0%,rgba(255,255,136,1) 100%);
	background: -ms-linear-gradient(top, rgba(255,255,136,1) 0%,rgba(255,255,136,1) 100%);
	background: linear-gradient(top, rgba(255,255,136,1) 0%,rgba(255,255,136,1) 100%);
	color: #990000;
	padding:2em 2em 1em;
	margin-bottom: 1.15em;
	text-align:center;
	text-transform: uppercase;
}

#errorMessage, .errorMessage {
	text-transform: none;
}

#error h3, .error h3 {
	margin: 0;
	padding: 0;
	font-size: 12pt;
}

.clr {
	clear: both;
}

.circle {
	display: block;
	float: left;
	-moz-border-radius: 2em;
	-webkit-border-radius: 2em;
	border: 2px solid #e5e5e5;
	font-weight: bold;
	font-size: 18px;
    font-size: 1.8rem;
	line-height:1.5em;
	color: #fff;
	height: 1.5em;
	width: 1.5em;
    margin: 0.75em;
    text-align: center;
    background: rgb(35,83,138);
	background: -moz-linear-gradient(top, rgba(35,83,138,1) 0%, rgba(167,207,223,1) 100%);
	background: -webkit-gradient(linear, left top, left bottom, color-stop(0%,rgba(35,83,138,1)), color-stop(100%,rgba(167,207,223,1)));
	background: -webkit-linear-gradient(top, rgba(35,83,138,1) 0%,rgba(167,207,223,1) 100%);
	background: -o-linear-gradient(top, rgba(35,83,138,1) 0%,rgba(167,207,223,1) 100%);
	background: -ms-linear-gradient(top, rgba(35,83,138,1) 0%,rgba(167,207,223,1) 100%);
	background: linear-gradient(top, rgba(35,83,138,1) 0%,rgba(167,207,223,1) 100%);
	-webkit-box-shadow: 0px 0px 2px rgba(0, 0, 0, 0.8) inset, 0px -1px 2px rgba(255,255,255,.9) inset,  0px 0px 1px rgba(0,0,0,.7); 0 -1px 1px rgba(0,0,0,.4);
    -moz-box-shadow: 0px 0px 2px rgba(0, 0, 0, 0.8) inset, 0px -1px 2px rgba(255,255,255,.9) inset,  0px 0px 1px rgba(0,0,0,.7); 0 -1px 1px rgba(0,0,0,.4);
    box-shadow: 0px 0px 2px rgba(0, 0, 0, 0.8) inset, 0px -1px 2px rgba(255,255,255,.9) inset,  0px 0px 1px rgba(0,0,0,.7); 0 -1px 1px rgba(0,0,0,.4);
}

.area-container {
	margin: 1em 4em;
}

#page2a .area-container {
	margin: 1em 0;
}

#runInstaller,
#runCleanup,
#gotoSite,
#gotoAdministrator,
#gotoPostRestorationRroubleshooting {
    margin: 0 2em 1.3em;
}

h2 {
	font-size: 24px;
    font-size: 2.4rem;
	font-weight: normal;
    line-height: 1.3;
	border: solid #ddd;
	text-shadow: 0px 1px #fff;
	border-top: 1px solid rgba(0,0,0,.05);
	border-bottom: 1px solid rgba(0,0,0,.2);
	border-left:none;
	border-right:none;
	padding: 0.5em 0;
    background: #f2f5f6;
	background: -moz-linear-gradient(top, #f2f5f6 0%, #e3eaed 37%, #c8d7dc 100%);
	background: -webkit-gradient(linear, left top, left bottom, color-stop(0%,#f2f5f6), color-stop(37%,#e3eaed), color-stop(100%,#c8d7dc));
	background: -webkit-linear-gradient(top, #f2f5f6 0%,#e3eaed 37%,#c8d7dc 100%);
	background: -o-linear-gradient(top, #f2f5f6 0%,#e3eaed 37%,#c8d7dc 100%);
	background: -ms-linear-gradient(top, #f2f5f6 0%,#e3eaed 37%,#c8d7dc 100%);
	background: linear-gradient(top, #f2f5f6 0%,#e3eaed 37%,#c8d7dc 100%);
}
#preextraction h2 {
	margin-top:0;
	border-top:0;
	text-align:center;
}

input,
select,
textarea {
    font-size : 100%;
    margin : 0;
    vertical-align : baseline;
    *vertical-align: middle;
}
button,
input {
    line-height : normal;
	font-weight:normal;
    *overflow: visible;
}
input,
select,
textarea {
	background:#fff;
	color:#777;
	font-size: 16px;
	font-size: 1.6rem;
	border:1px solid #d5d5d5;
    -webkit-border-radius: .25em;
    -moz-border-radius: .25em;
    border-radius: .25em;
	-webkit-box-sizing: border-box;
    -moz-box-sizing: border-box;
    box-sizing: border-box;
	width:50%;
	padding:0 0 0 .5em;
}
input[type="checkbox"] {
	width:auto;
}
.field {
	height:1.5em;
}
label {
	display:inline-block;
	width:30%;
	font-size: 85%;
    font-weight: normal;
	text-transform: uppercase;
    cursor : pointer;
	color: #777;
	margin:.5em 0;
}

input:focus, input:hover {
	background-color: #fffbb3;
}

.button {
	display: inline-block;
	margin: 1em .25em;
	text-transform: uppercase;
	padding: 1em 2em;
	background: #2cb12c;
	color:#fff;
	text-shadow: 0 1px 3px rgba(0, 0, 0, 0.2), 0 1px rgba(0, 0, 0, 0.4);
	-webkit-box-shadow: 0 1px 3px rgba(255, 255, 255, 0.5) inset, -1px 2px 2px rgba(0, 0, 0, 0.2);
    -moz-box-shadow: 0 1px 3px rgba(255, 255, 255, 0.5) inset, -1px 2px 2px rgba(0, 0, 0, 0.2);
    box-shadow: 0 1px 3px rgba(255, 255, 255, 0.5) inset, -1px 2px 2px rgba(0, 0, 0, 0.2);
	background: -moz-linear-gradient(top, #2cb12c 0%, #259625 100%);
	background: -webkit-gradient(linear, left top, left bottom, color-stop(0%,#2cb12c), color-stop(100%,#259625));
	background: -webkit-linear-gradient(top, #2cb12c 0%,#259625 100%);
	background: -o-linear-gradient(top, #2cb12c 0%,#259625 100%);
	background: -ms-linear-gradient(top, #2cb12c 0%,#259625 100%);
	background: linear-gradient(top, #2cb12c 0%,#259625 100%);
	border: solid #ddd;
	border: 1px solid rgba(0,0,0,.1);
	cursor: pointer;
	-webkit-border-radius: .25em;
	-moz-border-radius: .25em;
	border-radius: .25em;
	-webkit-transition: 0.3s linear all;
	-moz-transition: 0.3s linear all;
	-ms-transition: 0.3s linear all;
	-o-transition: 0.3s linear all;
  	transition: 0.3s linear all;
}
#checkFTPTempDir.button,
#resetFTPTempDir.button,
#testFTP.button,
#browseFTP,
#reloadArchives,
#notWorking.button {
	padding: .5em 1em;
	text-transform: none;
}

.button:hover {
	background: #259625;
	background: -moz-linear-gradient(top, #259625 0%, #2cb12c 100%);
	background: -webkit-gradient(linear, left top, left bottom, color-stop(0%,#259625), color-stop(100%,#2cb12c));
	background: -webkit-linear-gradient(top, #259625 0%,#2cb12c 100%);
	background: -o-linear-gradient(top, #259625 0%,#2cb12c 100%);
	background: -ms-linear-gradient(top, #259625 0%,#2cb12c 100%);
	background: linear-gradient(top, #259625 0%,#2cb12c 100%);
}
.button:active {
	background: #3c3;
	color: #444;
	text-shadow: 0 1px #fff;
	border: solid #ccc;
	border: 1px solid rgba(0,0,0,.3);
	-webkit-box-shadow: 0 1px 3px rgba(0,0,0, 0.5) inset;
    -moz-box-shadow: 0 1px 3px rgba(0,0,0, 0.5) inset;
    box-shadow: 0 1px 3px rgba(0,0,0, 0.5) inset;
}

#notWorking.button, .bluebutton {
	text-decoration: none;
	background: #7abcff;
	background: -moz-linear-gradient(top, #7abcff 0%, #60abf8 44%, #4096ee 100%);
	background: -webkit-gradient(linear, left top, left bottom, color-stop(0%,#7abcff), color-stop(44%,#60abf8), color-stop(100%,#4096ee));
	background: -webkit-linear-gradient(top, #7abcff 0%,#60abf8 44%,#4096ee 100%);
	background: -o-linear-gradient(top, #7abcff 0%,#60abf8 44%,#4096ee 100%);
	background: -ms-linear-gradient(top, #7abcff 0%,#60abf8 44%,#4096ee 100%);
	background: linear-gradient(top, #7abcff 0%,#60abf8 44%,#4096ee 100%);
}
#notWorking.button:hover, .bluebutton:hover {
	background: #4096ee;
	background: -moz-linear-gradient(top, #4096ee 0%, #60abf8 56%, #7abcff 100%);
	background: -webkit-gradient(linear, left top, left bottom, color-stop(0%,#4096ee), color-stop(56%,#60abf8), color-stop(100%,#7abcff));
	background: -webkit-linear-gradient(top, #4096ee 0%,#60abf8 56%,#7abcff 100%);
	background: -o-linear-gradient(top, #4096ee 0%,#60abf8 56%,#7abcff 100%);
	background: -ms-linear-gradient(top, #4096ee 0%,#60abf8 56%,#7abcff 100%);
	background: linear-gradient(top, #4096ee 0%,#60abf8 56%,#7abcff 100%);
}
#notWorking.button:active, .bluebutton:active {
	background: #7abcff;
}

.loprofile {
	padding: 0.5em 1em;
	font-size: 80%;
}

.black_overlay{
	display: none;
	position: absolute;
	top: 0%;
	left: 0%;
	width: 100%;
	height: 100%;
	background-color: black;
	z-index:1001;
	-moz-opacity: 0.8;
	opacity:.80;
	filter: alpha(opacity=80);
}

.white_content {
	display: none;
	position: absolute;
	padding: 0 0 1em;
	background: #fff;
	border: 1px solid #ddd;
	border: 1px solid rgba(0,0,0,.3);
	z-index:1002;
	overflow: hidden;
}
.white_content a{
	margin-left:4em;
}
ol {
	margin:0 2em;
	padding:0 2em 1em;
}
li {
	margin : 0 0 .5em;
}

#genericerror {
	background-color: #f0f000 !important;
	border: 4px solid #fcc !important;
}

#genericerrorInner {
	font-size: 110%;
	color: #33000;
}

#warn-not-close, .warn-not-close {
	padding: 0.2em 0.5em;
	text-align: center;
	background: #fcfc00;
	font-size: smaller;
	font-weight: bold;
}

#progressbar, .progressbar {
	display: block;
	width: 80%;
	height: 32px;
	border: 1px solid #ccc;
	margin: 1em 10% 0.2em;
	-moz-border-radius: .25em;
	-webkit-border-radius: .25em;
	border-radius: .25em;
}

#progressbar-inner, .progressbar-inner {
	display: block;
	width: 100%;
	height: 100%;
	background: #4096ee;
	background: -moz-linear-gradient(left, #4096ee 0%, #60abf8 56%, #7abcff 100%);
	background: -webkit-gradient(linear, left top, right top, color-stop(0%,#4096ee), color-stop(56%,#60abf8), color-stop(100%,#7abcff));
	background: -webkit-linear-gradient(left, #4096ee 0%,#60abf8 56%,#7abcff 100%);
	background: -o-linear-gradient(left, #4096ee 0%,#60abf8 56%,#7abcff 100%);
	background: -ms-linear-gradient(left, #4096ee 0%,#60abf8 56%,#7abcff 100%);
	background: linear-gradient(left, #4096ee 0%,#60abf8 56%,#7abcff 100%);
}

#currentFile {
	font-family: Consolas, "Courier New", Courier, monospace;
	font-size: 9pt;
	height: 10pt;
	overflow: hidden;
	text-overflow: ellipsis;
	background: #ccc;
	margin: 0 10% 1em;
	padding:.125em;
}

#extractionComplete {
}

#warningsContainer {
	border-bottom: 2px solid brown;
	border-left: 2px solid brown;
	border-right: 2px solid brown;
	padding: 5px 0;
	background: #ffffcc;
	-webkit-border-bottom-right-radius: 5px;
	-webkit-border-bottom-left-radius: 5px;
	-moz-border-radius-bottomleft: 5px;
	-moz-border-radius-bottomright: 5px;
}

#warningsHeader h2 {
	color: black;
	text-shadow: 2px 2px 5px #999999;
	border-top: 2px solid brown;
	border-left: 2px solid brown;
	border-right: 2px solid brown;
	border-bottom: thin solid brown;
	-webkit-border-top-right-radius: 5px;
	-webkit-border-top-left-radius: 5px;
	-moz-border-radius-topleft: 5px;
	-moz-border-radius-topright: 5px;
	background: yellow;
	font-size: large;
	padding: 2px 5px;
	margin: 0px;
}

#warnings {
	height: 200px;
	overflow-y: scroll;
}

#warnings div {
	background: #eeeeee;
	font-size: small;
	padding: 2px 4px;
	border-bottom: thin solid #333333;
}

#automode {
	display: inline-block;
	padding: 6pt 12pt;
	background-color: #cc0000;
	border: thick solid yellow;
	color: white;
	font-weight: bold;
	font-size: 125%;
	position: absolute;
	float: right;
	top: 1em;
	right: 1em;
}

.helpme,
#warn-not-close {
	background: rgb(255,255,136);
	background: -moz-linear-gradient(top, rgba(255,255,136,1) 0%, rgba(255,255,136,1) 100%);
	background: -webkit-gradient(linear, left top, left bottom, color-stop(0%,rgba(255,255,136,1)), color-stop(100%,rgba(255,255,136,1)));
	background: -webkit-linear-gradient(top, rgba(255,255,136,1) 0%,rgba(255,255,136,1) 100%);
	background: -o-linear-gradient(top, rgba(255,255,136,1) 0%,rgba(255,255,136,1) 100%);
	background: -ms-linear-gradient(top, rgba(255,255,136,1) 0%,rgba(255,255,136,1) 100%);
	background: linear-gradient(top, rgba(255,255,136,1) 0%,rgba(255,255,136,1) 100%);
	padding: 0.75em 0.5em;
	border: solid #febf01;
	border-width: 1px 0;
	text-align: center;
}

#update-notification {
	margin: 1em;
	padding: 0.5em;
	background-color: #FF9;
	color: #F33;
	text-align: center;
	border-radius: 20px;
	border: medium solid red;
	box-shadow: 5px 5px 5px black;
}

.update-notify {
	font-size: 20pt;
	font-weight: bold;
}

.update-links {
	color: #333;
	font-size: 14pt;
}

#update-dlnow {
	text-decoration: none;
	color: #333;
	border: thin solid #333;
	padding: 0.5em;
	border-radius: 5px;
	background-color: #f0f0f0;
	text-shadow: 1px 1px 1px #999;
}

#update-dlnow:hover {
	background-color: #fff;
}

#update-whatsnew {
	font-size: 11pt;
	color: blue;
	text-decoration: underline;
}

.update-whyupdate {
	color: #333;
	font-size: 9pt;
}

/* FTP Browser */
.breadcrumb {background-color: #F5F5F5; border-radius: 4px; list-style: none outside none; margin: 0 0 18px; padding: 8px 15px;}
.breadcrumb > li {display: inline-block; text-shadow: 0 1px 0 #FFFFFF;}
#ak_crumbs span {padding: 1px 3px;}
#ak_crumbs a {cursor: pointer;}
#ftpBrowserFolderList a{cursor:pointer}

/* Bootstrap porting */
.table {margin-bottom: 18px;width: 100%;}
.table th, .table td {border-top: 1px solid #DDDDDD; line-height: 18px; padding: 8px; text-align: left; vertical-align: top;}
.table-striped tbody > tr:nth-child(2n+1) > td, .table-striped tbody > tr:nth-child(2n+1) > th { background-color: #F9F9F9;}

/* Layout helpers
----------------------------------*/
.ui-helper-hidden { display: none; }
.ui-helper-hidden-accessible { border: 0; clip: rect(0 0 0 0); height: 1px; margin: -1px; overflow: hidden; padding: 0; position: absolute; width: 1px; }
.ui-helper-reset { margin: 0; padding: 0; border: 0; outline: 0; line-height: 1.3; text-decoration: none; font-size: 100%; list-style: none; }
.ui-helper-clearfix:before, .ui-helper-clearfix:after { content: ""; display: table; }
.ui-helper-clearfix:after { clear: both; }
.ui-helper-clearfix { zoom: 1; }
.ui-helper-zfix { width: 100%; height: 100%; top: 0; left: 0; position: absolute; opacity: 0; filter:Alpha(Opacity=0); }


/* Interaction Cues
----------------------------------*/
.ui-state-disabled { cursor: default !important; }

/* Icons
----------------------------------*/

/* states and images */
.ui-icon { display: block; text-indent: -99999px; overflow: hidden; background-repeat: no-repeat; }

/* Misc visuals
----------------------------------*/

/* Overlays */
.ui-widget-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
.ui-resizable { position: relative;}
.ui-resizable-handle { position: absolute;font-size: 0.1px; display: block; }
.ui-resizable-disabled .ui-resizable-handle, .ui-resizable-autohide .ui-resizable-handle { display: none; }
.ui-resizable-n { cursor: n-resize; height: 7px; width: 100%; top: -5px; left: 0; }
.ui-resizable-s { cursor: s-resize; height: 7px; width: 100%; bottom: -5px; left: 0; }
.ui-resizable-e { cursor: e-resize; width: 7px; right: -5px; top: 0; height: 100%; }
.ui-resizable-w { cursor: w-resize; width: 7px; left: -5px; top: 0; height: 100%; }
.ui-resizable-se { cursor: se-resize; width: 12px; height: 12px; right: 1px; bottom: 1px; }
.ui-resizable-sw { cursor: sw-resize; width: 9px; height: 9px; left: -5px; bottom: -5px; }
.ui-resizable-nw { cursor: nw-resize; width: 9px; height: 9px; left: -5px; top: -5px; }
.ui-resizable-ne { cursor: ne-resize; width: 9px; height: 9px; right: -5px; top: -5px;}
.ui-button { display: inline-block; position: relative; padding: 0; margin-right: .1em; cursor: pointer; text-align: center; zoom: 1; overflow: visible; } /* the overflow property removes extra width in IE */
.ui-button, .ui-button:link, .ui-button:visited, .ui-button:hover, .ui-button:active { text-decoration: none; }
.ui-button-icon-only { width: 2.2em; } /* to make room for the icon, a width needs to be set here */
button.ui-button-icon-only { width: 2.4em; } /* button elements seem to need a little more width */
.ui-button-icons-only { width: 3.4em; }
button.ui-button-icons-only { width: 3.7em; }

/*button text element */
.ui-button .ui-button-text { display: block; line-height: 1.4;  }
.ui-button-text-only .ui-button-text { padding: 0; }
.ui-button-icon-only .ui-button-text, .ui-button-icons-only .ui-button-text { padding: .4em; text-indent: -9999999px; }
.ui-button-text-icon-primary .ui-button-text, .ui-button-text-icons .ui-button-text { padding: .4em 1em .4em 2.1em; }
.ui-button-text-icon-secondary .ui-button-text, .ui-button-text-icons .ui-button-text { padding: .4em 2.1em .4em 1em; }
.ui-button-text-icons .ui-button-text { padding-left: 2.1em; padding-right: 2.1em; }
/* no icon support for input elements, provide padding by default */
input.ui-button { padding: .4em 1em; }

/*button icon element(s) */
.ui-button-icon-only .ui-icon, .ui-button-text-icon-primary .ui-icon, .ui-button-text-icon-secondary .ui-icon, .ui-button-text-icons .ui-icon, .ui-button-icons-only .ui-icon { position: absolute; top: 50%; margin-top: -8px; }
.ui-button-icon-only .ui-icon { left: 50%; margin-left: -8px; }
.ui-button-text-icon-primary .ui-button-icon-primary, .ui-button-text-icons .ui-button-icon-primary, .ui-button-icons-only .ui-button-icon-primary { left: .5em; }
.ui-button-text-icon-secondary .ui-button-icon-secondary, .ui-button-text-icons .ui-button-icon-secondary, .ui-button-icons-only .ui-button-icon-secondary { right: .5em; }
.ui-button-text-icons .ui-button-icon-secondary, .ui-button-icons-only .ui-button-icon-secondary { right: .5em; }

/*button sets*/
.ui-buttonset { margin-right: 7px; }
.ui-buttonset .ui-button { margin-left: 0; margin-right: -.3em; }

/* workarounds */
button.ui-button::-moz-focus-inner { border: 0; padding: 0; } /* reset extra padding in Firefox */
.ui-dialog { position: absolute; top: 0; left: 0; padding: .2em; width: 300px; overflow: hidden; }
.ui-dialog .ui-dialog-titlebar { padding: .4em 1em; position: relative;  }
.ui-dialog .ui-dialog-title { float: left; margin: .1em 16px .1em 0; }
.ui-dialog .ui-dialog-titlebar-close { position: absolute; right: .3em; top: 50%; width: 19px; margin: -10px 0 0 0; padding: 1px; height: 18px; display:none}
.ui-dialog .ui-dialog-titlebar-close span { display: none; margin: 1px; }
.ui-dialog .ui-dialog-titlebar-close:hover, .ui-dialog .ui-dialog-titlebar-close:focus { padding: 0; }
.ui-dialog .ui-dialog-content { position: relative; border: 0; padding: .5em 1em; background: none; overflow: auto; zoom: 1; }
.ui-dialog .ui-dialog-buttonpane { text-align: left; border-width: 1px 0 0 0; background-image: none; margin: .5em 0 0 0; padding: .3em 1em .5em .4em; }
.ui-dialog .ui-dialog-buttonpane .ui-dialog-buttonset { float: right; }
.ui-dialog .ui-dialog-buttonpane button { margin: .5em .4em .5em 0; cursor: pointer; }
.ui-dialog .ui-resizable-se { width: 14px; height: 14px; right: 3px; bottom: 3px; }
.ui-draggable .ui-dialog-titlebar { cursor: move; }

/* Component containers
----------------------------------*/
.ui-widget-content { border: 1px solid #a6c9e2; background: #fcfdfd; color: #222222; }
.ui-widget-content a { color: #222222; }
.ui-widget-header { border: 1px solid #4297d7; background: #5c9ccc ; color: #ffffff; font-weight: bold; }
.ui-widget-header a { color: #ffffff; }

/* Interaction states
----------------------------------*/
.ui-state-default a, .ui-state-default a:link, .ui-state-default a:visited {text-decoration: none; }
.ui-state-hover, .ui-widget-content .ui-state-hover, .ui-widget-header .ui-state-hover{
    background: #4096ee;
	background: -moz-linear-gradient(top, #4096ee 0%, #60abf8 56%, #7abcff 100%);
	background: -webkit-gradient(linear, left top, left bottom, color-stop(0%,#4096ee), color-stop(56%,#60abf8), color-stop(100%,#7abcff));
	background: -webkit-linear-gradient(top, #4096ee 0%,#60abf8 56%,#7abcff 100%);
	background: -o-linear-gradient(top, #4096ee 0%,#60abf8 56%,#7abcff 100%);
	background: -ms-linear-gradient(top, #4096ee 0%,#60abf8 56%,#7abcff 100%);
	background: linear-gradient(top, #4096ee 0%,#60abf8 56%,#7abcff 100%);
}
.ui-state-hover a, .ui-state-hover a:hover, .ui-state-hover a:link, .ui-state-hover a:visited { color: #1d5987; text-decoration: none; }
.ui-state-active a, .ui-state-active a:link, .ui-state-active a:visited { color: #e17009; text-decoration: none; }

/* Interaction Cues
----------------------------------*/
.ui-state-highlight, .ui-widget-content .ui-state-highlight, .ui-widget-header .ui-state-highlight  {border: 1px solid #fad42e; background: #fbec88 ; color: #363636; }
.ui-state-highlight a, .ui-widget-content .ui-state-highlight a,.ui-widget-header .ui-state-highlight a { color: #363636; }
.ui-state-error, .ui-widget-content .ui-state-error, .ui-widget-header .ui-state-error {border: 1px solid #cd0a0a; background: #fef1ec ; color: #cd0a0a; }
.ui-state-error a, .ui-widget-content .ui-state-error a, .ui-widget-header .ui-state-error a { color: #cd0a0a; }
.ui-state-error-text, .ui-widget-content .ui-state-error-text, .ui-widget-header .ui-state-error-text { color: #cd0a0a; }
.ui-priority-primary, .ui-widget-content .ui-priority-primary, .ui-widget-header .ui-priority-primary { font-weight: bold; }
.ui-priority-secondary, .ui-widget-content .ui-priority-secondary,  .ui-widget-header .ui-priority-secondary { opacity: .7; filter:Alpha(Opacity=70); font-weight: normal; }
.ui-state-disabled, .ui-widget-content .ui-state-disabled, .ui-widget-header .ui-state-disabled { opacity: .35; filter:Alpha(Opacity=35); background-image: none; }
.ui-state-disabled .ui-icon { filter:Alpha(Opacity=35); } /* For IE8 - See #6059 */

/* Icons
----------------------------------*/

/* states and images */
.ui-icon { display:none}

/* Misc visuals
----------------------------------*/

/* Corner radius */
.ui-corner-all, .ui-corner-top, .ui-corner-left, .ui-corner-tl { -moz-border-radius-topleft: 5px; -webkit-border-top-left-radius: 5px; -khtml-border-top-left-radius: 5px; border-top-left-radius: 5px; }
.ui-corner-all, .ui-corner-top, .ui-corner-right, .ui-corner-tr { -moz-border-radius-topright: 5px; -webkit-border-top-right-radius: 5px; -khtml-border-top-right-radius: 5px; border-top-right-radius: 5px; }
.ui-corner-all, .ui-corner-bottom, .ui-corner-left, .ui-corner-bl { -moz-border-radius-bottomleft: 5px; -webkit-border-bottom-left-radius: 5px; -khtml-border-bottom-left-radius: 5px; border-bottom-left-radius: 5px; }
.ui-corner-all, .ui-corner-bottom, .ui-corner-right, .ui-corner-br { -moz-border-radius-bottomright: 5px; -webkit-border-bottom-right-radius: 5px; -khtml-border-bottom-right-radius: 5px; border-bottom-right-radius: 5px; }

/* Overlays */
.ui-widget-overlay { background: #000000 ; opacity: .8;filter:Alpha(Opacity=80); }
.ui-widget-shadow { margin: -8px 0 0 -8px; padding: 8px; background: #000000 ; opacity: .8;filter:Alpha(Opacity=80); -moz-border-radius: 8px; -khtml-border-radius: 8px; -webkit-border-radius: 8px; border-radius: 8px; }


.ui-button {
    font-family: "Helvetica Neue",Helvetica,Arial,sans-serif;
    font-size: 1.4rem;
	display: inline-block;
	padding: .5em 1em;
	margin: 1em .25em;
	color:#fff;
	text-shadow: 0 1px 3px rgba(0, 0, 0, 0.2), 0 1px rgba(0, 0, 0, 0.4);
	-webkit-box-shadow: 0 1px 3px rgba(255, 255, 255, 0.5) inset, -1px 2px 2px rgba(0, 0, 0, 0.2);
    -moz-box-shadow: 0 1px 3px rgba(255, 255, 255, 0.5) inset, -1px 2px 2px rgba(0, 0, 0, 0.2);
    box-shadow: 0 1px 3px rgba(255, 255, 255, 0.5) inset, -1px 2px 2px rgba(0, 0, 0, 0.2);
    text-decoration: none;
	background: #7abcff;
	background: -moz-linear-gradient(top, #7abcff 0%, #60abf8 44%, #4096ee 100%);
	background: -webkit-gradient(linear, left top, left bottom, color-stop(0%,#7abcff), color-stop(44%,#60abf8), color-stop(100%,#4096ee));
	background: -webkit-linear-gradient(top, #7abcff 0%,#60abf8 44%,#4096ee 100%);
	background: -o-linear-gradient(top, #7abcff 0%,#60abf8 44%,#4096ee 100%);
	background: -ms-linear-gradient(top, #7abcff 0%,#60abf8 44%,#4096ee 100%);
	background: linear-gradient(top, #7abcff 0%,#60abf8 44%,#4096ee 100%);
	border: solid #ddd;
	border: 1px solid rgba(0,0,0,.1);
	cursor: pointer;
	-webkit-border-radius: .25em;
	-moz-border-radius: .25em;
	border-radius: .25em;
	-webkit-transition: 0.3s linear all;
	-moz-transition: 0.3s linear all;
	-ms-transition: 0.3s linear all;
	-o-transition: 0.3s linear all;
  	transition: 0.3s linear all;
}

CSS;

	callExtraFeature('onExtraHeadCSS');
}

/**
 * Akeeba Kickstart
 * A JSON-powered archive extraction tool
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / AkeebaBackup.com
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

function echoHeadJavascript()
{
?>
<script type="text/javascript" language="javascript">
	var akeeba_debug = <?php echo defined('KSDEBUG') ? 'true' : 'false' ?>;
	var sftp_path = '<?php echo TranslateWinPath(defined('KSROOTDIR') ? KSROOTDIR : __DIR__); ?>/';
	var isJoomla = true;

	/**
	 * Returns the version of Internet Explorer or a -1
	 * (indicating the use of another browser).
	 *
	 * @return   integer  MSIE version or -1
	 */
	function getInternetExplorerVersion()
	{
		var rv = -1; // Return value assumes failure.
		if (navigator.appName == 'Microsoft Internet Explorer')
		{
			var ua = navigator.userAgent;
			var re  = new RegExp("MSIE ([0-9]{1,}[\.0-9]{0,})");
			if (re.exec(ua) != null)
			{
				rv = parseFloat( RegExp.$1 );
			}
		}
		return rv;
	}

	$(document).ready(function(){
		// Hide 2nd Page
		$('#page2').css('display','none');

		// Translate the GUI
		translateGUI();

		// Hook interaction handlers
		$(document).keyup( closeLightbox );
		$('#kickstart\\.procengine').change( onChangeProcengine );
		$('#kickstart\\.setup\\.sourcepath').change( onArchiveListReload );
		$('#reloadArchives').click ( onArchiveListReload );
		$('#checkFTPTempDir').click( oncheckFTPTempDirClick );
		$('#resetFTPTempDir').click( onresetFTPTempDir );
		$('#browseFTP').click( onbrowseFTP );
		$('#testFTP').click( onTestFTPClick );
		$('#gobutton').click( onStartExtraction );
		$('#runInstaller').click( onRunInstallerClick );
		$('#runCleanup').click( onRunCleanupClick );
		$('#gotoSite').click(function(event){window.open('index.php','finalstepsite'); window.close();});
		$('#gotoAdministrator').click(function(event){window.open('administrator/index.php','finalstepadmin'); window.close();});
		$('#gotoStart').click( onGotoStartClick );

		// Reset the progress bar
		setProgressBar(0);

		// Show warning
		var msieVersion = getInternetExplorerVersion();
		if((msieVersion != -1) && (msieVersion <= 8.99))
		{
			$('#ie7Warning').css('display','block');
		}
		if(!akeeba_debug) {
			$('#preextraction').css('display','block');
			$('#fade').css('display','block');
		}

		// Trigger change, so we avoid problems if the user refreshes the page
		$('#kickstart\\.procengine').change();
	});

	var translation = {
		<?php echoTranslationStrings(); ?>
	}

	var akeeba_ajax_url = '<?php echo defined('KSSELFNAME') ? KSSELFNAME : basename(__FILE__); ?>';
	var akeeba_error_callback = onGenericError;
	var akeeba_restoration_stat_inbytes = 0;
	var akeeba_restoration_stat_outbytes = 0;
	var akeeba_restoration_stat_files = 0;
	var akeeba_restoration_stat_total = 0;
	var akeeba_factory = null;

	var akeeba_ftpbrowser_host = null;
	var akeeba_ftpbrowser_port = 21;
	var akeeba_ftpbrowser_username = null;
	var akeeba_ftpbrowser_password = null;
	var akeeba_ftpbrowser_passive = 1;
	var akeeba_ftpbrowser_ssl = 0;
	var akeeba_ftpbrowser_directory = '';

	var akeeba_sftpbrowser_host = null;
	var akeeba_sftpbrowser_port = 21;
	var akeeba_sftpbrowser_username = null;
	var akeeba_sftpbrowser_password = null;
	var akeeba_sftpbrowser_pubkey = null;
	var akeeba_sftpbrowser_privkey = null;
	var akeeba_sftpbrowser_directory = '';

	function translateGUI()
	{
		$('*').each(function(i,e){
			transKey = $(e).text();
			if(array_key_exists(transKey, translation))
			{
				$(e).text( translation[transKey] );
			}
		});
	}

	function trans(key)
	{
		if(array_key_exists(key, translation)) {
			return translation[key];
		} else {
			return key;
		}
	}

	function array_key_exists ( key, search ) {
		if (!search || (search.constructor !== Array && search.constructor !== Object)){
			return false;
		}
		return key in search;
	}

	function empty (mixed_var) {
		var key;

		if (mixed_var === "" ||
			mixed_var === 0 ||
			mixed_var === "0" ||
			mixed_var === null ||
			mixed_var === false ||
			typeof mixed_var === 'undefined'
		){
			return true;
		}

		if (typeof mixed_var == 'object') {
			for (key in mixed_var) {
				return false;
			}
			return true;
		}

		return false;
	}

	function is_array (mixed_var) {
		var key = '';
		var getFuncName = function (fn) {
			var name = (/\W*function\s+([\w\$]+)\s*\(/).exec(fn);
			if (!name) {
				return '(Anonymous)';
			}
			return name[1];
		};

		if (!mixed_var) {
			return false;
		}

		// BEGIN REDUNDANT
		this.php_js = this.php_js || {};
		this.php_js.ini = this.php_js.ini || {};
		// END REDUNDANT

		if (typeof mixed_var === 'object') {

			if (this.php_js.ini['phpjs.objectsAsArrays'] &&  // Strict checking for being a JavaScript array (only check this way if call ini_set('phpjs.objectsAsArrays', 0) to disallow objects as arrays)
				(
				(this.php_js.ini['phpjs.objectsAsArrays'].local_value.toLowerCase &&
				this.php_js.ini['phpjs.objectsAsArrays'].local_value.toLowerCase() === 'off') ||
				parseInt(this.php_js.ini['phpjs.objectsAsArrays'].local_value, 10) === 0)
			) {
				return mixed_var.hasOwnProperty('length') && // Not non-enumerable because of being on parent class
				!mixed_var.propertyIsEnumerable('length') && // Since is own property, if not enumerable, it must be a built-in function
				getFuncName(mixed_var.constructor) !== 'String'; // exclude String()
			}

			if (mixed_var.hasOwnProperty) {
				for (key in mixed_var) {
					// Checks whether the object has the specified property
					// if not, we figure it's not an object in the sense of a php-associative-array.
					if (false === mixed_var.hasOwnProperty(key)) {
						return false;
					}
				}
			}

			// Read discussion at: http://kevin.vanzonneveld.net/techblog/article/javascript_equivalent_for_phps_is_array/
			return true;
		}

		return false;
	}

	function resolvePath(filename)
	{
		filename = filename.replace('\/\/g', '\/');
		var parts = filename.split('/');
		var out = [];

		$.each(parts, function(i, part){
			if (part == '.') return;
			if (part == '..') {
				out.pop();
				return;
			}
			out.push(part);
		});

		return out.join('/');
	}

	/**
	 * Performs an AJAX request and returns the parsed JSON output.
	 * The global akeeba_ajax_url is used as the AJAX proxy URL.
	 * If there is no errorCallback, the global akeeba_error_callback is used.
	 * @param data An object with the query data, e.g. a serialized form
	 * @param successCallback A function accepting a single object parameter, called on success
	 * @param errorCallback A function accepting a single string parameter, called on failure
	 */
	function doAjax(data, successCallback, errorCallback)
	{
		var structure =
		{
			type: "POST",
			url: akeeba_ajax_url,
			cache: false,
			data: data,
			timeout: 600000,
			success: function(msg) {
				// Initialize
				var junk = null;
				var message = "";

				// Get rid of junk before the data
				var valid_pos = msg.indexOf('###');
				if( valid_pos == -1 ) {
					// Valid data not found in the response
					msg = 'Invalid AJAX data received:<br/>' + msg;
					if(errorCallback == null)
					{
						if(akeeba_error_callback != null)
						{
							akeeba_error_callback(msg);
						}
					}
					else
					{
						errorCallback(msg);
					}
					return;
				} else if( valid_pos != 0 ) {
					// Data is prefixed with junk
					junk = msg.substr(0, valid_pos);
					message = msg.substr(valid_pos);
				}
				else
				{
					message = msg;
				}
				message = message.substr(3); // Remove triple hash in the beginning

				// Get of rid of junk after the data
				var valid_pos = message.lastIndexOf('###');
				message = message.substr(0, valid_pos); // Remove triple hash in the end

				try {
					var data = eval('('+message+')');
				} catch(err) {
					var msg = err.message + "\n<br/>\n<pre>\n" + message + "\n</pre>";
					if(errorCallback == null)
					{
						if(akeeba_error_callback != null)
						{
							akeeba_error_callback(msg);
						}
					}
					else
					{
						errorCallback(msg);
					}
					return;
				}

				// Call the callback function
				successCallback(data);
			},
			error: function(Request, textStatus, errorThrown) {
				var message = '<strong>AJAX Loading Error</strong><br/>HTTP Status: '+Request.status+' ('+Request.statusText+')<br/>';
				message = message + 'Internal status: '+textStatus+'<br/>';
				message = message + 'XHR ReadyState: ' + Response.readyState + '<br/>';
				message = message + 'Raw server response:<br/>'+Request.responseText;
				if(errorCallback == null)
				{
					if(akeeba_error_callback != null)
					{
						akeeba_error_callback(message);
					}
				}
				else
				{
					errorCallback(message);
				}
			}
		};
		$.ajax( structure );
	}

	function onChangeProcengine(event)
	{
		if( $('#kickstart\\.procengine').val() == 'direct' )
		{
			$('#ftp-options').hide('fast');
		} else {
			$('#ftp-options').show('fast');
		}

		if($('#kickstart\\.procengine').val() == 'sftp' )
		{
			$('#ftp-ssl-passive').hide('fast');

			if($('#kickstart\\.ftp\\.dir').val() == ''){
				$('#kickstart\\.ftp\\.dir').val(sftp_path);
			}

			$('#testFTP').html(trans('BTN_TESTSFTPCON'))
		}
		else
		{
			$('#ftp-ssl-passive').show('fast');
			$('#testFTP').html(trans('BTN_TESTFTPCON'))
		}
	}

	function closeLightbox(event)
	{
		var closeMe = false;

		if( (event == null) || (event == undefined) ) {
			closeMe = true;
		} else if(event.keyCode == '27') {
			closeMe = true;
		}

		if(closeMe)
		{
			document.getElementById('preextraction').style.display='none';
			document.getElementById('genericerror').style.display='none';
			document.getElementById('fade').style.display='none';
			$(document).unbind('keyup', closeLightbox);
		}
	}

	function onGenericError(msg)
	{
		$('#genericerrorInner').html(msg);
		$('#genericerror').css('display','block');
		$('#fade').css('display','block');
		$(document).keyup(closeLightbox);
	}

	function setProgressBar(percent)
	{
		var newValue = 0;

		if(percent <= 1) {
			newValue = 100 * percent;
		} else {
			newValue = percent;
		}

		$('#progressbar-inner').css('width',percent+'%');
	}

	function oncheckFTPTempDirClick(event)
	{
		var data = {
			'task' : 'checkTempdir',
			'json': JSON.stringify({
				'kickstart.ftp.tempdir': $('#kickstart\\.ftp\\.tempdir').val()
			})
		};

		doAjax(data, function(ret){
			var key = ret.status ? 'FTP_TEMPDIR_WRITABLE' : 'FTP_TEMPDIR_UNWRITABLE';
			alert( trans(key) );
		});
	}

	function onTestFTPClick(event)
	{
		var type = 'ftp';

		if($('#kickstart\\.procengine').val() == 'sftp')
		{
			type = 'sftp';
		}

		var data = {
			'task' : 'checkFTP',
			'json': JSON.stringify({
				'type' : type,
				'kickstart.ftp.host':		$('#kickstart\\.ftp\\.host').val(),
				'kickstart.ftp.port':		$('#kickstart\\.ftp\\.port').val(),
				'kickstart.ftp.ssl':		$('#kickstart\\.ftp\\.ssl').is(':checked'),
				'kickstart.ftp.passive':	$('#kickstart\\.ftp\\.passive').is(':checked'),
				'kickstart.ftp.user':		$('#kickstart\\.ftp\\.user').val(),
				'kickstart.ftp.pass':		$('#kickstart\\.ftp\\.pass').val(),
				'kickstart.ftp.dir':		$('#kickstart\\.ftp\\.dir').val(),
				'kickstart.ftp.tempdir':	$('#kickstart\\.ftp\\.tempdir').val()
			})
		};
		doAjax(data, function(ret){
			if(type == 'ftp'){
				var key = ret.status ? 'FTP_CONNECTION_OK' : 'FTP_CONNECTION_FAILURE';
			}
			else{
				var key = ret.status ? 'SFTP_CONNECTION_OK' : 'SFTP_CONNECTION_FAILURE';
			}


			alert( trans(key) + "\n\n" + (ret.status ? '' : ret.message) );
		});
	}

	function onbrowseFTP ()
	{
		if($('#kickstart\\.procengine').val() != 'sftp')
		{
			akeeba_ftpbrowser_host      = $('#kickstart\\.ftp\\.host').val();
			akeeba_ftpbrowser_port      = $('#kickstart\\.ftp\\.port').val();
			akeeba_ftpbrowser_username  = $('#kickstart\\.ftp\\.user').val();
			akeeba_ftpbrowser_password  = $('#kickstart\\.ftp\\.pass').val();
			akeeba_ftpbrowser_passive   = $('#kickstart\\.ftp\\.passive').is(':checked');
			akeeba_ftpbrowser_ssl       = $('#kickstart\\.ftp\\.ssl').is(':checked');
			akeeba_ftpbrowser_directory = $('#kickstart\\.ftp\\.dir').val();

			var akeeba_onbrowseFTP_callback = function(path) {
				var charlist = ('/').replace(/([\[\]\(\)\.\?\/\*\{\}\+\$\^\:])/g, '$1');
				var re = new RegExp('^[' + charlist + ']+', 'g');
				path = '/' + (path+'').replace(re, '');
				$('#kickstart\\.ftp\\.dir').val(path);
			};

			akeeba_ftpbrowser_hook( akeeba_onbrowseFTP_callback );
		}
		else
		{
			akeeba_sftpbrowser_host = $('#kickstart\\.ftp\\.host').val();
			akeeba_sftpbrowser_port = $('#kickstart\\.ftp\\.port').val();
			akeeba_sftpbrowser_username = $('#kickstart\\.ftp\\.user').val();
			akeeba_sftpbrowser_password = $('#kickstart\\.ftp\\.pass').val();
			akeeba_sftpbrowser_directory = $('#kickstart\\.ftp\\.dir').val();

			var akeeba_postprocsftp_callback = function(path) {
				var charlist = ('/').replace(/([\[\]\(\)\.\?\/\*\{\}\+\$\^\:])/g, '$1');
				var re = new RegExp('^[' + charlist + ']+', 'g');
				path = '/' + (path+'').replace(re, '');
				$('#kickstart\\.ftp\\.dir').val(path);
			};

			akeeba_sftpbrowser_hook( akeeba_postprocsftp_callback );
		}
	}

	akeeba_ftpbrowser_hook = function( callback )
	{
		var ftp_dialog_element = $("#ftpdialog");
		var ftp_callback = function() {
			callback(akeeba_ftpbrowser_directory);
			ftp_dialog_element.dialog("close");
		};

		ftp_dialog_element.css('display','block');
		ftp_dialog_element.removeClass('ui-state-error');
		ftp_dialog_element.dialog({
			autoOpen	: false,
			title		: trans('CONFIG_UI_FTPBROWSER_TITLE'),
			draggable	: false,
			height		: 500,
			width		: 500,
			modal		: true,
			resizable	: false,
			buttons		: {
				"OK": ftp_callback,
				"Cancel": function() {
					ftp_dialog_element.dialog("close");
				}
			}
		});

		$('#ftpBrowserErrorContainer').css('display','none');
		$('#ftpBrowserFolderList').html('');
		$('#ak_crumbs').html('');

		ftp_dialog_element.dialog('open');

		if(empty(akeeba_ftpbrowser_directory)) akeeba_ftpbrowser_directory = '';

		var data = {
			'task'      : 'ftpbrowse',
			'json': JSON.stringify({
				'host'		: akeeba_ftpbrowser_host,
				'port'		: akeeba_ftpbrowser_port,
				'username'	: akeeba_ftpbrowser_username,
				'password'	: akeeba_ftpbrowser_password,
				'passive'	: (akeeba_ftpbrowser_passive ? 1 : 0),
				'ssl'		: (akeeba_ftpbrowser_ssl ? 1 : 0),
				'directory'	: akeeba_ftpbrowser_directory
			})
		};

		// Do AJAX call and Render results
		doAjax(
			data,
			function(data) {
				if(data.error != false) {
					// An error occured
					$('#ftpBrowserError').html(trans(data.error));
					$('#ftpBrowserErrorContainer').css('display','block');
					$('#ftpBrowserFolderList').css('display','none');
					$('#ak_crumbs').css('display','none');
				} else {
					// Create the interface
					$('#ftpBrowserErrorContainer').css('display','none');

					// Display the crumbs
					if(!empty(data.breadcrumbs)) {
						$('#ak_crumbs').css('display','block');
						$('#ak_crumbs').html('');
						var relativePath = '/';

						akeeba_ftpbrowser_addcrumb(trans('UI-ROOT'), '/', callback);

						$.each(data.breadcrumbs, function(i, crumb) {
							relativePath += '/'+crumb;

							akeeba_ftpbrowser_addcrumb(crumb, relativePath, callback);
						});
					} else {
						$('#ak_crumbs').hide();
					}

					// Display the list of directories
					if(!empty(data.list)) {
						$('#ftpBrowserFolderList').show();

						$.each(data.list, function(i, item) {
							akeeba_ftpbrowser_create_link(akeeba_ftpbrowser_directory+'/'+item, item, $('#ftpBrowserFolderList'), callback );
						});
					} else {
						$('#ftpBrowserFolderList').css('display','none');
					}
				}
			},
			function(message) {
				$('#ftpBrowserError').html(message);
				$('#ftpBrowserErrorContainer').css('display','block');
				$('#ftpBrowserFolderList').css('display','none');
				$('#ak_crumbs').css('display','none');
			}
		);
	};

	/**
	 * Creates a directory link for the FTP browser UI
	 */
	function akeeba_ftpbrowser_create_link(path, label, container, callback)
	{
		var row = $(document.createElement('tr'));
		var cell = $(document.createElement('td')).appendTo(row);

		var myElement = $(document.createElement('a'))
			.text(label)
			.click(function(){
				akeeba_ftpbrowser_directory = resolvePath(path);
				akeeba_ftpbrowser_hook(callback);
			})
			.appendTo(cell);
		row.appendTo($(container));
	}

	/**
	 * Adds a breadcrumb to the FTP browser
	 */
	function akeeba_ftpbrowser_addcrumb(crumb, relativePath, callback, last)
	{
		if(empty(last)) last = false;
		var li = $(document.createElement('li'));

		$(document.createElement('a'))
			.html(crumb)
			.click(function(e){
				akeeba_ftpbrowser_directory = relativePath;
				akeeba_ftpbrowser_hook(callback);
				e.preventDefault();
			})
			.appendTo(li);

		if(!last) {
			$(document.createElement('span'))
				.text('/')
				.addClass('divider')
				.appendTo(li);
		}

		li.appendTo('#ak_crumbs');
	}

	// FTP browser function
	akeeba_sftpbrowser_hook = function( callback )
	{
		var sftp_dialog_element = $("#ftpdialog");
		var sftp_callback = function() {
			callback(akeeba_sftpbrowser_directory);
			sftp_dialog_element.dialog("close");
		};

		sftp_dialog_element.css('display','block');
		sftp_dialog_element.removeClass('ui-state-error');
		sftp_dialog_element.dialog({
			autoOpen	: false,
			'title'		: trans('CONFIG_UI_SFTPBROWSER_TITLE'),
			draggable	: false,
			height		: 500,
			width		: 500,
			modal		: true,
			resizable	: false,
			buttons		: {
				"OK": sftp_callback,
				"Cancel": function() {
					sftp_dialog_element.dialog("close");
				}
			}
		});

		$('#ftpBrowserErrorContainer').css('display','none');
		$('#ftpBrowserFolderList').html('');
		$('#ak_crumbs').html('');

		sftp_dialog_element.dialog('open');

		if(empty(akeeba_sftpbrowser_directory)) akeeba_sftpbrowser_directory = '';

		var data = {
			'task'      : 'sftpbrowse',
			'json': JSON.stringify({
				'host'		: akeeba_sftpbrowser_host,
				'port'		: akeeba_sftpbrowser_port,
				'username'	: akeeba_sftpbrowser_username,
				'password'	: akeeba_sftpbrowser_password,
				'directory'	: akeeba_sftpbrowser_directory
			})
		};

		doAjax(
			data,
			function(data) {
				if(data.error != false) {
					// An error occured
					$('#ftpBrowserError').html(data.error);
					$('#ftpBrowserErrorContainer').css('display','block');
					$('#ftpBrowserFolderList').css('display','none');
					$('#ak_crumbs').css('display','none');
				} else {
					// Create the interface
					$('#ftpBrowserErrorContainer').css('display','none');

					// Display the crumbs
					if(!empty(data.breadcrumbs)) {
						$('#ak_crumbs').css('display','block');
						$('#ak_crumbs').html('');
						var relativePath = '/';

						akeeba_sftpbrowser_addcrumb(trans('UI-ROOT'), '/', callback);

						$.each(data.breadcrumbs, function(i, crumb) {
							relativePath += '/'+crumb;

							akeeba_sftpbrowser_addcrumb(crumb, relativePath, callback);
						});
					} else {
						$('#ftpBrowserCrumbs').css('display','none');
					}

					// Display the list of directories
					if(!empty(data.list)) {
						$('#ftpBrowserFolderList').css('display','block');

						$.each(data.list, function(i, item) {
							akeeba_sftpbrowser_create_link(akeeba_sftpbrowser_directory+'/'+item, item, $('#ftpBrowserFolderList'), callback );
						});
					} else {
						$('#ftpBrowserFolderList').css('display','none');
					}
				}
			},
			function(message) {
				$('#ftpBrowserError').html(message);
				$('#ftpBrowserErrorContainer').css('display','block');
				$('#ftpBrowserFolderList').css('display','none');
				$('#ftpBrowserCrumbs').css('display','none');
			}
		);
	};

	/**
	 * Creates a directory link for the SFTP browser UI
	 */
	function akeeba_sftpbrowser_create_link(path, label, container, callback)
	{
		var row = $(document.createElement('tr'));
		var cell = $(document.createElement('td')).appendTo(row);

		var myElement = $(document.createElement('a'))
			.text(label)
			.click(function(){
				akeeba_sftpbrowser_directory = resolvePath(path);
				akeeba_sftpbrowser_hook(callback);
			})
			.appendTo(cell);
		row.appendTo($(container));
	}

	/**
	 * Adds a breadcrumb to the SFTP browser
	 */
	function akeeba_sftpbrowser_addcrumb(crumb, relativePath, callback, last)
	{
		if(empty(last)) last = false;
		var li = $(document.createElement('li'));

		$(document.createElement('a'))
			.html(crumb)
			.click(function(e){
				akeeba_sftpbrowser_directory = relativePath;
				akeeba_sftpbrowser_hook(callback);
				e.preventDefault();
			})
			.appendTo(li);

		if(!last) {
			$(document.createElement('span'))
				.text('/')
				.addClass('divider')
				.appendTo(li);
		}

		li.appendTo('#ak_crumbs');
	}

	function onStartExtraction()
	{
		$('#page1').hide('fast');
		$('#page2').show('fast');

		$('#currentFile').text( '' );

		akeeba_error_callback = errorHandler;

		var data = {
			'task' : 'startExtracting',
			'json': JSON.stringify({
				'kickstart.setup.sourcefile':		$('#kickstart\\.setup\\.sourcefile').val(),
				'kickstart.jps.password':			$('#kickstart\\.jps\\.password').val(),
				'kickstart.tuning.min_exec_time':	$('#kickstart\\.tuning\\.min_exec_time').val(),
				'kickstart.tuning.max_exec_time':	$('#kickstart\\.tuning\\.max_exec_time').val(),
				'kickstart.stealth.enable': 		$('#kickstart\\.stealth\\.enable').is(':checked'),
				'kickstart.stealth.url': 			$('#kickstart\\.stealth\\.url').val(),
				'kickstart.tuning.run_time_bias':	75,
				'kickstart.setup.restoreperms':		0,
				'kickstart.setup.dryrun':			0,
				'kickstart.setup.ignoreerrors':		$('#kickstart\\.setup\\.ignoreerrors').is(':checked'),
				'kickstart.enabled':				1,
				'kickstart.security.password':		'',
				'kickstart.procengine':				$('#kickstart\\.procengine').val(),
				'kickstart.ftp.host':				$('#kickstart\\.ftp\\.host').val(),
				'kickstart.ftp.port':				$('#kickstart\\.ftp\\.port').val(),
				'kickstart.ftp.ssl':				$('#kickstart\\.ftp\\.ssl').is(':checked'),
				'kickstart.ftp.passive':			$('#kickstart\\.ftp\\.passive').is(':checked'),
				'kickstart.ftp.user':				$('#kickstart\\.ftp\\.user').val(),
				'kickstart.ftp.pass':				$('#kickstart\\.ftp\\.pass').val(),
				'kickstart.ftp.dir':				$('#kickstart\\.ftp\\.dir').val(),
				'kickstart.ftp.tempdir':			$('#kickstart\\.ftp\\.tempdir').val()
			})
		};
		doAjax(data, function(ret){
			processRestorationStep(ret);
		});
	}

	function processRestorationStep(data)
	{
		// Look for errors
		if(!data.status)
		{
			errorHandler(data.message);
			return;
		}

		// Propagate warnings to the GUI
		if( !empty(data.Warnings) )
		{
			$.each(data.Warnings, function(i, item){
				$('#warnings').append(
					$(document.createElement('div'))
						.html(item)
				);
				$('#warningsBox').show('fast');
			});
		}

		// Parse total size, if exists
		if(array_key_exists('totalsize', data))
		{
			if(is_array(data.filelist))
			{
				akeeba_restoration_stat_total = 0;
				$.each(data.filelist,function(i, item)
				{
					akeeba_restoration_stat_total += item[1];
				});
			}
			akeeba_restoration_stat_outbytes = 0;
			akeeba_restoration_stat_inbytes = 0;
			akeeba_restoration_stat_files = 0;
		}

		// Update GUI
		akeeba_restoration_stat_inbytes += data.bytesIn;
		akeeba_restoration_stat_outbytes += data.bytesOut;
		akeeba_restoration_stat_files += data.files;
		var percentage = 0;
		if( akeeba_restoration_stat_total > 0 )
		{
			percentage = 100 * akeeba_restoration_stat_inbytes / akeeba_restoration_stat_total;
			if(percentage < 0) {
				percentage = 0;
			} else if(percentage > 100) {
				percentage = 100;
			}
		}
		if(data.done) percentage = 100;
		setProgressBar(percentage);
		$('#currentFile').text( data.lastfile );

		if(!empty(data.factory)) akeeba_factory = data.factory;

		post = {
			'task'	: 'continueExtracting',
			'json'	: JSON.stringify({factory: akeeba_factory})
		};

		if(!data.done)
		{
			doAjax(post, function(ret){
				processRestorationStep(ret);
			});
		}
		else
		{
			$('#page2a').hide('fast');
			$('#extractionComplete').show('fast');

			$('#runInstaller').css('display','inline-block');
		}
	}

	function onGotoStartClick(event)
	{
		$('#page2').hide('fast');
		$('#error').hide('fast');
		$('#page1').show('fast');
	}

	function onRunInstallerClick(event)
	{
		var windowReference = window.open('installer.php','installer');
		if(!windowReference.opener) {
			windowReference.opener = this.window;
		}
		$('#runCleanup').css('display','inline-block');
		$('#runInstaller').hide('fast');
	}

	function onRunCleanupClick(event)
	{
		post = {
			'task'	: 'isJoomla',
			// Passing the factory preserves the renamed files array
			'json'	: JSON.stringify({factory: akeeba_factory})
		};

		doAjax(post, function(ret){
			isJoomla = ret;
			onRealRunCleanupClick();
		});
	}

	function onRealRunCleanupClick()
	{
		post = {
			'task'	: 'cleanUp',
			// Passing the factory preserves the renamed files array
			'json'	: JSON.stringify({factory: akeeba_factory})
		};

		doAjax(post, function(ret){
			$('#runCleanup').hide('fast');
			$('#gotoSite').css('display','inline-block');
			if (isJoomla)
			{
				$('#gotoAdministrator').css('display','inline-block');
			}
			else
			{
				$('#gotoAdministrator').css('display','none');
			}
			$('#gotoPostRestorationRroubleshooting').css('display','block');
		});

	}

	function errorHandler(msg)
	{
		$('#errorMessage').html(msg);
		$('#error').show('fast');
	}

	function onresetFTPTempDir(event)
	{
		$('#kickstart\\.ftp\\.tempdir').val('<?php echo addcslashes(AKKickstartUtils::getPath(),'\\\'"') ?>');
	}

	function onArchiveListReload()
	{
		post = {
			'task'	: 'listArchives',
			'json'	: JSON.stringify({path: $('#kickstart\\.setup\\.sourcepath').val()})
		}

		doAjax(post, function(ret){
			$('#sourcefileContainer').html(ret);
		});
	}

	/**
	 * Akeeba Kickstart Update Check
	 */

	var akeeba_update = {version: '0'};
	var akeeba_version = '4.0.0';

	function version_compare (v1, v2, operator) {
		// BEGIN REDUNDANT
		this.php_js = this.php_js || {};
		this.php_js.ENV = this.php_js.ENV || {};
		// END REDUNDANT
		// Important: compare must be initialized at 0.
		var i = 0,
			x = 0,
			compare = 0,
		// vm maps textual PHP versions to negatives so they're less than 0.
		// PHP currently defines these as CASE-SENSITIVE. It is important to
		// leave these as negatives so that they can come before numerical versions
		// and as if no letters were there to begin with.
		// (1alpha is < 1 and < 1.1 but > 1dev1)
		// If a non-numerical value can't be mapped to this table, it receives
		// -7 as its value.
			vm = {
				'dev': -6,
				'alpha': -5,
				'a': -5,
				'beta': -4,
				'b': -4,
				'RC': -3,
				'rc': -3,
				'#': -2,
				'p': -1,
				'pl': -1
			},
		// This function will be called to prepare each version argument.
		// It replaces every _, -, and + with a dot.
		// It surrounds any nonsequence of numbers/dots with dots.
		// It replaces sequences of dots with a single dot.
		//    version_compare('4..0', '4.0') == 0
		// Important: A string of 0 length needs to be converted into a value
		// even less than an unexisting value in vm (-7), hence [-8].
		// It's also important to not strip spaces because of this.
		//   version_compare('', ' ') == 1
			prepVersion = function (v) {
				v = ('' + v).replace(/[_\-+]/g, '.');
				v = v.replace(/([^.\d]+)/g, '.$1.').replace(/\.{2,}/g, '.');
				return (!v.length ? [-8] : v.split('.'));
			},
		// This converts a version component to a number.
		// Empty component becomes 0.
		// Non-numerical component becomes a negative number.
		// Numerical component becomes itself as an integer.
			numVersion = function (v) {
				return !v ? 0 : (isNaN(v) ? vm[v] || -7 : parseInt(v, 10));
			};
		v1 = prepVersion(v1);
		v2 = prepVersion(v2);
		x = Math.max(v1.length, v2.length);
		for (i = 0; i < x; i++) {
			if (v1[i] == v2[i]) {
				continue;
			}
			v1[i] = numVersion(v1[i]);
			v2[i] = numVersion(v2[i]);
			if (v1[i] < v2[i]) {
				compare = -1;
				break;
			} else if (v1[i] > v2[i]) {
				compare = 1;
				break;
			}
		}
		if (!operator) {
			return compare;
		}

		// Important: operator is CASE-SENSITIVE.
		// "No operator" seems to be treated as less than
		// Any other values seem to make the function return null.
		switch (operator) {
			case '>':
			case 'gt':
				return (compare > 0);
			case '>=':
			case 'ge':
				return (compare >= 0);
			case '<=':
			case 'le':
				return (compare <= 0);
			case '==':
			case '=':
			case 'eq':
				return (compare === 0);
			case '<>':
			case '!=':
			case 'ne':
				return (compare !== 0);
			case '':
			case '<':
			case 'lt':
				return (compare < 0);
			default:
				return null;
		}
	}

	function checkUpdates()
	{
		var structure =
		{
			type: "GET",
			url: 'http://query.yahooapis.com/v1/public/yql',
			data: {
				<?php if(KICKSTARTPRO): ?>
				q: 'SELECT * FROM xml WHERE url="http://nocdn.akeebabackup.com/updates/kickstart.xml"',
				<?php else: ?>
				q: 'SELECT * FROM xml WHERE url="http://nocdn.akeebabackup.com/updates/kickstartpro.xml"',
				<?php endif; ?>
				format: 'json',
				callback: 'updatesCallback'
			},
			cache: true,
			crossDomain: true,
			jsonp: 'updatesCallback',
			timeout: 15000
		};
		$.ajax( structure );
	}

	function updatesCallback(msg)
	{
		$.each(msg.query.results.updates.update, function(i, el){
			var myUpdate = {
				'version'	: el.version,
				'infourl'	: el.infourl['content'],
				'dlurl'		: el.downloads.downloadurl.content
			}
			if(version_compare(myUpdate.version, akeeba_update.version, 'ge')) {
				akeeba_update = myUpdate;
			}
		});

		if(version_compare(akeeba_update.version, akeeba_version, 'gt')) {
			notifyAboutUpdates();
		}
	}

	function notifyAboutUpdates()
	{
		$('#update-version').text(akeeba_update.version);
		$('#update-dlnow').attr('href', akeeba_update.dlurl);
		$('#update-whatsnew').attr('href', akeeba_update.infourl);
		$('#update-notification').show('slow');
	}

	<?php callExtraFeature('onExtraHeadJavascript'); ?>
</script>
<?php
}

/**
 * Akeeba Kickstart
 * A JSON-powered archive extraction tool
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / AkeebaBackup.com
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

function echoTranslationStrings()
{
	callExtraFeature('onLoadTranslations');
	$translation = AKText::getInstance();
	echo $translation->asJavascript();
}

function echoPage()
{
	$edition = KICKSTARTPRO ? 'Professional' : 'Core';
	$bestArchivePath = AKKickstartUtils::getBestArchivePath();
	$filelist = AKKickstartUtils::getArchivesAsOptions($bestArchivePath);
	?>
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html>
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<title>Akeeba Kickstart <?php echo $edition?> <?php echo VERSION?></title>
	<style type="text/css" media="all" rel="stylesheet">
		<?php echoCSS();?>
	</style>
	<?php if(@file_exists('jquery.min.js')):?>
		<script type="text/javascript" src="jquery.min.js"></script>
	<?php else: ?>
		<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
	<?php endif; ?>
	<?php if(@file_exists('json2.min.js')):?>
		<script type="text/javascript" src="json2.min.js"></script>
	<?php else: ?>
		<script type="text/javascript" src="//yandex.st/json2/2011-10-19/json2.min.js"></script>
	<?php endif; ?>
	<?php if(@file_exists('jquery-ui.min.js')):?>
		<script type="text/javascript" src="jquery-ui.min.js"></script>
	<?php else: ?>
		<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jqueryui/1.9.2/jquery-ui.min.js"></script>
	<?php endif; ?>
	<?php echoHeadJavascript(); ?>
	</head>
	<body>

	<div id="automode" style="display:none;">
		AUTOMODEON
	</div>

	<div id="fade" class="black_overlay"></div>

	<div id="page-container">

	<div id="preextraction" class="white_content">
		<div id="ie7Warning" style="display:none;">
			<h2>Deprecated Internet Explorer version</h2>
			<p>
				This script is not guaranteed to work properly on Internet Explorer 8
				or previous version, or on Internet Explorer 9 and higher running
				in compatibility mode.
			</p>
			<p>
				Please use Internet Explorer 9 or later in native mode (the
				&quot;broken page&quot; icon next to the address bar should not be
				enabled). Alternatively, you may use the latest versions of Firefox,
				Safari, Google Chrome or Opera.
			</p>
		</div>

		<h2>THINGS_HEADER</h2>
		<ol>
			<li>THINGS_01</li>
			<li>THINGS_02</li>
			<li>THINGS_03</li>
			<li>THINGS_04</li>
			<li>THINGS_05</li>
			<li>THINGS_06</li>
			<li>THINGS_07</li>
			<li>THINGS_08</li>
			<li>THINGS_09</li>
		</ol>
		<a href="javascript:void(0)" onclick="closeLightbox();">CLOSE_LIGHTBOX</a>
	</div>

	<div id="genericerror" class="white_content">
		<pre id="genericerrorInner"></pre>
	</div>

	<div id="header">
		<div class="title">Akeeba Kickstart <?php echo $edition?> 4.0.0</div>
	</div>

	<div id="update-notification" style="display: none">
		<p class="update-notify">UPDATE_HEADER</p>
		<p class="update-whyupdate">UPDATE_NOTICE</p>
		<p class="update-links">
			<a href="#" id="update-dlnow">UPDATE_DLNOW</a>
			<a href="#" id="update-whatsnew" target="_blank">UPDATE_MOREINFO</a>
		</p>
	</div>

	<div id="page1">
		<?php callExtraFeature('onPage1'); ?>

		<div id="page1-content">

			<div class="helpme">
				<span>NEEDSOMEHELPKS</span> <a href="https://www.akeebabackup.com/documentation/quick-start-guide/using-kickstart.html" target="_blank">QUICKSTART</a>
			</div>

			<div class="step1">
				<div class="circle">1</div>
				<h2>SELECT_ARCHIVE</h2>
				<div class="area-container">
					<?php callExtraFeature('onPage1Step1'); ?>
					<div class="clr"></div>

					<label for="kickstart.setup.sourcepath">ARCHIVE_DIRECTORY</label>
			<span class="field">
				<input type="text" id="kickstart.setup.sourcepath" value="<?php echo htmlentities($bestArchivePath); ?>" />
				<span class="button" id="reloadArchives" style="margin-top:0;margin-bottom:0">RELOAD_ARCHIVES</span>
			</span>
					<br/>

					<label for="kickstart.setup.sourcefile">ARCHIVE_FILE</label>
			<span class="field" id="sourcefileContainer">
				<?php if(!empty($filelist)):?>
					<select id="kickstart.setup.sourcefile">
						<?php echo $filelist; ?>
					</select>
				<?php else:?>
					<a href="https://www.akeebabackup.com/documentation/troubleshooter/ksnoarchives.html" target="_blank">NOARCHIVESCLICKHERE</a>
				<?php endif;?>
			</span>
					<br />
					<label for="kickstart.jps.password">JPS_PASSWORD</label>
					<span class="field"><input type="password" id="kickstart.jps.password" value="" /></span>
				</div>
			</div>

			<div class="clr"></div>

			<div class="step2">
				<div class="circle">2</div>
				<h2>SELECT_EXTRACTION</h2>
				<div class="area-container">
					<label for="kickstart.procengine">WRITE_TO_FILES</label>
			<span class="field">
				<select id="kickstart.procengine">
					<option value="hybrid">WRITE_HYBRID</option>
					<option value="direct">WRITE_DIRECTLY</option>
					<option value="ftp">WRITE_FTP</option>
					<option value="sftp">WRITE_SFTP</option>
				</select>
			</span><br/>

					<label for="kickstart.setup.ignoreerrors">IGNORE_MOST_ERRORS</label>
					<span class="field"><input type="checkbox" id="kickstart.setup.ignoreerrors" /></span>

					<div id="ftp-options">
						<label for="kickstart.ftp.host">FTP_HOST</label>
						<span class="field"><input type="text" id="kickstart.ftp.host" value="localhost" /></span><br />
						<label for="kickstart.ftp.port">FTP_PORT</label>
						<span class="field"><input type="text" id="kickstart.ftp.port" value="21" /></span><br />
						<div id="ftp-ssl-passive">
							<label for="kickstart.ftp.ssl">FTP_FTPS</label>
							<span class="field"><input type="checkbox" id="kickstart.ftp.ssl" /></span><br />
							<label for="kickstart.ftp.passive">FTP_PASSIVE</label>
							<span class="field"><input type="checkbox" id="kickstart.ftp.passive" checked="checked" /></span><br />
						</div>
						<label for="kickstart.ftp.user">FTP_USER</label>
						<span class="field"><input type="text" id="kickstart.ftp.user" value="" /></span><br />
						<label for="kickstart.ftp.pass">FTP_PASS</label>
						<span	class="field"><input type="password" id="kickstart.ftp.pass" value="" /></span><br />
						<label for="kickstart.ftp.dir">FTP_DIR</label>
				<span class="field">
                    <input type="text" id="kickstart.ftp.dir" value="" />
                    <span class="button" id="browseFTP" style="margin-top:0;margin-bottom:0">FTP_BROWSE</span>
                </span><br />

						<label for="kickstart.ftp.tempdir">FTP_TEMPDIR</label>
				<span class="field">
					<input type="text" id="kickstart.ftp.tempdir" value="<?php echo htmlentities(AKKickstartUtils::getPath()) ?>" />
					<span class="button" id="checkFTPTempDir">BTN_CHECK</span>
					<span class="button" id="resetFTPTempDir">BTN_RESET</span>
				</span><br />
						<label></label>
						<span class="button" id="testFTP">BTN_TESTFTPCON</span>
						<a id="notWorking" class="button" href="https://www.akeebabackup.com/documentation/troubleshooter/kscantextract.html" target="_blank">CANTGETITTOWORK</a>
						<br />
					</div>

				</div>
			</div>

			<div class="clr"></div>

			<div class="step3">
				<div class="circle">3</div>
				<h2>FINE_TUNE</h2>
				<div class="area-container">
					<label for="kickstart.tuning.min_exec_time">MIN_EXEC_TIME</label>
					<span class="field"><input type="text" id="kickstart.tuning.min_exec_time" value="1" /></span> <span>SECONDS_PER_STEP</span><br />
					<label for="kickstart.tuning.max_exec_time">MAX_EXEC_TIME</label>
					<span class="field"><input type="text" id="kickstart.tuning.max_exec_time" value="5" /></span> <span>SECONDS_PER_STEP</span><br />

					<label for="kickstart.stealth.enable">STEALTH_MODE</label>
					<span class="field"><input type="checkbox" id="kickstart.stealth.enable" /></span><br />
					<label for="kickstart.stealth.url">STEALTH_URL</label>
					<span class="field"><input type="text" id="kickstart.stealth.url" value="" /></span><br />
				</div>
			</div>

			<div class="clr"></div>

			<div class="step4">
				<div class="circle">4</div>
				<h2>EXTRACT_FILES</h2>
				<div class="area-container">
					<span></span>
					<span id="gobutton" class="button">BTN_START</span>
				</div>
			</div>

			<div class="clr"></div>

		</div>

		<div id="ftpdialog" style="display:none;">
			<p class="instructions alert alert-info">FTPBROWSER_LBL_INSTRUCTIONS</p>
			<div class="error alert alert-error" id="ftpBrowserErrorContainer">
				<h3>FTPBROWSER_LBL_ERROR</h3>
				<p id="ftpBrowserError"></p>
			</div>
			<ul id="ak_crumbs" class="breadcrumb"></ul>
			<div class="row-fluid">
				<div class="span12">
					<table id="ftpBrowserFolderList" class="table table-striped">
					</table>
				</div>
			</div>
		</div>
	</div>

	<div id="page2">
		<div id="page2a">
			<div class="circle">5</div>
			<h2>EXTRACTING</h2>
			<div class="area-container">
				<div id="warn-not-close">DO_NOT_CLOSE_EXTRACT</div>
				<div id="progressbar">
					<div id="progressbar-inner">&nbsp;</div>
				</div>
				<div id="currentFile"></div>
			</div>
		</div>

		<div id="extractionComplete" style="display: none">
			<div class="circle">6</div>
			<h2>RESTACLEANUP</h2>
			<div id="runInstaller" class="button">BTN_RUNINSTALLER</div>
			<div id="runCleanup" class="button" style="display:none">BTN_CLEANUP</div>
			<div id="gotoSite" class="button" style="display:none">BTN_SITEFE</div>
			<div id="gotoAdministrator" class="button" style="display:none">BTN_SITEBE</div>
			<div id="gotoPostRestorationRroubleshooting" style="display:none">
				<a href="https://www.akeebabackup.com/documentation/troubleshooter/post-restoration.html" target="_blank">POSTRESTORATIONTROUBLESHOOTING</a>
			</div>
		</div>

		<div id="warningsBox" style="display: none;">
			<div id="warningsHeader">
				<h2>WARNINGS</h2>
			</div>
			<div id="warningsContainer">
				<div id="warnings"></div>
			</div>
		</div>

		<div id="error" style="display: none;">
			<h3>ERROR_OCCURED</h3>
			<p id="errorMessage"></p>
			<div id="gotoStart" class="button">BTN_GOTOSTART</div>
			<div>
				<a href="https://www.akeebabackup.com/documentation/troubleshooter/kscantextract.html" target="_blank">CANTGETITTOWORK</a>
			</div>
		</div>
	</div>

	<div id="footer">
		<div class="copyright">Copyright &copy; 2008&ndash;2013 <a	href="http://www.akeebabackup.com">Nicholas K.
				Dionysopoulos / Akeeba Backup</a>. All legal rights reserved.<br />

			This program is free software: you can redistribute it and/or modify it under the terms of
			the <a href="http://www.gnu.org/gpl-3.htmlhttp://www.gnu.org/copyleft/gpl.html">GNU General
				Public License</a> as published by the Free Software Foundation, either version 3 of the License,
			or (at your option) any later version.<br />
			Design credits: <a href="http://internet-inspired.com/">Internet Inspired</a>, slightly modified by AkeebaBackup.com
		</div>
	</div>

	</div>

	</body>
	</html>
<?php
}

/**
 * Akeeba Kickstart
 * A JSON-powered archive extraction tool
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / AkeebaBackup.com
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

// Register additional feature classes
callExtraFeature();

$retArray = array(
	'status'	=> true,
	'message'	=> null
);

$task = getQueryParam('task', 'display');
$json = getQueryParam('json');
$ajax = true;

switch($task)
{
	case 'checkTempdir':
		$retArray['status'] = false;
		if(!empty($json))
		{
			$data = json_decode($json, true);
			$dir = @$data['kickstart.ftp.tempdir'];
			if(!empty($dir))
			{
				$retArray['status'] = is_writable($dir);
			}
		}
		break;

	case 'checkFTP':
		$retArray['status'] = false;
		if(!empty($json))
		{
			$data = json_decode($json, true);
			foreach($data as $key => $value)
			{
				AKFactory::set($key, $value);
			}

            if($data['type'] == 'ftp')
            {
                $ftp = new AKPostprocFTP();
            }
            else
            {
                $ftp = new AKPostprocSFTP();
            }

			$retArray['message'] = $ftp->getError();
			$retArray['status'] = empty($retArray['message']);
		}
		break;

    case 'ftpbrowse':
        if(!empty($json))
        {
            $data = json_decode($json, true);

            $retArray = getListing($data['directory'], $data['host'], $data['port'], $data['username'], $data['password'], $data['passive'], $data['ssl']);
        }
        break;

    case 'sftpbrowse':
        if(!empty($json))
        {
            $data = json_decode($json, true);

            $retArray = getSftpListing($data['directory'], $data['host'], $data['port'], $data['username'], $data['password']);
        }
        break;

	case 'startExtracting':
	case 'continueExtracting':
		// Look for configuration values
		$retArray['status'] = false;
		if(!empty($json))
		{
			if($task == 'startExtracting') AKFactory::nuke();

			$oldJSON = $json;
			$json = json_decode($json, true);
			if(is_null($json)) {
				$json = stripslashes($oldJSON);
				$json = json_decode($json, true);
			}
			if(!empty($json)) foreach($json as $key => $value)
			{
				if( substr($key,0,9) == 'kickstart' ) {
					AKFactory::set($key, $value);
				}
			}

			// A "factory" variable will override all other settings.
			if( array_key_exists('factory', $json) )
			{
				// Get the serialized factory
				$serialized = $json['factory'];
				AKFactory::unserialize($serialized);
				AKFactory::set('kickstart.enabled', true);
			}

			// Make sure that the destination directory is always set (req'd by both FTP and Direct Writes modes)
			$removePath = AKFactory::get('kickstart.setup.destdir','');
			if(empty($removePath)) AKFactory::set('kickstart.setup.destdir', AKKickstartUtils::getPath());

			if($task=='startExtracting')
			{
				// If the Stealth Mode is enabled, create the .htaccess file
				if( AKFactory::get('kickstart.stealth.enable', false) )
				{
					createStealthURL();
				}
			}

			$engine = AKFactory::getUnarchiver(); // Get the engine
			$observer = new ExtractionObserver(); // Create a new observer
			$engine->attach($observer); // Attach the observer
			$engine->tick();
			$ret = $engine->getStatusArray();

			if( $ret['Error'] != '' )
			{
				$retArray['status'] = false;
				$retArray['done'] = true;
				$retArray['message'] = $ret['Error'];
			}
			elseif( !$ret['HasRun'] )
			{
				$retArray['files'] = $observer->filesProcessed;
				$retArray['bytesIn'] = $observer->compressedTotal;
				$retArray['bytesOut'] = $observer->uncompressedTotal;
				$retArray['status'] = true;
				$retArray['done'] = true;
			}
			else
			{
				$retArray['files'] = $observer->filesProcessed;
				$retArray['bytesIn'] = $observer->compressedTotal;
				$retArray['bytesOut'] = $observer->uncompressedTotal;
				$retArray['status'] = true;
				$retArray['done'] = false;
				$retArray['factory'] = AKFactory::serialize();
			}

			if(!is_null($observer->totalSize))
			{
				$retArray['totalsize'] = $observer->totalSize;
				$retArray['filelist'] = $observer->fileList;
			}

			$retArray['Warnings'] = $ret['Warnings'];
			$retArray['lastfile'] = $observer->lastFile;
		}
		break;

	case 'cleanUp':
		if(!empty($json))
		{
			$json = json_decode($json, true);
			if( array_key_exists('factory', $json) )
			{
				// Get the serialized factory
				$serialized = $json['factory'];
				AKFactory::unserialize($serialized);
				AKFactory::set('kickstart.enabled', true);
			}
		}

		$unarchiver = AKFactory::getUnarchiver(); // Get the engine
		$engine = AKFactory::getPostProc();

		// 1. Remove installation
		recursive_remove_directory('installation');

		// 2. Run the renames, backwards
		$renames = $unarchiver->renameFiles;
		if(!empty($renames)) foreach( $renames as $original => $renamed ) {
			$engine->rename( $renamed, $original );
		}

		// 3. Delete the archive
		foreach( $unarchiver->archiveList as $archive )
		{
			$engine->unlink( $archive );
		}

		// 4. Suicide
		$engine->unlink( basename(__FILE__) );

		// 5. Delete translations
		$dh = opendir(AKKickstartUtils::getPath());
		if($dh !== false)
		{
			$basename = basename(__FILE__, '.php');
			while( false !== $file = @readdir($dh) )
			{
				if( strstr($file, $basename.'.ini') )
				{
					$engine->unlink($file);
				}
			}
		}

		// 6. Delete cacert.pem
		$engine->unlink('cacert.pem');

		// 7. Delete jquery.min.js and json2.min.js
		$engine->unlink('jquery.min.js');
		$engine->unlink('json2.min.js');

		break;

	case 'display':
		$ajax = false;
		echoPage();
		break;

	case 'isJoomla':
		$ajax = true;
		if(!empty($json))
		{
			$json = json_decode($json, true);
			if( array_key_exists('factory', $json) )
			{
				// Get the serialized factory
				$serialized = $json['factory'];
				AKFactory::unserialize($serialized);
				AKFactory::set('kickstart.enabled', true);
			}
		}
		$path = AKFactory::get('kickstart.setup.destdir','');
		$path = rtrim($path, '/\\');
		$isJoomla = @is_dir($path . '/administrator');
		if ($isJoomla)
		{
			$isJoomla = @is_dir($path . '/libraries/joomla');
		}
		$retArray = $isJoomla;

		break;

	case 'listArchives':
		$ajax = true;

		$path = null;

		if(!empty($json))
		{
			$json = json_decode($json, true);

			if( array_key_exists('path', $json) )
			{
				$path = $json['path'];
			}
		}

		if (empty($path) || !@is_dir($path))
		{
			$filelist = null;
		}
		else
		{
			$filelist = AKKickstartUtils::getArchivesAsOptions($path);
		}

		if (empty($filelist))
		{
			$retArray = '<a href="https://www.akeebabackup.com/documentation/troubleshooter/ksnoarchives.html" target="_blank">' .
				AKText::_('NOARCHIVESCLICKHERE')
				. '</a>';
		}
		else
		{
			$retArray = '<select id="kickstart.setup.sourcefile">' . $filelist . '</select>';
		}

		break;

	default:
		$ajax = true;
		if(!empty($json)) {
			$params = json_decode($json, true);
		} else {
			$params = array();
		}
		$retArray = callExtraFeature($task, $params);
		break;
}

if($ajax)
{
	// JSON encode the message
	$json = json_encode($retArray);
	// Do I have to encrypt?
	$password = AKFactory::get('kickstart.security.password', null);
	if(!empty($password))
	{
		$json = AKEncryptionAES::AESEncryptCtr($json, $password, 128);
	}

	// Return the message
	echo "###$json###";
}

/**
 * Akeeba Kickstart
 * A JSON-powered archive extraction tool
 *
 * @copyright   2010-2014 Nicholas K. Dionysopoulos / AkeebaBackup.com
 * @license     GNU GPL v2 or - at your option - any later version
 * @package     akeebabackup
 * @subpackage  kickstart
 */

/**
 * Akeeba Kickstart Import from URL add-on feature
 */
class AKFeatureURLImport
{
	private $params = array();

	private static $downloadPageURL = 'http://www.joomla.org/download.html';

	private function getParam($key, $default = null)
	{
		if(array_key_exists($key, $this->params)) {
			return $this->params[$key];
		} else {
			return $default;
		}
	}

	/**
	 * Echoes extra CSS to the head of the page
	 */
	public function onExtraHeadCSS()
	{

	}

	/**
	 * Echoes extra Javascript to the head of the page
	 */
	public function onExtraHeadJavascript()
	{
?>

var akeeba_url_filename = null;

$(document).ready(function(){
	$('#ak-url-showgui').click(function(e){
		$('#ak-url-gui').show('fast');
		$('#ak-url-progress').hide('fast');
		$('#ak-url-complete').hide('fast');
		$('#ak-url-error').hide('fast');
		$('#page1-content').hide('fast');
	});
	$('#ak-url-hidegui').click(function(e){
		$('#ak-url-gui').hide('fast');
		$('#ak-url-progress').hide('fast');
		$('#ak-url-complete').hide('fast');
		$('#ak-url-error').hide('fast');
		$('#page1-content').show('fast');
	});
	$('#ak-url-reload').click(function(e){
		window.location.reload();
	});
	$('#ak-url-gotoStart').click(function(e){
		$('#ak-url-gui').show('fast');
		$('#ak-url-progress').hide('fast');
		$('#ak-url-complete').hide('fast');
		$('#ak-url-error').hide('fast');
	});
});

function onAKURLImport()
{
	akeeba_url_filename = $('#url\\.filename').val();
	ak_urlimport_start();
}

function AKURLsetProgressBar(percent)
{
	var newValue = 0;

	if(percent <= 1) {
		newValue = 100 * percent;
	} else {
		newValue = percent;
	}

	$('#ak-url-progressbar-inner').css('width',percent+'%');
}

function ak_urlimport_start()
{
	akeeba_error_callback = AKURLerrorHandler;

	$('#ak-url-gui').hide('fast');
	$('#ak-url-progress').show('fast');
	$('#ak-url-complete').hide('fast');
	$('#ak-url-error').hide('fast');

	AKURLsetProgressBar(0);
	$('#ak-url-progresstext').html('');

	var data = {
		'task' : 'urlimport',
		'json' : JSON.stringify({
			'file'		: akeeba_url_filename,
			'frag'		: "-1",
			'totalSize'	: "-1"
		})
	};
	doAjax(data, function(ret){
		ak_urlimport_step(ret);
	});
}

function ak_urlimport_step(data)
{
	// Look for errors
	if(!data.status)
	{
		AKURLerrorHandler(data.error);
		return;
	}

	var totalSize = 0;
	var doneSize = 0;
	var percent = 0;
	var frag = -1;

	// get running stats
	if(array_key_exists('totalSize', data)) {
		totalSize = data.totalSize;
	}
	if(array_key_exists('doneSize', data)) {
		doneSize = data.doneSize;
	}
	if(array_key_exists('percent', data)) {
		percent = data.percent;
	}
	if(array_key_exists('frag', data)) {
		frag = data.frag;
	}

	// Update GUI
	AKURLsetProgressBar(percent);
	//$('#ak-url-progresstext').text( percent+'% ('+doneSize+' / '+totalSize+' bytes)' );
	$('#ak-url-progresstext').text( percent+'% ('+doneSize+' bytes)' );

	post = {
		'task'	: 'urlimport',
		'json'	: JSON.stringify({
			'file'		: akeeba_url_filename,
			'frag'		: frag,
			'totalSize'	: totalSize,
			'doneSize'  : doneSize
		})
	};

	if(percent < 100) {
		// More work to do
		doAjax(post, function(ret){
			ak_urlimport_step(ret);
		});
	} else {
		// Done!
		$('#ak-url-gui').hide('fast');
		$('#ak-url-progress').hide('fast');
		$('#ak-url-complete').show('fast');
		$('#ak-url-error').hide('fast');
	}
}

function onAKURLJoomla()
{
	akeeba_error_callback = AKURLerrorHandler;

	var data = {
		'task' : 'getjurl'
	};

	doAjax(data, function(ret)
	{
		ak_urlimport_gotjurl(ret);
	});
}

function onAKURLWordpress()
{
	$('#url\\.filename').val('http://wordpress.org/latest.zip');
}

function ak_urlimport_gotjurl(data)
{
	var url = '';

	if(array_key_exists('url', data)) {
		url = data.url;
	}

	$('#url\\.filename').val(url);
}

function AKURLerrorHandler(msg)
{
	$('#ak-url-gui').hide('fast');
	$('#ak-url-progress').hide('fast');
	$('#ak-url-complete').hide('fast');
	$('#ak-url-error').show('fast');

	$('#ak-url-errorMessage').html(msg);
}
<?php
	}

	/**
	 * Echoes extra HTML on page 1 of Kickstart
	 */
	public function onPage1()
	{

?>
<div id="ak-url-gui" style="display: none">
	<div class="step1">
		<div class="circle">1</div>
		<h2>AKURL_TITLE_STEP1</h2>
		<div class="area-container">
			<label for="url.filename">AKURL_FILENAME</label>
			<span class="field"><input type="text" style="width: 45%" id="url.filename" value="" /></span>
			<a id="ak-url-joomla" class="button bluebutton loprofile" onclick="onAKURLJoomla()">AKURL_JOOMLA</a>
			<a id="ak-url-wordpress" class="button bluebutton loprofile" onclick="onAKURLWordpress()">AKURL_WORDPRESS</a>

			<div class="clr"></div>
			<a id="ak-url-connect" class="button" onclick="onAKURLImport()">AKURL_IMPORT</a>
			<a id="ak-url-hidegui" class="button bluebutton">AKURL_CANCEL</a>
		</div>
	</div>
	<div class="clr"></div>
</div>

<div id="ak-url-progress" style="display: none">
	<div class="circle">2</div>
	<h2>AKURL_TITLE_STEP2</h2>
	<div class="area-container">
		<div id="ak-url-importing">
			<div class="warn-not-close">AKURL_DO_NOT_CLOSE</div>
			<div id="ak-url-progressbar" class="progressbar">
				<div id="ak-url-progressbar-inner" class="progressbar-inner">&nbsp;</div>
			</div>
			<div id="ak-url-progresstext"></div>
		</div>
	</div>
</div>

<div id="ak-url-complete" style="display: none">
	<div class="circle">3</div>
	<h2>AKURL_TITLE_STEP3</h2>
	<div class="area-container">
		<div id="ak-url-reload" class="button">AKURL_BTN_RELOAD</div>
	</div>
</div>

<div id="ak-url-error" class="error" style="display: none;">
	<h3>ERROR_OCCURED</h3>
	<p id="ak-url-errorMessage" class="errorMessage"></p>
	<div id="ak-url-gotoStart" class="button">BTN_GOTOSTART</div>
</div>
<?php
	}

	/**
	 * Outputs HTML to be shown before Step 1's archive selection pane
	 */
	public function onPage1Step1()
	{
?>
	<a id="ak-url-showgui" class="button bluebutton loprofile">AKURL_IMPORT</a>
<?php
	}

	public function urlimport($params)
	{
		$this->params = $params;

		// Fetch data
		$filename		= $this->getParam('file');
		$frag			= $this->getParam('frag', -1);
		$totalSize		= $this->getParam('totalSize', -1);
		$doneSize		= $this->getParam('doneSize', -1);

		debugMsg('Importing from URL');
		debugMsg('  file      : ' . $filename);
		debugMsg('  frag      : ' . $frag);
		debugMsg('  totalSize : ' . $totalSize);
		debugMsg('  doneSize  : ' . $doneSize);

		// Init retArray
		$retArray = array(
			"status"		=> true,
			"error"			=> '',
			"frag"			=> $frag,
			"totalSize"		=> $totalSize,
			"doneSize"		=> $doneSize,
			"percent"		=> 0,
		);

		try {
			AKFactory::set('kickstart.tuning.max_exec_time', '5');
			AKFactory::set('kickstart.tuning.run_time_bias', '75');
			$timer = new AKCoreTimer();
			$start = $timer->getRunningTime(); // Mark the start of this download
			$break = false; // Don't break the step

			while( ($timer->getTimeLeft() > 0) && !$break )
			{
				// Figure out where on Earth to put that file
				$local_file = KSROOTDIR . '/' . basename($filename);

				debugMsg("- Importing from $filename");

				// Do we have to initialize the file?
				if($frag == -1)
				{
					debugMsg("-- First frag, killing local file");
					// Currently downloaded size
					$doneSize = 0;

					// Delete and touch the output file
					@unlink($local_file);
					$fp = @fopen($local_file, 'wb');

					if($fp !== false)
					{
						@fclose($fp);
					}

					// Init
					$frag = 0;
				}

				// Calculate from and length
				$length = 1048576;
				$from = $frag * $length;
				$to = $length + $from - 1;
				//if($from == 0) $from = 1;

				// Try to download the first frag
				$temp_file = $local_file . '.tmp';
				@unlink($temp_file);
				$required_time = 1.0;
				debugMsg("-- Importing frag $frag, byte position from/to: $from / $to");

				$filesize = 0;

				try
				{
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL, $filename);
					curl_setopt($ch, CURLOPT_RANGE, "$from-$to");
					curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
					@curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

					$result = curl_exec($ch);

					$errno = curl_errno($ch);
					$errmsg = curl_error($ch);
					$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

					if ($result === false)
					{
						$error = "cURL error $errno: $errmsg";
					}
					elseif ($http_status > 299)
					{
						$result = false;
						$error = "HTTP status $http_status";
					}
					else
					{
						$result = file_put_contents($temp_file, $result);

						if ($result === false)
						{
							$error = "Could not open temporary file $temp_file for writing";
						}
					}

					curl_close($ch);
				}
				catch (Exception $e)
				{
					$error = $e->getMessage();
				}

				if(!$result) {
					@unlink($temp_file);

					// Failed download
					if ($frag == 0)
					{
						// Failure to download first frag = failure to download. Period.
						$retArray['status'] = false;
						$retArray['error'] = $error;

						debugMsg("-- Download FAILED");

						return $retArray;
					}
					else
					{
						// Since this is a staggered download, consider this normal and finish
						$frag = -1;
						debugMsg("-- Import complete");
						$doneSize = $totalSize;
						$break = true;
						continue;
					}
				}

				// Add the currently downloaded frag to the total size of downloaded files
				if($result)
				{
					clearstatcache();
					$filesize = (int)@filesize($temp_file);
					debugMsg("-- Successful download of $filesize bytes");
					$doneSize += $filesize;

					// Append the file
					$fp = @fopen($local_file,'ab');

					if($fp === false)
					{
						debugMsg("-- Can't open local file for writing");
						// Can't open the file for writing
						@unlink($temp_file);
						$retArray['status'] = false;
						$retArray['error'] = 'Can\'t write to the local file';

						return false;
					}

					$tf = fopen($temp_file,'rb');

					while(!feof($tf))
					{
						$data = fread($tf, 262144);
						fwrite($fp, $data);
					}

					fclose($tf);
					fclose($fp);
					@unlink($temp_file);

					debugMsg("-- Temporary file merged and removed");

					if ($filesize > $length)
					{
						debugMsg("-- Read more data than the requested length. I assume this file is complete.");
						$frag = -1;
					}
					else
					{
						$frag++;

						debugMsg("-- Proceeding to next fragment, frag $frag");
					}
				}

				// Advance the frag pointer and mark the end
				$end = $timer->getRunningTime();

				// Do we predict that we have enough time?
				$required_time = max(1.1 * ($end - $start), $required_time);

				if( $required_time > (10-$end+$start) )
				{
					$break = true;
				}

				$start = $end;
			}

			if ($frag == -1)
			{
				$percent = 100;
			}
			elseif($doneSize <= 0)
			{
				$percent = 0;
			}
			else
			{
				if ($totalSize > 0)
				{
					$percent = 100 * ($doneSize / $totalSize);
				}
				else
				{
					$percent = 0;
				}
			}

			// Update $retArray
			$retArray = array(
				"status"		=> true,
				"error"			=> '',
				"frag"			=> $frag,
				"totalSize"		=> $totalSize,
				"doneSize"		=> $doneSize,
				"percent"		=> $percent,
			);
		}
		catch(Exception $e)
		{
			debugMsg("EXCEPTION RAISED:");
			debugMsg($e->getMessage());
			$retArray['status'] = false;
			$retArray['error'] = $e->getMessage();
		}

		return $retArray;
	}

	public function getjurl($params)
	{
		return array(
			"url"		=> $this->getLatestJoomlaURL(),
		);
	}

	public function onLoadTranslations()
	{
		$translation = AKText::getInstance();
		$translation->addDefaultLanguageStrings(array(
			'AKURL_IMPORT'		=> "Import from URL",
			'AKURL_TITLE_STEP1'	=> "Specify the URL",
			'AKURL_FILENAME'	=> "URL to import",
			'AKURL_JOOMLA'		=> "Latest Joomla! release",
			'AKURL_WORDPRESS'	=> "Latest WordPress release",
			'AKURL_CANCEL'		=> "Cancel import",
			'AKURL_TITLE_STEP2'	=> "Importing...",
			'AKURL_DO_NOT_CLOSE'	=> "Please do not close this window while your backup archives are being imported",
			'AKURL_TITLE_STEP3'	=> "Import is complete",
			'AKURL_BTN_RELOAD'	=> "Reload Kickstart",
		));
	}

	private function getLatestJoomlaURL()
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, self::$downloadPageURL);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		@curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

		$pageHTML = curl_exec($ch);
		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		if (($pageHTML === false) || ($http_status >= 300))
		{
			return '';
		}

		$uniqueHTMLid = '<a id="latest"';

		$posStart = strpos($pageHTML, $uniqueHTMLid);

		if ($posStart === false)
		{
			return '';
		}

		$posStart += strlen($uniqueHTMLid);
		$posEnd = strpos($pageHTML, '>', $posStart);

		if ($posEnd === false)
		{
			return '';
		}

		$innerAttributes = substr($pageHTML, $posStart, $posEnd - $posStart);

		$posStart = strpos($innerAttributes, '"');
		$posEnd = strpos($innerAttributes, '"', $posStart + 1);

		$url = substr($innerAttributes, $posStart + 1, $posEnd - $posStart - 1);

		return $url;
	}
}

