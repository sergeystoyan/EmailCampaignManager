<?php
//********************************************************************************************
//Author: Sergey Stoyan, CliverSoft.com
//        http://cliversoft.com
//        stoyan@cliversoft.com
//        sergey.stoyan@gmail.com
//        27 February 2007
//Copyright: (C) 2007, Sergey Stoyan
//********************************************************************************************

include_once("common/logger.php");
include_once("common/db.php");

$User = Login::GetCurrentUser();
if(!$User)  		
  	Respond(null, 'The user is not identified. Please provide correct login info.');
/*$method = $_SERVER['REQUEST_METHOD'];
//$request = explode("/", substr(@$_SERVER['PATH_INFO'], 1));
switch ($method) 
{
  case 'PUT': 
    break;
  case 'POST':
    break;
  case 'GET':
    break;
  case 'DELETE':
    break;
  case 'HEAD':
  case 'OPTIONS':
	default:
	throw new Exception('Unhandled REQUEST_METHOD: '.$_SERVER['REQUEST_METHOD']);
}*/

/*class Action
{
	public static function Index()
	{
		Respond(DataTable::FetchData(
			[
				['Name'=>'id', 'Searchable' => false, 'Order' => null, 'Expression'=>null],
				['Name'=>'name', 'Searchable' => true, 'Order' => null, 'Expression'=>null],
				['Name'=>'type', 'Searchable' => true, 'Order' => null, 'Expression'=>null],
			],
			'FROM users'
			)
		);
	}
	
	public static function Perform()
	{
		$action = isset($_GET['action']) ? $_GET['action'] : null;
		switch ($action) 
		{
			case null:
		    return;
		  	case 'Add':
		  		Respond(DataTable::Insert('users', $_POST));
		    return;
		  	case 'GetByKeys':
		  		Respond(DataTable::GetByKeys('users', $_POST));
		    return;
		  	case 'Save':
		  		Respond(DataTable::Save('users', $_POST));
		    return;
		  	case 'Delete':
		  		Respond(DataTable::Delete('users', $_POST));
		    return;
			default:
				throw new Exception("Unhandled action: $action");
		}
	}
}*/

function Respond($data, $error=null)
{
	header('Content-Type: application/json');
	if($error)
		if(isset($data['_ERROR']))
			$data['_ERROR'] .= '\r\n'.$error;
		else
			$data['_ERROR'] = $error;
	echo json_encode($data);
	exit();
}

class Login
{
	static function GetCurrentUser()
	{
		$user = null;
						
		session_start();
		if(session_id())
			$user = Db::GetRowArray("SELECT * FROM users WHERE _session_id='".session_id()."'");			
		if(!$user and array_key_exists('permanent_session_id', $_COOKIE))
			$user = Db::GetRowArray("SELECT * FROM users WHERE _session_id='".addslashes($_COOKIE['permanent_session_id'])."'");	
		if(!$user and array_key_exists('UserName', $_REQUEST))
			$user = Db::GetRowArray("SELECT * FROM users WHERE name='".addslashes($_REQUEST['UserName'])."' AND password='".addslashes($_REQUEST['Password'])."'");
		
		if(!$user)
			return null;
			
		if(!array_key_exists('User', $_SESSION))
		{
	    	Db::Query("UPDATE users SET _session_id='".session_id()."' WHERE id=".$user['id']);
        	if(array_key_exists('RememberMe', $_REQUEST)) 
        		setcookie("permanent_session_id", session_id(), time() + 360*24*3600, "/");        		
        	setcookie("user_type", $user['type'], time() + 360*24*3600, "/");
        	unset($user['password']);
        	$_SESSION['User'] = $user;
		}	    	
		
		return $_SESSION['User'];	
	}
}

/*public class Field
{
	public Name;
    public bool Searchable = false;
    public string Expression = null;
    public Order = null;
    public string Entity;
    
    __construct(string name, bool searchable = false, string order = null, string expression = null)
    {
        $this->Name = name;
        $this->Searchable = searchable;
        $this->Order = order;
        $this->Expression = expression;
        $this->Entity = Expression != null ? Expression : Name;
    }
}*/

class DataTable
{	
	public static function Insert($table, $fields2value)
	{	
		$ss = [];
		foreach($fields2value as $f=>$v)
			array_push($ss, "$f='".addslashes($v)."'");
		return Db::Query("INSERT INTO $table SET ".join(',', $ss));	
	}	
	
	public static function GetByKeys($table, $keys2value)
	{	
		$ss = [];
		foreach($keys2value as $k=>$v)
			array_push($ss, "$k='".addslashes($v)."'");
		return Db::GetRowArray("SELECT * FROM $table WHERE ".join(' AND ', $ss));
	}	
	
	public static function Save($table, $fields2value)
	{	
		$id = $fields2value['id'];
		unset($fields2value['id']);
		$ss = [];
		foreach($fields2value as $f=>$v)
			array_push($ss, "$f='".addslashes($v)."'");
		return Db::Query("UPDATE $table SET ".join(', ', $ss)." WHERE id=$id");
	}	
	
	public static function Delete($table, $fields2value)
	{	
		return Db::Query("DELETE FROM $table WHERE id='".addslashes($fields2value['id'])."'");
	}
	
	public static function FetchData($fields, $from_sql, $ignore_first_column_search = true)
	{			
		foreach($fields as $k=>$f)
			$fields[$k]['Entity'] = $f['Expression'] != null ? $f['Expression'] : $f['Name'];
		
		$total_count = Db::GetSingleValue("SELECT COUNT(".$fields[0]['Entity'].") $from_sql");
			
		$filtered_count = $total_count;
        $search_conditions = [];
        if($_POST['search']['value'])
        {
        	$search = preg_replace("@\%@", "\\$0", $_POST['search']['value']);
            foreach($fields as $f)
            {
				if(!$f['Searchable'])
					continue;
                $search_conditions[] = $f['Entity']." LIKE '%".Db::EscapeString($search)."%'";
            }
        }
		$where_sql = '';
        if(count($search_conditions))
        {
            if(strstr($from_sql, ' WHERE '))
                $where_sql = " AND ";
            else
                $where_sql = " WHERE "; 
            $where_sql .= "(".join(" OR ", $search_conditions).")";
        }
        if($where_sql)
            $filtered_count = Db::GetSingleValue("SELECT COUNT(".$fields[0]['Entity'].") $from_sql $where_sql");		
		
    	$limit_sql = '';
    	if($_POST['start'] && $_POST['length'])
        	$limit_sql = "LIMIT ".$_POST['start'].", ".$_POST['length'];
    
  		$sql_order = '';  		
        $ofes2nothing = []; 
        $ordered_fields = [];
        foreach($_POST['order'] as $order)
        {
        	/*if ($ignore_first_column_search)
            {
            	$ignore_first_column_search = false;
                continue;
            }*/                      
            $fe = $fields[$order['column']]['Entity'];
            $ordered_fields[] = $fe." ".$order['dir'];
            $ofes2nothing[$fe] = 0;
        }
        foreach($fields as $field)
        {
            if (!$field['Order'])
                continue;
            if(isset($ofes2nothing[$field['Entity']]))
                continue;
            $ordered_fields[] = $field." ".$field['Order'];
        }
        $order_sql = "ORDER BY ";
        if(count($ordered_fields))
            $order_sql .= join(", ", $ordered_fields);
        else
            $order_sql .= $fields[0]['Entity'];
            
        $fs = [];
        foreach($fields as $f) 
        	array_push($fs, $f['Expression'] ? $f['Expression']." AS ".$f['Name'] : $f['Name']);        	
        $fields_sql = join(', ', $fs);        	
        	
        $sql = "SELECT $fields_sql $from_sql $where_sql $order_sql $limit_sql";
        $rs = Db::GetArray($sql);
        if($_POST['search']['value'])
        {			
        	$search = preg_quote($_POST['search']['value']);
        	foreach($rs as $i=>$r)
        	{
        		$j = 0;
        		foreach($r as $n=>$v)
                    if ($fields[$j++]['Searchable'])
                        $rs[$i][$n] = preg_replace("@$search@i", "<span class='match'>$0</span>", $rs[$i][$n]);
            }
        }
        
        $vss = [];
        foreach($rs as $r)
        {
        	$vs = [];
        	foreach($r as $v)
        		array_push($vs, $v);
        	array_push($vss, $vs);
        }
        
        $hs = [];        
        foreach($fields as $f)
        	array_push($hs, $f['Name']);
     
     	return [
     		'draw'=>$_REQUEST['draw'], 
			'recordsTotal'=>$total_count, 
			'recordsFiltered'=>$filtered_count,
			'data'=>$vss,
			'header'=>$hs
		];		
	}
}


?>