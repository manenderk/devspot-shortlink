<?php

define('SHORTLINK_PREFIX', 'dt');
define('SHORTLINK_LENGTH', 6);

class Shortlink{
    private $id;
    private $userId;
    private $shortLink;
    private $redirectLink;
    private $created;

    function __construct($id = null, $userId = null, $shortLink = null, $redirectLink = null, $created = null, $modified = null){
        $this->id = $id;
        if(empty($userId))
            $userId = $this->getCurrentUserId();
        $this->userId = $userId;
        if(empty($shortLink))
            $shortLink = $this->getNewShortLink();
        $this->shortLink = $shortLink;
        $this->redirectLink = $redirectLink;
        if(empty($created))
            $created = current_time('mysql', 1);
        $this->created = $created;
    }

    private function getCurrentUserId(){        
        $current_user = wp_get_current_user();
        return $current_user->ID;
    }

    private function getNewShortLink(){
        $string = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $newString = '';
        for($i = 0; $i < SHORTLINK_LENGTH; $i++){
            $newString .= $string[rand(0,strlen($string)-1)];
        }
        return SHORTLINK_PREFIX.$newString;        
    }

    public function __get($property) {
        if (property_exists($this, $property)) {
          return $this->$property;
        }
    }
    
    public function __set($property, $value) {
        if (property_exists($this, $property)) {
            $this->$property = $value;
        }
        return $this;
    }


}