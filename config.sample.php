<?php
/*
 / Rename this file to config.custom.php
*/

// Set TRUE to enable debug message (very verbose)
define("DEBUG", false);
// A page to save changes instead of modify the real page
define("DEBUG_PAGE", "");

// Token generated https://github.com/settings/tokens
// Needed only "PUBLIC_REPO" permission
define("GITHUB_TOKEN", "");

// Public repo information
define("GITHUB_USER", "");
define("GITHUB_REPO", "");

// Credential generated at https://wiki.mudlet.org/w/Special:BotPasswords
// Needed only "Edit pages" permission
// Can be input in the script page instead
define("WIKI_BOT_USER", "");
define("WIKI_BOT_PASS", "");