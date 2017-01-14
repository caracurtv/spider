<?php

/*
  Author:Motoc Vladimir
  skype:it_create
  mail:motoc_vladimir@mail.ru
 */

// class includes helper methods to work with database
class helper {

    public $conect = false; // Type of connection to the server (DB) (false-not successful, true-successfully)
    public $link_conect = false; //A pointer to the connection to the database server

    //-------Connection to the database--------------
    public function conect_bd($arr) {
        /*
          $arr ['server'] - the server
          $arr ['user_name'] - the user name
          $arr ['user_pass'] - the user's password
          $arr ['bd_name'] - name of the database
         */

        $this->conect = true;
        //    $this->conect_bd($this->config_bd);
        $this->link_conect = mysqli_connect($arr['server'], $arr['user_name'], $arr['user_pass'], $arr['bd_name']);
        //if (!mysqli_ping($this->link_conect)) $this->link_conect= mysqli_connect($arr['server'],$arr['user_name'],$arr['user_pass'],$arr['bd_name']);
        if ($this->link_conect == false) {
            echo 'Connection MYSQL database server is not installed!';
            $this->conect = false;
            exit;
        }
        $conect_bd = mysqli_select_db($this->link_conect, $arr['bd_name']);
        if ($conect_bd == false) {
            echo 'A database connection is not established!';
            $this->conect = false;
            exit;
        }
        mysqli_query($this->link_conect, "SET NAMES 'utf8';");
        return $this->conect;
    }

    //-------close database ------------------
    public function close_bd() {
        mysqli_close($this->link_conect);
    }

    public function escape($str) {
        return mysqli_real_escape_string($this->link_conect, $str);
    }

    //-------Querying the database--------
    public function query_bd($sql) { // return void
        $res = mysqli_query($this->link_conect, $sql);
        if (!$res) {
            echo "<br>Cant't do $sql!";
            echo mysqli_error($this->link_conect) . '<br>';
        }
    }

    //-------adding records to the database--------
    public function insert_bd($table_name, $arr) { // arr- an array of results
        $result = true;
        $sql = '';
        if ($this->conect == false) {
            echo 'No connection to the database, an entry in the table is not possible!';
            $result = false;
            exit;
        } else {
            $str_1 = "INSERT INTO `$table_name` (";
            $str_2 = "";
            $str_3 = "(";
            $len = count($arr);
            $i = 0;
            foreach ($arr as $key => $value) {
                $value = str_replace("'", "", $value);
                $key = str_replace("'", "", $key);
                if ($len - 1 > $i) {
                    $str_2 = $str_2 . "`" . $key . "`" . ',';
                    $str_3 = $str_3 . "'" . $value . "'" . ',';
                } else {
                    $str_2 = $str_2 . "`" . $key . "`)";
                    $str_3 = $str_3 . "'" . $value . "');";
                }
                $i++;
            }
            $sql = $str_1 . $str_2 . " VALUES " . $str_3;
            mysqli_query($this->link_conect, "SET CHARACTER SET utf8");
            mysqli_query($this->link_conect, "SET collation_connection = utf8");
            $res=mysqli_query($this->link_conect, $sql);
            if (!$res) {
                echo "<br>Cant't do insert in $table_name!";
                echo $sql . '<br>';
                echo mysqli_error($this->link_conect) . '<br>';
                $result = false;
            }
        }
        return $result;
    }

    //-------update records in the database--------
    public function update_bd($table_name, $arr, $id) {
        $result = true;
        $sql = '';
        if ($this->conect == false) {
            echo "Can't do connection with DB";
            $result = false;
            exit;
        } else {
            $str_1 = "UPDATE `$table_name` SET ";
            $str_2 = "";
            $str_3 = " WHERE `id`=";
            $len = count($arr);
            $i = 0;
            foreach ($arr as $key => $value) {
                if ($len - 1 > $i) {
                    $str_2 = $str_2 . "`" . $key . "`='" . $value . "',";
                } else {
                    $str_2 = $str_2 . "`" . $key . "`='" . $value . "'";
                }
                $i++;
            }
            $sql = $str_1 . $str_2 . $str_3 . $id;
            $res=mysqli_query($this->link_conect, $sql);
            if (!$res) {
                echo "<br>Can't do update: $sql";
                $result = false;
         }
        }
        return $result;
    }

    //--------Removal from the database records--------
    public function delete_bd($table_name, $id) {
        $result = true;
        $sql = "DELETE FROM  $table_name WHERE `id`=";
        if ($this->conect == false) {
            echo 'No connection to the database, deleting is not possible!';
            $result = false;
            exit;
        } else {
            $sql = $sql . $id;
            $res=mysqli_query($this->link_conect, $sql);
            if (!$res) {
                echo "<br>No connection to the database, deleting is not possible!";
                $result = false;
                exit;
            }
            //mysql_close($this->link_conect);
        }
        return $result;
    }

    public function reconnect() {
        if (!mysqli_ping($this->link_conect)) {
            $this->conect_bd($this->config_bd);
            mysqli_query($this->link_conect, "SET NAMES 'utf8';");
        }
    }

    //--------Select query from the database------
    public function select_bd($sql) {
        $arr = array();
        if ($this->conect == false) {
            echo "Cant't do connection!";
            exit;
        } else {
            //$this->reconnect();
            $res = mysqli_query($this->link_conect, $sql);
            if (!$res) {
                echo "Can not do this query:$sql<br>".PHP_EOL;
            } else {
                if (mysqli_num_rows($res) == 1) {
                    $arr[] = mysqli_fetch_array($res);
                } else {
                    while ($row = mysqli_fetch_array($res)) {
                        $arr[] = $row;
                    }
                }
            }
           //mysqli_free_result($res);
        }
        return $arr;
    }

//---------------------------------------------------------------------------------------
//clear table
    public function clear_table($table) {
        if ($this->conect == false) {
            echo "Cant't do connection!";
            exit;
        } else {
            mysqli_query($this->link_conect, "DELETE FROM $table");
        }
    }

}

?>