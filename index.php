<?php
session_start();

require './vendor/autoload.php';
require 'chat.php';

use Respect\Rest\Router as Router;
use Symfony\Component\HttpFoundation\Request as Request;
use Symfony\Component\HttpFoundation\Response as Response;
use Symfony\Component\HttpFoundation\JsonResponse as JsonResponse;

$router = new Router();
$request = Request::createFromGlobals();
$response = new Response;

$appid = "YOUR_APPID";
$appkey = "YOUR_APPKEY";

$email = "YOUR_USER_EMAIL";
$password = "YOUR_USER_PASSWORD";

$chat = Chat::connect($appid, $appkey);
$chat->login($email, $password);

$router->get('/', function(){
	return file_get_contents('index.html');
});

$router->post('/messages', function() use ($request, $response, $chat){

	try {

		$data = array(
			"name" => $request->request->get('name'),
			"message" => $request->request->get('message'),
			"timestamp" => time() 
		);
		$bucketName = "YOUR_BUCKET_NAME";
		$data = $chat->saveToBucket($bucketName, $data);

		$response->setStatusCode(Response::HTTP_OK);
	
	} catch (Exception $e) {
		$data = array(
					'error' => true,
					'message' => $e->getMessage()
				);
		$response->setStatusCode(Response::HTTP_BAD_REQUEST);
	}

	$response->setContent(json_encode($data));
	$response->headers->set('Content-Type', 'application/json');

	return $response->sendHeaders()->getContent();
});

$router->get('/messages', function() use ($request, $response, $chat){

	$lastInsertTime = $request->query->get('lastInsertTime');

	$currentTime = null;

	while(time() <= time() + 10){

	    if ($currentTime != $lastInsertTime){

			try {

				$bucketName = "YOUR_BUCKET_NAME";
				$getData = $chat->getFromBucket($bucketName, $lastInsertTime);
			    
		        $messages = array();
			    if (isset($getData)) {

			        foreach ($getData as $row) {
			        	if (isset($row->metadata)) {
							$data = array(
								'name' => $row->metadata->name,
								'message' => $row->metadata->message,
								'timestamp' => $row->metadata->timestamp,
							);
							array_push($messages, $data);
			        	}
					}

			        if (isset($messages[0]['timestamp'])) {
			            $currentTime = $messages[0]['timestamp'];
			        }
			    }

		        $data = array('error' => false, 'messages' => array_reverse($messages), 'lastInsertTime' => $currentTime);

		    	$response->setStatusCode(Response::HTTP_OK);
		    	$response->setContent(json_encode($data));
				$response->headers->set('Content-Type', 'application/json');

				return $response->sendHeaders()->getContent();
		        break;

			} catch (Exception $e) {
				$data = array(
							'error' => true,
							'message' => $e->getMessage()
						);
				$response->setStatusCode(Response::HTTP_BAD_REQUEST);
				$response->setContent(json_encode($data));
				$response->headers->set('Content-Type', 'application/json');

				return $response->sendHeaders()->getContent();
			}

	    } else {
	    	sleep(7);
	    }
			
	}
});

?>