<?php

error_reporting(E_ALL);
date_default_timezone_set('Europe/Moscow');
require_once('helper.php');

class crm extends helper {

   // Database settings
    public $config_bd = array('server' => 'localhost',
        'user_name' => 'parser',
        'user_pass' => '7777777mv',
        'bd_name' => 'parser');
    // An array of results
    public $result = array();
    // Database table
    public $table = 'search_result';
    //The maximum number of sample records
    public $limit = 500;
    // File with record results
    public $file = 'crm.data';

    public function __construct() {
        $this->conect_bd($this->config_bd);
    }

    //---------------------------------------------------------------------------------------------------------------------
    public function num_rows($table) {// return int
        if (!mysqli_ping($this->link_conect)) {
            $this->conect_bd($this->config_bd);
        }
        $query = $this->select_bd("SELECT count(*) as count FROM $table");
        $num_rows = $query[0]['count'];
        return $num_rows;
    }

    //---------------------------------------------------------------------------------------------------------------------
    public function get_portion() {// return void
        $limit = $this->limit;
        $table = $this->table;
        $query = array();
        $this->reconnect();
        $query = $this->select_bd("SELECT * FROM $table  WHERE send_crm='0000-00-00 00:00:00' OR send_crm='' GROUP BY site_id, event   ORDER BY date DESC LIMIT $limit");
        //отправка в CRM
        if (count($query) > 0) {
            $this->send_crm($query);
        }
    }

    //----------------------------------------------------------------------------------------------------------------------
    public function start() {// return void
        $limit = $this->limit;
        $table = $this->table;
        $query = $this->get_portion($this->table);

        $num_rows = $this->num_rows($table) + 1;
        $num_pages = floor($num_rows / $limit); //Count pages
        //We read the one page
        for ($i = 1; $i <= $num_pages; $i++) {
            $query = $this->get_portion($this->table);
        }
        $delta = $num_rows - $limit*$num_pages;
        //We read the tail
        $sql = "SELECT * FROM $table  WHERE send_crm='0000-00-00 00:00:00' OR send_crm='' GROUP BY site_id, event   ORDER BY date DESC LIMIT $delta";
        $this->reconnect();
        $query = $this->get_portion($this->table);
    }

    //----------------------------------------------------------------------------------------------------------------------
    // Preparation of data to be sent to crm
    public function send_crm($query) {
        $data = array();
        $send_crm = date("Y-m-d H:i:s");
        foreach ($query as $key => $value) {
            $id = $value['id'];
            $site_id = $value['site_id'];
            $string_id = $value['string_id'];
            $path = $value['path'];
            $event = $value['event'];
            $date = $value['date'];
            $this->reconnect();
            $query = $this->select_bd("SELECT * FROM sites WHERE id='$site_id' LIMIT 1");
            if (count($query) > 0) {
                $domain = $query[0]['site'];
            }
            $this->reconnect();
            $query = $this->select_bd("SELECT * FROM string WHERE id='$string_id' LIMIT 1");
            if (count($query) > 0) {
                $string = $query[0]['string'];
            }
            $data = array('id' => $id,
                'site_id' => $site_id,
                'domain' => $domain,
                'string' => $string,
                'path' => $path,
                'event' => $event,
                'date' => $date,);
            if ($this->api($data)) {
                $this->reconnect();
                $this->update_bd($this->table, array('send_crm' => $send_crm), $id);
            }
        }
    }

    //----------------------------------------------------------------------------------------------------------------------
    // Search record data to a file
    public function put_data_file($data) {
        $data = implode('|', $data);
        $file = $this->file;
        $f = fopen($file, 'a+');
        flock($f, LOCK_EX);
        fputs($f, "$data\r\n");
        fflush($f);
        flock($f, LOCK_UN);
        fclose($f);
        @chmod("$fp", 0644);
    }

    //----------------------------------------------------------------------------------------------------------------------
    // console report
    public function console_log($data) {
        $domain = $data['domain'];
        $path = $data['path'];
        $string = $data['string'];
        $event = $data['event'];
        echo sprintf('CRM put: <b>Domain:</b> % -80s # String: %d # <b>Url:</b> %d # <b>Event:</b> %s', $domain, $path, $string, $event) . ' <br>' . PHP_EOL;
        echo PHP_EOL;
        flush();
    }

    //----------------------------------------------------------------------------------------------------------------------
    //  API : for test now it FILE
    public function api($data) {
        $domain = $data['domain'];
        $site_id = $data['site_id'];
        $date = $data['date'];
        $path = $data['path'];
        $string = $data['string'];
        $event = $data['event'];
        $this->console_log($data);
        if ($event == 'found') {
            $event = "Find";
        } else {
            $event = "Deleted";
        }
        // delete this code if you in in your API
        $data = array("name" => $event,"description" => 'site_id:' . $site_id . '|' . 'Domain:' . $domain . '|' . 'Page:' . $path . '|' . 'Find string:' . $string . '|' . 'Date:' . $date);// delete this
        $this->put_data_file($data); // delete this
        /* CRM real
          there you code to send in CRM system
         */
        return true;
    }
    //----------------------------------------------------------------------------------------------------------------------
    // Clear result in 'search_result' table
    public function clear_search_result() {
      $this->query_bd("UPDATE search_result SET send_crm=NULL WHERE send_crm<>-1");  }


}

//---------------------------------------------------------------------------------------------------------------------
$obj = new crm();
if(isset($_GET['clear']))
 {
   $obj->clear_search_result();
 }
  else
   {    $obj->start();   }
//---------------------------------------------------------------------------------------------------------------------
?>