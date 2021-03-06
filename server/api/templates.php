<?php
//********************************************************************************************
//Author: Sergey Stoyan, CliverSoft.com
//        http://cliversoft.com
//        stoyan@cliversoft.com
//        sergey.stoyan@gmail.com
//        27 February 2007
//Copyright: (C) 2007, Sergey Stoyan
//********************************************************************************************
include_once("../core.php");

if(!Login::UserType())
	Respond(null, "User of type '".Login::UserType()."' cannot do this operation.");

$_POST['user_id'] = Login::UserId();  		
$action = isset($_GET['action']) ? $_GET['action'] : null;
switch ($action) 
{
	case 'GetTableData':
		Respond(DataTable::FetchData(
			[
				['Name'=>'id', 'Searchable' => false, 'Order' => null, 'Expression'=>null],
				['Name'=>'name', 'Searchable' => true, 'Order' => null, 'Expression'=>null],
				['Name'=>'from_name', 'Searchable' => true, 'Order' => null, 'Expression'=>null],
				['Name'=>'subject', 'Searchable' => true, 'Order' => null, 'Expression'=>null],
				//['Name'=>'template', 'Searchable' => true, 'Order' => null, 'Expression'=>null],
			],
			'FROM templates WHERE user_id='.$_POST['user_id']
		));
    return;
  	case 'Add':
  		Respond(DataTable::Insert('templates', $_POST));
    return;
  	case 'GetByKeys':
  		Respond(DataTable::GetByKeys('templates', $_POST));
    return;
  	case 'Save':
  		Respond(DataTable::Save('templates', $_POST));
    return;
  	case 'Delete':
  		//if(Db::GetSingleValue("SELECT id FROM campaigns WHERE id=".$_POST['id']))
  		//	Respond(null, "This template is used by campaigns");
  		Respond(DataTable::Delete('templates', $_POST));
    return;
	default:
		throw new Exception("Unhandled action: $action");
}

?>