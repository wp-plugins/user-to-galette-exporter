<?php
/* Plugin Name: User to Galette Exporter
Description: User to Galette Exporter is a plug-in that synchronise users from Wordpress with Galette users.
Version: 0.1
Author: Jud Alex
Author URI:  http://jud-alex.com/
License: GPLv2
*/
?>
<?php 
add_action('admin_menu', 'galette_user_exporter');

function galette_user_exporter()
{
    add_options_page('User to Galette Exporter', 'User to Galette Exporter', 'manage_options', 'galette_user_exporter_id', 'galette_user_exporter_options');
}

function encrypt($input_string, $key){
    $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
    $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
    $h_key = hash('sha256', $key, TRUE);
    return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $h_key, $input_string, MCRYPT_MODE_ECB, $iv));
}

function decrypt($encrypted_input_string, $key){
    $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
    $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
    $h_key = hash('sha256', $key, TRUE);
    return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $h_key, base64_decode($encrypted_input_string), MCRYPT_MODE_ECB, $iv));
}

function galette_user_exporter_options() 
{
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	?>
	
	
	<?php 
	global $AUTH_KEY;
	
		if($_POST['galette'] == 'Y') {
			//Form data sent
			$DB_USER = sanitize_text_field($_POST['DB_USER']);
			update_option('DB_USER', $DB_USER);
			
			$DB_PASSWORD = sanitize_text_field(encrypt($_POST['DB_PASSWORD'],$AUTH_KEY));
			update_option('DB_PASSWORD', $DB_PASSWORD);

			$DB_NAME = sanitize_text_field($_POST['DB_NAME']);
			update_option('DB_NAME', $DB_NAME);
			
			$DB_HOST = sanitize_text_field($_POST['DB_HOST']);
			update_option('DB_HOST', $DB_HOST);
			
			$Galette_Prefix = sanitize_text_field($_POST['Galette_Prefix']);
			update_option('Galette_Prefix', $Galette_Prefix);
			?>
			<div class="updated"><p><strong><?php _e('Options saved.', 'galette_trdom'); ?></strong></p></div>
			<?php
		} else {
			//Normal page display
			$DB_USER = get_option('DB_USER');
			$DB_PASSWORD = decrypt(get_option('DB_PASSWORD'),$AUTH_KEY);
			$DB_NAME = get_option('DB_NAME');
			$DB_HOST = get_option('DB_HOST');
			$Galette_Prefix = get_option('Galette_Prefix');
		}
	?>	
	
	
	<div class="wrap">
    <?php    echo "<h2>" . 'User to Galette Exporter' . "</h2>"; ?>
     
    <form name="galette" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
			<?php
		if ($DB_USER == ""){
		echo 'DB_USER<br>
		<input type="text" name="DB_USER" id="DB_USER" value="'. $DB_USER .'">
		<br>
		DB_PASSWORD<br>
		<input  type="password" name="DB_PASSWORD" id="DB_PASSWORD" value="'. $DB_PASSWORD .'">
		<br>
		DB_NAME<br>
		<input type="text" name="DB_NAME" id="DB_NAME" value="'. $DB_NAME .'">
		<br>
		DB_HOST<br>
		<input type="text" name="DB_HOST" id="DB_HOST" value="'. $DB_HOST .'">
		<br>
		Galette Prefix<br>
		<input type="text" name="Galette_Prefix" id="Galette_Prefix" value="'. $Galette_Prefix .'">
		<br>';
		}
		else {
		echo '
		BDD infos:
		<input type="text" name="DB_USER" id="DB_USER" placeholder="DB_USER" value="'. $DB_USER .'">
		<input  type="password" name="DB_PASSWORD" id="DB_PASSWORD" placeholder="DB_PASSWORD" value="'. $DB_PASSWORD .'">
		<input type="text" name="DB_NAME" id="DB_NAME" placeholder="DB_NAME" value="'. $DB_NAME .'">
		<input type="text" name="DB_HOST" id="DB_HOST" placeholder="DB_HOST" value="'. $DB_HOST .'">
		<input type="text" name="Galette_Prefix" id="Galette_Prefix" placeholder="Galette_Prefix" value="'. $Galette_Prefix .'">
		<br>';}
		?>
		<input type="hidden" name="galette" value="Y">
        <input type="submit" name="Submit" value="Mise à jour" />
    </form>
	<?php
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        galette_user_exporters();
    }
    echo '</div>';


}

	function galette_user_exporters()
	{
	global $wpdb;
	$DB_USER = get_option('DB_USER');
	$DB_PASSWORD = decrypt(get_option('DB_PASSWORD'),$AUTH_KEY);
	$DB_NAME = get_option('DB_NAME');
	$DB_HOST = get_option('DB_HOST');
	$Galette_Prefix = get_option('Galette_Prefix');
	if ($DB_USER == '' or $DB_PASSWORD == '' or $DB_NAME == '' or $DB_HOST == '') {
		$newdb = $wpdb;
	} else {
		$newdb = new wpdb($DB_USER , $DB_PASSWORD , $DB_NAME , $DB_HOST);
	}
	$newdb->show_errors();
	$userstable = $wpdb->users;
	$resultats = $wpdb->get_results("SELECT * FROM  $wpdb->users") ;
	if ($Galette_Prefix == ''){
		$galettetable = 'galette_adherents';
	} else {
		$galettetable = $Galette_Prefix . '_adherents';
	}
	foreach ($resultats as $post) {
			$modifycounter = 0;
			$list_of_modif ="";
			$the_user = get_user_by('login', $post->user_login);
			$the_user_id = $the_user->ID;
			$result = $newdb->get_row($newdb->prepare("SELECT * FROM {$galettetable} WHERE login_adh = %s", $post->user_login));

			if ($result->login_adh == $post->user_login){
				//update email
				if (get_userdata($the_user_id)->user_email != "$result->email_adh"){
					$newdb->update(
						$galettetable,
						array('email_adh' => get_userdata($the_user_id)->user_email),
						array('login_adh' => $result->login_adh)
						);
					$modifycounter += 1;
					$list_of_modif .= ", email";
				}
				//update url
				if (get_userdata($the_user_id)->user_url != "$result->url_adh"){
					$newdb->update(
						$galettetable,
						array('url_adh' => get_userdata($the_user_id)->user_url),
						array('login_adh' => $result->login_adh)
						);
					$modifycounter += 1;
					$list_of_modif .= ", url";
				}
				//update pseudo
				if (get_userdata($the_user_id)->display_name != "$result->pseudo_adh"){
					$newdb->update(
						$galettetable,
						array('pseudo_adh' => get_userdata($the_user_id)->display_name),
						array('login_adh' => $result->login_adh)
						);
					$modifycounter += 1;
					$list_of_modif .= ", pseudo";
				}
				//update first_name
				if (get_user_meta( $the_user_id, 'first_name', true )!="$result->prenom_adh"){
					$newdb->update(
						$galettetable,
						array('prenom_adh' => get_user_meta( $the_user_id, 'first_name', true )),
						array('login_adh' => $result->login_adh)
						);
					$modifycounter += 1;
					$list_of_modif .= ", first_name";
				}
				//update last_name
				if (get_user_meta( $the_user_id, 'last_name', true )!="$result->nom_adh"){
					$newdb->update(
						$galettetable,
						array('nom_adh' => get_user_meta( $the_user_id, 'last_name', true )),
						array('login_adh' => $result->login_adh)
						);
					$modifycounter += 1;
					$list_of_modif .= ", last_name";
				}

				
				if ($modifycounter != 0){
					echo '<p>'. $result->login_adh . ' has ' . $modifycounter . ' modifications:' . substr($list_of_modif, 1) . '</p>';
				} else {
					echo '<p>'. $result->login_adh . ' is up to date</p>';
				}
				} else  {			
			$newdb->insert(
						$galettetable,
						array(
							'email_adh' => get_userdata($the_user_id)->user_email,
							'pseudo_adh' => get_userdata($the_user_id)->display_name,
							'url_adh' => get_userdata($the_user_id)->user_url,
							'prenom_adh' => get_user_meta( $the_user_id, 'first_name', true ),
							'nom_adh' => get_user_meta( $the_user_id, 'last_name', true ),
							'login_adh'	=> get_userdata($the_user_id)->user_login		
						),
						array(
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s'
						)
						);
						echo '<p>'. $post->login_adh . ' is added</p>';
			
				
		}
	}
	echo "BDD updated";
}
?>