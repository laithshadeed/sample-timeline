<?php

class Timeline {
    
    protected $api;
    protected $cache;
    protected $template;
    
    public function __construct($api, $cache, $template) {
        $this->api = $api;
        $this->cache = $cache;
        $this->template = $template;
    }
    
    public function get($screenName, $followersLimit) {
        //Check cache
        $key = rawurlencode($screenName . $followersLimit);
        $timeline = $this->cache->get($key);
        if ($timeline) {
            return $timeline;
        }
        
        $followers = $this->getFollowers($screenName, $followersLimit);
        $statuses = $this->getStatuses($followers);
        
        $html = '';
        
        foreach($statuses as $status) {
            $html .= $this->formatStatus($status);
        }
        
        $this->cache->set($key, $html);
        return $html;
    }
    
    protected function getFollowers($screenName, $followersLimit) {
        $followers = $this->api->get('/followers/ids.json?count=100&screen_name=' . $screenName);
        return array_slice($followers->ids, 0, $followersLimit);
    }
    
    protected function getStatuses($ids) {
        $urls = array();
        $statuses = array();
        $result = array();
        
        foreach($ids as $id) {
            $urls[] = '/statuses/user_timeline.json?count=1&user_id=' . $id;
        }
        
        $statuses = $this->api->getMany($urls);

        foreach($statuses as $status) {
            $status = $status[0];
            
            $time = strtotime($status->created_at);
            $text = $status->text;
            $statusId = $status->id;
            $username = $status->user->screen_name;
            $name = $status->user->name;
            $result[$time] = array (
                'time' => $time,
                'text' => $text,
                'id' => $statusId,
                'username' => $username,
                'name' => $name
            );
        }
        
        krsort($result);
        
        return $result;
    }
    
    protected function formatStatus($statusArr) {
        //TODO: filter text against injections
        $time = $statusArr['time'];
        $status = $statusArr['text'];
        $statusId = $statusArr['id'];
        $username = $statusArr['username'];
        $name = $statusArr['name'];
        
        $timeAgo = $this->formatTimeAgo($time);
        
        //TODO: Add href support for hashtags also
        $status = preg_replace(
            '/(?<!\w)@(\w{0,15})/i', 
            '<a href="https://twitter.com/$1" target="_blank" class="hashtag">@$1</a>',
            $status
        );
        
        return $this->template->render(
            'tweet.tpl',
            array('::status', '::id', '::username', '::name', '::time'),
            array($status, $statusId, $username, $name, $timeAgo)
        );
    }
    
    protected function formatTimeAgo($timestamp)
    {
        $peroidMap = array(
            'd' => 86400,
            'h' => 3600,
            'm' => 60
        );
            
        $elapsed = $_SERVER['REQUEST_TIME'] - $timestamp;
    
        if ($elapsed < 1) {
            return 'now';
        }
        
        $ago = $elapsed . 's'; //Default in seconds
        
        foreach ($peroidMap as $peroidStr => $periodSecs) {
            $agoNum = $elapsed/$periodSecs;
            
            //It means peroid is just larger than elpased, we got it 
            if ($agoNum >= 1) {
                $ago = round($agoNum) . $peroidStr;
                break;
            }
        }
        
         return $ago;
    }
}