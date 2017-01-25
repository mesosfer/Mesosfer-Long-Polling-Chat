<?php

require './vendor/autoload.php';

class Chat
{
	public static $instance;
	private $client;
	private $appId;
	private $appKey;

	public static function connect($appId, $appKey)
	{
		if (!isset(Chat::$instance)) {
			Chat::$instance = new Chat($appId, $appKey);
		}
		return Chat::$instance;
	}

	function __construct($appId, $appKey)
	{
		$this->appId = $appId;	
		$this->appKey = $appKey;
		$this->client = new \GuzzleHttp\Client();
	}

	public function login($email='', $password='')
	{
		// create new token if token is empty
		if (!$this->getToken()) {
			try{
				$respAuth = $this->client->request('POST', 'https://api.mesosfer.com/api/v2/users/signin', [
				    'json'    => ['email' => $email, 'password' => $password],
				    'headers' => ['X-Mesosfer-AppId' => $this->appId, 'X-Mesosfer-AppKey' => $this->appKey]
				]);

				$bodyAuth = (string) $respAuth->getBody();
				$auth = json_decode($bodyAuth);

				if (isset($auth->accessToken)) {
					$this->saveToken($auth->accessToken);
				}
			}

			catch(Exception $e){
				throw new Exception($e->getMessage(), 1);
			}
		}
	}

	public function getToken()
	{
		if (isset($_SESSION['token'])) {
			return $_SESSION['token'];
		}
	}

	function saveToken($token='')
	{
		$_SESSION['token'] = $token;
	}

	public function saveToBucket($bucketName, $data)
	{
		try {

			$respCreateBucket = $this->client->request('POST', 'https://api.mesosfer.com/api/v2/data/bucket/'.$bucketName, [
			    'json'    => ['metadata' => $data],
			    'headers' => ['Authorization' => 'Bearer '.$this->getToken(), 'X-Mesosfer-AppId' => $this->appId, 'X-Mesosfer-AppKey' => $this->appKey]
			]);
			
			$createBucket = (string) $respCreateBucket->getBody();
			$bucket = json_decode($createBucket);
			if (isset($bucket->result)) {
				return $bucket->result;
			} else {
				return $bucket;
			}

		} catch (Exception $e) {
			throw new Exception($e->getMessage(), 1);
		}
	}

	public function getFromBucket($bucketName, $timestamp)
	{
		try {

			if ($timestamp) {
				$query = array(
					'metadata.timestamp' => array(
						'$gt' => (int) $timestamp
					)
				);
				$queryParams['where'] = json_encode($query);
			}

			$queryParams['order'] = '-createdAt';
			$queryParams['limit'] = '40';

			$buildQuery = http_build_query($queryParams);

			$respGetBucket = $this->client->request('GET', 'https://api.mesosfer.com/api/v2/data/bucket/'.$bucketName.'?'.$buildQuery, [
			    'headers' => ['Authorization' => 'Bearer '.$this->getToken(), 'X-Mesosfer-AppId' => $this->appId, 'X-Mesosfer-AppKey' => $this->appKey]
			]);

			$bodyBucket = (string) $respGetBucket->getBody();
			$getBucket = json_decode($bodyBucket); 

			if (isset($getBucket->results)) {
				return $getBucket->results;
			}
			
		} catch (Exception $e) {
			return array('error' => true, 'message' => $e->getMessage());
		}
	}
}

?>