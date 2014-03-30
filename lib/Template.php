<?php

class Template {
    protected $dir;
    protected $templates = array();
    
    public function __construct($dir = __DIR__) {
        $this->dir = $dir;
    }
    
    protected function getTemplate($file) {
        $file = $this->dir . '/' . $file;
        
        //Check if already loaded into memory
        if (array_key_exists($file, $this->templates)) {
            return $this->templates[$file];
        }
        
        if (!file_exists($file)) {
            throw new Exception('Unable to find ' . $file);
        }
        
        $tpl = file_get_contents($file);
        
        if ($tpl === false) {
            throw new Exception('Unable to read ' . $file);
        }
        
        $this->templates[$file] = $tpl;
        
        return $tpl;
    }
    
    public function render($tpl, $keys, $vals) {
        return str_replace($keys, $vals, $this->getTemplate($tpl));
    }
}