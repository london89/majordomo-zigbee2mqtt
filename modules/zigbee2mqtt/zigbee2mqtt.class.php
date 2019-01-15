<?php
/**
* MQTT 
*
* Mqtt
*
* @package project
* @author Serge J. <jey@tut.by>
* @copyright http://www.atmatic.eu/ (c)
* @version 0.1 (wizard, 13:07:08 [Jul 19, 2013])
*/
//
//
class zigbee2mqtt extends module {
/**
* mqtt
*
* Module class constructor
*
* @access private
*/
function zigbee2mqtt() {
  $this->name="zigbee2mqtt";
  $this->title="zigbee2mqtt";
  $this->module_category="<#LANG_SECTION_DEVICES#>";
  $this->checkInstalled();
}
/**
* saveParams
*
* Saving module parameters
*
* @access public
*/
function saveParams($data=0) {
 $p=array();
 if (IsSet($this->id)) {
  $p["id"]=$this->id;
 }
 if (IsSet($this->view_mode)) {
  $p["view_mode"]=$this->view_mode;
 }
 if (IsSet($this->edit_mode)) {
  $p["edit_mode"]=$this->edit_mode;
 }
 if (IsSet($this->tab)) {
  $p["tab"]=$this->tab;
 }
 return parent::saveParams($p);
}
/**
* getParams
*
* Getting module parameters from query string
*
* @access public
*/
function getParams() {
  global $id;
  global $mode;
  global $view_mode;
  global $edit_mode;
  global $tab;
  if (isset($id)) {
   $this->id=$id;
  }
  if (isset($mode)) {
   $this->mode=$mode;
  }
  if (isset($view_mode)) {
   $this->view_mode=$view_mode;
  }
  if (isset($edit_mode)) {
   $this->edit_mode=$edit_mode;
  }
  if (isset($tab)) {
   $this->tab=$tab;
  }
}
/**
* Run
*
* Description
*
* @access public
*/
function run() {
 global $session;
  $out=array();
  if ($this->action=='admin') {
   $this->admin($out);
  } else {
   $this->usual($out);
  }
  if (IsSet($this->owner->action)) {
   $out['PARENT_ACTION']=$this->owner->action;
  }
  if (IsSet($this->owner->name)) {
   $out['PARENT_NAME']=$this->owner->name;
  }
  $out['VIEW_MODE']=$this->view_mode;
  $out['EDIT_MODE']=$this->edit_mode;
  $out['MODE']=$this->mode;
  $out['ACTION']=$this->action;
  $out['TAB']=$this->tab;
  if (IsSet($this->location_id)) {
   $out['IS_SET_LOCATION_ID']=1;
  }
  if ($this->single_rec) {
   $out['SINGLE_REC']=1;
  }
  $this->data=$out;
  $p=new parser(DIR_TEMPLATES.$this->name."/".$this->name.".html", $this->data, $this);
  $this->result=$p->result;
}

  function prepareQueueTable() {
   //SQLExec ("DROP TABLE IF EXISTS `mqtt_queue`;");
   $sqlQuery = "CREATE TABLE IF NOT EXISTS `zigbee2mqtt_queue`
               (`ID`  int(10) unsigned NOT NULL auto_increment,
                `PATH` varchar(255) NOT NULL,
                `VALUE` varchar(255) NOT NULL,
                 PRIMARY KEY (`ID`)
               ) ENGINE = MEMORY DEFAULT CHARSET=utf8;";
    SQLExec ( $sqlQuery );   
  }

    function pathToTree($array){
        $tree = array();
        foreach($array AS $item) {
            $pathIds = explode("/", ltrim($item["PATH"], "/") .'/'. $item["ID"]);
            $current = &$tree;
            $cp='';
            foreach($pathIds AS $id) {
                if(!isset($current["CHILDS"][$id])) {
                    $current["CHILDS"][$id] = array('CP'=>$cp);
                }
                $current = &$current["CHILDS"][$id];
                if($id == $item["ID"]) {
                    $current = $item;
                }
            }
        }
        return ($this->childsToArray($tree['CHILDS']));
    }

    function childsToArray($items,$prev_path='') {
        $res=array();
        foreach($items as $k=>$v) {  
            if (!$v['PATH']) {
                $v['TITLE']=$k.' '.$v['CP'];
                $pp = $k; 
            }
            else {
                $v['TITLE'] = '';
                $pp = '';
            }
            if (isset($v['CHILDS'])) {
                $items=$this->childsToArray($v['CHILDS'],$prev_path!='' ? $prev_path.'/'.$pp : $pp);
                if (count($items)==1) {
                    $v=$items[0];     
                    $v['TITLE'] = $pp.($v['TITLE']!='' ? '/'.$v['TITLE'] : '');
                } else {
                    $v['RESULT']=$items;
                }
                unset($v['CHILDS']);
            }
            $res[]=$v;
        }
        return $res;
    }

/**
* Title
*
* Description
*
* @access public
*/
 function setProperty($id, $value, $set_linked=0) {
debmes('Нужно изменить значение id='.$id.' на '.$value, 'zigbee2mqtt');

debmes("SELECT * FROM zigbee2mqtt WHERE ID='".$id."'", 'zigbee2mqtt');
  $rec=SQLSelectOne("SELECT * FROM zigbee2mqtt WHERE ID='".$id."'");

  if (!$rec['ID'] || !$rec['PATH']) {
debmes('Не хватает данных', 'zigbee2mqtt');
   return 0;
  }


     if ($rec['REPLACE_LIST']!='') {
         $list=explode(',',$rec['REPLACE_LIST']);
         foreach($list as $pair) {
             $pair=trim($pair);
             list($new,$old)=explode('=',$pair);
             if ($value==$old) {
                 $value=$new;
                 break;
             }
         }
     }
  //if ($new_connection) {

  include_once("./lib/mqtt/phpMQTT.php");

   $this->getConfig();
   if ($mqtt->config['MQTT_CLIENT']) {
    $client_name=$mqtt->config['MQTT_CLIENT'];
   } else {
    $client_name="MajorDoMo MQTT";
   }

   if ($this->config['MQTT_AUTH']) {
    $username=$this->config['MQTT_USERNAME'];
    $password=$this->config['MQTT_PASSWORD'];
   }
   if ($this->config['MQTT_HOST']) {
    $host=$this->config['MQTT_HOST'];
   } else {
    $host='localhost';
   }
   if ($this->config['MQTT_PORT']) {
    $port=$this->config['MQTT_PORT'];
   } else {
    $port=1883;
   }

   $mqtt_client = new phpMQTT($host, $port, $client_name.' Client');

   if(!$mqtt_client->connect(true, NULL,$username,$password))
   {
debmes('Ошибка подключения к mqtt', 'zigbee2mqtt');
    return 0;
   }


if ($rec['CONVERTONOFF']=='1') {
if ($value=='1')  $json=array( $rec['METRIKA']=> 'ON');
if ($value=='0')  $json=array( $rec['METRIKA']=> 'OFF');
$jsonvalue=json_encode($json) ;


} else 
{
$json=array( $rec['METRIKA']=> $value);
$jsonvalue=json_encode($json) ;
}
debmes('Публикую zigbee2mqqtt '.$rec['PATH_WRITE'].'/set'.":".$jsonvalue, 'zigbee2mqtt');


   if ($rec['PATH_WRITE']) {

   $mqtt_client->publish($rec['PATH_WRITE'].'/set',$jsonvalue, (int)$rec['QOS'], (int)$rec['RETAIN']);
       
   }

// else {    $mqtt_client->publish($rec['PATH'],$jsonvalue, (int)$rec['QOS'], (int)$rec['RETAIN']);   }
   $mqtt_client->close();

  /*
  } else {

   $this->prepareQueueTable();
   $data=array();
   $data['PATH']=$rec['PATH'];
   $data['VALUE']=$value;
   SQLInsert('mqtt_queue', $data);

  }
  */

  $rec['VALUE']=$value.'';
  $rec['UPDATED']=date('Y-m-d H:i:s');
  SQLUpdate('zigbee2mqtt', $rec);


  if ($set_linked && $rec['LINKED_OBJECT'] && $rec['LINKED_PROPERTY']) {
   setGlobal($rec['LINKED_OBJECT'].'.'.$rec['LINKED_PROPERTY'], $value, array($this->name=>'0'));
  }

 }

/**
* Title
*
* Description
*
* @access public
*/
 function processMessage($path, $value) {
   if (preg_match('/\#$/', $path)) {
    return 0;
   }

   if (preg_match('/^{/',$value)) {
       $ar=json_decode($value,true);
       foreach($ar as $k=>$v) {
           if (is_array($v))
               $v = json_encode($v);
           $this->processMessage($path.'/'.$k,$v);
       }
   }

   /* Search 'PATH' in database (db) */
$dev_title=explode('/',$path)[1];
//echo $path.":".$dev_title."<br>";

//if (strpos($dev_title,"/set/")==0)
if (strpos($path,"set")===false)
{
     $rec=SQLSelectOne("SELECT * FROM zigbee2mqtt_devices WHERE IEEEADDR LIKE '%".DBSafe($dev_title)."%'");
     
     if(!$rec['ID']) { /* If path_write foud in db */
     $rec['TITLE']=$dev_title;
     $rec['IEEEADDR']=$dev_title;

     $rec['FIND']=date('Y-m-d H:i:s');
SQLInsert('zigbee2mqtt_devices', $rec);
     $this->refresh_db();

       }
else 
{
     $rec['IEEEADDR']=$dev_title;
     $rec['FIND']=date('Y-m-d H:i:s');
SQLUPDATE('zigbee2mqtt_devices', $rec);

}
       $dev_id=SQLSelectOne("SELECT * FROM zigbee2mqtt_devices WHERE TITLE LIKE '%".DBSafe($dev_title)."%'")['ID'];





   $rec=SQLSelectOne("SELECT * FROM zigbee2mqtt WHERE PATH LIKE '".DBSafe($path)."'");
   
   if(!$rec['ID']){ /* If 'PATH' not found in db */
     /* New query to search 'PATH_WRITE' record in db */
     $rec=SQLSelectOne("SELECT * FROM zigbee2mqtt WHERE PATH LIKE '".DBSafe($path)."'");
     
     if($rec['ID']) { /* If path_write foud in db */
       if($rec['DISP_FLAG']!="0"){ /* check disp_flag */
         return 0; /* ignore message if flag checked */
       }
     }
     /* Insert new record in db */
     $rec['PATH']=$path;
     $rec['METRIKA']=substr($path,strrpos($path,'/')+1); 
     $rec['PATH_WRITE']=substr($path,0,strrpos($path,'/')); 
//     $rec['METRIKA']="1"; 
     $rec['DEV_ID']=$dev_id;
     $rec['TITLE']=$path;
     $rec['VALUE']=$value.'';
     $rec['UPDATED']=date('Y-m-d H:i:s');
     $rec['ID']=null;
SQLInsert('zigbee2mqtt', $rec);
   }else{
     /* Update values in db */
     $rec['VALUE']=$value.'';
     $rec['DEV_ID']=$dev_id;
     $rec['UPDATED']=date('Y-m-d H:i:s');

     SQLUpdate('zigbee2mqtt', $rec);
     /* Update property in linked object if it exist */
     if($rec['LINKED_OBJECT'] && $rec['LINKED_PROPERTY']) {

         $value=$rec['VALUE'];
         if ($rec['REPLACE_LIST']!='') {
             $list=explode(',',$rec['REPLACE_LIST']);
             foreach($list as $pair) {
                 $pair=trim($pair);
                 list($new,$old)=explode('=',$pair);
                 if ($value==$new) {
                     $value=$old;
                     break;
                 }
             }
         }


if ($rec['CONVERTONOFF']=='1' && $value=='ON') $newvalue=1;
if ($rec['CONVERTONOFF']=='1' && $value=='OFF') $newvalue=0;


//пишем в переменную
//       setGlobal($rec['LINKED_OBJECT'].'.'.$rec['LINKED_PROPERTY'], $newvalue, array($this->name=>'0'));
     }
     if ($rec['LINKED_OBJECT'] && $cmd_rec['LINKED_METHOD']) {
       callMethod($rec['LINKED_OBJECT'] . '.' . $rec['LINKED_METHOD'], $rec['VALUE']);
     }

   }
}




 }

/**
* BackEnd
*
* Module backend
*
* @access public
*/
function admin(&$out) {
 if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
  $out['SET_DATASOURCE']=1;
 }

 $this->getConfig();
 $out['MQTT_CLIENT']=$this->config['MQTT_CLIENT'];
 $out['MQTT_HOST']=$this->config['MQTT_HOST'];
 $out['MQTT_PORT']=$this->config['MQTT_PORT'];
 $out['ZIGBEE2MQTTPATH']=$this->config['ZIGBEE2MQTTPATH'];
 $out['MQTT_QUERY']=$this->config['MQTT_QUERY'];

 if (!$out['MQTT_HOST']) {
  $out['MQTT_HOST']='localhost';
 }


 if (!$out['MQTT_CLIENT']) {
  $out['MQTT_CLIENT']='md_zigbee2mqtt';
 }


 if (!$out['ZIGBEE2MQTTPATH']) {
  $out['ZIGBEE2MQTTPATH']='/opt/zigbee2mqtt/';
 }


 if (!$out['MQTT_PORT']) {
  $out['MQTT_PORT']='1883';
 }
 if (!$out['MQTT_QUERY']) {
  $out['MQTT_QUERY']='zigbee2mqtt/#';
 }

 $out['MQTT_USERNAME']=$this->config['MQTT_USERNAME'];
 $out['MQTT_PASSWORD']=$this->config['MQTT_PASSWORD'];
 $out['MQTT_AUTH']=$this->config['MQTT_AUTH'];


     if ($this->tab=='edit_device') {

//if ( $this->TAB=='edit_device') {
//$vm=$this->VIEW_MODE;
// echo "<script type='text/javascript'>";
// echo "alert('$vm');";
// echo "</script>";


//echo '123';

  $res=SQLSelectOne("SELECT * FROM zigbee2mqtt_devices where ID=".$this->id);



$out['ID']=$res['ID'];
$out['TITLE']=$res['TITLE'];
$out['MODEL']=$res['MODEL'];
$out['TYPE']=$res['TYPE'];
$out['IEEADDR']=$res['IEEADDR'];
$out['NWKADDR']=$res['NWKADDR'];
$out['MANIFID']=$res['MANIFID'];
$out['MANUFNAME']=$res['MANUFNAME'];
$out['POWEDSOURCE']=$res['POWEDSOURCE'];
$out['MODELID']=$res['MODELID'];
$out['STATUS']=$res['STATUS'];
$out['DID']=$res['DID'];
$out['D_ID']=$res['D_ID'];
$out['FIND']=$res['FIND'];
$out['LOCATION_ID']=$res['LOCATION_ID'];

$res1=SQLSelectOne("SELECT * FROM zigbee2mqtt_devices_list where zigbeeModel='".$res['MODEL']."'");
$out['MODELNAME']=$res1['model'];
$out['VENDOR']=$res1['vendor'];
$out['DESCRIPTION']=$res1['description'];
$out['EXTEND']=$res1['extend'];
$out['SUPPORTS']=$res1['supports'];
$out['FROMZIGBEE']=$res1['fromZigbee'];
$out['TOZIGBEE']=$res1['toZigbee'];

  //options for 'LOCATION_ID' (select)
  $tmp=SQLSelect("SELECT ID, TITLE FROM locations ORDER BY TITLE");
  $locations_total=count($tmp);
  for($locations_i=0;$locations_i<$locations_total;$locations_i++) {
   $location_id_opt[$tmp[$locations_i]['ID']]=$tmp[$locations_i]['TITLE'];
  }
  for($i=0;$i<count($tmp);$i++) {
   if ($rec['LOCATION_ID']==$tmp[$i]['ID']) $tmp[$i]['SELECTED']=1;
  }
  $out['LOCATION_ID_OPTIONS']=$tmp;

}

     if ($this->view_mode=='update_device') {
//$vm=$this->id;
// echo "<script type='text/javascript'>";
// echo "alert('$vm');";
// echo "</script>";


  $table_name='zigbee2mqtt_devices';
  $rec=SQLSelectOne("SELECT * FROM $table_name WHERE ID='".$this->id."'");

   global $dev_title;
   $rec['TITLE']=$dev_title;
   if ($rec['TITLE']=='') {
    $out['ERR_TITLE']=1;
    $ok=0;
   }




   global $dev_location_id;
  $rec['LOCATION_ID']=$dev_location_id;
   
//$vm=$dev_location_id;
// echo "<script type='text/javascript'>";
// echo "alert('$vm');";
// echo "</script>";



  //UPDATING RECORD
  
    if ($rec['ID']) {
     SQLUpdate($table_name, $rec); // update
    } else {
     $new_rec=1;
     $rec['ID']=SQLInsert($table_name, $rec); // adding new record
    }
    

 $this->redirect("?view_mode=view_mqtt&id=".$this->id."&tab=edit_device");
}






 if ($this->view_mode=='update_log') {

// if ($this->update_log=='update_log') {
 $this->getConfig();

global $file;
global $limit;
$zigbee2mqttpath=$this->config['ZIGBEE2MQTTPATH'];
$filename=$zigbee2mqttpath.'/data/log/'.$file.'/log.txt';
$out['FN']=$filename;
//$out['FN']="1234";

$a=file_get_contents ($filename);
$a =  str_replace( array("\r\n","\r","\n") , '<br>' , $a);
$out['LOG']=$a;


            $path = $zigbee2mqttpath.'/data/log';

            if ($handle = opendir($path)) {
                $files = array();

                while (false !== ($entry = readdir($handle))) {
                    if ($entry == '.' || $entry == '..')
                        continue;

                    $files[] = array('TITLE' => $entry);
                }

                sort($files);
            }

            $out['FILES'] = $files;

//$this->search_mqtt($out);





                    

//$vm1=$filename;
// echo "<script type='text/javascript'>";
// echo "alert('$vm1');";
// echo "</script>";

// $this->redirect("?tab=log");

}




//$vm1=$this->view_mode;
// echo "<script type='text/javascript'>";
// echo "alert('$vm1');";
// echo "</script>";


 if ($this->tab=='service') {

$a=shell_exec("sudo systemctl status zigbee2mqtt");
$a =  str_replace( array("\r\n","\r","\n") , '<br>' , $a);
$out['status']=$a;


}

 if ($this->tab=='map') {
$maparray=SQLSelectOne ('select * from zigbee2mqtt where value like "digraph%"');

$out['map_array']=$maparray['VALUE'];


}



 if ($this->view_mode=='srv_start') {
$a=shell_exec("sudo systemctl start zigbee2mqtt");
$a=shell_exec("sudo systemctl status zigbee2mqtt");
$a =  str_replace( array("\r\n","\r","\n") , '<br>' , $a);
$out['status']=$a;
   $this->redirect("?tab=service");

}

 if ($this->view_mode=='srv_stop') {
$a=shell_exec("sudo systemctl stop zigbee2mqtt");
$a=shell_exec("sudo systemctl status zigbee2mqtt");
$a =  str_replace( array("\r\n","\r","\n") , '<br>' , $a);
$out['status']=$a;
   $this->redirect("?tab=service");

}

 if ($this->view_mode=='srv_restart') {
$a=shell_exec("sudo systemctl restart zigbee2mqtt");
$a=shell_exec("sudo systemctl status zigbee2mqtt");
$a =  str_replace( array("\r\n","\r","\n") , '<br>' , $a);
$out['status']=$a;


   $this->redirect("?tab=service");
}


 if ($this->view_mode=='get_map') {

   $this->get_map();
   $this->redirect("?tab=map");
}





 if ($this->view_mode=='update_settings') {

//$vm1=$this->view_mode;
// echo "<script type='text/javascript'>";
// echo "alert('$vm1');";
// echo "</script>";


   global $mqtt_client;
   global $mqtt_host;
   global $mqtt_username;
   global $mqtt_password;
   global $mqtt_auth;
   global $mqtt_port;
   global $mqtt_query;
   global $zigbee2mqttpath;
//echo $zigbee2mqttpath;

   $this->config['MQTT_CLIENT']=trim($mqtt_client);
   $this->config['ZIGBEE2MQTTPATH']=trim($zigbee2mqttpath);
   $this->config['MQTT_HOST']=trim($mqtt_host);
   $this->config['MQTT_USERNAME']=trim($mqtt_username);
   $this->config['MQTT_PASSWORD']=trim($mqtt_password);
   $this->config['MQTT_AUTH']=(int)$mqtt_auth;
   $this->config['MQTT_PORT']=(int)$mqtt_port;
   $this->config['MQTT_QUERY']=trim($mqtt_query);
   $this->saveConfig();

   setGlobal('cycle_zigbee2mqttControl', 'restart');

   $this->redirect("?tab=settings");
 }

 if (!$this->config['MQTT_HOST']) {
  $this->config['MQTT_HOST']='localhost';
  $this->saveConfig();
 }
 if (!$this->config['MQTT_PORT']) {
  $this->config['MQTT_PORT']='1883';
  $this->saveConfig();
 }

 if (!$this->config['ZIGBEE2MQTTPATCH']) {
  $this->config['ZIGBEE2MQTTPATCH']='/opt/zigbee2mqtt/';
  $this->saveConfig();
 }


 if (!$this->config['MQTT_QUERY']) {
  $this->config['MQTT_QUERY']='zigbee2mqtt/#';
  $this->saveConfig();
 }


 if ($this->data_source=='mqtt' || $this->data_source=='') {
//  if ($this->view_mode=='' || $this->view_mode=='search_mqtt') {
   $this->search_mqtt($out);
  }




  if ($this->view_mode=='edit_mqtt') {
   $this->edit_mqtt($out, $this->id);
  }
  if ($this->view_mode=='delete_mqtt') {
   $this->delete_mqtt($this->id);
   $this->redirect("?");
  }
     if ($this->view_mode=='clear_trash') {
         $this->clear_trash();
         $this->redirect("?");
     }

     if ($this->view_mode=='refresh_db') {
         $this->refresh_db();
         $this->redirect("?");
     }









 }


function clear_trash() {
    $res=SQLSelect("SELECT ID FROM zigbee2mqtt WHERE LINKED_OBJECT='' AND LINKED_PROPERTY=''");
//    $res=SQLSelect("SELECT ID FROM zigbee2mqtt_devices WHERE LINKED_OBJECT='' AND LINKED_PROPERTY=''");
    $total = count($res);
    for ($i=0;$i<$total;$i++) {
        $this->delete_mqtt($res[$i]['ID']);
    }
}


function refresh_db() {
 $this->getConfig();
$zigbee2mqttpath=$this->config['ZIGBEE2MQTTPATH'];
$filename=$zigbee2mqttpath.'/data/database.db';
$a=file_get_contents ($filename);

$settings = explode("\n", $a);

    $total = count($settings);
    for ($i=0;$i<$total-1;$i++) {
	$json=json_decode($settings[$i]);
        foreach ($json as $key=> $value) {if ($key=='ieeeAddr') $cdev=$value;	  }

$sql="SELECT * FROM zigbee2mqtt_devices where IEEEADDR='$cdev'";
debmes($sql,'zigbee2mqtt');
    $res=SQLSelectOne($sql);
     if($res['ID']) { /* If path_write foud in db */
{
debmes($cdev.' � ������ ������','zigbee2mqtt');

        foreach ($json as $key=> $value) {
if ($key=='type') $res['TYPE']=$value;
if ($key=='nwkAddr') $res['NWKADDR']=$value;
if ($key=='manufId') $res['MANUFID']=$value;
if ($key=='manufName') $res['MANUFNAME']=$value;
if ($key=='powerSource') $res['POWERSOURCE']=$value;
if ($key=='modelId') $res['MODEL']=$value;
if ($key=='modelId') $res['MODELID']=$value;
if ($key=='status') $res['STATUS']=$value;
if ($key=='devId') $res['DID']=$value;
if ($key=='_id') $res['D_ID']=$value;
}

//print_r($res);
//echo "<br><br>";

SQLUPDATE('zigbee2mqtt_devices', $res);

       }


}


}}

/**
* FrontEnd
*
* Module frontend
*
* @access public
*/
function usual(&$out) {
    if ($this->ajax) {
        global $op;
        $result=array();
        if ($op=='getvalues') {
            global $ids;
            if (!is_array($ids)) {
                $ids=array(0);
            } else {
                $ids[]=0;
            }
            $data=SQLSelect("SELECT ID,VALUE FROM zigbee2mqtt WHERE ID IN (".implode(',',$ids).")");
            $total = count($data);
            for($i=0;$i<$total;$i++) {
                $data[$i]['VALUE']=str_replace('":','": ',$data[$i]['VALUE']);
            }
            $result['DATA']=$data;
        }
        echo json_encode($result);
        exit;
    }
 $this->admin($out);
}
/**
* mqtt search
*
* @access public
*/
 function search_mqtt(&$out) {
  require(DIR_MODULES.$this->name.'/mqtt_search.inc.php');
 }
/**
* mqtt edit/add
*
* @access public
*/
 function edit_mqtt(&$out, $id) {
  require(DIR_MODULES.$this->name.'/mqtt_edit.inc.php');
 }

 function propertySetHandle($object, $property, $value) {


   $mqtt_properties=SQLSelect("SELECT * FROM zigbee2mqtt WHERE LINKED_OBJECT LIKE '".DBSafe($object)."' AND LINKED_PROPERTY LIKE '".DBSafe($property)."'");
   $total=count($mqtt_properties);
debmes($object.":". $property.":". $value. ' найдено результатов '. $total, 'zigbee2mqtt');

   if ($total) {
    for($i=0;$i<$total;$i++) {
     debmes('Запускаем setProperty '. $mqtt_properties[$i]['ID'].":".$value, 'zigbee2mqtt');
     $this->setProperty($mqtt_properties[$i]['ID'], $value);
    }
   }  
 }
    

/**
* mqtt delete record
*
* @access public
*/
 function delete_mqtt($id) {

//debmes("SELECT * FROM zigbee2mqtt WHERE DEV_ID='$id'",'zigbee2mqtt');
//  $rec=SQLSelectOne("SELECT * FROM zigbee2mqtt WHERE DEV_ID='$id'");
  // some action for related tables

//debmes("DELETE FROM zigbee2mqtt WHERE DEV_ID='".$rec['DEV_ID']."'",'zigbee2mqtt');
debmes("DELETE FROM zigbee2mqtt_devices WHERE DEV_ID='".$id."'", 'zigbee2mqtt');
  SQLExec("DELETE FROM zigbee2mqtt_devices WHERE DEV_ID='".$id."'");
debmes("DELETE FROM zigbee2mqtt_devices WHERE ID='".$id."'", 'zigbee2mqtt');
  SQLExec("DELETE FROM zigbee2mqtt_devices WHERE ID='".$id."'");

 }
/**
* Install
*
* Module installation routine
*
* @access private
*/
 function install($data='') {
  parent::install();
 }
/**
* Uninstall
*
* Module uninstall routine
*
* @access public
*/




function get_map(){
  include_once("./lib/mqtt/phpMQTT.php");

   $this->getConfig();
   if ($mqtt->config['MQTT_CLIENT']) {
    $client_name=$mqtt->config['MQTT_CLIENT'];
   } else {
    $client_name="MajorDoMo MQTT";
   }

   if ($this->config['MQTT_AUTH']) {
    $username=$this->config['MQTT_USERNAME'];
    $password=$this->config['MQTT_PASSWORD'];
   }
   if ($this->config['MQTT_HOST']) {
    $host=$this->config['MQTT_HOST'];
   } else {
    $host='localhost';
   }
   if ($this->config['MQTT_PORT']) {
    $port=$this->config['MQTT_PORT'];
   } else {
    $port=1883;
   }

   $mqtt_client = new phpMQTT($host, $port, $client_name.' Client');

   if(!$mqtt_client->connect(true, NULL,$username,$password))
   {
    return 0;
   }


debmes('Запрашиваем карту ', 'zigbee2mqtt');




   $mqtt_client->publish('zigbee2mqtt/bridge/networkmap','graphviz');
   $mqtt_client->publish('zigbee2mqtt/bridge/networkmap','raw');
   //$mqtt_client->publish($rec['PATH_WRITE'].'/set',$jsonvalue, (int)$rec['QOS'], (int)$rec['RETAIN']);


   $mqtt_client->close();
}


 function uninstall() {
  SQLExec('DROP TABLE IF EXISTS zigbee2mqtt');
  SQLExec('DROP TABLE IF EXISTS zigbee2mqtt_devices');
   
  parent::uninstall();
 }
/**
* dbInstall
*
* Database installation routine
*
* @access private
*/
 function dbInstall($data) {
/*
mqtt - MQTT
*/



 SQLExec("DROP PROCEDURE IF EXISTS SPLIT_STRING") ;
 SQLExec("CREATE FUNCTION IF NOT EXISTS  SPLIT_STRING(str VARCHAR(255), delim VARCHAR(12), pos INT) RETURNS VARCHAR(255) RETURN REPLACE(SUBSTRING(SUBSTRING_INDEX(str, delim, pos),        LENGTH(SUBSTRING_INDEX(str, delim, pos-1)) + 1),        delim, '');");



  $data = <<<EOD

 zigbee2mqtt_devices: ID int(10) unsigned NOT NULL auto_increment
 zigbee2mqtt_devices: TITLE varchar(100) NOT NULL DEFAULT ''

 zigbee2mqtt_devices: ONLINE varchar(100) NOT NULL DEFAULT ''
 zigbee2mqtt_devices: MANUFACTURE varchar(100) NOT NULL DEFAULT ''
 zigbee2mqtt_devices: DEVICE_NAME varchar(100) NOT NULL DEFAULT ''
 zigbee2mqtt_devices: MODEL varchar(100) NOT NULL DEFAULT ''
 zigbee2mqtt_devices: TYPE varchar(100) NOT NULL DEFAULT ''
 zigbee2mqtt_devices: LASTPING varchar(100) NOT NULL DEFAULT ''
 zigbee2mqtt_devices: IEEEADDR varchar(100) NOT NULL DEFAULT ''
 zigbee2mqtt_devices: NWKADDR varchar(100) NOT NULL DEFAULT ''
 zigbee2mqtt_devices: MANUFID varchar(100) NOT NULL DEFAULT ''
 zigbee2mqtt_devices: MANUFNAME varchar(100) NOT NULL DEFAULT ''
 zigbee2mqtt_devices: POWERSOURCE varchar(100) NOT NULL DEFAULT ''
 zigbee2mqtt_devices: MODELID varchar(100) NOT NULL DEFAULT ''
 zigbee2mqtt_devices: STATUS varchar(100) NOT NULL DEFAULT ''
 zigbee2mqtt_devices: JOINTIME varchar(100) NOT NULL DEFAULT ''
 zigbee2mqtt_devices: DID varchar(100) NOT NULL DEFAULT ''
 zigbee2mqtt_devices: D_ID varchar(100) NOT NULL DEFAULT ''
 zigbee2mqtt_devices: FIND datetime
 zigbee2mqtt_devices: LOCATION_ID int(10) NOT NULL DEFAULT '0'

 zigbee2mqtt_devices_list: ID int(10) unsigned NOT NULL auto_increment
 zigbee2mqtt_devices_list: zigbeeModel varchar(100) NOT NULL DEFAULT ''
 zigbee2mqtt_devices_list: model varchar(100) NOT NULL DEFAULT ''
 zigbee2mqtt_devices_list: vendor varchar(100) NOT NULL DEFAULT ''
 zigbee2mqtt_devices_list: description varchar(300) NOT NULL DEFAULT ''
 zigbee2mqtt_devices_list: extend varchar(100) NOT NULL DEFAULT ''
 zigbee2mqtt_devices_list: supports varchar(100) NOT NULL DEFAULT ''
 zigbee2mqtt_devices_list: fromZigbee varchar(300) NOT NULL DEFAULT ''
 zigbee2mqtt_devices_list: toZigbee varchar(300) NOT NULL DEFAULT ''


 zigbee2mqtt: ID int(10) unsigned NOT NULL auto_increment
 zigbee2mqtt: TITLE varchar(255) NOT NULL DEFAULT ''
 zigbee2mqtt: LOCATION_ID int(10) NOT NULL DEFAULT '0'
 zigbee2mqtt: UPDATED datetime
 zigbee2mqtt: VALUE varchar(255) NOT NULL DEFAULT ''
 zigbee2mqtt: PATH varchar(255) NOT NULL DEFAULT ''
 zigbee2mqtt: METRIKA varchar(255) NOT NULL DEFAULT ''
 zigbee2mqtt: PATH_WRITE varchar(255) NOT NULL DEFAULT ''
 zigbee2mqtt: REPLACE_LIST varchar(255) NOT NULL DEFAULT ''
 zigbee2mqtt: LINKED_OBJECT varchar(255) NOT NULL DEFAULT ''
 zigbee2mqtt: LINKED_PROPERTY varchar(255) NOT NULL DEFAULT ''
 zigbee2mqtt: LINKED_METHOD varchar(255) NOT NULL DEFAULT ''
 zigbee2mqtt: QOS int(3) NOT NULL DEFAULT '0'
 zigbee2mqtt: RETAIN int(3) NOT NULL DEFAULT '0'
 zigbee2mqtt: CONVERTONOFF int(3) NOT NULL DEFAULT '0'
 zigbee2mqtt: DEV_ID int(5) NOT NULL DEFAULT '0'
 zigbee2mqtt: DISP_FLAG int(3) NOT NULL DEFAULT '0'
EOD;
  parent::dbInstall($data);

//https://github.com/Koenkk/zigbee-shepherd-converters/blob/master/devices.js
$par1=SQLSelectOne ("select * from zigbee2mqtt_devices_list where ID=1");

if (!$par1['ID']) {

$par1['zigbeeModel'] = 'lumi.light.aqcn02';
$par1['model'] = "ZNLDP12LM";		 
$par1['vendor'] = "Xiaomi";		 
$par1['description'] = "Aqara smart LED bulb";		 
$par1['extend'] = "generic.light_onoff_brightness_colortemp";		 
$par1['supports'] = "";		 
$par1['fromZigbee'] = "fz.light_brightness, fz.light_color_colortemp, fz.generic_state, fz.xiaomi_bulb_interval, fz.ignore_light_brightness_report, fz.ignore_light_color_colortemp_report, fz.ignore_onoff_change,            fz.ignore_basic_change";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);						


$par1['zigbeeModel'] = 'lumi.sensor_switch';
$par1['model'] = "WXKG01LM";		 
$par1['vendor'] = "Xiaomi";		 
$par1['description'] = "MiJia wireless switch";		 
$par1['extend'] = "";		 
$par1['supports'] = "single, double, triple, quadruple, many, long, long_release click";		 
$par1['fromZigbee'] = "fz.xiaomi_battery_3v, fz.WXKG01LM_click, fz.ignore_onoff_change, fz.ignore_basic_change";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);						

$par1['zigbeeModel'] = 'lumi.sensor_switch.aq2, lumi.remote.b1acn01\u0000\u0000\u0000\u0000\u0000\u0000';
$par1['model'] = "WXKG11LM";		 
$par1['vendor'] = "Xiaomi";		 
$par1['description'] = "Aqara wireless switch";		 
$par1['extend'] = "";		 
$par1['supports'] = "single, double click (and triple, quadruple, hold, release depending on model)";		 
$par1['fromZigbee'] = " fz.xiaomi_battery_3v, fz.WXKG11LM_click, fz.ignore_onoff_change, fz.ignore_basic_change,            fz.xiaomi_action_click_multistate, fz.ignore_multistate_change,";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);						

$par1['zigbeeModel'] = 'lumi.sensor_switch.aq3, lumi.sensor_swit';
$par1['model'] = "WXKG12LM";		 
$par1['vendor'] = "Xiaomi";		 
$par1['description'] = "Aqara wireless switch (with gyroscope)";		 
$par1['extend'] = "";		 
$par1['supports'] = "single, double, shake, hold, release";		 
$par1['fromZigbee'] = "fz.xiaomi_battery_3v, fz.WXKG12LM_action_click_multistate, fz.ignore_onoff_change,fz.ignore_basic_change, fz.ignore_multistate_change,";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);						

$par1['zigbeeModel'] = 'lumi.sensor_86sw1\u0000lu, lumi.remote.b186acn01\u0000\u0000\u0000';
$par1['model'] = "WXKG03LM";		 
$par1['vendor'] = "Xiaomi";		 
$par1['description'] = "Aqara single key wireless wall switch";		 
$par1['extend'] = "";		 
$par1['supports'] = "single click";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);						

$par1['zigbeeModel'] = 'lumi.sensor_86sw2\u0000Un, lumi.sensor_86sw2.es1, lumi.remote.b286acn01\u0000\u0000\u0000';
$par1['model'] = "WXKG02LM";		 
$par1['vendor'] = "Xiaomi";		 
$par1['description'] = "Aqara double key wireless wall switch";		 
$par1['extend'] = "";		 
$par1['supports'] = "left, right and both click";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);						


$par1['zigbeeModel'] = 'lumi.ctrl_neutral1';
$par1['model'] = "QBKG04LM";		 
$par1['vendor'] = "Xiaomi";		 
$par1['description'] = "Aqara single key wired wall switch";		 
$par1['extend'] = "";		 
$par1['supports'] = "on/off";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);						

$par1['zigbeeModel'] = 'lumi.ctrl_ln1.aq1, lumi.ctrl_ln1';
$par1['model'] = "QBKG11LM";		 
$par1['vendor'] = "Xiaomi";		 
$par1['description'] = "Aqara single key wired wall switch";		 
$par1['extend'] = "";		 
$par1['supports'] = "on/off, power measurement";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);						

$par1['zigbeeModel'] = 'lumi.ctrl_neutral2';
$par1['model'] = "QBKG03LM";		 
$par1['vendor'] = "Xiaomi";		 
$par1['description'] = "Aqara double key wired wall switch";		 
$par1['extend'] = "";		 
$par1['supports'] = "release/hold, on/off";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);						

$par1['zigbeeModel'] = 'lumi.ctrl_ln2.aq1';
$par1['model'] = "QBKG12LM";		 
$par1['vendor'] = "Xiaomi";		 
$par1['description'] = "Aqara double key wired wall switch";		 
$par1['extend'] = "";		 
$par1['supports'] = "on/off, power measurement";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);						

$par1['zigbeeModel'] = 'lumi.sens';
$par1['model'] = "WSDCGQ01LM";		 
$par1['vendor'] = "Xiaomi";		 
$par1['description'] = "MiJia temperature & humidity sensor";		 
$par1['extend'] = "";		 
$par1['supports'] = "temperature and humidity";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);						

$par1['zigbeeModel'] = 'lumi.weather';
$par1['model'] = "WSDCGQ11LM";		 
$par1['vendor'] = "Xiaomi";		 
$par1['description'] = "Aqara temperature, humidity and pressure sensor";		 
$par1['extend'] = "";		 
$par1['supports'] = "temperature, humidity and pressure";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);						


$par1['zigbeeModel'] = 'lumi.sensor_motion';
$par1['model'] = "RTCGQ01LM";		 
$par1['vendor'] = "Xiaomi";		 
$par1['description'] = "MiJia human body movement sensor";		 
$par1['extend'] = "";		 
$par1['supports'] = "occupancy";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);						

$par1['zigbeeModel'] = 'lumi.sensor_motion.aq2';
$par1['model'] = "RTCGQ11LM";		 
$par1['vendor'] = "Xiaomi";		 
$par1['description'] = "Aqara human body movement and illuminance sensor";		 
$par1['extend'] = "";		 
$par1['supports'] = "occupancy and illuminance";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);						

$par1['zigbeeModel'] = 'lumi.sensor_magnet';
$par1['model'] = "MCCGQ01LM";		 
$par1['vendor'] = "Xiaomi";		 
$par1['description'] = "MiJia door & window contact sensor";		 
$par1['extend'] = "";		 
$par1['supports'] = "contact";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);						

$par1['zigbeeModel'] = 'lumi.sensor_magnet.aq2';
$par1['model'] = "MCCGQ11LM";		 
$par1['vendor'] = "Xiaomi";		 
$par1['description'] = "Aqara door & window contact sensor";		 
$par1['extend'] = "";		 
$par1['supports'] = "contact";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);						

$par1['zigbeeModel'] = 'lumi.sensor_wleak.aq1';
$par1['model'] = "SJCGQ11LM";		 
$par1['vendor'] = "Xiaomi";		 
$par1['description'] = "Aqara water leak sensor";		 
$par1['extend'] = "";		 
$par1['supports'] = "water leak true/false";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);						

$par1['zigbeeModel'] = 'lumi.sensor_cube, lumi.sensor_cube.aqgl01';
$par1['model'] = "MFKZQ01LM";		 
$par1['vendor'] = "Xiaomi";		 
$par1['description'] = "Mi/Aqara smart home cube";		 
$par1['extend'] = "";		 
$par1['supports'] = "shake, wakeup, fall, tap, slide, flip180, flip90, rotate_left and rotate_right";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);						

$par1['zigbeeModel'] = 'lumi.plug';
$par1['model'] = "ZNCZ02LM";		 
$par1['vendor'] = "Xiaomi";		 
$par1['description'] = "Mi power plug ZigBee";		 
$par1['extend'] = "";		 
$par1['supports'] = "on/off, power measurement";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);						

$par1['zigbeeModel'] = 'lumi.ctrl_86plug, lumi.ctrl_86plug.aq1';
$par1['model'] = "QBCZ11LM";		 
$par1['vendor'] = "Xiaomi";		 
$par1['description'] = "Aqara socket Zigbee";		 
$par1['extend'] = "";		 
$par1['supports'] = "on/off, power measurement";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);						

$par1['zigbeeModel'] = 'lumi.sensor_smoke';
$par1['model'] = "JTYJ-GD-01LM/BW";		 
$par1['vendor'] = "Xiaomi";		 
$par1['description'] = "MiJia Honeywell smoke detector";		 
$par1['extend'] = "";		 
$par1['supports'] = "smoke";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);						

$par1['zigbeeModel'] = 'lumi.sensor_natgas';
$par1['model'] = "JTQJ-BF-01LM/BW";		 
$par1['vendor'] = "Xiaomi";		 
$par1['description'] = "MiJia gas leak detector";		 
$par1['extend'] = "";		 
$par1['supports'] = "gas";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);						

$par1['zigbeeModel'] = 'lumi.lock.v1';
$par1['model'] = "A6121";		 
$par1['vendor'] = "Xiaomi";		 
$par1['description'] = "Vima Smart Lock";		 
$par1['extend'] = "";		 
$par1['supports'] = "inserted, forgotten, key error";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);						

$par1['zigbeeModel'] = 'lumi.vibration.aq1';
$par1['model'] = "DJT11LM";		 
$par1['vendor'] = "Xiaomi";		 
$par1['description'] = "Aqara vibration sensor";		 
$par1['extend'] = "";		 
$par1['supports'] = "drop, tilt and touch";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);						

$par1['zigbeeModel'] = 'lumi.curtain';
$par1['model'] = "ZNCLDJ11LM";		 
$par1['vendor'] = "Xiaomi";		 
$par1['description'] = "Aqara curtain motor";		 
$par1['extend'] = "";		 
$par1['supports'] = "open, close, stop, position";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);						

//IKEA

$par1['zigbeeModel'] = 'TRADFRI bulb E27 WS opal 980lm, TRADFRI bulb E26 WS opal 980lm,TRADFRI bulb E27 WS\uFFFDopal 980lm';
$par1['model'] = "LED1545G12";		 
$par1['vendor'] = "IKEA";		 
$par1['description'] = "TRADFRI LED bulb E26/E27 980 lumen, dimmable, white spectrum, opal white";		 
$par1['extend'] = "";		 
$par1['supports'] = "generic.light_onoff_brightness_colortemp";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);						

$par1['zigbeeModel'] = 'TRADFRI bulb E27 WS clear 950lm, TRADFRI bulb E26 WS clear 950lm';
$par1['model'] = "LED1546G12";		 
$par1['vendor'] = "IKEA";		 
$par1['description'] = "TRADFRI LED bulb E26/E27 950 lumen, dimmable, white spectrum, clear";		 
$par1['extend'] = "";		 
$par1['supports'] = "generic.light_onoff_brightness_colortemp";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);						

$par1['zigbeeModel'] = 'TRADFRI bulb E27 opal 1000lm, TRADFRI bulb E27 W opal 1000lm';
$par1['model'] = "LED1623G12";		 
$par1['vendor'] = "IKEA";		 
$par1['description'] = "TRADFRI LED bulb E27 1000 lumen, dimmable, opal white";		 
$par1['extend'] = "";		 
$par1['supports'] = "generic.light_onoff_brightness,";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);						

$par1['zigbeeModel'] = 'TRADFRI bulb GU10 WS 400lm';
$par1['model'] = "LED1537R6";		 
$par1['vendor'] = "IKEA";		 
$par1['description'] = "TRADFRI LED bulb GU10 400 lumen, dimmable, white spectrum";		 
$par1['extend'] = "";		 
$par1['supports'] = "generic.light_onoff_brightness_colortemp";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);						

$par1['zigbeeModel'] = 'TRADFRI bulb GU10 W 400lm';
$par1['model'] = "LED1650R5";		 
$par1['vendor'] = "IKEA";		 
$par1['description'] = "TRADFRI LED bulb GU10 400 lumen, dimmable";		 
$par1['extend'] = "";		 
$par1['supports'] = "generic.light_onoff_brightness";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);

$par1['zigbeeModel'] = 'TRADFRI bulb E14 WS opal 400lm, TRADFRI bulb E12 WS opal 400lm';
$par1['model'] = "LED1536G5";		 
$par1['vendor'] = "IKEA";		 
$par1['description'] = "TRADFRI LED bulb E12/E14 400 lumen, dimmable, white spectrum, opal white";		 
$par1['extend'] = "";		 
$par1['supports'] = "generic.light_onoff_brightness_colortemp";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);

$par1['zigbeeModel'] = 'TRADFRI bulb E26 opal 1000lm, TRADFRI bulb E26 W opal 1000lm';
$par1['model'] = "LED1622G12";		 
$par1['vendor'] = "IKEA";		 
$par1['description'] = "TRADFRI LED bulb E26 1000 lumen, dimmable, opal white";		 
$par1['extend'] = "";		 
$par1['supports'] = "generic.light_onoff_brightness";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);

$par1['zigbeeModel'] = 'TRADFRI bulb E27 CWS opal 600lm, TRADFRI bulb E26 CWS opal 600lm';
$par1['model'] = "LED1624G9";		 
$par1['vendor'] = "IKEA";		 
$par1['description'] = "TRADFRI LED bulb E27/E26 600 lumen, dimmable, color, opal white";		 
$par1['extend'] = "";		 
$par1['supports'] = "generic.light_onoff_brightness_colortemp_colorxy";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);

$par1['zigbeeModel'] = 'TRADFRI bulb E14 W op/ch 400lm', 'TRADFRI bulb E12 W op/ch 400lm,TRADFRI bulb E17 W op/ch 400lm';
$par1['model'] = "LED1649C5";		 
$par1['vendor'] = "IKEA";		 
$par1['description'] = "TRADFRI LED bulb E12/E14/E17 400 lumen, dimmable warm white, chandelier opal";		 
$par1['extend'] = "";		 
$par1['supports'] = " generic.light_onoff_brightness";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);

$par1['zigbeeModel'] = 'TRADFRI wireless dimmer';
$par1['model'] = "ICTC-G-1";		 
$par1['vendor'] = "IKEA";		 
$par1['description'] = "TRADFRI wireless dimmer";		 
$par1['extend'] = "";		 
$par1['supports'] = "brightness [0-255], quick rotate for instant 0/255";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);

$par1['zigbeeModel'] = 'TRADFRI transformer 10W';
$par1['model'] = "ICPSHC24-10EU-IL-1";		 
$par1['vendor'] = "IKEA";		 
$par1['description'] = "TRADFRI driver for wireless control (10 watt)";		 
$par1['extend'] = "";		 
$par1['supports'] = " generic.light_onoff_brightness";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);

$par1['zigbeeModel'] = 'TRADFRI transformer 30W';
$par1['model'] = "ICPSHC24-30EU-IL-1";		 
$par1['vendor'] = "IKEA";		 
$par1['description'] = "TRADFRI driver for wireless control (30 watt)";		 
$par1['extend'] = "";		 
$par1['supports'] = "generic.light_onoff_brightness";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);

$par1['zigbeeModel'] = 'FLOALT panel WS 30x30';
$par1['model'] = "L1527";		 
$par1['vendor'] = "IKEA";		 
$par1['description'] = "FLOALT LED light panel, dimmable, white spectrum (30x30 cm)";		 
$par1['extend'] = "";		 
$par1['supports'] = "generic.light_onoff_brightness_colortemp";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);

$par1['zigbeeModel'] = 'FLOALT panel WS 60x60';
$par1['model'] = "L1529";		 
$par1['vendor'] = "IKEA";		 
$par1['description'] = "FLOALT LED light panel, dimmable, white spectrum (60x60 cm)";		 
$par1['extend'] = "";		 
$par1['supports'] = "generic.light_onoff_brightness_colortemp";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);


$par1['zigbeeModel'] = 'FLOALT panel WS 30x90';
$par1['model'] = "L1528";		 
$par1['vendor'] = "IKEA";		 
$par1['description'] = "FLOALT LED light panel, dimmable, white spectrum (30x90 cm)";		 
$par1['extend'] = "";		 
$par1['supports'] = "generic.light_onoff_brightness_colortemp";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);

$par1['zigbeeModel'] = 'SURTE door WS 38x64';
$par1['model'] = "L1531";		 
$par1['vendor'] = "IKEA";		 
$par1['description'] = "SURTE door light panel, dimmable, white spectrum (38x64 cm)";		 
$par1['extend'] = "";		 
$par1['supports'] = "generic.light_onoff_brightness_colortemp";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);

$par1['zigbeeModel'] = 'TRADFRI control outlet';
$par1['model'] = "E1603";		 
$par1['vendor'] = "IKEA";		 
$par1['description'] = "TRADFRI control outlet";		 
$par1['extend'] = "";		 
$par1['supports'] = "generic.light_onoff_brightness_colortemp";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);

$par1['zigbeeModel'] = 'TRADFRI control outlet';
$par1['model'] = "E1603";		 
$par1['vendor'] = "IKEA";		 
$par1['description'] = "TRADFRI control outlet";		 
$par1['extend'] = "";		 
$par1['supports'] = "on/off";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);

$par1['zigbeeModel'] = 'TRADFRI remote control';
$par1['model'] = "E1524";		 
$par1['vendor'] = "IKEA";		 
$par1['description'] = "TRADFRI remote control";		 
$par1['extend'] = "";		 
$par1['supports'] = "toggle, arrow left/right click/hold/release, brightness up/down click/hold/release";		 
$par1['fromZigbee'] = "";		 
$par1['toZigbee'] = "";		 
SQLInsert('zigbee2mqtt_devices_list', $par1);











}





 }
// --------------------------------------------------------------------
}
/*
*
* TW9kdWxlIGNyZWF0ZWQgSnVsIDE5LCAyMDEzIHVzaW5nIFNlcmdlIEouIHdpemFyZCAoQWN0aXZlVW5pdCBJbmMgd3d3LmFjdGl2ZXVuaXQuY29tKQ==
*
*/
?>
