<?php

# http://php.net/manual/en/timezones.php
date_default_timezone_set('America/Chicago'); 


class db
{
    public $db;

    function __construct($sqliteFile) 
    {
        try {
            $this->db = new PDO('sqlite:'. $sqliteFile);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            # default to fetching associative arrays
            $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            # keep parameters from interpolating into the queries, automatic injection prevention
            $this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } 
        catch(PDOException $e) {
            echo $e->getMessage();
        }
    }

    public function time($in = 0) 
    {
        if ($in) {
            return date('Y-m-d H:i:s', $in); 
        } else {
            return date('Y-m-d H:i:s');
        }
    }

    public function truncate($text, $limit) 
    {
        if (strlen($text) > $limit) {
            $words = str_word_count($text, 2);
            $pos = array_keys($words);
            $text = substr($text, 0, $pos[$limit]) .'...';
        }
        return $text;
    }

    public function report($text, $status = 'info') 
    {
        $class = null;
        switch ($status) {
            case 'info':
                $class = 'report info';
                break;
            case 'success':
                $class = 'report success';
                break;
            case 'warning':
                $class = 'report warning';
                break;
            case 'error':
                $class = 'report error';
                break;
        } 
        
        echo "<div class=\"$class\">$text</div>";
    }

    // public function getIpAddress() 
    // {
    //     if (isset($_SERVER["REMOTE_ADDR"])) {
    //         return $_SERVER["REMOTE_ADDR"]; 
    //     }
    //     else if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
    //         return $_SERVER["HTTP_X_FORWARDED_FOR"]; 
    //     }
    //     else if (isset($_SERVER["HTTP_CLIENT_IP"])) {
    //         return $_SERVER["HTTP_CLIENT_IP"]; 
    //     }
    // }

    // public function isEmail($email) 
    // {
    //     if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
    //         return true;
    //     } else {
    //         return false;
    //     }
    // }

}

?>