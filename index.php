<?php
/*
  Author:Motoc Vladimir
  skype:it_create
  mail:motoc_vladimir@mail.ru
 */

error_reporting(E_ALL);
date_default_timezone_set('Europe/Moscow');
ini_set('memory_limit', '2048M');
ini_set('mysql.connect_timeout', 36000000);
ini_set('default_socket_timeout', 36000000);
ini_set('max_allowed_packet', 200000);

require_once('helper.php');

class parser extends helper {

    //Database settings
    public $config_bd = array(
          'server' => 'localhost',
          'user_name' => 'parser',
          'user_pass' => '7777777mv',
          'bd_name' => 'parser');
    // Table Template
    public $table_temp = 'sites';
    //Table of the storage
    public $table_result = 'search_result';
    // A table with a row for the search
    public $table_string = 'string';
    // An array of links on the current page
    public $links = array();
    //The maximum number of sample records
    public $limit = 500;
    // Maximum number of iterations for desired search string
    private $max_iteration = 30;
    // Number of iterations
    private $iteration = 0;
    //Invalid extension
    public $not_valid_types = array('jpg', 'jpeg', 'txt', 'bmp', 'avi', 'flv', 'mp3', 'data', 'bmp', 'doc', 'xls', 'xlsx', 'ico', 'css', 'pdf', 'exe', 'png');
    //web pages extension
    public $page_valid_types = array('htm', 'html', 'php', 'asp', 'jsp', 'js');
    // Cache size for accessibility: Number of pages in the cache
    public $cache_url_limit=1000;
    // Content cache size: Number of pieces of content
    public $cache_html_limit=100;
    // Curl: while a connection in milliseconds
    private  $connecttimeout_ms=2000;
    // curl: time to work function in milliseconds
    private  $timeout_ms=2000;
    // curl: the maximum size of the retrieved content in bytes
    private  $size_download=100000;

    /**
     * Cache pages accessibility
     * @var array
     */
    private $_exist_url_cache = array();

    /**
     * Content Cache
     * @var type
     */
    private $_get_html_cache = array();

    /**
     *  Output line considering cli mode
     * @param string $str
     */
    private function _echo_log_str($str) {
        if (php_sapi_name() === 'cli') {
            $str = strip_tags(str_replace('<br>', PHP_EOL, $str));
        }
        echo $str;
    }

    //----------------------------------------------------------------------------
    // Rrecording data in the table 'sites'
    public function put_data_sites($data) {  // return void
        $site = $data['site'];
        $date_check = date("Y-m-d H:i:s");
        $this->reconnect();
        $sql = "SELECT id FROM sites WHERE site = '$site'";
        $query = $this->select_bd($sql);
        if (count($query) > 0) {
            $this->query_bd("UPDATE sites SET date_check='$date_check' WHERE site='$site'");
        } else {
            $this->insert_bd('sites', $data);
        }
    }

    /**
     * Output line to the log
     * @param string $str
     */
    public function console_log($str) {
        $this->_echo_log_str($str);
        flush();
    }

    //----------------------------------------------------------------------------
    // Console output for event search
    public function console_log_event($data) {
        $action = $data['action'];
        $path = $data['path'];
        $string_id = $data['string_id'];
        $event = $data['event'];
        $this->_echo_log_str(sprintf('Table: search_result # Action: %-10s # Site: % -80s # String_id: %d # Event: %s', $action, $path, $string_id, $event) . ' <br>');
        echo PHP_EOL;
        flush();
    }

    //----------------------------------------------------------------------------
    // The console output for a domain
    public function console_log_domain($data) {
        $domain = $data['domain'];
        $access = $data['access'];
        echo PHP_EOL;
        $this->_echo_log_str('[ ' . date("Y-m-d H:i:s") . ' ] ' . sprintf('<b>Parsing domain:</b> %-5s # access: %-5s', $domain, $access) . ' <br>');
        echo PHP_EOL;
        flush();
    }

    //----------------------------------------------------------------------------
    // The console output for a page
    public function console_log_page($data) {
        $url = $data['url'];
        $string_id = $data['string_id'];
        $access = $data['access'];
        $this->_echo_log_str(sprintf('Parsing url: %-5s # string_id %-5s # access: %-5s# %-5s#', $url, $string_id, $access, round(memory_get_usage(true)/1048576, 2)."Mb") . ' <br>');
        flush();
    }

    //----------------------------------------------------------------------------
    // The console output of the new domain insertion sites in the table
    public function console_log_put_in_sites($data) {
        $site = $data['site'];
        $this->_echo_log_str(sprintf('Add new domain : %-5s # site', $site, round(memory_get_usage(true)/1048576, 2)."Mb") . ' <br>');
        flush();
    }

    //----------------------------------------------------------------------------
    // Get  all strings to the domain
    public function get_strings() {// rerutn array
        $result = array();
        $table = $this->table_string;
        $sql = "SELECT id, string FROM $table WHERE is_active='1'";
        $this->reconnect();
        $result = $this->select_bd($sql);
        return $result;
    }

    //----------------------------------------------------------------------------
    //Search strings in html code
    public function find_string($html, $string) {// return boolean
        if (strpos($html, strtolower($string)) === false) {
            return false;
        } else {
            return true;
        }
    }

    //----------------------------------------------------------------------------
    // Check the validity of the page
    public function valid_url($url) {// return boolean
       //if(strpos("http://",$url)===false && strpos('https://',$url)===false)$url='http://'.$url;
        if (in_array(parse_url($url, PHP_URL_SCHEME), array('http', 'https'))) {
            if (filter_var($url, FILTER_VALIDATE_URL) !== false) {
                //valid url
                return true;
            } else {
                //not valid url
                return false;
            }
        } else {
            //no http or https
            return false;
        }
    }
    //----------------------------------------------------------------------------
    public function get_html($url) {// return string
        $html = '';

        $url_hash = md5($url);

        if (isset($this->_get_html_cache[$url_hash])) {
            return $this->_get_html_cache[$url_hash];
        }

        if (count($this->_get_html_cache) >$this->cache_html_limit) {
            $this->_get_html_cache = array();
        }

        if ($this->valid_url($url) && $this->exist_url($url)) {

            //$start = microtime();

            $curl = curl_init();
            //curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 2);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT_MS, $this->connecttimeout_ms);
            //curl_setopt($curl, CURLOPT_TIMEOUT, 5);
            curl_setopt($curl, CURLOPT_TIMEOUT_MS, $this->timeout_ms);
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HEADER, true);
            curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.116 Safari/537.36 OPR/35.0.2066.92');

            $html = curl_exec($curl);
            $size = (int)curl_getinfo($curl, CURLINFO_SIZE_DOWNLOAD);

            curl_close($curl);

            //$time_diff = $this->_microtime_diff($start);
            //$this->console_log("Time: get_html: {$url} {$time_diff} <br>");

            if ($size <= $this->size_download) {
                $this->_get_html_cache[$url_hash] =  $html;
            }

        } else {
            $this->_get_html_cache[$url_hash] = $html;
        }

        return $html;
    }

    /**
     ** Check the availability of the page
     * @param String $url URL, which check
     * @param Boolean $get_html get there at the same time the page content
     * @return Boolean
     */
    public function exist_url($url, $get_html = false) {

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $url_hash = md5($url);

        if (isset($this->_exist_url_cache[$url_hash])) {
            return $this->_exist_url_cache[$url_hash];
        }

        if (count($this->_exist_url_cache) > $this->cache_url_limit) {
            $this->_exist_url_cache = array();
        }

        $get_html = $get_html && !isset($this->_get_html_cache[$url_hash]);

        //$start = microtime();

        $curlInit = curl_init($url);
        //curl_setopt($curlInit, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($curlInit, CURLOPT_CONNECTTIMEOUT_MS, 1000);
        //curl_setopt($curlInit, CURLOPT_TIMEOUT, 5);
        curl_setopt($curlInit, CURLOPT_TIMEOUT_MS, 1000);
        curl_setopt($curlInit, CURLOPT_HEADER, true);
        curl_setopt($curlInit, CURLOPT_NOBODY, true);
        curl_setopt($curlInit, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlInit, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.116 Safari/537.36 OPR/35.0.2066.92');

        if ($get_html) {
            curl_setopt($curlInit, CURLOPT_CONNECTTIMEOUT_MS, $this->connecttimeout_ms);
            curl_setopt($curlInit, CURLOPT_TIMEOUT_MS, $this->timeout_ms);
            curl_setopt($curlInit, CURLOPT_HEADER, true);
            curl_setopt($curlInit, CURLOPT_NOBODY, false);
        }

        $response = curl_exec($curlInit);

        if ($get_html) {
            $size = (int) curl_getinfo($curlInit, CURLINFO_SIZE_DOWNLOAD);
            if ($size <= $this->size_download) {
                $this->_get_html_cache[$url_hash] = $response;
            }
        }

        curl_close($curlInit);

        /*
        $time_diff = $this->_microtime_diff($start);
        $str = "Time: exist_url: {$url} {$time_diff} <br>";
        if ($get_html) {
            $str = str_replace('exist_url', 'exist_and_get_url', $str);
        }
        $this->console_log($str);*/

        if ($response) {
            $this->_exist_url_cache[$url_hash] =  true;
            return true;
        }

        $this->_exist_url_cache[$url_hash] = false;

        return false;
    }
   //---------------------------------------------------------------------------------------------
   // Get all the links in the document
   // $url - link
   public function get_links($domain,$url) {

        $result = array();

        if (!$this->exist_url($url, true)) {
            return $result;
        }

        $result[] = $url;
        // Create a new object class
        $dom = new domDocument;
        // Get content
        @$dom->loadHTML($this->get_html($url));
        // Remove gaps
        $dom->preserveWhiteSpace = false;
        // Extract all the tags reference
        $links = $dom->getElementsByTagName('a');
        // Get the href attribute value to all links
        foreach ($links as $tag) {
            $href = $tag->getAttribute('href');
            if ($this->valid_url($href) && $this->if_valid_types($href)) {
                $result[] = $tag->getAttribute('href');
            }
        }

        if ($this->max_iteration < count($result)) {
            for ($i = 0; $i < $this->max_iteration; $i++) {
                $copy[] = $result[$i];
            }
        } else {
            $copy = $result;
        }
        /*
        foreach($copy as $key=>$value)
         {           $arr=parse_url($value);
           print_r($arr);
           $host=isset($arr['host'])? $arr['host']:'';
           $scheme=isset($arr['scheme'])? $arr['scheme']:'';
           $path=isset($arr['path'])? $arr['path']:'';

           if($this->if_page($path)&& strpos(".",$host)===false ){
           	 $url=$scheme.'://'.$domain.$host.$path;
           	 echo "$url<br>";
           	 //$url=str_replace($url,$url.'/',$url);
           	 $copy[$key]=$url;
           }         }
        */
        return $copy;

    }

    //----------------------------------------------------------------------------
    //recursion-parsing, obtaining links to internal and external pages
    public function parsing($all,$internal_pages,$external_pages,$level_links,$domain) {
      /*
       all- array of all links
       level_links- array next pass level
       enter_pages- array of internal links
       extern_pages- array of external links
       return arr ( 'all', 'enter_pages', 'extern_pages', 'level_links')
      */
     $links=array();// An array of references to read content
     $result = array('all' => array(), 'level_links' => array(),'internal_pages'=> array(),'external_pages' => array());// An array of results
      if (count($level_links) == 0) {
            $this->iteration++;
        }
        for ($i = 0; $i < count($level_links); $i++) {
        	     if($this->if_internal_page($domain,$level_links[$i])){// Check if the internal link; da drifts into an array enter_pages
        	       $links = $this->get_links($domain,$level_links[$i]);
        	       for($j=0;$j<count($links);$j++){
                    if($this->if_internal_page($domain,$links[$j])){                     $internal_pages[]=$links[$j];
                    }else{
                      $external_pages[]=$links[$j];
        	         }
        	       }
        	      }
                 $all = array_merge($all,$links);
                 $all = array_unique($all);
                 $internal_pages = array_unique($internal_pages);
                 $external_pages = array_unique($external_pages);
                 $result = array('all' => $all, 'level_links' => $links,'internal_pages' => $internal_pages,'external_pages' =>$external_pages );
                 $this->links = $result;
                 $this->iteration++;
        }
         if ($this->iteration >= $this->max_iteration){
        	     $this->iteration=0;
                 return 0;
         }
      $this->parsing($result['all'],$result['internal_pages'], $result['external_pages'],$result['level_links'],$domain);
    }

    //---------------------------------------------------------------------------
    // Number of entries in the table
    public function num_rows($table) {// return int
        $this->reconnect();
        $query = $this->select_bd("SELECT count(id) as count FROM $table WHERE date_check is NULL OR DATEDIFF(NOW(), date_check) > 30");
        $num_rows = $query[0]['count'];
        return $num_rows;
    }

    //---------------------------------------------------------------------------
    // 1 sample batch records from the table
    public function get_portion($table) {// return array
        $limit = $this->limit;
        $this->reconnect();
        $query = $this->select_bd("SELECT * FROM $table WHERE date_check is NULL OR DATEDIFF(NOW(), date_check) > 30 LIMIT $limit");
        return $query;
    }

    //--------------------------------------------------------------------------------------------
    //Identification and selection of which table wake pattern and result
    public function choose_table() {// return void
        $this->table_temp = 'sites';
        $this->table_result = 'search_result';
    }

    //--------------------------------------------------------------------------------------------
    //If the domain instead of page
    public function if_domain($url) {// return boolean
        if ($url[strlen($url) - 1] == '/') {
            $url = substr($url, 0, -1);
        }
        $url = str_replace(array('http://', 'https://'), '', $url);
        if (strpos($url, '/') === false && strpos($url, '?') === false && $url != '') {
            return true;
        } else {
            return false;
        }
    }

    //If page
    public function if_page($url) {// return boolean
        $arr_ext=explode('.',$url);
        $ext=end($arr_ext);
        return in_array($ext,$this->page_valid_types);
    }

    //--------------------------------------------------------------------------------------------
    //if there is a domain in the 'sites' table
    public function if_exist_in_sites($url) {// return boolean
        $this->reconnect();
        $sql = "SELECT id FROM sites WHERE site='" . $this->escape($url) . "' limit 1;";
        $query = $this->select_bd($sql);
        if (count($query) > 0) {
            return true;
        } else {
            return false;
        }
    }

    //--------------------------------------------------------------------------------------------
    //If there is an event 'found', then the new 'found' not generate
    public function if_exist_event($site_id, $string_id, $evt = 'found') { // return boolean true= found
        $this->reconnect();
        $sql = "SELECT event FROM search_result WHERE site_id = '$site_id' AND string_id = '$string_id' AND event = '$evt'    LIMIT 1";
        $query = $this->select_bd($sql);
        return count($query) == 1 ? true : false;
    }

    //--------------------------------------------------------------------------------------------
    //If the page file extension corresponds to the permissible expansion
    public function if_valid_types($url) {// return boolean; true=ok
        $_tmp = explode(".", mb_strtolower($url));
        $extension = end($_tmp);
        return !in_array($extension, $this->not_valid_types);
    }

    //--------------------------------------------------------------------------------------------
    //Get the domain name from a URL
    public function get_domain($url) {// return string
        $url = str_replace(array('http://', 'https://', 'www.'), '', $url);
        if (strpos($url, '/') === false) {
            return $url;
        } else {
            $arr = explode('/', $url);
            $url = $arr[0];
            return $url;
        }
    }

    // Whether the string has been previously
    //--------------------------------------------------------------------------------------------
    public function was_later($path, $string_id) { // return boolean
        $this->reconnect();
        $sql = "SELECT id FROM search_result WHERE path = '$path' AND string_id = " . (int) $string_id . " limit 1;";
        $query = $this->select_bd($sql);
        return count($query) > 0 ? true : false;
    }

    // If there is a url in the table search_result
    //--------------------------------------------------------------------------------------------
    public function if_exist_url($url) { // return boolean
        $this->reconnect();
        $sql = "SELECT `id` FROM search_result WHERE path = '" . $this->escape($url) . "'";
        $query = $this->select_bd($sql);
        return count($query) > 0 ? true : false;
    }

    // Id getting on the field and on the field value
    //--------------------------------------------------------------------------------------------
    public function get_id($url, $string_id) { // return int
        $this->reconnect();
        $sql = "SELECT id FROM search_result WHERE path = '" . $this->escape($url) . "' AND string_id = '$string_id'";
        $query = $this->select_bd($sql);
        return count($query) > 0 ? $query[0]['id'] : -1;
    }

    // Get the id for the event on site_id, string_id, event
    //--------------------------------------------------------------------------------------------
    public function get_id_to_event($site_id, $string_id, $event) { // return int
        $this->reconnect();
        $sql = "SELECT id FROM search_result WHERE site_id = '$site_id' AND  string_id = '$string_id'  AND  event = '$event'   LIMIT 1";
        $query = $this->select_bd($sql);
        return count($query) > 0 ? $query[0]['id'] : -1;
    }

    // Getting the last event on site_id, string_id
    //--------------------------------------------------------------------------------------------
    public function get_last_event($site_id, $string_id) { // return array or integer
        $res = array();
        $this->reconnect();
        $sql = "SELECT * FROM search_result WHERE site_id = '$site_id'  AND  string_id = '$string_id' ORDER BY id DESC LIMIT 1";
        $query = $this->select_bd($sql);
        if (count($query) > 0) {
            $res = array
                (
                'id' => $query[0]['id'],
                'event' => $query[0]['event'],
                'path' => $query[0]['path']
            );
        } else {
            $res = array
                (
                'event' => '-1'
            );
        }
        return $res;
    }

    // If an internal link for a domain
    //--------------------------------------------------------------------------------------------
    public function if_internal_page($domain, $url) {// return boolean
        if (strrpos($url, $domain) === false & $this->if_page($url)==false) {
            return false;
        } else {
            return true;
        }
    }

    // Receipt of all internal pages for the URL (domain) without http: //, https: //, and found or did not find the code for at least one
    //--------------------------------------------------------------------------------------------
    public function if_get_internal_pages($domain,$string) {// return  boolean
        $find = false;
        $this->parsing($all = array(),$internal=array(),$external=array(), $level_links = array($domain),$domain);
        $links = $this->links;
        foreach ($links['all'] as $key => $value) {
            if ($this->if_page($value)) {
                $value = $domain . '/' . $value;
            }
            if ($this->if_internal_page($domain, $value) && $this->find_string($value,$string)) {
                $find = true;
            }
        }
        return $find;
    }

    // Receipt of all internal pages for the URL (domain) without http: //, https: //
    //--------------------------------------------------------------------------------------------
    public function get_internal_pages($pages,$domain) {// return arr
        $result = array();
        foreach ($pages as $key => $value) {
            if ($this->if_page($value)) {
                $value = $domain . '/' . $value;
            }
            if ($this->if_internal_page($domain, $value)) {
                $result[] = $value;
            }
        }
        return $result;
    }

    //function-the-counter
    public function calc($result) {// return void
        //result- is an array of strings with the domain analysis
        $strings = $this->get_strings();
        for ($i = 0; $i < count($result); $i++) { // domains
            $id = $result[$i]['id'];
            $domain = 'http://' . $result[$i]['site'];
            //if the domain is not valid or is not available, move on ...
            if ($this->exist_url($domain) == false) {
                $access = 'false';
            } else {
                $access = 'true';
            }
            $data['domain']=$domain;
            $data['access']=$access;
            $this->console_log_domain($data);
            // Note in the database run by domain
            $data = array('site' => $result[$i]['site']);
            $this->put_data_sites($data);
            if ($access == 'false') continue;
            $this->parsing($all = array(),$internal_pages = array(),$external_pages = array(),$level_links = array($domain),$domain);
            $links_parsing=$this->links;
            //print_r($links_parsing);
            $internal_pages=$links_parsing['internal_pages'];
            $external_pages=$links_parsing['external_pages'];
            // attempt to find links on the domain with the new domain
            if (count($external_pages)>0) {
               foreach ($external_pages as $key => $value) {
                 $url = $this->get_domain($value);
                 if ($this->if_domain($url)) {
                    $data1 = array('site' => $url);
                    if ($this->if_exist_in_sites($url) == false) {
                        $this->put_data_sites($data1);
                        $this->console_log_put_in_sites($data1);
                    }
                }
              }
            }

            foreach ($internal_pages as $key => $value) {
                //1.Internal domain page
                $search = array('http://', 'https://');
                /*
                if ($this->if_page($value)) {
                    $value = $domain . '/' . $value;
                }
                */
                //If the link is not valid or is not available, then go ahead ...
                if ($this->exist_url($value, true) == false) {
                    $access = 'false';
                } else {
                    $access = 'true';
                }

                $url = str_replace($search, '', $value);
                if ($access == 'false') {
                    $data['url'] = $url;
                    $data['string_id'] = '';
                    $data['access'] = $access;
                    $this->console_log_page($data);
                    continue;
                }

                $html = $this->get_html($value);
                for ($j = 0; $j < count($strings); $j++) {  //2. Strings
                    $string = $strings[$j]['string'];
                    $string_id = $strings[$j]['id'];
                    $data['url'] = $url;
                    $data['string_id'] = $string_id;
                    $data['access'] = $access;
                    $this->console_log_page($data);
                    if ($this->find_string($html, $string)) {
                        //Search the last event for the domain and the line
                        $res = $this->get_last_event($id, $string_id);
                        $data = array(
                            'site_id' => $id,
                            'date' => date("Y-m-d H:i:s"),
                            'string_id' => $string_id,
                            'path' => $url,
                            'event' => 'found',
                            'send_crm' => '');
                        if ($res['event'] <> 'found') {
                            // if not found, then add
                            $this->reconnect();
                            $this->insert_bd('search_result', $data);
                            $data['action'] = "Insert";
                            $this->console_log_event($data);
                            //break 1; // выход на цикл 1.
                        } else {
                            // Find the latest event to found and update it
                            $data_event['path'] = $url;
                            $data_event['date'] = date("Y-m-d H:i:s");
                            $this->reconnect();
                            $this->update_bd('search_result', $data_event, $res['id']);
                            $data['action'] = "Update";
                            $this->console_log_event($data);
                            //break 1;
                        }
                    } else {
                        if ($this->was_later($url, $string_id)) {
                            if ($this->if_get_internal_pages($domain, $string) == false) {
                                // Search the last event for the domain and the line
                                $res = $this->get_last_event($id, $string_id);
                                $data = array('site_id' => $id,
                                    'date' => date("Y-m-d H:i:s"),
                                    'string_id' => $string_id,
                                    'path' => $res['path'],
                                    'event' => 'deleted',
                                    'send_crm' => '');
                                if ($res['event'] == 'found') {
                                    $this->reconnect();
                                    $this->insert_bd('search_result', $data);
                                    $data['action'] = "Insert";
                                    $this->console_log_event($data);
                                    //break 1;
                                } else {
                                    // Find the latest event to deleted and update it
                                    $data_event['path'] = $res['path'];
                                    $data_event['date'] = date("Y-m-d H:i:s");
                                    $this->reconnect();
                                    $this->update_bd('search_result', $data_event, $res['id']);
                                    $data['action'] = "Update";
                                    $this->console_log_event($data);
                                    //break 1;
                                }
                            }
                        }
                    }
                } // Loop;Strings end
            }//Loop;Internal Links end
        } //Loop;domains end
    }

    //--------------------------------------------------------------------------------------------
    //Start function
    public function start() {// return void
        $result = array(); // write a few one portion
        $limit = $this->limit;
        $table = $this->table_temp;
        $num_rows = $this->num_rows($table) + 1;
        $num_pages = floor($num_rows / $limit); // count pages
        // We read the one page
        echo "Total parsing sites: $num_rows, steps: $num_pages, per step: $limit <br>" . PHP_EOL;
        for ($i = 1; $i <= $num_pages; $i++) {
            $query = $this->get_portion($this->table_temp);
            $this->calc($query);
        }
        $delta = $num_rows - $limit*$num_pages;
        // Read the tail
        $sql = "SELECT * FROM $table WHERE date_check is NULL OR DATEDIFF(NOW(), date_check) > 30 LIMIT $delta";
        $this->reconnect();
        $query = $this->select_bd($sql);
        $this->calc($query);
    }

    /**
    * Calculate a precise time difference.
    * @param string $start result of microtime()
    * @param string $end result of microtime(); if NULL/FALSE/0/'' then it's now
    * @return flat difference in seconds, calculated with minimum precision loss
    */
    /*
    private function _microtime_diff($start, $end = null) {
        if (!$end) {
            $end = microtime();
        }
        list($start_usec, $start_sec) = explode(" ", $start);
        list($end_usec, $end_sec) = explode(" ", $end);
        $diff_sec = intval($end_sec) - intval($start_sec);
        $diff_usec = floatval($end_usec) - floatval($start_usec);
        return floatval($diff_sec) + $diff_usec;
    }
    */
   //-------------------------------------------------------------------------------------------------------------------
   //Clear table 'sites' results
   public function clear_sites() {
   	$this->query_bd("UPDATE sites SET date_check=NULL WHERE date_check<>-1");
   }
}

//-----------------------------------------------------------------------------------------------------------------------
$obj = new Parser();
$conect = $obj->conect_bd($obj->config_bd);


if(isset($_GET['clear']))
 {
   if ($conect == true)
    {
     $obj->clear_sites();
    }
 }
  else
   {
      if ($conect == true) {
      $time_start = microtime(true);
      $obj->choose_table();
      $obj->start();
      $obj->close_bd();
      $time_end = microtime(true);
      $time = $time_end - $time_start;
      $time = round($time, 2);
      print "<br><b>Working Time in sec: $time</b>";
   }
}
?>