<?php
require("config.php");
/*
 * Parse all input parameters
 */
$qs_action   = @$_POST['param']['action'];
$qs_area51   = @$_POST['param']['area51'];
$qs_merge51  = @$_POST['param']['merge51'];
$qs_delete51 = @$_POST['param']['delete51'];
$qs_latest   = @$_POST['param']['latestDate'];

$response = $_POST;
$response['data'] = array();

/*
 * Handle github/wiki action
 */
switch ($qs_action) {
  case "Area51":
	// Retrive the functions from area51 page
	$oGithub = new cGithub();
    $oWiki = new cWiki($qs_area51, $oGithub);
    $response['data']['status'] = $oWiki->getArea51();
    $response['data']['table'] = $oWiki->area51_table;
  break;
  case "Area51Insert":
	// Add the functions from area51 and merge it with the original page
	$oGithub = new cGithub();
    $oWiki = new cWiki($qs_area51, $oGithub);
    $response['data']['status'] = $oWiki->getArea51($qs_merge51);
	$response['data']['merged'] = $oWiki->area51_ok;
    $response['data']['table'] = $oWiki->area51_table;
  break;
  case "Area51Delete":
	// Delete the function from area51
	$oGithub = new cGithub();
    $oWiki = new cWiki($qs_area51, $oGithub);
    $response['data']['status'] = $oWiki->delArea51($qs_delete51);
	$response['data']['deleted'] = $oWiki->area51_ok;
    $response['data']['table'] = $oWiki->area51_table;
  break;  
}

print json_encode($response);

/*
 * Class to handle all Wiki integration
 */

class cWiki {
	public $area51_table;
	public $area51_content;
	public $area51_ok = array();

	private $login_token = "";
	private $csrf_token = "";

	private $area51 = "";
	private $git;
	private $tmp_table;
	private $tmp_content;
	private $api_url = WIKI_URL . "api.php";

	function __construct($area51, $github) {
		$this->area51 = basename($area51);
		$this->git = $github;
	}

	function getArea51($insert = array()) {
		$arrWikipage = array();
		$arrWikicont = array();
		$ret = $this->getFunctions($this->area51);
		$this->area51_table = $this->tmp_table;
		$this->area51_content = $this->tmp_content;

		// check mergable
		foreach ($this->area51_table as $kTable => $vTable) {
			foreach ($vTable as $kMerge => $vMerge) {
			  if ($vMerge["NAME"] != "STUBLAST") {
				// Base information
				$this->area51_table[$kTable][$kMerge]["ID"] = $vMerge["NAME"]; // Unique function reference
				$this->area51_table[$kTable][$kMerge]["MERGE"] = MERGE_NONE; // Is marging possibile
				$this->area51_table[$kTable][$kMerge]["NOTE" ] = ""; // Output information for human
				// Separate NAME from PR
				$arrName = $this->splitPR($vMerge["NAME"]);
				$this->area51_table[$kTable][$kMerge]["NAME"] = $arrName[0]; // Name of the function
				$this->area51_table[$kTable][$kMerge]["PR"] = (isset($arrName[1]) ? $arrName[1] : ""); // PR reference number
				// Extract the content and fix it
				$tmpContent = $this->getContent($this->area51_content, $vMerge["OFFSET"]);
				$tmpContent = "==" . $this->area51_table[$kTable][$kMerge]["NAME"] . "==\n" . $tmpContent . "\n";
				$this->area51_table[$kTable][$kMerge]["CONTENT"]  = $tmpContent; // Content of the function
				// Add other information
				$this->area51_table[$kTable][$kMerge]["LINK"] = WIKI_URL . "w/" . $this->area51 . "#" . $this->wikiAnchor($vMerge["NAME"]); // Link to the area51 function
				$this->area51_table[$kTable][$kMerge]["WIKI_LINK"] = ""; // Link to the real wiki page
				$this->area51_table[$kTable][$kMerge]["OFFSET_INSERT"] = -1; // Offset where insert the function
				$this->area51_table[$kTable][$kMerge]["OFFSET_STOP"] = -1; // Offset to stop insert the function (in MERGE_REPLACE)

				// Check if I can separate function markup from other text
				if ($this->area51_table[$kTable][$kMerge]["CONTENT"] != "") {
					// Check if there is a wiki page assignet to this section
					if (isset(WIKI_PAGES[$kTable]) && WIKI_PAGES[$kTable] != "") {
						// Check if there is a PR
						if ($this->area51_table[$kTable][$kMerge]["PR"] != "") {
							// Search where to add this functions
							if (!isset($arrWikipage[$kTable])) {
								$ret = $this->getFunctions(WIKI_PAGES[$kTable]);
								if ($ret != "OK") {
								} else {
									$arrWikipage[$kTable] = $this->tmp_table;
									$arrWikicont[$kTable] = $this->tmp_content;
								}
							}
							// Check again if there is a functions page
							if (isset($arrWikipage[$kTable])) {
								// Check if there is more than a result
								if (count($arrWikipage[$kTable]) == 1) {
									$arrFunctions = reset($arrWikipage[$kTable]);
									// Avoid STUBLAST element
									for ($i = count($arrFunctions) - 2; $i >= 0; $i--) {
										$tmpCompare = strcmp($arrFunctions[$i]["NAME"], $this->area51_table[$kTable][$kMerge]["NAME"]);
										// Insert or replace the functions test
										if ($tmpCompare === 0) {
											$this->area51_table[$kTable][$kMerge]["MERGE"] = MERGE_REPLACE;
											$this->area51_table[$kTable][$kMerge]["NOTE"] = "REPLACE " . $arrFunctions[$i]["NAME"];
											$this->area51_table[$kTable][$kMerge]["OFFSET_INSERT"] = $arrFunctions[$i]["OFFSET"];
											$this->area51_table[$kTable][$kMerge]["OFFSET_STOP"  ] = $arrFunctions[$i+1]["OFFSET"];
											break;
										} elseif ($tmpCompare < 0) {
											$this->area51_table[$kTable][$kMerge]["MERGE"] = MERGE_INSERT;
											$this->area51_table[$kTable][$kMerge]["NOTE"] = "INSERT after " . $arrFunctions[$i]["NAME"];
											$this->area51_table[$kTable][$kMerge]["OFFSET_INSERT"] = $arrFunctions[$i+1]["OFFSET"];
											break;
										}
									}
									if ($this->area51_table[$kTable][$kMerge]["MERGE"] == MERGE_NONE && $this->area51_table[$kTable][$kMerge]["NOTE"] == "") {
										$this->area51_table[$kTable][$kMerge]["NOTE"] = "Cannot find where insert functions";
									} else {										
										$this->area51_table[$kTable][$kMerge]["WIKI_LINK"] = WIKI_URL . "w/" . WIKI_PAGES[$kTable] . "#" . $this->wikiAnchor($arrFunctions[$i]["NAME"]);
									}
								} else {
									$this->area51_table[$kTable][$kMerge]["NOTE"] = "Too many sections " . $kTable;
								}
							} else {
								$this->area51_table[$kTable][$kMerge]["NOTE"] = "Cannot load functions page " . $kTable;
							}
						} else {
							$this->area51_table[$kTable][$kMerge]["NOTE"] = "Cannot merge a functions without PR";
						}
					} else {
						$this->area51_table[$kTable][$kMerge]["NOTE"] = "Sections not found or not mergeable";
					}
				} else {
					$this->area51_table[$kTable][$kMerge]["NOTE"] = "Cannot detect function content";
				}
			  }
			}
		}

		// Let's start the real work
		if ($ret == "OK" && count($insert) > 0) {
			foreach ($this->area51_table as $kTable => $vTable) {
				$bolOneAdd = false;
				$arrOK = array();
				$vTable = array_reverse($vTable);
				foreach ($vTable as $kMerge => $vMerge) {
					if ($vMerge["NAME"] != "STUBLAST") {
						if ($vMerge["OFFSET_INSERT"] > 0 && in_array($vMerge["ID"], $insert)) {
							$bolOneAdd = true;
							
							// replace the wiki page content with the functions one
							if ($vMerge["MERGE"] == MERGE_INSERT) {
								$arrWikicont[$kTable] = substr_replace($arrWikicont[$kTable], $vMerge["CONTENT"], $vMerge["OFFSET_INSERT"], 0);
								$arrOK[] = $vMerge["ID"];
							} elseif ($vMerge["MERGE"] == MERGE_REPLACE) {
								$arrWikicont[$kTable] = substr_replace($arrWikicont[$kTable], $vMerge["CONTENT"], $vMerge["OFFSET_INSERT"], $vMerge["OFFSET_STOP"] - $vMerge["OFFSET_INSERT"]);
								$arrOK[] = $vMerge["ID"];
							}
						}
					}
				}
				// wiki call to edit the original page
				if ($bolOneAdd) {
					$tmpRet = $this->loginAll($kTable, $arrWikicont[$kTable]);
					if ($tmpRet == "OK") {
						$this->area51_ok = array_merge($this->area51_ok, $arrOK);
					} else {
						$ret .= $tmpRet . "\n";
					}			
					break; // TODO: levare
				}
			}
		}
		return $ret;
	}
	
	function delArea51($delete) {
		$ret = $this->getFunctions($this->area51);
		// Devo partire dal fondo altrimenti gli offset non tornano dopo la cancellazione
		$this->area51_table = array_reverse($this->tmp_table);
		$this->area51_content = $this->tmp_content;

		// Let's start the real work
		if ($ret == "OK" && count($delete) > 0) {
			$arrOK = array();
			foreach ($this->area51_table as $kTable => $vTable) {

				// devo partire dal fondo a cancellare
				$vTable = array_reverse($vTable);
				for ($i = 0; $i < count($vTable); $i++) {
					$vMerge = $vTable[$i];
					if ($vMerge["NAME"] != "STUBLAST") {
						if (in_array($vMerge["NAME"], $delete)) {
							// delete the wiki area51 content
							$this->area51_content = substr_replace($this->area51_content, '', $vMerge["OFFSET"], $vTable[$i-1]["OFFSET"] - $vMerge["OFFSET"]);
							$arrOK[] = $vMerge["NAME"];
						}
					}
				}
				// wiki call to update the area51 page
				$tmpRet = $this->loginAll(basename($this->area51), $this->area51_content);
				if ($tmpRet == "OK") {
					$this->area51_ok = array_merge($this->area51_ok, $arrOK);
				} else {
					$ret .= $tmpRet . "\n";
				}
			}
		}
		return $ret;
	}

	function getFunctions($page) {
		$arrSections = array();
		$arrFunctions = array();
		$this->tmp_table = array();

		// request a page to wikimedia
		$url = $this->api_url . "?action=query&prop=revisions&titles=" . $page . "&rvslots=*&rvprop=content&formatversion=2&format=json";
		$response = get_web_page($url, array(), true, array());
		$ret = $this->isJson($response["content"]);
		if ($ret == "OK") {
			$responseJSON = json_decode($response["content"], true);
			$ret = $this->isError($responseJSON);
			if ($ret == "OK") {
				$this->tmp_content = $responseJSON["query"]["pages"][0]["revisions"][0]["slots"]["main"]["content"];

				// Search for sections and functions
				preg_match_all('/^=[^=].+[^=]=/m', $this->tmp_content, $arrSections, PREG_OFFSET_CAPTURE);

				preg_match_all('/^==[^=].+[^=]==/m', $this->tmp_content, $arrFunctions, PREG_OFFSET_CAPTURE);

				foreach ($arrFunctions[0] as $functions) {
					for ($i = count($arrSections[0]) - 1; $i >= 0; $i--) {
						$sections = $arrSections[0][$i];
						if ($functions[MATCH_OFFSET] >= $sections[MATCH_OFFSET]) {
							// fix name per output display
							$secName = $this->cleanName($sections[MATCH_NAME]);
							$funName = $this->cleanName($functions[MATCH_NAME]);

							// create / populate the output array
							if (!isset($this->tmp_table[$secName])) $this->tmp_table[$secName] = array();
							$this->tmp_table[$secName][] = array(
								"NAME" => $funName,
								"OFFSET" => $functions[MATCH_OFFSET]
							);
							break;
						}
					}
				}

				// Add the lastest stub element to every section
				for ($i = count($arrSections[0]) - 1; $i >= 0; $i--) {
					$sections = $arrSections[0][$i];
					$secName = $this->cleanName($sections[MATCH_NAME]);
					if (isset($this->tmp_table[$secName]) && count($this->tmp_table[$secName]) > 0) {
						$tmpFunction = end($this->tmp_table[$secName]);
						// Search the last section of a page, skip the first line with section title
						if (preg_match("/^(=|\[)/m", $this->tmp_content, $matches, PREG_OFFSET_CAPTURE, $tmpFunction["OFFSET"] + 1)) {
							$this->tmp_table[$secName][] = array(
								"NAME" => "STUBLAST",
								"OFFSET" => $matches[0][1]
							);
						}
					}
				}
			}
		}
		return $ret;
	}

	private function getContent($content, $offset) {
		$ret = "";
		// Position at function title
		$content = substr($content, $offset);
		// Extract the title
		$posTitle = strpos($content, "\n");
		$title = substr($content, 0, $posTitle);
		// Search for end
		$content = substr($content, $posTitle);
		if (preg_match("/^(=|\[)/m", $content, $matches, PREG_OFFSET_CAPTURE)) {
			$ret = substr($content, 0, $matches[0][1] - 1);
		}
		return $ret;
	}

	private function cleanName($name) {
		return trim(str_replace("=", "", $name));
	}

	private function splitPR($name) {
		$name = str_replace(", ", " ", $name);
		$arrName = explode(" ", $name, 2);
		// try to extract PR number
		if (isset($arrName[1])) {
			if (preg_match("/#(\d+)/", $arrName[1], $matches)) {
				$arrName[1] = "#" . $matches[1] . " ";
				// search PR status in GIT
				if ($this->git->getPR($matches[1]) == "OK") {
					$arrName[1] .= $this->git->state;
				} else {
					$arrName[1] .= "NOT FOUND";
				}
			}
		}
		return $arrName;
	}

	private function wikiAnchor($anchor) {
		$anchor = urlencode($anchor);
		$anchor = str_replace(array("%", "+"), array(".", "_"), $anchor);
		return $anchor;
	}

	private function loginAll($page, $content) {
		$ret = "OK";
		if ($this->login_token == "") {
			$ret = $this->getLoginToken(); // Step 1
			if ($ret == "OK") {
				$ret = $this->loginRequest(); // Step 2
				if ($ret != "OK") $ret = "loginRequest: " . $ret;
			} else {
				$ret = "LoginToken: " . $ret;
			}
		}
		if ($this->csrf_token == "" && $ret == "OK") {
			$ret = $this->getCSRFToken(); // Step 3
			if ($ret != "OK") $ret = "getCSRFToken: " . $ret;
		}
		if ($ret == "OK") {
			$ret = $this->editRequest($page, $content); // Step 4
		}
		return $ret;		
	}


	// Step 1: GET request to fetch login token
	private function getLoginToken() {
		$params1 = [
			"action" => "query",
			"meta" => "tokens",
			"type" => "login",
			"format" => "json"
		];

		$url = $this->api_url . "?" . http_build_query( $params1 );

		$response = get_web_page($url, array(), true, array(
			CURLOPT_COOKIEJAR => "wikiedit.txt",
			CURLOPT_COOKIEFILE => "wikiedit.txt"
		));
		$ret = $this->isJson($response["content"]);
		if ($ret == "OK") {
			$responseJSON = json_decode($response["content"], true);
			$ret = $this->isError($responseJSON);
			if ($ret == "OK") {
				$this->login_token = $responseJSON["query"]["tokens"]["logintoken"];
			}
		}
		return $ret;
	}

	// Step 2: POST request to log in. Use of main account for login is not
	// supported. Obtain credentials via Special:BotPasswords
	// (https://www.mediawiki.org/wiki/Special:BotPasswords) for lgname & lgpassword
	private function loginRequest() {
		$params2 = [
			"action" => "login",
			"lgname" => WIKI_BOT_USER, // BOT NAME
			"lgpassword" => WIKI_BOT_PASS, // BOT PASSWORD
			"lgtoken" => $this->login_token,
			"format" => "json"
		];

		$response = get_web_page($this->api_url, $params2, false, array(
			CURLOPT_COOKIEJAR => "wikiedit.txt",
			CURLOPT_COOKIEFILE => "wikiedit.txt"
		));
		$ret = $this->isJson($response["content"]); // TODO: è un json?
		// var_dump("loginRequest");
		// var_dump($response);
		if ($ret == "OK") {
			$responseJSON = json_decode($response["content"], true);
			$ret = $this->isError($responseJSON);
			if ($ret == "OK") {
				// Data saved in Cookie
			}
		}
		return $ret;
	}

	// Step 3: GET request to fetch CSRF token
	private function getCSRFToken() {
		$params3 = [
			"action" => "query",
			"meta" => "tokens",
			"format" => "json"
		];

		$url = $this->api_url . "?" . http_build_query( $params3 );

		$response = get_web_page($url, array(), true, array(
			CURLOPT_COOKIEJAR => "wikiedit.txt",
			CURLOPT_COOKIEFILE => "wikiedit.txt"
		));
		$ret = $this->isJson($response["content"]);
		if ($ret == "OK") {
			$responseJSON = json_decode($response["content"], true);
			$ret = $this->isError($responseJSON);
			if ($ret == "OK") {
				$this->csrf_token = $responseJSON["query"]["tokens"]["csrftoken"];
			}
		}
		return $ret;
	}

	// Step 4: POST request to edit a page
	public function editRequest($page, $content) {
    $page = "User:Molideus"; // TODO: levare
    
		$params4 = [
			"action" => "edit",
			"title" => $page,
			"text" => $content,
			"token" => $this->csrf_token,
			"bot" => 1,
			"format" => "json"
		];

		$response = get_web_page($this->api_url, $params4, false, array(
			CURLOPT_COOKIEJAR => "wikiedit.txt",
			CURLOPT_COOKIEFILE => "wikiedit.txt"
		));
		// var_dump($response["content"]);
		$ret = $this->isJson($response["content"]);
		if ($ret == "OK") {
			$responseJSON = json_decode($response["content"], true);
			$ret = $this->isError($responseJSON);
			if ($ret == "OK") {
				// $this->status = $responseJSON["edit"]["result"];
			}
		}
		return $ret;
	}


  /*
   * check if JSON is valid
   */
	private function isJson($json) {
		$ret = "OK";
		json_decode($json);
		if (json_last_error() !== JSON_ERROR_NONE) {
			// Errore JSON
			$ret = json_last_error();
		}
		return $ret;
	}

  /*
   * Check if JSON result is a crowdin error
   */
	function isError($json) {
		$ret = "OK";
		if (isset($json['error'])) {
			$ret = $json['error']['code'] . ' - ' . $json['error']['message'];
		}
		return $ret;
	}
}

/*
 * Class to handle all Github integration
 */
 class cGithub {
	public $state = "";

	private $PR = array();
	private $token = GITHUB_TOKEN;
	private $api_url = GITHUB_API;

	/*
	* get the PR status and return it
	*/
	function getPR($number) {
		if (isset($this->PR[$number])) {
			$ret = "OK";
			$this->state = $this->PR[$number];
		} else {
			$response = get_web_page($this->api_url . $number, array(), true, array(
				CURLOPT_HTTPHEADER => array (
					"Accept: application/vnd.github+json",
					"Authorization: Bearer " . $this->token,
					"X-GitHub-Api-Version: 2022-11-28"
			)));

			$ret = $this->isJson($response["content"]);
			if ($ret == "OK") {
				$responseJSON = json_decode($response["content"], true);
				$ret = $this->isError($responseJSON);
				if ($ret == "OK") {
					$this->state = $responseJSON["state"];
					$this->PR[$number] = $this->state;
				}
			}
		}
		return $ret;
	}

	/*
	 * check if JSON is valid
	 */
	  private function isJson($json) {
		  $ret = "OK";
		  json_decode($json);
		  if (json_last_error() !== JSON_ERROR_NONE) {
			  // Errore JSON
			  $ret = json_last_error();
		  }
		  return $ret;
	  }

	/*
	 * Check if JSON result is a crowdin error
	 */
	function isError($json) {
		$ret = "OK";
		if (isset($json['message'])) {
			$ret = $json['message'] . ' - ' . $json['documentation_url'];
		}
			return $ret;
		}
	}

/*
 * Curl request... just a little more verbose
 */
function get_web_page( $url, $post = array(), $bolGET = false, $options = array()) {
    $options = array_replace(array(
        CURLOPT_RETURNTRANSFER => true,     // return web page
        CURLOPT_HEADER         => false,     // don't return headers
        CURLOPT_FOLLOWLOCATION => true,     // follow redirects
        CURLOPT_ENCODING       => "",       // handle all encodings
        CURLOPT_USERAGENT      => "spider", // who am i
        //CURLOPT_AUTOREFERER    => true,     // set referer on redirect
        CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
        CURLOPT_TIMEOUT        => 120,      // timeout on response
        CURLOPT_MAXREDIRS      => 10,        // stop after 10 redirects
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false
    ), $options);

    if (!$bolGET) {
      $options[CURLOPT_POST      ] = 1;
      $options[CURLOPT_POSTFIELDS] = $post;
    } else {
      $options[CURLOPT_HTTPGET   ] = true;
    }

    $ch      = curl_init( $url );
    foreach ($options as $key => $value) {
      curl_setopt( $ch, $key, $value );
    }
    $content = curl_exec( $ch );
    $err     = curl_errno( $ch );
    $errmsg  = curl_error( $ch );
    $header  = curl_getinfo( $ch );
    curl_close( $ch );

    $header['errno']   = $err;
    $header['errmsg']  = $errmsg;
    $header['content'] = $content;
    return $header;
}