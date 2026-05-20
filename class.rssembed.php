<?php

class embed {
    private $type;
    private $maxHeight;
    private $maxWidth;
    private $url;

    function __construct($url) 
    {
        $this->url = $url;
    }
    
    public function embed()
    {
        # check for direct image URL
        $regexImg = '/https?:\/\/\S+(?:png|jpg|jpeg|gif|svg)\b/';
        preg_match($regexImg, $this->url, $img);
    
        # check for direct video URL
        $regexVid = '/https?:\/\/\S+(?:mp4|webm|ogg)\b/';
        preg_match($regexVid, $this->url, $vid);
    
        if (!empty($img)) {
            $type = "image";
        } else if (!empty($vid)) {
            $type = "video";
        } else {
            $type = "oembed";
        }
    
        switch ($type) {
            # embed the image
            case 'image':
                return sprintf('<img src="%s" alt="" />', $this->url);
            break;
    
            # embed the video
            case 'video':
                return sprintf('
                    <video width="640" height="360" controls>
                        <source src="%s">
                        Your browser is too old to support native video.
                    </video>', $this->url);
            break;
    
            # anything else: embedly
            default:
                $content = '';
                $content = $this->embedVideo($this->url);
                $content.= $this->embedImage($this->url);
                return $content;
            break;
        }
    }

    private function embedVideo($videoUrl)
    {
        if (strpos($videoUrl, 'youtu.be') !== false || 
            strpos($videoUrl, 'youtube.com') !== false) {
            $oembed = 'https://www.youtube.com/oembed?url=';
        } elseif (strpos($videoUrl, 'vimeo.com') !== false) {
            $oembed = 'http://vimeo.com/api/oembed.json?url=';
        } elseif (strpos($videoUrl, 'dailymotion.com') !== false) {
            $oembed = 'http://www.dailymotion.com/services/oembed?format=json&url=';
        } elseif (strpos($videoUrl, 'blip.tv') !== false) {
            $oembed = 'http://blip.tv/oembed/&url=';
        } elseif (strpos($videoUrl, 'instagram.com') !== false ||
                  strpos($videoUrl, 'instagr.am') !== false) {
            $oembed = 'http://api.instagram.com/oembed?url=';
        }
        $jsonFile = file_get_contents($oembed . $videoUrl);
        $obj = json_decode($jsonFile);
        return $obj->html;
    }


    # utilize image hosts' api and return embed html (galleries included)
    private function embedImage($link)
    {
        $link = $this->fixUrl($link);
        $imgSites = array(
            "directURL"  => '/https?:\/\/\S+(?:png|jpg|gif|svg)\b/',
            "flickr"     => '/https?:\/\/[w\.]*flickr\.com\/photos\/([^?]*)/is', 
            "twitpic"    => '/https?:\/\/[w\.]*twitpic\.com\/([^?]*)/is', 
            "galimgur"   => '/https?:\/\/([a-z0-9]+[.])*imgur\.[^\/]*\/gallery\/([^?]*)/is',
            "imgur"      => '/https?:\/\/([a-z0-9]+[.])*imgur\.[^\/]*\/([^?]*)/is', 
            "deviantart" => '/https?:\/\/[^\/]*\.*deviantart\.[^\/]*\/([^?]*)/is', 
            "instagram"  => '/https?:\/\/[w\.]*instagram\.[^\/]*\/([^?]*)/is'
        );

        foreach($imgSites as $site => $regexp) {
            preg_match($regexp, $link, $match);
            if(!empty($match)) {
                switch ($site) {
                    case "directURL":
                    $img = "<img src=\"$link\" style=\"max-width:100%; alt=\"\" />";
                    break;
                    case "flickr":
                    $jsonFlickr = "http://www.flickr.com/services/oembed/?format=json&maxwidth=$maxWidth&maxheight=$maxHeight&url=".$link;
                    $img = $this->getJsonResponse($jsonFlickr);
                    break;
                    case "instagram":
                    $jsonInstagram = "http://api.instagram.com/oembed?format=json&maxwidth=$maxWidth&maxheight=$maxHeight&url=".$link;
                    $img = $this->getJsonResponse($jsonInstagram);
                    break;
                    case "deviantart":
                    $jsonDeviant = "http://backend.deviantart.com/oembed?format=json&thumbnail_width=$maxWidth&thumbnail_height=$maxHeight&url=".$link;
                    $img = $this->getJsonResponse($jsonDeviant);
                    break;
                    case "twitpic":
                        $code = $match[1];
                        $img = "<img src='http://twitpic.com/show/large/".$code.".jpg'>";
                    break;
                    case "galimgur":
                        $jsonImgur = "http://api.imgur.com/oembed/?format=json&url=".$link;
                        $img = $this->getJsonResponse($jsonImgur);
                    break;
                    case "imgur":
                        $jsonImgur = "http://api.imgur.com/oembed/?format=json&url=".$link;
                        $img = $this->getJsonResponse($jsonImgur);
                    break;
                    case "":
                    $img = "";
                    break;
                }
                return $img;
            }
        }
    }

    # function used to fix the url by adding http / https
    private function fixUrl($url) 
    {
        if (substr($url, 0, 7) == 'http://' || 
            substr($url, 0, 8) == 'https://') {
            return $url;
        } else return 'http://'. $url;
    }

    private function getJsonResponse($url)
    {
        $jsonResponse = $this->getUrlData($url);
        $res = json_decode($jsonResponse, true);
        if(is_array($res) && !empty($res)) {
            if (!empty($res["html"])) {
                return $res["html"];    
            } else {
                $img = "<img src='".$res["url"]."'>";
                return $img;
            }
        }
    }

    # curl function to get json response
    private function getUrlData($url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2.16) Gecko/20110319 Firefox/3.6.16");
        $curlData = curl_exec($curl);
        curl_close($curl);
        return $curlData;
    }

}