
### About

Another simple and "ugly" script that reads new functions document on the Area_51 wiki page. It allows to automatically merge the functions in the proper page and then clean up the Area_51 page if needed.

### Configuration

Open the config.sample.php file and compile the mandatory field then save it sa config.custom.php

#### General
+ DEBUG: enable it to save the modified wiki page to ttps://wiki.mudlet.org/w/User:Molideus for testing porpuse. This flag also enable a verbose "Content" field that show a list of parsed function

#### Crowdin
+ WIKI_BOT: it's the new of the bot that should modify the wiki page
+ WIKI_BOT_PASS: password generated on the page https://wiki.mudlet.org/w/Special:BotPasswords. This script needs only EDIT_PAGE permission.

#### Github
+ GITHUB_USER: the user who own the repository
+ GITHUB_REPO: the repository name
+ GITHUB_TOKEN: legacy access token with only PUBLIC_REPO permission https://github.com/settings/tokens

### Usage
+ Simply open the url wiki.php on the web folder you publish this scripts (ex. https://example.org/script/wiki.php)
+ Change the Area_51 url if needed
+ Press "START" button and wait some seconds for the output string
+ After a few seconds, you can see the table results with functions that need to be merged or deleted. 
+ Press the proper button under the table and wait for the operations to completed

### How the script works
+ FUNCTIONS TABLE: the script retrives the wiki source code of the Area_51 pages and search for  functions delimited by "\=\=\=" grouping by sections delimited by "\=\=". From the functions name the script extract the PR number to show the proper status of the PR on github
+ MERGE: the script retrives the wiki source code of the destination pages of the functions selected, then split them in functions and sections like is done for the functions table. The script made a replace of the original functions body
+ DELETE: the script remove the functions from the Area_51 page. The functions should be use only after a succefully merge

### Requirements
+ PHP 8.0+ with Curl enabled
+ Works both on Apache and IIS
+ No database Needed