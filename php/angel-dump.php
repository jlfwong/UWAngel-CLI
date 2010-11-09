#!/usr/bin/env php
<?php
/**
* UWAngel-CLI is a CLI for navigating the University of Waterloo's
* UW-ACE/UW-Angel online system
*
* @version 0.1
* @author Jamie Wong <jamie.lf.wong@gmail.com>
* @requires phpcurl
* @requires stty
* @requires head
* @requires wget
*/

// Includes the class used to access all UWACE resources
include_once("AngelAccess.class.php");

// Includes the terminal codes for displaying color
include_once("cli_colours.php");

// Greet the user
print <<<EOT
Welcome to Angel Access
Login using your UW-ACE Username & Password
As you type your password, it will not be shown on screen,
but it is being entered.
--

EOT;

// Initialize the accesor
$cmd = new AngelAccess();

// Authentication
$cmd->Login();

/*
* The directory stack keeps track of the user's navigation.
* When the user decides to go back to a directory above the current,
* The stack will just pop off the current element and display the
* listing for the next element. This prevents some of the duplicate
* HTTP requests
*/
$dirstack = array();
$outdirstack = array();

$outdir = "uwace-".$cmd->GetUserName();

// Retrieve the user's course listings
print "Retrieving courses to $outdir....";
traverse($dirstack, $outdirstack, $cmd->GetClasses(), $outdir);

// These are the colours corresponding to each item to be displayed
$colortype = array(
	'Course' => $COLOR_LIGHTGRAY,
	'Folder' => $COLOR_BLUE,
	'File' => $COLOR_PURPLE,
	'Page' => $COLOR_CYAN,
);

function dir_sanitize($dir) {
	$dir = preg_replace('/:/',' - ',$dir);
	$dir = preg_replace('/\ \ /',' ',$dir);
  $dir = preg_replace('/\//',' ',$dir);
	return $dir;
}

function traverse($dirstack, $outdirstack, $curdir, $curoutdir) {
	global $colortype, $COLOR_RED, $COLOR_DEFAULT, $cmd;
	// The current directory to be displayed
	//$curdir = $dirstack[0];
	array_unshift($dirstack,$curdir);

	if (strlen($curoutdir)) {
		array_unshift($outdirstack,getcwd());

		$curoutdir = dir_sanitize($curoutdir);

		if (!is_dir($curoutdir)) {
			if (!mkdir($curoutdir)) {
        print_r($dirstack);
				die("Failed to create \"$curoutdir\"");
			}
			clearstatcache();
		}

		chdir($curoutdir);
	}
	
	// The maximum number of digits requires to display any menu command
	$numdigs = intval(log10(count($curdir))) + 1;
	if ($numdigs < 1) {
		$numdigs = 1;
	}

	// The format string to display the menu options
	$format = "%s[%".$numdigs."s|%8s] %s\n";


	// Whitespace used to erase status messages
	print "                                                  \n";
	foreach ($curdir as $key => $val) {
		if (isset($colortype[$val['type']])) {
			$color = $colortype[$val['type']];
		} else {
			$color = $COLOR_RED;
		}

		printf($format,
			$color,		
			$key,
			$val['type'],
			$val['name']
		);
	}

	print $COLOR_RED."CUR DIR: ".getcwd().$COLOR_DEFAULT."\n";	

	foreach ($curdir as $key => $val) {
		print "$COLOR_DEFAULT(Processing item $COLOR_RED$key$COLOR_DEFAULT...)";

		$curitem = $val;
		if ($curitem['type'] == "Course") {
			if (!(stristr($curitem['name'], "PDENG") === false)) {
				print "Skipping PDENG course \"".$curitem['name']."\"...";
			}elseif (!(stristr($curitem['name'], "Co-op") === false)) {
				print "Skipping Co-op course \"".$curitem['name']."\"...";
			}else{
				print "Retrieving course content...";
				traverse($dirstack,$outdirstack,$cmd->BrowseClass($curitem['id']),$curitem['name']);
			}
			
		} else if ($curitem['type'] == "Folder") {
			print "Retrieving folder contents...";
			traverse($dirstack,$outdirstack,$cmd->BrowseFolder($curitem['id']),$curitem['name']);

		} else if ($curitem['type'] == "File") {
			print "Retrieving file location...";
			$fileurl = $cmd->GetFileUrl($curitem['id']);
			
			// FIXME What if this file already exists? We ignore $curitem['name'] entirely.
			$outname = basename($fileurl);
      if(file_exists($outname)) {
        print "File \"$outname\" already exists - skipping\n";
        continue;
      }

			// Download only if newer 
			$com = "wget -N --no-check-certificate -O \"$outname\" \"https://$fileurl\"";
			print $com;
			system($com);

		} else if ($curitem['type'] == "Page") {
			continue;
			print "Retrieving page\"".$curitem['name']."\"...";
			$pagetext = $cmd->GetPage($curitem['id']);

			$page_ofh = fopen($curitem['name'].".txt","wb");

			fwrite($page_ofh,$pagetext);
		} else {
			print "ERROR - No protocol for handling item type: {$curitem['type']}\n";
		}
	}

	if (strlen($curoutdir)) {
		chdir($outdirstack[0]);
	}	

}

	
?>
