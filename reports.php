<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.txt');

include('../_connect.php');
include('../../includes/helpers/short.php');

header('Content-Type: application/json; charset=utf-8');
?>
<?php 
/*
---Little helper function from james@crid.land for reporting

Put this file in a new folder within the /api/ folder, called "reporting", and call it "reports.php". (Or whatever you like).

Call by POST to api/reporting/reports.php with the following mandatory elements
  'api_key' => (your API key)
  'brand_id' => 1
  'label' => (the campaign name)
  
  (Using the campaign name allows you to programmatically call a campaign without knowing its campaign ID)

The data return is in JSON and looks like the following:

{"total_opens":361,"unique_opens":208,"country_opens":{"US":127,"EU":47,"GB":57,"AP":19,"CZ":1,"":3,"DE":3,"IE":16,"AU":48,"SE":6,"ES":2,"CH":1,"FR":4,"SI":2,"CA":14,"NL":2,"MV":1,"NZ":2,"AE":1,"IN":1,"BE":1,"JP":2,"BR":1},"total_sent":"341","brand_id":"1","label":"podnews 2017-08-07"}

total_opens: the total opens figure, visible in your dashboard
unique_opens: de-duplicated opens figure
country_opens: an array with individual countries opened out. Note - this is based on total_opens, not unique
total_sent: the total sent for this campaign
brand_id: the brand ID you sent
label: the label you requested

*/


//-------------------------- ERRORS -------------------------//
	$error_core = array('No data passed', 'API key not passed', 'Invalid API key');
	$error_passed = array(
	  'Brand ID not passed'
	, 'Label not passed'
  , 'This combination of Brand ID and Label does not exist'
	);
	//-----------------------------------------------------------//

  //  
	
	//--------------------------- POST --------------------------//
	//api_key	
	if(isset($_POST['api_key']))
		$api_key = mysqli_real_escape_string($mysqli, $_POST['api_key']);
	else $api_key = null;
	
	//brand_id
	if(isset($_POST['brand_id']) && is_numeric($_POST['brand_id']))
		$brand_id = mysqli_real_escape_string($mysqli, $_POST['brand_id']);
	else $brand_id = null;
		
	//label
	if(isset($_POST['label']))
		$label = mysqli_real_escape_string($mysqli, $_POST['label']);
	else $label = null;
	
	//-----------------------------------------------------------//
	
	//----------------------- VERIFICATION ----------------------//
	//Core data
	if($api_key==null && $brand_id==null && $label==null)
	{
		echo $error_core[0];
		exit;
	}
	if($api_key==null)
	{
		echo $error_core[1];
		exit;
	}
	else if(!verify_api_key($api_key))
	{
		echo $error_core[2];
		exit;
	}
	
	//Passed data
	if($brand_id==null)
	{
		echo $error_passed[0];
		exit;
	}
	else if($label==null)
	{
		echo $error_passed[1];
		exit;
	}

  //So, here we are, I think.
  //We've been passed a brandID and a label.

  // $app = trim(short($brand_id,true));

  $q = 'SELECT to_send,opens FROM campaigns WHERE app = '.$brand_id.' AND label = "'.$label.'";';
  $r = mysqli_query($mysqli, $q);

  if ($r === false) {
      // Log the error message
      error_log('MySQL query error: ' . mysqli_error($mysqli));
      // Return an appropriate response
      http_response_code(500);
      echo json_encode(['error' => 'Database query failed']);
      exit;
  }

  if (mysqli_num_rows($r) == 0) 
  {
    echo $error_passed[2]; 
    exit;
  }
  else
  {
    $data = mysqli_fetch_assoc($r);
    $opens = stripslashes($data['opens']);
    $opens_array = explode(',', $opens);
    $data['total_opens'] = count($opens_array);

    $data_opens = array();
    $data_country = array(); // Initialize the array

    foreach ($opens_array as $open) {
        list($id, $country) = explode(':', $open);
        if (!isset($data_opens[$id])) {
            $data_opens[$id] = 0;
        }
        $data_opens[$id]++;
        
        if (!isset($data_country[$country])) {
            $data_country[$country] = 0;
        }
        $data_country[$country]++;
    }

    $data['unique_opens'] = count($data_opens);
    $data['country_opens'] = $data_country;

    // Tidy up the data a little
    $data['total_sent'] = $data['to_send'];
    $data['brand_id'] = $brand_id;
    $data['label'] = $label;
    unset($data['to_send']);
    unset($data['opens']);

    echo json_encode($data);
  }
	//-----------------------------------------------------------//
?>