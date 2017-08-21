<?
use DatabaseManager\Database;
use PortalManager\Template;
use PortalManager\Users;
use PortalManager\User;
use PortalManager\Portal;
use Applications\Captcha;
use PortalManager\Lang;
use ProjectManager\Projects;

class Controller
{
	public $title = '';
	public $smarty = null;
  public $hidePatern = true;
	public $subfolder = 'site/';
  public static $pageTitle;
  public static $user_opt = array();
  public $lang;
  public $vars;
	public $configs = array();

  function __construct($arg = array()){
    Session::init();
    Helper::setMashineID();
    $this->gets 		= Helper::GET();

		$this->loadConfig();

		if ( $arg['root'] ) {
			$this->subfolder = $arg['root'].'/';
		}

    /**
		* CORE
		**/
		// SMARTY
    $this->db      = new Database();
    $template_root = VIEW . $this->subfolder . 'templates/';

    $this->smarty = new Smarty();
		$this->smarty->caching = false;
		$this->smarty->cache_lifetime = 0;
		$this->smarty->setTemplateDir(    $template_root );
		$this->smarty->setCompileDir(     VIEW . $this->subfolder . 'templates_c/' );
		$this->smarty->setConfigDir(      './settings' );
		$this->smarty->setCacheDir(       VIEW . $this->subfolder . 'cache/' );
		setlocale(LC_ALL, 'hu_HU');

     $this->out( 'template_root', $template_root );

    $this->smarty->configLoad( 'vars.conf' );

		define( 'IMG',    '/'.VIEW . $this->subfolder . 'assets/images/' );
		define( 'STYLE',  '/'.VIEW . $this->subfolder . 'assets/css/' );
		define( 'JS',     '/'.VIEW . $this->subfolder . 'assets/js/' );

    //////////////////////////////////////////////////////
     /**
     * LANGUAGES
     * */
    $this->lang = new Lang( $this->gets, $this->subfolder, $this->smarty->getConfigVars() );

    $lng_global = $this->lang->loadLangText( 'global', true );
    $this->outSet( $lng_global );

    $lng_head = $this->lang->loadLangText( 'head', true );
    $this->outSet( $lng_head );

    $lng_footer = $this->lang->loadLangText( 'footer', true );
    $this->outSet( $lng_footer );

    $lng_spec = $this->lang->loadLangText( );
    $this->outSet( $lng_spec );

		// SETTINGS
			$this->settings = $this->getAllValtozo();
      $this->out( 'settings', $this->settings );
		// GETS
      $this->gets 		= Helper::GET();
			$this->out( 'GETS', $this->gets );

		// Objects
        $lang_users = array_merge (
          $this->lang->loadLangText( 'class/users', true, true ),
          $this->lang->loadLangText( 'form/users', true, true ),
          $this->lang->loadLangText( 'mails', true, true ),
          $this->lang->loadLangText( 'activate/reg', true, true ),
          $this->lang->loadLangText( 'services', true, true ),
          $this->lang->loadLangText( 'transaction', true, true )
        );

				$this->User = new Users( array( 'db' => $this->db, 'lang' => $lang_users, 'smarty' => $this->smarty, 'view' => $this->getAllVars() ) );
        $this->Portal = new Portal( array( 'db' => $this->db ) );
				$this->Projects = new Projects( array( 'db' => $this->db, 'lang' => $lang_users, 'smarty' => $this->smarty ) );

        //$this->captcha      = (new Captcha)->init( $this->settings['recaptcha_public_key'], $this->settings['recaptcha_private_key'] );
        $user =  $this->User->get( self::$user_opt );
				$me =  new User($user['data']['ID'], array( 'db' => $this->db, 'lang' => $lang_users, 'smarty' => $this->smarty, 'settings' => $this->settings) );

        if( !$user && $this->gets[0] != 'welcome' && $this->gets[0] != 'forms'){
            header('Location: /welcome');
        }

				$this->out( 'USERS', $this->User);
				$this->out( 'user', $user);
				$this->out( 'me', $me);
				$this->out( 'projects', $this->Projects->getList($me));
				$this->out( 'projects_payments', $this->Projects->getListPayments());

        if( $_GET['logout'] == '1' ) {
            $this->User->logout();
            header('Location: /welcome');
        }

        $this->loadAllVars();

        if( $_GET['start'] == 'off' ) {
            setcookie( 'stredir', '1', time() + 3600*24*365, '/' );
        }

        if(!$arg[hidePatern]){ $this->hidePatern = false; }
    }

    function out( $viewKey, $output ){
        $this->smarty->assign( $viewKey, $output );
    }

    function outSet( $set_array = array() ){
        foreach ($set_array as $key => $value) {
          $this->smarty->assign( $key, $value );
        }
    }

    public function getAllVars()
    {
       $vars = array();

       if( !$this->smarty ) return false;

       $list = $this->smarty->tpl_vars;

       foreach ( $list as $key => $value ) {
          $vars[$key] = $value->value;
       }

       return $vars;
    }

     public function loadAllVars()
    {
       $vars = array();

       if( !$this->smarty ) return false;

       $list = $this->smarty->tpl_vars;

       foreach ( $list as $key => $value ) {
          $this->vars[$key] = $value->value;
       }
    }

     public function getVar( $key )
    {
       $vars = $this->smarty->tpl_vars[$key]->value;

       return $vars;
    }


    function bodyHead($key = ''){
        $subfolder  = '';

        $this->theme_wire   = ($key != '') ? $key : '';

        if($this->getThemeFolder() != ''){
            $subfolder  = $this->getThemeFolder().'/';
        }

        # Oldal címe
        if(self::$pageTitle != null){
            $this->title = self::$pageTitle . ' | ' . $this->settings['page_title'];
        } else {
            $this->title = $this->settings['page_title'] .$this->settings['page_description'];
        }

        # Render HEADER
        if(!$this->hidePatern){
			$this->out( 'title', $this->title );
			$this->displayView( $subfolder.$this->theme_wire.'head' );
        }

        # Aloldal átadása a VIEW-nek
        $this->called = $this->fnTemp;
    }

	// Facebook content
	function addOG($type, $content){
		return '<meta property="og:'.$type.'" content="'.$content.'" />'."\n\r";
	}

	// Meta content
	function addMeta($name, $content){
		return '<meta name="'.$name.'" content="'.$content.'" />'."\n\r";
	}

    function __destruct(){
        $mode       = false;
        $subfolder  = '';

        if($this->getThemeFolder() != ''){
            $mode       = true;
            $subfolder  = $this->getThemeFolder().'/';
        }

        if(!$this->hidePatern){
            # Render FOOTER
			$this->displayView( $subfolder.$this->theme_wire.'footer' );
        }

        $this->db = null;
        $this->smarty = null;
    }

    function setTitle($title){
        $this->title = $title;
    }

    function valtozok($key){
        $d = $this->db->query("SELECT bErtek FROM settings WHERE bKulcs = '$key'");
        $dt = $d->fetch(PDO::FETCH_ASSOC);

        return $dt[bErtek];
    }

		public function loadConfig()
		{
			$configFile = $_SERVER["DOCUMENT_ROOT"] . '/config.ini';

			if (!file_exists( $configFile )) {
				die('Hiányzik a config.ini fájl.');
			}

			$configs = parse_ini_file( $configFile, true );

			$this->configs = (array)$configs;
		}

    function getAllValtozo(){
        $v = array( );
        $d = $this->db->query("SELECT bErtek, bKulcs FROM settings");
        $dt = $d->fetchAll(PDO::FETCH_ASSOC);

        foreach($dt as $d){
            $v[$d[bKulcs]] = $d[bErtek];
        }

        $v['title'] = ' &mdash; ' . $v['page_slogan'];

        // Országkód: 1 = HU
        $v['country_id'] = 1;
         // Valuta
        $v['valuta'] = 'HUF';
         // Nyelv
        $v['language'] = 'hu';

        return $v;
    }

    function setValtozok($key,$val){
        $iq = "UPDATE settings SET bErtek = '$val' WHERE bKulcs = '$key'";
        $this->model->db->query($iq);
    }

    function setThemeFolder($folder = ''){
        $this->theme_folder = $folder;
    }

    protected function getThemeFolder(){
        return $this->theme_folder;
    }

	public function displayView( $tpl, $has_folder = false){
		$folder = '';

		if ( $has_folder ) {
			if( $this->subfolder != 'site/' ) {
				$tpl = str_replace( $this->subfolder, '', $tpl);
			}
			$folder = ($this->gets[1] ?: 'home') . '/';
		}

        $templateDir = $this->smarty->getTemplateDir();

		if( !file_exists( $templateDir[0] . $folder . $tpl.'.tpl') ) {
			if( $this->subfolder == 'site/' ) {
				$folder = '';
			} else {
				$folder = 'PageNotFound/';
			}
		}

		$this->smarty->display( $folder . $tpl.'.tpl' );
	}
}

?>
