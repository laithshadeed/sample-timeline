<?php
/*
 * Implementation of OAuth 2.0 as per
 * https://dev.twitter.com/docs/auth/application-only-auth
 */
 
class API {
    protected $cache;
    protected $baseUrl;
    protected $consumerKey;
    protected $consumerSecret;
    protected $accessToken;
    
    public function __construct($params, $cache, $http) {
        $this->cache = $cache;
        $this->http = $http;
        $this->baseUrl = $params['base_url'];
        $this->consumerKey = $params['consumer_key'];
        $this->consumerSecret = $params['consumer_secret'];
    }

    public function get($path, $retry = 3) {
        $url = $this->baseUrl . $path;
        
        //Check file-cache
        $key = rawurlencode($path);
        $data = $this->cache->get($key);
        if ($data) {
           return json_decode($data);    
        }
        
        $headers = array(
            'Authorization: Bearer ' . $this->getAccessToken()
        );
        
        $options = array(
            CURLOPT_HTTPHEADER => $headers
        );
        
        $data = $this->http->get($url, $options);
        
        if ($data === false) {
            throw new Exception('Unable to request ' . $path);
        }
        
        $json = json_decode($data);
        
        if (!$json) {
            if ($retry > 0) {
                return $this->get($path, $retry - 1);
            }
            throw new Exception('Invalid JSON response from ' . $path);
        }

        if (isset($json->errors) && $json->errors[0]) {
            if ($retry > 0) {
                return $this->get($path, $retry - 1);
            }
            
            throw new Exception('Error fetching ' .
                $path .  ': ' . $json->errors[0]->message);
        }
        
        if (isset($json->error)) {
            if ($retry > 0) {
                return $this->get($path, $retry - 1);
            }
            
            throw new Exception('Error fetching ' .
                $path .  ': ' . $json->error);
        }
        
        $this->cache->set($key, $data);
        return $json;
    }
    
    public function getMany($paths) {
        $urls = array();
        $results = array();
        
        foreach($paths as $path) {
            //Check file-cache
            $url = $this->baseUrl . $path;
            $key = rawurlencode($url);
            $data = $this->cache->get($key);
            if ($data) {
                $results[] =  json_decode($data);
                continue;
            }
            
            $urls[] = $url;
        }
        
        //If all files in cache, return directly
        if (empty($urls)) {
            return $results;
        }
        
        $headers = array(
            'Authorization: Bearer ' . $this->getAccessToken()
        );
        
        $options = array(
            CURLOPT_HTTPHEADER => $headers
        );
        
        $data = $this->http->get($urls, $options);
        
        foreach($data as $url=>$item) {
            $json = json_decode($item);
                
            if (!$json) {
                continue; //TODO: retry failed requests
            }

            if (isset($json->errors) && $json->errors[0]) {
                error_log($json->errors[0]->message);
                continue;  //TODO: retry failed requests
            }
            
            if (isset($json->error)) {
                error_log($json->error);
                continue;  //TODO: retry failed requests
            }
        
            $key = rawurlencode($url);
            $this->cache->set($key, $item);
            $results[] = $json;
        }

        return $results;
    }
    protected function getAccessToken() {
        //Check it token loaded in memory
        if ($this->accessToken) {
            return $this->accessToken;
        }
        
        //Check if token loaded in file-cache
        $this->accessToken = $this->cache->get('access_token');
        if ($this->accessToken) {
            return $this->accessToken;
        }
        
        //Step 1: Encode consumer key and secret
        $encodedConsumerKey = rawurlencode($this->consumerKey);
        $encodedConsumerSecret = rawurlencode($this->consumerSecret);
        $bearerToken = $encodedConsumerKey . ':' . $encodedConsumerSecret;
        $encodedBearerToken = base64_encode($bearerToken);
        
        //Step 2: Obtain a bearer token
        $headers = array(
            'Authorization: Basic ' . $encodedBearerToken,
            'Content-type: application/x-www-form-urlencoded;charset=UTF-8'
        );
        
        $options = array(
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials'
        );
        
        //Step 3: Perform request
        $data = $this->http->get(
            'https://api.twitter.com/oauth2/token',
            $options
        );
        
        if ($data === false) {
            throw new Exception('Unable to get bearer token');
        }
        
        $json = json_decode($data);
        
        if ($json === false) {
            throw new Exception('Invalid JSON from bearer token error #' . json_last_error());
        }
        
        if (isset($json->errors) && $json->errors[0]) {
            throw new Exception('Getting bearer token error: '  . $json->errors[0]->message);
        }
        
        $accessToken = $json->access_token;
        
        if (!$accessToken) {
            throw new Exception('No bearer token returned from twitter');
        }
        
        //Cache in-memory, for multiple function calls
        $this->accessToken = $accessToken;
        
        //Cache in-file, for multiple page requests
        $this->cache->set('access_token', $accessToken);
    }
}