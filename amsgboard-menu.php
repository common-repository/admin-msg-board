<?php

global $current_user;
	
// If needed, update who read the last message to include the current user
function amsgboardreadby() {
	
	global $current_user;
	global $wpdb;
	
	if ( "Delete!" != $_POST['amsgboardform'] and "Delete all messages" != $_POST['amsgboardform'] ) {

		$amsgboardlast = $wpdb->get_row("SELECT id,readby,sentto FROM wp_amsgboard order by id DESC limit 1", ARRAY_A);

		// Explode the string that saves who read already the story into an array and check for the current user
		if ( !in_array($current_user->ID, explode("-", $amsgboardlast["readby"])) and  "subscriber" != $current_user->roles[0] and ( in_array($current_user->ID, explode("-", $amsgboardlast["sentto"])) or "all users" == $amsgboardlast["sentto"] ) ) {

			// Add the current user to the string and update the field
			$amsgboardlast["readby"] .= "-" . $current_user->ID;
			$wpdb->query("UPDATE wp_amsgboard SET readby = '$amsgboardlast[readby]' WHERE id = $amsgboardlast[id]");
		}
	}
}

// Function to display how long ago the messages were posted
function amsgboardtimesago( $amsgboarddatestored ) {

	//Get the current time
	$amsgboardtimenowtime = current_time(timestamp);
	
	// Do the math to check the difference between when it was stored and right now
	$amsgboardtimesum = $amsgboardtimenowtime - $amsgboarddatestored;
	
	if ( $amsgboardtimesum > 31104000 )
		$amsgboardreturned = round($amsgboardtimesum / 60 / 60 / 24 / 30 / 12) . __(' years', 'wp-admin-msg-board');
	elseif ( $amsgboardtimesum > 2592000 )
		$amsgboardreturned = round($amsgboardtimesum / 60 / 60 / 24 / 30) . __(' months', 'wp-admin-msg-board');
	elseif ( $amsgboardtimesum > 604800 )
		$amsgboardreturned = round($amsgboardtimesum / 60 / 60 / 24 / 7) . __(' weeks', 'wp-admin-msg-board');	
	elseif ( $amsgboardtimesum > 86400 )
		$amsgboardreturned = round($amsgboardtimesum / 60 / 60 / 24) . __(' days', 'wp-admin-msg-board');
	elseif ( $amsgboardtimesum > 3600 )
		$amsgboardreturned = round($amsgboardtimesum / 60 / 60) . __(' hours', 'wp-admin-msg-board');
	elseif ( $amsgboardtimesum > 60 )
		$amsgboardreturned = round($amsgboardtimesum / 60) . __(' minutes', 'wp-admin-msg-board');
	else
		$amsgboardreturned = $amsgboardtimesum . __(' seconds', 'wp-admin-msg-board');
	
	// Remove the extra "s"	if the number is 1, only in English and Spanish, the languages I created and know
	$current_locale = get_locale();
	if ( $current_locale == "" or $current_locale == "es_ES" ) {
		if ( in_array("1", explode(" ",$amsgboardreturned)) )
			$amsgboardreturned = substr($amsgboardreturned, 0, -1);
			
		// Fix error with "meses" (Spanish for months) that in singular should be "mes" and not "mese"
		if ( $current_locale == "es_ES" and $amsgboardtimesum < 5184000 and $amsgboardtimesum > 2592000 )
			$amsgboardreturned = substr($amsgboardreturned, 0, -1);
	}
	
	$amsgboardreturned .= __(' ago', 'wp-admin-msg-board');
	return $amsgboardreturned;
}

// If something was done (added a message, deleted all messages or deleted the oldest "x" messages)
if ( !empty($_POST['amsgboardform']) ) {

	switch ( $_POST['amsgboardform'] ) {
		
		// The current user sent a new message
		case __('Send', 'wp-amsgboard'):
			
			// Get all the info to populate the db and insert it
			$dateamsgboard = current_time(timestamp);
			$msgamsgboard = $wpdb->escape($_POST["message"]);
			$readbyamsgboard = $current_user->ID;

			$amsgboardsentto = "";
			if ( "all users" == $wpdb->escape($_POST["amsgboardall"]) )
			  $amsgboardsentto = "all users";
			elseif  ( "no" == $wpdb->escape($_POST["amsgboardall"]) ) {
				$amsgboardi=1;
				while ($_POST["amsgboardlastvalue"] >= $amsgboardi) {
					if ( is_numeric($wpdb->escape($_POST['amsgboardusers'.$amsgboardi])) )
						$amsgboardsentto .= $wpdb->escape($_POST['amsgboardusers'.$amsgboardi]) . "-";
					$amsgboardi++;
				}
			}
			
			// Get the last message id and add one
			$idamsgboard = $wpdb->get_var("SELECT id FROM wp_amsgboard order by id DESC limit 1") + 1;
			
			$insert = "INSERT INTO wp_amsgboard (id, dateposted, msgposted, sentto, readby) " .
            		"VALUES ('$idamsgboard', '$dateamsgboard', '$msgamsgboard', '$amsgboardsentto', '$readbyamsgboard')";

			$results = $wpdb->query($insert);
			break;
		
		// The current user deleted all messages
		case __('Delete all messages', 'wp-amsgboard'):
		
			// Check if the current user is administrator
			if ( "administrator" == $current_user->roles[0] )
				$truncateamsgboard = $wpdb->query("TRUNCATE TABLE wp_amsgboard");
			break;
			
		// The current user dropped entire database table
		case __('Drop entire table', 'wp-amsgboard'):
		
			// Check if the current user is administrator
			if ( "administrator" == $current_user->roles[0] ) {
				
				// Drop table
				$dropamsgboard = $wpdb->query("DROP TABLE wp_amsgboard");
				
				// Delete option and deactivate plugin
				delete_option("amsgboard_db_version");
				$amsgboardcurrent = get_option('active_plugins');
				array_splice($amsgboardcurrent, array_search( "admin-msg-board/admin-msg-board.php", $amsgboardcurrent), 1 ); // Array-fu!
				update_option('active_plugins', $amsgboardcurrent);
				do_action('deactivate_' . trim( "admin-msg-board/admin-msg-board.php" ));
				echo "<script language='JavaScript'>";
				echo "location = '".get_option('siteurl')."/wp-admin/plugins.php?deactivate=true'";
				echo "</script>";
			}
			break;
		
		// The current user deleted the oldest "x" messages
		case __('Delete!', 'wp-amsgboard'):
		
			// Check if the current user is the administrator
			if ( "administrator" == $current_user->roles[0] ) {
				$amsgboardmany = $_POST["amsgboardformdel"] + 1;
				$amsgboarddelid = $wpdb->get_results("SELECT id FROM wp_amsgboard order by id ASC limit $amsgboardmany", ARRAY_A);
				$amsgboardmax = $amsgboarddelid[count($amsgboarddelid)-1][id];
			
				if ( count( 0 == $amsgboarddelid)-1 )
					$deleteamsgboard = $wpdb->query("DELETE FROM wp_amsgboard WHERE id = $amsgboardmax");
				elseif ( count($amsgboarddelid)-1 > 0 )
					$deleteamsgboard = $wpdb->query("DELETE FROM wp_amsgboard WHERE id < $amsgboardmax");
			}
			break;
	}
}

// Calls the function to update who read the last message to include the current user if needed
amsgboardreadby();

?>

<div class="wrap">

<SCRIPT LANGUAGE="JavaScript">
<!-- Begin
function textCounter(field, countfield, maxlimit) {
if (field.value.length > maxlimit)
field.value = field.value.substring(0, maxlimit);
else 
countfield.value = maxlimit - field.value.length;
}
// End -->
</script>

<h2 style="margin-bottom:30px">Admin Msg Board</h2>

<?php

// Get all the messages to show
$amsgboardshows = $wpdb->get_results("SELECT * FROM wp_amsgboard order by id DESC");

// Browse the messages to print them
foreach ( $amsgboardshows as $amsgboardshow ) {
	
	// Get the author, who is the first user to read the message (readby field)
	if ( strpos($amsgboardshow->readby, '-' ) )
	  $amsgboardauthor = substr($amsgboardshow->readby, 0, strpos($amsgboardshow->readby, '-'));
	else
	  $amsgboardauthor = $amsgboardshow->readby;

	// Check if the message was sent to the current user to decide whether to display it or not
	if ( "subscriber" != $current_user->roles[0] and ($current_user->ID == $amsgboardauthor or in_array($current_user->ID, explode("-",$amsgboardshow->sentto)) or "all users" == $amsgboardshow->sentto) ) {
	
	echo '<table width="70%" border="0" cellspacing="3" cellpadding="3">
		<tr class="thead">
			<td style="padding-left:5px;"><span style="float:left; margin-top:3px;"><strong>';
			
			$amsgboarduser = get_userdata($amsgboardauthor);
			echo $amsgboarduser->display_name;
					
			echo '</strong> ' . amsgboardtimesago($amsgboardshow->dateposted) . '</span><span style="float:right; padding-right: 20px; color:#000; font-weight:bold; font-size:20px"><span style="font-size:10px; color: #666666; paddding-right:3px;">msg id</span> ' . $amsgboardshow->id . '</span></td>
		</tr>
		<tr>
			<td valign="top" style="background-color: #eee; padding-left:20px">' . $amsgboardshow->msgposted . '
			<p align="right" style="margin-right:5px"><strong>';
			_e('Sent to:', 'wp-admin-msg-board');
			echo "</strong> ";
			if ( "all users" == $amsgboardshow->sentto )
			  _e('All users.', 'wp-admin-msg-board');
			else {
				$amsgboardlist = explode("-",$amsgboardshow->sentto);
				for ( $i=0; $i < count($amsgboardlist); $i++ ) {
					$amsgboarduser = get_userdata($amsgboardlist[$i]);
					echo $amsgboarduser->display_name;
					if ( 2 < count($amsgboardlist) - $i )
						echo ", ";
				}
				echo ".</p>";
			}
			
				echo '</td>
		</tr>
	</table><br>';
	}
}

?>

<p style="margin-top:30px"><?php _e('Post a new message:', 'wp-admin-msg-board'); ?></p>
<form name="new_amsgboard_form" method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
<input type="hidden" name="amsgboardform" value="Send">
<p><textarea wrap="physical" rows="2" cols="80" name="message" onKeyDown="textCounter(this.form.message,this.form.remLen,140);" onKeyUp="textCounter(this.form.message,this.form.remLen,140);" /></textarea></p>
<p style="margin-bottom:30px;"><input readonly type="text" name="remLen" size="3" maxlength="3" value="140">
<?php _e('characters left', 'wp-admin-msg-board'); ?> <input class="button" style="margin-left:15px; padding-right:15px; padding-left:15px;" type="submit" name="amsgboardform1" value="<?php _e('Send', 'wp-admin-msg-board'); ?>" /></p>

<table width="500px" border="0" cellpadding="0" cellspacing="0">
<tr><td>
	<p><input style="background:none; border:0" type="radio" name="amsgboardall" value="all users" checked> <?php _e('Send to all users', 'wp-admin-msg-board'); ?></p>
	
</td><td>

	<p><input style="background:none; border:0" type="radio" name="amsgboardall" value="no"> <?php _e('Select recipients:', 'wp-admin-msg-board'); ?></p>
	
</td>
</tr>
<tr>
<td>&nbsp;</td>
<td>
<?php

// Display users to select recipients of the message
$userids = $wpdb->get_col("SELECT ID FROM $wpdb->users;");
$amsgboardcountform = 0;
foreach($userids as $userid) {
	$user_object = new WP_User($userid);

	// Don't display Subscribers
	if ( $user_object->user_level > 0 ) {
		$amsgboardcountform++;
		echo "<p><input style='background:none; border:0' type='checkbox' name='amsgboardusers".$amsgboardcountform."' id='amsgboardusers".$amsgboardcountform."' value='{$user_object->ID}' /> <label for='amsgboardusers".$amsgboardcountform."'>$user_object->display_name</label></p>";
	}
}
?>
<input type="hidden" name="amsgboardlastvalue" value="<?php echo $amsgboardcountform; ?>">

</form>
</td>
</tr>
</table>
<?php

// // Check if the current user is the administrator to decide whether to allow him to delete messages or not
if ( "administrator" == $current_user->roles[0] ) {
?>
<form name="delete_amsgboard_form" method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
<input type="hidden" name="amsgboardform" value="Delete!">
<p align="right"><?php _e('Delete the oldest', 'wp-admin-msg-board'); ?> <input type="text" size="3" name="amsgboardformdel"> <?php _e('messages', 'wp-admin-msg-board'); ?> <input class="button" type="submit" name="amsgboardform2" value="<?php _e('Delete!', 'wp-admin-msg-board'); ?>" onclick="return confirm('<?php _e('You Are About To Delete some messages posted at the Admin Msg Board.\n\n Choose [Cancel] To Stop, [OK] To Delete.', 'wp-admin-msg-board'); ?>')" />
</form>

<p style="text-align: right; margin-right:75px;"><?php _e('or', 'wp-admin-msg-board'); ?><p>

<form name="delete_amsgboard_form" method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
<input type="hidden" name="amsgboardform" value="Delete all messages">
<p align="right" class="submit"><input style="background:#FF0000; color:#FFFFFF" type="submit" name="amsgboardform3" value="<?php _e('Delete all messages', 'wp-admin-msg-board'); ?>" onclick="return confirm('<?php _e('You Are About To Delete all messages posted at the Admin Msg Board.\n\n Choose [Cancel] To Stop, [OK] To Delete.', 'wp-admin-msg-board'); ?>')" />
</form>

<p style="text-align: right; margin-right:75px;"><?php _e('or', 'wp-admin-msg-board'); ?><p>

<form name="delete_amsgboard_form" method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
<input type="hidden" name="amsgboardform" value="Drop entire table">
<p align="right" class="submit"><input style="background:#FF0000; color:#FFFFFF" type="submit" name="amsgboardform4" value="<?php _e('Drop entire database table', 'wp-admin-msg-board'); ?>" onclick="return confirm('<?php _e('You Are About To Delete the whole database table Admin Msg Board creates when activated, and also deactivate this plugin.\n\n Choose [Cancel] To Stop, [OK] To Delete.', 'wp-admin-msg-board'); ?>')" />
</form>
<?php
}
?>

<?php echo "<p style='margin-top:50px' align='right'>Admin Msg Board " . __('version ', 'wp-admin-msg-board') . get_option( "amsgboard_db_version" ) . __(' by', 'wp-admin-msg-board') . " <a href='http://bitsignals.com'>Julian Yanover</a></p>"; ?>

</div>