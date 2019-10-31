<?php

/* USAGE: (assuming this php file is called vip.php)
    
    Lock Car = https://yourdomain.com/vip.php?apiKey=ABC123&cmd=arm_status
    Unlock Car = https://yourdomain.com/vip.php?apiKey=ABC123&cmd=disarm_status
    Turn on/off (toggle) Car = https://yourdomain.com/vip.php?apiKey=ABC123&cmd=remote_status
    Start Car & Unlock it = https://yourdomain.com/vip.php?apiKey=ABC123&cmd=remote_status&cmd2=disarm_status

*/

$smartStartUserName = 'joe@blah.com';
$smartStartPassword = 'P@ssw0rd!';
$deviceID = '3000011966'; //Use postman post requests to get your deviceID - https://github.com/vqndev/viper-smart-start-postman

// API KEY - I know, its no the best security
$apiKey = $_GET['apiKey'] ?? '';
if($apiKey !== 'ABC123') die('no api');

// STORED ACCESS TOKEN - Store access token for faster access
$accessToken = false;
$accessTokenPath = './viperToken.txt';
if(file_exists($accessTokenPath)){
    $myfile = fopen($accessTokenPath, "r");
    $fileContents = fread($myfile,filesize($accessTokenPath));
    $tokenData = json_decode($fileContents);
    $expiration = (int) substr($tokenData->expiration, 0, 10);
    $accessTokenIsValid = $expiration > time();
    if($accessTokenIsValid){
        $accessToken = $tokenData->accessToken;
    }
}

// If no access token, then login
if(!$accessToken){
    logMe('no access token!');
    // Enter your real creds here for viper smart start
    login($smartStartUserName, $smartStartPassword);
    // die('the end');
}

$command = $_GET['cmd'] ?? 'req_extended_status'; // arm_status / disarm_status / req_extended_status / remote_status
$command2 = $_GET['cmd2'] ?? false;


if($accessToken){
    $cmdResult = runCommand($command, $deviceID, $accessToken, !$command2);
    echo json_encode($cmdResult);
    if($command2){
        // sleep(20)
        $cmdResult2 = runCommand($command2, $deviceID, $accessToken, true);
    }

    // echo json_encode($cmdResult);
} else {
    echo 'login failed';
}






function login($user, $pass)
{
    global $accessToken;
    global $accessTokenPath;
    logMe('function login');
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL,"https://www.vcp.cloud/v1/auth/login");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_1; en-us) AppleWebKit/531.9 (KHTML, like Gecko) Version/4.0.3 Safari/531.9");
    curl_setopt($ch, CURLOPT_POSTFIELDS,
                http_build_query(['username'=>$user, 'password'=>$pass]));


    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    list($header, $body) = explode("\r\n\r\n", $response, 2);
    curl_close ($ch);

    // Further processing ...
    $body = json_decode($body);


    $accessToken = $body->results->authToken->accessToken ?? false;

    if(!$accessToken){
        die('cant login');
    }

    // Writing Token Data to File
    $tokenData = $body->results->authToken ?? false;
    $myfile = fopen($accessTokenPath, "w") or die("Unable to open file!");
    fwrite($myfile, json_encode($tokenData));
    fclose($myfile);
    logMe('Wrote token info to file');

    // exit();
    // return $accessToken;
}

function runCommand($cmd, $deviceID, $accessToken, $sendUpdate = false)
{
    logMe('function runCommand');
    $ch = curl_init();
    $data = json_encode(['command' => $cmd, 'deviceId' => $deviceID]);
    curl_setopt($ch, CURLOPT_URL,"https://www.vcp.cloud/v1/devices/command");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data),
        'Authorization: Bearer ' . $accessToken,
    ));
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_1; en-us) AppleWebKit/531.9 (KHTML, like Gecko) Version/4.0.3 Safari/531.9");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    list($header, $body) = explode("\r\n\r\n", $response, 2);
    curl_close ($ch);

    // Further processing ...
    echo "<pre>";
    $body = json_decode($body);

    $engine = $body->results->device->deviceStatus->remoteStarterActive ?? null;
    $doors = $body->results->device->deviceStatus->doorsLocked ?? null;
    $lat = $body->results->device->latitude ?? null;
    $lng = $body->results->device->longitude ?? null;

    if($sendUpdate && !is_null($engine) && !is_null($doors)){
        $engine_text = $engine ? 'on' : 'off';
        $doors_text = $doors ? 'locked' : 'unlocked';

        /*
        
        // I set up an IFTTT trigger to notify me the status of my car

        $ch = curl_init();
        $data = json_encode(['value1' => $engine_text, 'value2' => $doors_text, 'value3' => $lat.','.$lng]);
        curl_setopt($ch, CURLOPT_URL,"https://maker.ifttt.com/trigger/car_notification/with/key/xmrm9klg8QWDNHCPTf4EB");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data)
        ));
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_1; en-us) AppleWebKit/531.9 (KHTML, like Gecko) Version/4.0.3 Safari/531.9");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $response = curl_exec($ch);
        // $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        // list($header2, $body2) = explode("\r\n\r\n", $response, 2);
        curl_close ($ch);
        */
    }

    return $body;
}

function logMe($msg) {
   // echo $msg . "<br/>";
}
