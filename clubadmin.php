<?
set_include_path(get_include_path() . PATH_SEPARATOR . 'gapi/src');
require_once ('Google/autoload.php');

//AUTHENTICATION
define( 'WP_USE_THEMES', false ); // Do not use the theme files
define( 'COOKIE_DOMAIN', false ); // Do not append verify the domain to the cookie
define( 'DISABLE_WP_CRON', true ); // We don't want extra things running...
require("wp-load.php"); // Path (absolute or relative) to where WP core is running

if ( is_user_logged_in() && !is_wp_error(wp_get_current_user()) ) {
	$user = wp_get_current_user();
	if ( is_wp_error( $user ) ) {
		echo $user->get_error_message();
		die();
	}
} else {
	$creds                  = array();
	$creds['user_login']    = htmlspecialchars($_POST['user_login']);
	$creds['user_password'] = htmlspecialchars($_POST['user_password']);
	$creds['remember']      = true;
	$user                   = wp_signon( $creds, false );
	if ( is_wp_error( $user ) ) {
		echo $user->get_error_message();
		echo '<form method=post>';
		echo "	User: <input name='user_login' value='" . htmlspecialchars($_POST['user_login']) . "'><br>";
		echo "	Pass: <input name='user_password' type=password value='" . htmlspecialchars($_POST['user_login']) . "'><br><input type=submit></form>";
		die();
	} else {
		wp_set_auth_cookie( $user->ID, true );
	}
}  //END OF AUTHENTICATION

include "Quick_CSV_import.php";

$con = mysqli_connect("server","db","user","pass");



// Check connection
if (mysqli_connect_errno())
  {
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
  }

$csv = new Quick_CSV_import();



if(isset($_POST["Labels"]) && ""!=$_POST["Labels"]) //form was submitted
{ 
	require('fpdf181/fpdf.php');
	$date = new DateTime($_POST["date"]);
	$arrClubs = fetchClubs($date);
	$arrStudents = fetchStudents($arrClubs,$date,$con);
	$arrText = array(); $i = 0;
	foreach($arrStudents as $student){
		$arrText[$i] = $student["First Name"]." ".$student["Last Name"].":   ".$student["Teacher"]  ."\n".
			$student["Club Dismissal"] . "         " . $date->format('m/d') .  "     (". $student["Non-Club Dismissal"] . ")\n".  
				substr($student["Parent Name"],0,14) .": ". $student["Cellphone"];
				if (!empty($student["SecCellPhone"])) $arrText[$i] = $arrText[$i] . " / " . $student["SecCellPhone"];
					$arrText[$i] = $arrText[$i] ."\n".	$student["Club"];
		$i++;
	}
	
	
	printLabels($arrStudents);
	die();
}

function printLabels($arr) {
	$date = new DateTime($_POST["date"]);
	$pdf = new FPDF();
	$pdf->AddPage();
	$pdf->SetFont('Helvetica', 'B', 8);//
	$pdf->SetMargins(0, 0);
	$pdf->SetAutoPageBreak(false);
	$x = $y = 0;
    $left = 4; // 0.19" in mm
    $top = 27.7; // 0.5" in mm
    $width = 64; // 2.63" in mm
    $height = 25.4; // 1.0" in mm
    $hgap = 5; // 0.12" in mm
    $vgap = 0.0;
	$lineHeight = 5.5;
	
	foreach($arr as $student) {

		$fx = $left + (($width + $hgap) * $x);
		$fy = $top + (($height + $vgap) * $y);
		$pdf->SetXY($fx, $fy);
		$pdf->Cell($width,$lineHeight*4,"",1,0,'L');
		
		$pdf->SetXY($fx, $fy);		
		$pdf->SetFont('Helvetica', 'B', 11);//
		$pdf->Cell($width,$lineHeight,$student["First Name"]." ".$student["Last Name"],1,0,'L');
		$pdf->SetFont('Helvetica', '', 8);//
		$pdf->SetXY($fx, $fy);
		$pdf->Cell($width,$lineHeight,$student["Teacher"],0,1,'R');
		
		$fy+=$lineHeight;
		$pdf->SetXY($fx, $fy);
		$pdf->Cell($width,$lineHeight,$student["Club Dismissal"],0,0,'L');
		$pdf->SetXY($fx, $fy);
		$pdf->Cell($width,$lineHeight,$date->format('m/d'),0,1,'C');
		$pdf->SetXY($fx, $fy);
		$pdf->Cell($width,$lineHeight,"(". $student["Non-Club Dismissal"] . ")",0,1,'R');
		
		$fy+=$lineHeight;
		$pdf->SetXY($fx, $fy);
		$pdf->Cell($width/3,$lineHeight,substr($student["Parent Name"],0,18),0,0,'L');  // could make this a cutoff with 2 cells
		$pdf->SetXY($fx, $fy);
		$txt = $student["Cellphone"];if (!empty($student["SecCellPhone"])) $txt = $txt . " / " . $student["SecCellPhone"];
		$pdf->Cell($width,$lineHeight,$txt,0,0,'R');  // could make this a cutoff with 2 cells

		$fy+=$lineHeight;
		$pdf->SetXY($fx, $fy);
		$pdf->Cell($width,$lineHeight,$student["Club"],0,0,'L');

		$y++; // next row
		if($y == 10) { // end of page wrap to next column
			$x++;
			$y = 0;
			if($x == 3) { // end of page
				$x = 0;
				$y = 0;
				$pdf->AddPage();
			}
		}
	}
	$pdf->Output(); 
}


function merge2dArray(&$merged,$additions) {
  if (!empty($additions)) {
	  if (empty($merged)) {
		  $merged = $additions;
	  } else {
		  $merged = array_merge_recursive($merged,$additions);
	  }
  }
}

function queryToTable($res) {
	$data = array();
	while($row = mysqli_fetch_assoc($res)) {
		$data[] = $row;
	};
	if (empty($data)) {echo "No results to display";return;}
	
	$colNames = array_keys(reset($data));
	
	echo "<table><tr>";
	foreach($colNames as $colName) {
		echo "<th>$colName</th>";
	}
	echo "</tr>";
	foreach($data as $row) {
		echo "<tr>";
		foreach($colNames as $colName) {
			echo "<td>".$row[$colName]."</td>";
		}   
		echo "</tr>";
	}
	echo "</table>";
	return $data;
}

function queryToArray($res) {
	$data = array();
	if (!empty($res))  {
		while($row = mysqli_fetch_assoc($res)) {
			$data[] = $row;
		};
	return $data;
	} else {
		echo "NO DATA!";
		return NULL;
	}
}

function arrToTable($data,$arrInclude = array()) {
	if (empty($data)) {echo "No results to display";return;}
	$colNames = array_keys(reset($data));
	if (!empty($arrInclude)) $colNames = $arrInclude;
	$result = "<table border=1 cellpadding=3 cellspacing=0>\r\n<tr>";
	foreach($colNames as $colName) {
		$result .= "<th>$colName</th>";
	}
	$result .= "</tr>\r\n";
	$i= 0;
	foreach($data as $row) {
		$i++;
		$result .= "<tr>";
		foreach($colNames as $colName) {
			$result .= "<td>".$row[$colName]."</td>";
		}   
		$result .= "</tr>\r\n";
	}
	$result .= "</table>\r\n". $i." entries<br>";
	return $result;
}

?>
	
  <a href="groupemails.php">Click here to email a group</a><br>
  <form method="post" enctype="multipart/form-data">
  <div style="float:right">
   Upload latest student or staff roster from CSV file:<br>
   <input type="file" name="file_source" id="file_source" class="edt" value="<?=$file_source?>">
   <br><br>
   <input type="Submit" name="Students" value="Upload STUDENT Roster" >
   <br><br>
   <input type="Submit" name="Staff" value="Upload STAFF roster" onclick="">
   </div>
   <div>
   <div style="width:500px;border:1px;display: inline-block;border-width: 1px;">
	<?
		echo (!empty($csv->error) ? "<hr/>Errors: ".$csv->error : "");
		if(isset($_POST["Students"]) && ""!=$_POST["Students"]) //form was submitted
		{
		  $csv->file_name = $_FILES['file_source']['tmp_name'];
		  $csv->table_drop = true; 
		  $csv->table_name = "clubs_roster";
		  echo "students:" . $csv->import();//start import now
		}
		
		if(isset($_POST["Staff"]) && ""!=$_POST["Staff"]) //form was submitted
		{
		  $csv->file_name = $_FILES['file_source']['tmp_name'];
		  $csv->table_drop = true; 
		  $csv->table_name = "clubs_staff";
		  echo "staff:" . $csv->import();//start import now
		}
		
	?>
  <script src="//code.jquery.com/jquery-1.10.2.js"></script>
  <script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
<link rel="stylesheet" href="http://code.jquery.com/ui/1.10.3/themes/smoothness/jquery-ui.css" />  
  <script>
  $(function() {
    $( "#datepicker" ).datepicker({
		dateFormat: "yy-mm-dd"
	});
  });


  </script>
	<br><br>
	Prepare clubs for:	
	<input type=input value="<?
		if(isset($_POST["date"])) 
			echo $_POST["date"];
		else
			echo date(("Y-m-j"),time());
	?>" name=date id=datepicker></input>
	<br>
	<input type="Submit" name="Emails" value="Preview emails" >
	<?
	$date = new DateTime($_POST["date"]);
	if(isset($_POST["Emails"])) {
		if ("Preview emails"==$_POST["Emails"])	{
		echo "<hr>Subject: <input name='Email_Subject' size=50 value='[EakinClubs] Report for " . $date->format('l, Y-m-d') . "'><br>";
		echo "Body Introduction: <br><textarea name='Email_Intro' rows=4 cols=50>The following children will be attending clubs on " . $date->format('l, Y-m-d') . "</textarea><br>";
			
		echo "<br><input type='Submit' name='Emails' value='SEND EMAILS' >";
		}
		elseif ("SEND EMAILS"==$_POST["Emails"]) {
			
			
		}
	}
	?>
	<br><br>
	<input type="Submit" name="Labels" value="PDF Labels" >
  <style>
		div {
			font-size:xx-small;
		}
		div th {
			font-size:xx-small;
		}
		div td {
			font-size:xx-small;
		}
		@media print
		{
			table {font-size:10px} /*Type the value that you want*/
		}
  </style>
  </div>
  <div  style="width:200px;display: inline-block;">
  <input type="checkbox" name="group_ECP" value="true" <? if (!isset($_POST["Emails"]) || $_POST['group_ECP'] == 'true') echo 'checked';?>>ECP<br>
<input type="checkbox" name="group_EHH" value="true" <? if (!isset($_POST["Emails"]) || $_POST['group_EHH'] == 'true') echo 'checked';?>>EHH<br>
<input type="checkbox" name="group_teachers" value="true" <? if (!isset($_POST["Emails"]) || $_POST['group_teachers'] == 'true') echo 'checked';?>>Teachers<br>
<input type="checkbox" name="group_clubs" value="true" <? if (!isset($_POST["Emails"]) || $_POST['group_clubs'] == 'true') echo 'checked';?>>Club Leaders<br>
<input type="checkbox" name="group_support" value="true" <? if (!isset($_POST["Emails"]) || $_POST['group_support'] == 'true') echo 'checked';?>>Support Staff<br>
(uncheck everything and you will still receive the admin email with lists for each group)
  </div>
  </div>
  </form>
 <?
function fetchClubs($date) {
	$client = new Google_Client();
	$client->setApplicationName("Client_Library");
	$apiKey = "google-api-key"; 
	$client->setDeveloperKey($apiKey);
	$service = new Google_Service_Calendar($client);
	$calendarId = 'calendaraccount@gmail.com';  //club dates stored in google calendar for user friendliness
	$optParams = array(
	  'singleEvents' => TRUE,
	  'timeMin' => $date->format('Y-m-d').'T00:00:00-06:00',
	  'timeMax' => $date->format('Y-m-d').'T23:59:59-06:00',
	);
	$results = $service->events->listEvents($calendarId, $optParams);
	if (count($results->getItems()) == 0) {
		echo "No upcoming events found.\n<br>";
		die();
	} else {
	  $i=0;
	  foreach ($results->getItems() as $event) {
		  $arrRes[$i] = array($event->getSummary(),$event->getDescription());
		  if (empty($arrRes[$i][1])) $arrRes[$i][1] = 0;
		  $i++;
	  }
	  return $arrRes;
	}
}
function fetchStudents($clubs,$date,$con) {
	$sql = ""; 
	foreach ($clubs as $club) {
		  $sql = $sql . "SELECT `Division Name` `Club`,`DivisionID` `ID`,`Player First Name` `First Name`,`Player Last Name` `Last Name`, ".
			"`Players Age` `Age`,`Gender`,`Parent Name`,`Cellphone`,`SecCellPhone`,`Email`,`Current Grade` `Grade`,`Student's Teacher` `Teacher`, ".
			"`How will your child dismiss on club days?` `Club Dismissal`,`How does your child dismiss from school on non-club days?` `Non-Club Dismissal`".
		  
		  "FROM clubs_roster WHERE `DivisionID` = '".$club[1].  "'" .
          "AND `Division Name` LIKE '%". substr($date->format('l'),0,3) . "%'" .
		  " UNION ";		
	}
    $sql = substr($sql,0,strrpos($sql,"UNION"));
	$res = @mysqli_query($con,$sql);
	return queryToArray($res);
}

require_once 'vendor/swiftmailer/swiftmailer/lib/swift_required.php';
$adminEmail = 'admin@mailserver.com';
$adminTitle = 'Clubs Coordinator';
$adminPassword = 'password';

function send_mail($recipients,$content) {

  $message = Swift_Message::newInstance()
  ->setSubject($_POST["Email_Subject"])
  ->setFrom(array($adminEmail => $adminTitle))
  ->setBody("<html><head><title>Clubs Email</title></head><body><h4>".
	$_POST["Email_Intro"]."</h4>".$content."</html>", 'text/html')
  ;
  $transport = Swift_SmtpTransport::newInstance('smtp.mailserver.com', 25)
  ->setUsername($adminEmail)
  ->setPassword($adminPassword)
  ;
  $mailer = Swift_Mailer::newInstance($transport);  
  $mailer->registerPlugin(new Swift_Plugins_AntiFloodPlugin(95, 60));
  $failedRecipients = array();
  $numSent = 0;

	foreach ($recipients as $recipient)
	{
	  $message->setTo(array($recipient['EMAIL'] => $recipient['NAME']));
	  $numSent += $mailer->send($message, $failedRecipients);
	  echo "<font color=red>Sending to ".$recipient['NAME']. " &lt;".$recipient['EMAIL'] ."&gt;</font><br>\r\n";
	}

	printf("<font color=red>Sent %d messages\n<br></font>", $numSent);
	if (!empty($failedRecipients))
		echo "Failures: <br><font color=red>". implode('<br>',$failedRecipients)."</font>";
	
}

			
if(isset($_POST["Emails"]) && ""!=$_POST["Emails"]) //form was submitted
{ 
  $date = new DateTime($_POST["date"]);
  print "<h3> Clubs on ". $date->format('l, Y-m-d') .":</h3>";
  $arrClubs = fetchClubs($date);
  //echo implode("<br>",$arrClubs);
  foreach ($arrClubs as $club) echo $club[0] . " (".$club[1].")<br>";
  echo "<br>";
  $arrStudents = fetchStudents($arrClubs,$date,$con);
 

  echo "<hr><h3>Previewing email blast for " . $date->format('l, Y-m-d') . ":</h3>";
  $sql = "SELECT * FROM clubs_staff";
  $res = @mysqli_query($con,$sql);
  echo mysqli_error($con); 
  $arrStaff = queryToArray($res);
  mysqli_free_result($res);
  $combinedChart = "";
	
	////////////////////////////////////ECP
	$gStaff = array(); $i = 0;
	foreach ($arrStaff as $staffMember) {
	  if ($staffMember['GROUP']=='ECP') {
		  $gStaff[$i] = $staffMember;
		  $i++;
	  }
	}
	$fields = array("NAME","EMAIL");  
	$staffChart =  "<h3>ECP staff to be emailed</h3>" . arrToTable($gStaff,$fields);

	$gStudents = array(); $i =0;
	foreach($arrStudents as $student) {
	  if ($student['Club Dismissal'] == 'ECP') {
		  $gStudents[$i]=$student;
		  $i++;
	  }
	}
	$fields = array("First Name","Last Name","Club","Teacher");  
	$studentChart =  "<h3>ECP students with clubs</h3>" . arrToTable($gStudents,$fields) . "<hr>";
	$combinedChart .= $studentChart;
	if ("true"==$_POST["group_ECP"]) {  
		echo $staffChart;
		if ("SEND EMAILS"==$_POST["Emails"]) send_mail($gStaff,$studentChart);
		echo  $studentChart;
	}

	/////////////////////////////////////EHH
	$gStaff = array(); $i = 0;
	foreach ($arrStaff as $staffMember) {
	  if ($staffMember['GROUP']=='EHH') {
		  $gStaff[$i] = $staffMember;
		  $i++;
	  }
	}
	$fields = array("NAME","EMAIL");  
	$staffChart = "<h3>EHH staff to be emailed</h3>" . arrToTable($gStaff,$fields);

	$gStudents = array();  $i =0;
	foreach($arrStudents as $student) {
	  if ($student['Club Dismissal'] == 'EHH') {
		  $gStudents[$i]=$student;
		  $i++;
	  }
	}
	$fields = array("First Name","Last Name","Club","Teacher");  
	$studentChart =  "<h3>EHH students with clubs</h3>" . arrToTable($gStudents,$fields) . "<hr>";
	$combinedChart .= $studentChart;
	if ("true"==$_POST["group_EHH"]) { 
		echo "<hr>";
		echo  $staffChart;
		if ("SEND EMAILS"==$_POST["Emails"]) send_mail($gStaff,$studentChart);
		echo  $studentChart;
	}
  
  
	usort($arrStudents, function($a, $b) {return $a["Teacher"] > $b["Teacher"];});
	$arrGrades = array('K','1','2','3','4');
	foreach ($arrGrades as $grade) {
		$gStaff = array(); $i = 0;
		foreach ($arrStaff as $staffMember) {
			if ($staffMember['GRADE']==$grade) {
				$gStaff[$i] = $staffMember;
				$i++;
			}
		}
		$fields = array("NAME","EMAIL");  
		$staffChart = "<hr><h3>Grade ".$grade." teachers to be emailed</h3>" . arrToTable($gStaff,$fields);

		$gStudents = array(); $i =0;
		foreach($arrStudents as $student) {
			if (substr($student['Grade'],0,1) == $grade) {
				$gStudents[$i]=$student;
				$i++;
			}
		}
		$fields = array("Teacher","First Name","Last Name","Club");  
		$studentChart =  "<h3>Grade ".$grade." students with clubs</h3>" . arrToTable($gStudents,$fields) . "<hr>";
		$combinedChart .= $studentChart;
		if ("true"==$_POST["group_teachers"])  { 
			echo $staffChart;
			if ("SEND EMAILS"==$_POST["Emails"]) send_mail($gStaff,$studentChart);
			echo $studentChart;
		}
	}
	
	
	///////////////CLUB LEADERS
	foreach($arrClubs as $club) {
		$gStaff = array(); $i = 0;
		foreach ($arrStaff as $staffMember) {
			if ($staffMember['GROUP'] == 'club' && strncasecmp($staffMember['CLUB'],$club[0],strlen($staffMember['CLUB'])) == 0) {
				$gStaff[$i] = $staffMember;
				$i++;
			}
		}
		$fields = array("NAME","EMAIL");  
		$staffChart = "<hr><h3>" . $club[0] . " staff:</h3>" . arrToTable($gStaff,$fields);

		$gStudents = array(); $i =0;
		$x = 0;		
		foreach($arrStudents as $student) {
			//if (substr($student['Club'],0,strlen($club[0])) == $club[0]) {
			//echo $x++ . $student['ID'] . "=" . $club[1] . " <br>";
			if ($student['ID'] == $club[1]) {
				$gStudents[$i]=$student;
				$i++;
			}
		}
		$fields = array("First Name","Last Name","Club Dismissal","Teacher","Parent Name","Cellphone","SecCellPhone","Email");  
		$studentChart =  "<h3>Students attending " . $club[0] . ":</h3>" . arrToTable($gStudents,$fields) . "<hr>";
		$combinedChart .= $studentChart;
		if ("true"==$_POST["group_clubs"]) {
			echo  $staffChart;		  
			if ("SEND EMAILS"==$_POST["Emails"]) send_mail($gStaff,$studentChart);
			echo  $studentChart;		  
		}
	}
  
	//////////////SUPPORT
	usort($arrStudents, function($a, $b) {return $a["Last Name"] > $b["Last Name"];});
	$gStaff = array(); $i = 0;
	foreach ($arrStaff as $staffMember) {
		if ($staffMember['GROUP']=='support') {
			$gStaff[$i] = $staffMember;
			$i++;
		}
	}
	$fields = array("NAME","EMAIL");  
	$staffChart = "<hr><h3>Support staff to be emailed the master list</h3>" . arrToTable($gStaff,$fields);

	$fields = array("Club","First Name","Last Name","Gender","Age","Club Dismissal","Non-Club Dismissal","Teacher","Parent Name","Cellphone","SecCellPhone","Email");  
	$studentChart =  "<h1>Master list of kids with clubs:</h1>" . arrToTable($arrStudents,$fields) . "<hr>";
	$combinedChart .= $studentChart;
	if ("true"==$_POST["group_support"])  { 
		echo  $staffChart;		  
		if ("SEND EMAILS"==$_POST["Emails"]) send_mail($gStaff,$studentChart);
		echo  $studentChart;		  
	}
  
  //send combined list to admin
  if ("SEND EMAILS"==$_POST["Emails"]) send_mail(array(array('EMAIL' => $adminEmail, 'NAME' =>$adminTitle)),$combinedChart);

}
?>
</html>
 