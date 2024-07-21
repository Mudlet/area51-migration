<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
ini_set("display_startup_errors", 1);

if (is_file("config.custom.php")) {
	require_once("config.custom.php");
} else {
	print "<h1>Please create a config.custom.php file<br />starting from config.sample.php</h1>";
	die();
}

if (!defined("DEBUG")) define("DEBUG", false);

 /*
 /  Check che custom.config.php constant
*/

// Wiki constant
if (!defined("WIKI_BOT_USER")) print "<h1>Wiki Bot User missing, please check config.custom.php</h1>";
if (!defined("WIKI_BOT_PASS")) print "<h1>Wiki Bot Pass missing, please check config.custom.php</h1>";

// Public repo information
if (!defined("GITHUB_USER")) print "<h1>Github User missing, please check config.custom.php</h1>";
if (!defined("GITHUB_REPO")) print "<h1>Github Repo missing, please check config.custom.php</h1>";

if (!defined("GITHUB_TOKEN")) print "<h1>Github Token missing, please check config.custom.php</h1>";

// DO NOT TOUCH BELOW
define("WIKI_URL", "https://wiki.mudlet.org/");
define("GITHUB_URL",  "https://github.com/Mudlet/Mudlet/pull/");
define("GITHUB_API", "https://api.github.com/repos/" . GITHUB_USER . "/" . GITHUB_REPO . "/pulls/");

// Array of mergable page/functions
define("WIKI_PAGES", array(
	"Basic Essential Functions" => "Manual:Basic_Essentials",
	"Database Functions" => "Manual:Database_Functions",
	"Date/Time Functions" => "Manual:Date/Time_Functions",
	"File System Functions" => "Manual:File_System_Functions",
	"Mapper Functions" => "Manual:Mapper_Functions",
	"Miscellaneous Functions" => "Manual:Miscellaneous_Functions",
	"Mudlet Object Functions" => "Manual:Mudlet_Object_Functions",
	"Networking Functions" => "Manual:Networking_Functions",
	"String Functions" => "Manual:String_Functions",
	"Table Functions" => "Manual:Table_Functions",
	"Text to Speech Functions" => "Manual:Text_to_Speech_Functions",
	"UI Functions" => "Manual:UI_Functions",
	"Discord Functions" => "Manual:Discord_Functions",
	"Events" => "Manual:Event_Engine"
));

// INTERNAL CONSTANT
define("MATCH_NAME"  , 0);
define("MATCH_OFFSET", 1);

define("MERGE_NONE"   , 0);
define("MERGE_INSERT" , 1);
define("MERGE_REPLACE", 2);