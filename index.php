<?php
require_once('./cdnflush.class.php');
require_once('./config.php');

$hostname = $_POST['hostname'];
$urls = $_POST['urls'];
$recipients = $_POST['recipients'];

//TODO : add secret verification

//TODO : add controls on hostname, urls & recipients

$api = new CDNetworkFlush($hostname, $urls, $recipients);
$api->setCredentials($cdnetworksLogin, $cdnetworksPassword);

$return = $api->forwardFlush();

echo $return;