<?php
/*
Plugin Name: DFWPReplace
Plugin URI: 
Description: Replace URLs in posts GUID and posts content
Version: 1.0.0
License: GPL2
Author: Clément Biron
Author URI: http://www.clementbiron.com
Text Domain: dfwpreplace
Domain Path: /lang
*/

//Exit si accès direct
if(!defined('ABSPATH')) exit;

if(!class_exists('DFWPReplace')){

	class DFWPReplace{

		//Vars
		private $settings;

		/**
		 * Constructeur
		 */
		public function __construct(){

			//Initialisation des settings
			$this->settings = array(
				'basename'         => plugin_basename(__FILE__),
				'path'             => plugin_dir_path(__FILE__),
				'dir'              => plugin_dir_url(__FILE__),
				'textdomain'       => 'dfwpreplace',
				'hiddenfield_name' => 'dfwpreplace_hidden',
				'newurlfield_name' => 'dfwpreplace_newurl',
				'oldurlfield_name' => 'dfwpreplace_oldurl',
				'newurl_value'     => '',
				'oldurl_value'     => get_option('siteurl'),
			);
		}

		/**
		 * Initialisation des actions
		 */
		public function initialize()
		{
			//Load languages
			add_action('plugins_loaded', array($this,'loadLang'));

			//Notices
			add_action('admin_menu', array($this,'initNotices'));

			//Register management page
			add_action('admin_menu', array($this,'registerManagementPage'));
		}

		/**
		 * Chargement des langs
		 */
		public function loadLang()
		{
			load_plugin_textdomain($this->settings['textdomain'],false, dirname($this->settings['basename']).'/lang/');
		}


		/**
		 * Gestion des notices
		 */
		public function initNotices(){
			function DFWPNoticeAction($msg,$error = false){
				$css = $error == true ? 'error' : 'updated';
				echo '<div class="'.$css.'"><p>'.$msg.'</p></div>'; 
			}
			add_action('DFWPNotice','DFWPNoticeAction',10,2);
		}

		/**
		 * Register management page
		 */
		public function registerManagementPage(){			
			add_management_page(
				'DFWPReplace',
				'DFWPReplace',
				'manage_options', 
				'DFWPReplace',
				array($this, 'buildManagementPage')
			);
		}

		/**
		 * Management page
		 */
		public function buildManagementPage()
		{	
			//Si on a posté le formulaire
			if(isset($_POST[$this->settings['hiddenfield_name']]) && $_POST[$this->settings['hiddenfield_name']] == 'Y') 
			{
				//Récupérer la nouvelle url
				$this->settings['newurl_value'] = esc_url($_POST[$this->settings['newurlfield_name']],array('http','https'));

				//Récupérer l'ancienne url
				$this->settings['oldurl_value'] = esc_url($_POST[$this->settings['oldurlfield_name']],array('http','https'));

				//Si rien de passé par le formulaire on récupere la valeur en bdd
				$this->settings['oldurl_value'] =  (!empty($this->settings['oldurl_value'])) ? esc_url($this->settings['oldurl_value'],array('http','https')) : get_option('siteurl');
				
				//Si on a bien une url de remplacement
				if(isset($this->settings['newurl_value']) && !empty($this->settings['newurl_value'])){

					//Replace GUID
					$this->replaceGUID();

					//Replace post content
					$this->replacePostContent();
				}

				//Sinon erreur
				else{
					do_action('DFWPNotice',__('Please enter the new url',$this->settings['textdomain']),true);
				}

				//On réinitialise
				$this->settings['oldurl_value'] = get_option('siteurl');
			}
		?>
			<div class="wrap">
				<h2 class="dashicons-before dashicons-admin-generic">DFWPReplace</h2> 
				<p><?php _e('Replace URLs in posts GUID and posts content',$this->settings['textdomain']); ?></p>
				<form name="form1" method="post" action="">
					<input type="hidden" name="<?php echo $this->settings['hiddenfield_name']; ?>" value="Y">

					<p>
						<?php _e('Current URL',$this->settings['textdomain']); ?>
						<input type="text" name="<?php echo $this->settings['oldurlfield_name']; ?>" value="<?php echo esc_url($this->settings['oldurl_value'],array('http','https')); ?>" size="30">
					</p>

					<p>
						<?php _e('replace by',$this->settings['textdomain']); ?>
						<input required type="text" name="<?php echo $this->settings['newurlfield_name']; ?>" value="<?php echo esc_url($this->settings['newurl_value'],array('http','https')); ?>" size="30">
					</p>

					<p class="submit">
						<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e(__('Replace',$this->settings['textdomain'])) ?>" />
					</p>
					<hr />
				</form>
			</div>
			<?php
		}

		/**
		 * Replace GUID
		 */
		private function replaceGUID()
		{
			//use wpdb
			global $wpdb;

			//Préparation de la requête
			$guidSql = $wpdb->prepare(
				"
				UPDATE $wpdb->posts 
				SET guid = replace(
					guid, 
					%s, 
					%s
				) 
				",
				$this->settings['oldurl_value'],
				$this->settings['newurl_value']
			);

			//Requête
			$guidRequete = $wpdb->query($guidSql,OBJECT);

			//Erreur
			if($guidRequete === false){
				do_action('DFWPNotice',__('GUID replace error',$this->settings['textdomain']),true);
			}

			//Tout est bon
			else{
				do_action('DFWPNotice',sprintf(__('%d replacements in posts GUID',$this->settings['textdomain']),$guidRequete),false);
			}
		}

		/**
		 * Replace in post content
		 */
		private function replacePostContent()
		{
			//use wpdb
			global $wpdb;
			
			//Préparation de la requête
			$postContentSql = $wpdb->prepare(
				"
				UPDATE $wpdb->posts 
				SET post_content = replace(
					post_content, 
					%s, 
					%s
				) 
				",
				$this->settings['oldurl_value'],
				$this->settings['newurl_value']
			);

			//Requête
			$postContentRequete = $wpdb->query($postContentSql,OBJECT);

			//Erreur
			if($postContentRequete === false){
				do_action('DFWPNotice',__('Posts content replace error',$this->settings['textdomain']),true);
			}

			//Tout est bon
			else{
				do_action('DFWPNotice',sprintf(__('%d replacements in posts content',$this->settings['textdomain']),$postContentRequete),false);
			}
		}
	}

	//Init
	$DFWPReplace = new DFWPReplace();
	$DFWPReplace->initialize();
}
