<?php

//TODO: Handle non-200 respones
class Http {
    protected $timeout;
    
    public function __construct($timeout) {
        $this->timeout = $timeout;
    }
    public function get($url, $opt) {
        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout
        );
        
        foreach($opt as $k=>$v) {
            $options[$k] = $v;
        }
        
        if (is_array($url)) {
            return $this->getMany($url, $options);
        } else {
            return $this->getSingle($url, $options);
        }
    }
    
    protected function getSingle($url, $opt) {
        $ch = curl_init($url);
        curl_setopt_array($ch, $opt);
        $data = curl_exec($ch);
        curl_close($ch);
        
        return $data;
    }
    
    //TODO: Support throttling up to 10 or 20 parallel requests
    protected function getMany($urls, $opt) {        
        $mh = curl_multi_init();
        $chs = array();
        $data = array();
        
        foreach($urls as $url) {
            $ch = curl_init();
            $opt[CURLOPT_URL] = $url;
            curl_setopt_array($ch, $opt);
            curl_multi_add_handle($mh, $ch);
            $chs[$url] = $ch;
        }
        
        $active = null;
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        
        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($mh) == -1) {
                continue;
            }
            
            do {
                $mrc = curl_multi_exec($mh, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        }
        
        foreach($chs as $url=>$ch) {
            $data[$url] = curl_multi_getcontent($ch);
            curl_multi_remove_handle($mh, $ch);
        }
        
        curl_multi_close($mh);
        
        return $data;
    }
}