<?php
function getPreferredLanguage(){
	$acceptedLanguages = @explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
	$preferredLanguage = null;
	$maxWeight = 0.0;
 
	foreach((array)$acceptedLanguages as $acceptedLanguage){
		$weight = (float)@substr(explode(';', $acceptedLanguage)[1], 2);

		if(!$weight){
			$weight = 1.0;
		}
 
		if($weight > $maxWeight){
			$preferredLanguage =  substr($acceptedLanguage, 0, 2);
			$maxWeight = $weight;
		}
	}
 
	return $preferredLanguage;
}

echo getPreferredLanguage();
//echo '<br /><br />';
//echo $_SERVER['HTTP_ACCEPT_LANGUAGE'];

/*
foreach(getallheaders() as $name => $value){
	echo "$name: $value <br /><br />";
}
*/

// Armory::GetLocale()
// Armory::GetLoc()


// Microsoft Edge		= de-DE
// Opera				= de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7
// Internet Explorer	= de-DE
// Google Chrome		= de-DE,de;q=0.9
// Mozilla Firefox		= de,en-US;q=0.7,en;q=0.3

// HTTP_ACCEPT_LANGUAGE	= de,en,es,ru,fr /* <-THIS! */


// Armory::GetLocale() = 'de_de';	/* DATEI */
// Armory::GetLoc() = 3;			/* DATENBANK */

/*
ADD: 127.0.0.1
SID: 9ueo1jrarbc082ek42d8ulv4s3
MD5: 619a2aa2705a42942f57d31d46098ae5

1569101215‬
*/

?>