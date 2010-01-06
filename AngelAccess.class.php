<?
/**
* AngelAccess facilitates navigation through the UW-ACE interface
*
* @version 0.1
* @author Jamie Wong <jamie.lf.wong@gmail.com>
* @requires phpcurl
* @requires stty
* @requires head
*/

require_once("utility.php");
define("ERR_USERPASS","Invalid Username or Password");
class AngelAccess {
	private $username;
	private $password;
	private $curlHandle;

	/**
	 * Constructor - Set up the Accessor
	 *
	 * Initialize the curl handler
	 *
	 * @return none
	 */
	public function __construct() {
		$this->username = "";
		$this->password = "";
		$this->curlHandle = curl_init();
		$this->cookiefile = tempnam(sys_get_temp_dir(),"angel");
		$options = array(
			CURLOPT_HEADER          => true,
			CURLOPT_FOLLOWLOCATION  => true,
			CURLOPT_COOKIEFILE      => $this->cookiefile,
			CURLOPT_COOKIEJAR       => $this->cookiefile,
			CURLOPT_RETURNTRANSFER  => true
		);
		curl_setopt_array($this->curlHandle,$options);
	}	
	/**
	* Delete the cookie file, close the curl handler
	*
	*/
	public function __destruct() {
		curl_close($this->curlHandle);
		unlink($this->cookiefile);
	}

	/**
	* Perform an HTTP GET request using the established curl handler
	*
	* @param string $page The page being access
	* @return string The html data returned
	*/
	public function CurlGet($page) {
		$url = 'https://uwangel.uwaterloo.ca'.$page;
		$options = array(
			CURLOPT_URL      => $url,
			CURLOPT_POST     => false
		);
		curl_setopt_array($this->curlHandle,$options);
		return curl_exec($this->curlHandle);
	}

	/**
	* Perform an HTTP POST request using the established curl handler
	*
	* @param string $page The page being access
	* @param string $payload The data to be delivered via POST
	* @return string The html data returned
	*/
	public function CurlPost($page,$payload) {
		$url = 'https://uwangel.uwaterloo.ca'.$page;
		$options = array(
			CURLOPT_URL             => $url,
			CURLOPT_POST            => true,
			CURLOPT_POSTFIELDS      => $payload,
		);
		curl_setopt_array($this->curlHandle,$options);
		return curl_exec($this->curlHandle);
	}		
	
	/**
	* Prompts the user for input in the CLI
	*
	* @param string prompt_text The text for the user to be prompted with
	* @param boolean hide Should the user's input be hidden?
	* @return string The user's input
	*/
	public function Prompt($prompt_text,$hide = false) {
		echo $prompt_text;
		$input = "";
		if ($hide) {
			$input = trim(`stty -echo; head -n1; stty echo`);
			echo "\n";
		} else {
			$input = trim(fgets(STDIN));
		}
		return $input;
	}
	
	/**
	* Encode an associative array in var1=val1&var2=val2 format
	*
	* @param array $assoc_ar The array to be encoded
	* @return string The post-encoded data
	*/
	public function PostEncode($assoc_ar) {
		$ret_string = "";
		foreach($assoc_ar as $key=>$value) {
			if (strlen($ret_string)) {
				$ret_string .= '&';
			}
			$ret_string .= "$key=$value";
		}
		return $ret_string;
	}
	
	/**
	* Does the provided text contain the provided error?
	*
	* @param string $return_text The text to be searched for the error
	* @param string $error_code The text of the error
	* @return boolean Does The text contain the error string?
	*/
	public function HasErr($return_text,$error_code) {
		return (strpos($return_text,$error_code) !== FALSE);
	}
	
	/**
	* Authenticate the user in UWACE
	*
	* $return none
	*/
	public function Login() {
		$pagetext = "";
		do {
			if ($this->HasErr($pagetext,ERR_USERPASS)) {
				echo "Invalid Username/Password Combination\n";
			}
			$this->username = $this->Prompt("Username: ");
			$this->password = $this->Prompt("Password: ",true);

			$pagetext = $this->CurlPost("/uwangel/signon/authenticate.asp",
				$this->PostEncode(
					array(
						"username" => $this->username,
						"password" => $this->password
					)
				)
			);
		} while($this->HasErr($pagetext,ERR_USERPASS));
	}

	/**
	* Retrieve the list of classes the user is registered for
	*
	* @return mixed Array containing course listing
	*/
	public function GetClasses() {
		$pagetext = $this->CurlGet("/uwangel/default.asp");
		preg_match_all(
			'@default.asp\?id=([^"]*)"><span>([^<]*)@',
			$pagetext,
			$matches
		);
		$ret = array();
		for ($i = 0; $i < count($matches[1]); $i++) {
			$ret[] = array(
				'id' => $matches[1][$i],
				'type' => 'Course',
				'name' => $matches[2][$i]
			);
		}
		return $ret;
	}

	/**
	* Retrieve the content listing for a given class
	*
	* @param string $id The id specifying the course
	* @return mixed Array containing file/folder listing
	*/
	public function BrowseClass($id) {
		$pagetext = $this->CurlGet("/uwangel/section/default.asp?id=$id");
		return $this->BrowseFolder();
	}

	/**
	* Retrieve the directory listing for a given directory
	*
	* @param string $id The id specifying the directory
	* @return mixed Array containing folder contents
	*/
	public function BrowseFolder($id = "") {
		if (strlen($id)) {
			$url = "/uwangel/section/content/default.asp?WCI=pgDisplay&WCU=CRSCNT&ENTRY_ID=$id";
		} else {
			$url = "/uwangel/section/content/";
		}
		$pagetext = $this->CurlGet($url);
		preg_match_all(
			'/ENTRY_ID=([^"]*)"><img.*?alt="([^"]*)".*?><img [^>]*>([^<]*)/',
			$pagetext,
			$matches
		);
		$ret = array();
		for ($i = 0; $i < count($matches[0]); $i++) {
			$ret[] = array(
				'id' => $matches[1][$i],
				'type' => $matches[2][$i],
				'name' => $matches[3][$i]
			);
		}
			
		return $ret;
	}

	/**
	* Retrieve the download url for a given file
	*
	* @param string $id The id specifying the file
	* @return string The url of the file
	*/
	public function GetFileUrl($id) {
		$url = "/uwangel/section/content/File.aspx?WCI=pgDisplay&WCU=CRSCNT&ENTRY_ID=$id";
		$pagetext = $this->CurlGet($url);
		preg_match('/"countdown\(\'0\'\)" href="([^"]*)/',$pagetext,$matches);
		$fileurl = trim($matches[1]);

		return "uwangel.uwaterloo.ca/$fileurl";
	}

	/**
	* Retrieves the contents of an item of type "Page"
	*
	* @param string $id The id specifying the page
	* @return string The contents of the page
	*/
	public function GetPage($id) {
		$url = "/uwangel/section/content/Page.aspx?WCI=pgDisplay&WCU=CRSCNT&ENTRY_ID=$id";
		$pagetext = $this->CurlGet($url);
		//NOTE: The below line only works completely if
		// there are no divs in the page content

		preg_match('/<div id="ActionMessages"><\/div>[^<]*?<div class="normalSpan">([\w\W]*?)<\/div>[^<]*?<script/',
			$pagetext,
			$matches
		);
		return $matches[1];
	}


}
?>
