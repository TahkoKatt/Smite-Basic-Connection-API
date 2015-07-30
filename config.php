<?php
$smiteConfig = new stdClass();
$smiteConfig->API_KEY = ""; //Get your API key from https://fs12.formsite.com/HiRez/form48/secure_index.html (if you get in you will be sent an email with the key)
$smiteConfig->DEV_ID = 0; //They will also send this to you in the email.
$smiteConfig->SITE_URL = "http://api.smitegame.com/smiteapi.svc/"; //Leave this be unless they change the API URL for some reason.
$smiteConfig->SESSION_DURATION = 60*15; //15 minutes default (This is how long sessions last by default in Smite's API)

$smiteConfig->database = new stdClass();
$smiteConfig->database->name = ""; //Database name
$smiteConfig->database->username = ""; //Database user
$smiteConfig->database->password = ""; //Database password