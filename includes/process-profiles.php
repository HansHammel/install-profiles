<?php 
	
	
	function wpip_save_profile() {
	// checks for form submission
	$lines = ($_POST['pluginNames']);
	$linesArray = explode("\n", $lines);
		
		// checks for new filename or saves over existing file
		if ( !empty($_POST['profileName']) ) {
			$profileName = esc_attr($_POST['profileName']) . '.profile';
		} else {
			$profileName = esc_attr($_POST['profileFilename']);
		}
		
		$profileName = str_replace(' ', '-', $profileName);
		
		// write file if nonce verifies
		if ( wp_verify_nonce($_POST['wpip_submit'],'plugins_to_download') ) {	
			$newProfile = fopen(WP_PLUGIN_DIR . '/install-profiles/profiles/' . $profileName,"w"); 
			$written =  fwrite($newProfile, $lines);
	
			fclose($newProfile);
		}
	
	if ( ($written > 0) && !isset($_POST['downloadPlugins']) ) { ?>
		<div class="updated">
			<p><strong><?php print esc_attr($profileName); ?></strong> saved.&nbsp;  
			<a href="plugins.php?page=installation_profiles&download=<?php print $profileName ?>">Download</a>
			</p>
		</div>
	<?php }
	}
	
	function wpip_profile_select() { 
		// manages the ajax request to load profile files
	?>	
		<script type="text/javascript">
			jQuery(document).ready(function($) {
					$('#profileFilename').change(function() {
					var filename = $(this).val();
					var filepath = '<?php print plugins_url('profiles',dirname(__FILE__)) ?>/' + filename;
					
					$.ajax({
						url: filepath,
						cache:false,
						success:function(text) {
							$('#pluginNames').val(text);
						}
					});
					
				}); // end .change
			});
		</script>
	<?php }
	
	
function wpip_download_profile() {
		// sanitize filename & path 
		$file = trim(urldecode($_GET['download']));
		$fileExtension = end(explode('.', $file));
		
		if ( !validate_file($file) && $fileExtension == 'profile' ) {
			$file = WP_PLUGIN_DIR . '/install-profiles/profiles/' . $file;
			
	
			if (file_exists($file)) {
				header('Content-Description: File Transfer');
				header('Content-Type: application/octet-stream');
				header('Content-Disposition: attachment; filename='.basename($file));
				header('Content-Transfer-Encoding: binary');
				header('Expires: 0');
				header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
				header('Pragma: public');
				header('Content-Length: ' . filesize($file));
				ob_clean();
				flush();
				readfile($file);
				exit;
			}
	} // end check for valid file

}
	
	function wpip_import_profile() {
		
		// add check for '.profile' in filename
		$newFile = $_FILES['importedFile']['tmp_name'];
		$newFileName = $_FILES['importedFile']['name'];
		$uploadDir = WP_PLUGIN_DIR . '/install-profiles/profiles/' . $newFileName;
		
		
		// check if file ends in .profile
		$fileExtension = end(explode('.', $newFileName));
		
		if ( $fileExtension == 'profile' && wp_verify_nonce($_POST['wpip_upload'],'upload_profile') ) {
			$moved = move_uploaded_file($newFile,$uploadDir);
		}
		
		
		if ( $moved ) { ?>
			<div class="updated">
				<p>Imported <strong><?php print esc_attr($newFileName); ?></strong>. </p>
			</div>
		<?php }	else { ?>
			<div class="error">
				<p>Couldn't import <strong><?php print esc_attr($newFileName); ?></strong>. </p>
			</div>
		<?php }
	}
	
	
	function wpip_fetch_plugins() {
		$lines = $_POST['pluginNames'];
		$linesArray = explode("\n", $lines);
		
		
		if ( !empty($lines) && $_POST['downloadPlugins'] && wp_verify_nonce($_POST['wpip_submit'],'plugins_to_download')) { ?>
			<div class="updated">
			<p><strong>Downloaded plugins:</strong></p>
			<ul id="pluginDownloadSuccess">
			<?php 
			foreach ($linesArray as $line) {
				unset($downloadTest);
				$apiFilename = trim(str_replace(' ', '-', $line));
				$apiFilename = urlencode($apiFilename);
				
				if ( empty($apiFilename) || $apiFilename == 'install-profiles' ) {
					continue;
				}
				$apiURL = 'http://api.wordpress.org/plugins/info/1.0/' . $apiFilename . '.xml';
				
				$plugin = simplexml_load_file($apiURL);

				// gets filename from Wordpress API
					$pluginURL = $plugin->download_link;
					$apiName = $plugin->name;
					$apiVersion = $plugin->version;
					$apiHomepage = $plugin->homepage;
					
					if ( !empty($pluginURL) ) {
						$path_parts = pathinfo($pluginURL);
						$filename = $path_parts['filename'] . '.' . $path_parts['extension'];
						$path = $filename;
					
						$ch = curl_init($pluginURL);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					 
						$data = curl_exec($ch);
					 
						curl_close($ch);
					 
						$downloadTest = file_put_contents($path, $data);

						// extracts and deletes zip file
						$zip = new ZipArchive;
							
						if ($zip->open($filename) === TRUE) {
							$zip->extractTo(WP_PLUGIN_DIR);
							$zip->close();
							//echo 'ok';
						} else {
							//echo 'failed';
						}
					}
					
					if ( $downloadTest > 0 ) {
						$delete = unlink($filename);
						print '<li><a href="' . esc_url($apiHomepage) . '" target="_blank">'. esc_attr($apiName) . '</a> ' . esc_attr($apiVersion) . '</li>';
					} else {
						print "<li>Couldn't find <strong>'" . esc_attr($line) . "'</strong></li>";
					}  

			} // end foreach  ?>
			</ul>		
			<p style="margin-top:20px;font-weight:bold">
				<?php print '<a href="' . admin_url('plugins.php?plugin_status=inactive') . '">Visit plugins page</a>'; ?>
			</p>
			</div>
		<?php } // end if isset 
		
	}
	
	/////////////////////////////////////////////////////
	
	function wpip_import_from_wpip_api() {
		$apiUserName = urlencode($_POST['apiUserName']);
		
		$wpipApiURL = 'http://plugins.ancillaryfactory.com/api/user/'.$apiUserName;
		$apiProfileData = simplexml_load_file($wpipApiURL);
		
		$profileCount = count($apiProfileData->profile);
		
		if ( wp_verify_nonce($_POST['wpip_api'],'import_from_api')  ) {
		
			$i = 0;
			while ( $i < $profileCount ) { 
				unset($importedProfilePlugins);
				
				$importedProfileName = $apiProfileData->profile[$i]->name;
				$importedFileName = $importedProfileName . '.profile';
			
				$plugins =  $apiProfileData->profile[$i]->plugins->plugin; 
				foreach ( $plugins as $plugin ) { 
					if ( !empty($plugin) ) {
						$importedProfilePlugins .= trim($plugin) . PHP_EOL;
					} 
				} // end foreach
			
				file_put_contents(WP_PLUGIN_DIR . '/install-profiles/profiles/' . $importedFileName,$importedProfilePlugins);	
				$i++;
			}  // end while 
		} // end nonce check	
	
	?>
		
		<div class="updated">
			<p>Imported <?php print $profileCount; ?>
				<?php if ( $profileCount == 1 ) {
					echo ' profile ';
				} else {
					echo ' profiles ';
				} ?>
			from <?php print esc_attr($apiUserName);?>.</p>
		</div>
	<?php } 


function wpip_choose_plugins_to_save() { ?>
	<form method="post" action="" id="pluginCheckboxForm" style="display: none">
	
	<a href="#" class="simplemodal-close" style="float:right;text-decoration: none;font-weight: bold;color:#000;font-size:16px">X</a>
	
	<div style="margin-bottom:30px">
		<label class="modalHeadline">Save profile as: </label>
		<input class="largeInput" type="text" name="profileName" value="<?php echo str_replace(' ', '-', get_bloginfo( 'name' ));?>" required="required"/> 
	</div>
	<p><strong>Include the following plugins:</strong>
		<span style="margin-left: 150px"><a class="button" id="wpip_check_all" href="#">Check all</a>&nbsp;&nbsp;<a class="button" href="#" id="wpip_clear_all">Uncheck all</a></span>
	</p>
	
	
	
	<div id="checkboxContainer">
	
		<?php 
		$i = 0;
		$plugins = get_plugins();
		$slugs = array_keys($plugins);
	
		foreach ($plugins as $plugin) { 
	     	$slug = array_keys($plugin); 
			$slugPath = $slugs[$i++]; 
	 		
	 		// use the folder name as the slug
	 		$arr = explode("/", $slugPath, 2);
	  		$slug = $arr[0]; 
			
			// no need to add WPIP to a profile!
			if ($slug == 'install-profiles'){continue;}
			
			// skip over plugins that aren't in folders
			$pos = strpos($slug, '.php');
			if ($pos) {
				continue;
			}
			
			?>
			<div class="pluginCheckbox">
				<input class="pluginCheckbox" name="currentSlugs[]" type="checkbox" 
					<?php if (is_plugin_active($slugPath)) { ?>
						checked="checked"
					<?php } ?>
					value="<?php echo esc_attr($slug); ?>"/>
				<?php echo esc_attr($plugin['Name']);?>
				<br/>
			</div>
		<?php } ?>
	
		</div> <!-- end #checkboxContainer-->
	<?php wp_nonce_field('build_custom_profile','wpip_custom'); ?>
	<input name="customProfileSubmit" type="submit" class="button-primary" value="Save and Download" style="float:right"/>
	</form>
<?php }


function wpip_build_custom_profile() {
	$profileName = sanitize_title($_POST['profileName'], get_bloginfo( 'name' ));	
	$profileName = str_replace(' ', '-', $profileName) . '.profile';
	
	if (!validate_file($profileName) && wp_verify_nonce($_POST['wpip_custom'],'build_custom_profile')) { // false means the file validates
		$fileContents = '';
	
		$currentSlugs = esc_attr($_POST['currentSlugs']);
		
		// assemble the file contents from the $_POST checkbox array
		foreach ($currentSlugs as $slug) {	
			$fileContents .= $slug . PHP_EOL;
		}
		
		$newProfile = fopen(WP_PLUGIN_DIR . '/install-profiles/profiles/' . $profileName,"w"); 
		$written =  fwrite($newProfile, $fileContents);
	
		fclose($newProfile);

	
		$file = WP_PLUGIN_DIR . '/install-profiles/profiles/' . $profileName;
		
		$fileContents = '';
		
		$currentSlugs = $_POST['currentSlugs'];
		
		// assemble the file contents from the $_POST checkbox array
		foreach ($currentSlugs as $slug) {	
			$fileContents .= $slug . PHP_EOL;
		}
		
		$newProfile = fopen(WP_PLUGIN_DIR . '/install-profiles/profiles/' . $profileName,"w"); 
		$written =  fwrite($newProfile, $fileContents);
	
		fclose($newProfile);
		
		// send the file download to the browser
		if (file_exists($file)) {
				header('Content-Description: File Transfer');
				header('Content-Type: application/octet-stream');
				header('Content-Disposition: attachment; filename='.basename($file));
				header('Content-Transfer-Encoding: binary');
				header('Expires: 0');
				header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
				header('Pragma: public');
				header('Content-Length: ' . filesize($file));
				ob_clean();
				flush();
				readfile($file);
				exit;
			}
	} // end check for validate_file()
}

