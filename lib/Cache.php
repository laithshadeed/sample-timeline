<?php
//TODO: Automtic cache flush or old files
class Cache {
    
    protected $dir;
    protected $ttl;
    
    public function __construct($dir = __DIR__, $ttl = 300) {
        $this->dir = $dir;
        $this->ttl = $ttl;
    }
    
    protected function isFresh($file) {
        if (!file_exists($file)) {
            return false;
        }
        
        $mtime = filemtime($file);
        $time = $_SERVER['REQUEST_TIME'] - $mtime;
        
        if ($time > $this->ttl) {
            return false;
        }
        
        return true;
    }
    
    public function get($key) {
        $file = $this->dir . '/' . $key;
        
        if (!$this->isFresh($file)) {
            return false;
        }
        
        $data = file_get_contents($file);
        
        if ($data === false) {
            throw new Exception("Unable to read " . $file);
        }
        
        return $data;
    }
    
    public function set($key, $data) {
        $file = $this->dir . '/' . $key;
        $written = file_put_contents($file, $data);
        
        if ($written === false) {
            throw new Exception("Unable to write " . $file);
        }
        
        return $written;
    }
}