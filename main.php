<?php
define('BOT_TOKEN', '12345678:ABCDEFGHIJKLMNOPQRSTUVWXYZ'); //-- Replace Your Token. --
define('API_URL', 'https://api.telegram.org/bot'.BOT_TOKEN.'/');
$log = false;
function apiRequestWebhook($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }
  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }
  $parameters["method"] = $method;
  header("Content-Type: application/json");
  echo json_encode($parameters);
  return true;
}
function exec_curl_request($handle) {
  $response = curl_exec($handle);
  if ($response === false) {
    $errno = curl_errno($handle);
    $error = curl_error($handle);
    error_log("Curl returned error $errno: $error\n");
    curl_close($handle);
    return false;
  }
  $http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
  curl_close($handle);
  if ($http_code >= 500) {
    // do not wat to DDOS server if something goes wrong
    sleep(10);
    return false;
  } else if ($http_code != 200) {
    $response = json_decode($response, true);
    error_log("Request has failed with error {$response['error_code']}: {$response['description']}\n");
    if ($http_code == 401) {
      throw new Exception('Invalid access token provided');
    }
    return false;
  } else {
    $response = json_decode($response, true);
    if (isset($response['description'])) {
      error_log("Request was successfull: {$response['description']}\n");
    }
    $response = $response['result'];
  }
  return $response;
}
function apiRequest($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }
  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }
  foreach ($parameters as $key => &$val) {
    // encoding to JSON array parameters, for example reply_markup
    if (!is_numeric($val) && !is_string($val)) {
      $val = json_encode($val);
    }
  }
  $url = API_URL.$method.'?'.http_build_query($parameters);
  $handle = curl_init($url);
  curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($handle, CURLOPT_TIMEOUT, 60);
  return exec_curl_request($handle);
}
function apiRequestJson($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }
  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }
  $parameters["method"] = $method;
  $handle = curl_init(API_URL);
  curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($handle, CURLOPT_TIMEOUT, 60);
  curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
  curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
  return exec_curl_request($handle);
}
      
function processMessage($message) {
    // process incoming message
    $message_id = $message['message_id'];
    $chat_id = $message['chat']['id'];
    
    if (isset($message['text'])) {
        // incoming text message
        $text = $message['text'];
    	$mfid = $message['from']['id'];	
    	$mfusername = $message['from']['username'];	
    	$mffirst_name = $message['from']['first_name'];
        $mflast_name = $message['from']['last_name'];
        $id_str ;

        //process bot commands 
		if ($text == "/on") {	
            apiRequest("sendMessage", array('chat_id' => $chat_id, "text" =>'ROBOT IS ONLINE!'));
		} else if (strpos($text, "/start") === 0) {
		    $c = "http://www.nationalgeographic.com/photography/photo-of-the-day/";
		    libxml_use_internal_errors(true);
		    $ex = file_get_contents($c);
		    $doc = new DomDocument();
            $doc->loadHTML($ex);
            $xpath = new DOMXPath($doc);
            $query = '//*/meta[starts-with(@property, \'og:\')]';
            $metas = $xpath->query($query);
            $result = array();
            foreach ($metas as $meta) {
                $property = $meta->getAttribute('property');
                $content = $meta->getAttribute('content');
                $result[$property] = $content;
            }
            $cap = $result['og:title'];
            $img = $result['og:image'];
            $des = $result['og:description'];
            $uRl = $result['og:url'];
            $msgLink = " $cap \n\n$des";
            apiRequest("sendPhoto", array('chat_id' => $chat_id, 
                                            "photo" => $img,  
                                            'caption'=>$msgLink,
                                            'reply_markup' => array('inline_keyboard' => 
                                            array(array(array('text' => 'National Geographic', 'url' => $uRl))))));
        }else{}

    } 
}

function processInline($inline){
    $inline_id = $inline['id'];
    $querys = $inline['query'];
    $from_id = $inline['from']['id'];
    $from_name = $inline['from']['first_name'];
    $from_user = $inline['from']['username'];
    
    $c = "http://www.nationalgeographic.com/photography/photo-of-the-day/";
    libxml_use_internal_errors(true);
    $ex = file_get_contents($c);
    $doc = new DomDocument();
    $doc->loadHTML($ex);
    $xpath = new DOMXPath($doc);
    $query = '//*/meta[starts-with(@property, \'og:\')]';
    $metas = $xpath->query($query);
    $result = array();
    foreach ($metas as $meta) {
        $property = $meta->getAttribute('property');
        $content = $meta->getAttribute('content');
        $result[$property] = $content;
    }
    
    $cap = $result['og:title'];
    $img = $result['og:image'];
    $des = $result['og:description'];  if(strlen($des)<1){$des = " ";}
    $uRl = $result['og:url']; 
    $msgLink = " $cap \n\n$des";
    $res = array( array('type' => 'photo',
						'id' => time() . 'sadra',
						'photo_url' => $img,
						'thumb_url' => $img,
						'title' => $cap,
						'description' => $des,
						'caption' =>  $msgLink,
						'reply_markup' => array('inline_keyboard' => 
						array(array(array('text' => 'National Geographic', 'url' => $uRl)))))); 
	apiRequestJson("answerInlineQuery", array('inline_query_id' => $inline_id,'cache_time' => 0,'results' => $res));
}

define('WEBHOOK_URL', 'https://site/file.php'); // Set Your URL
if (php_sapi_name() == 'cli') {
  // if run from console, set or delete webhook
  apiRequest('setWebhook', array('url' => isset($argv[1]) && $argv[1] == 'delete' ? '' : WEBHOOK_URL));
  exit;
}

$content = file_get_contents("php://input");
$update = json_decode($content, true);
if (!$update) {exit;}
if (isset($update["message"])) {
    processMessage($update["message"]);
    if($log)file_put_contents('last_update.txt', print_r($update, true));  
}else if (isset($update["inline_query"])) {
	processInline($update["inline_query"]);
	if($log)file_put_contents('last_inline.txt', print_r($update, true)); 
}
// https://api.telegram.org/bot*BOT TOKEN*/setWebhook?url=*WEBHOOK URL*
