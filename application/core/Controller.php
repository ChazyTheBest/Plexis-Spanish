<?php
/* 
| --------------------------------------------------------------
| Plexis
| --------------------------------------------------------------
| Author:       Steven Wilson 
| Author:       Tony (Syke)
| Copyright:    Copyright (c) 2011-2012, Plexis
| License:      GNU GPL v3
| ---------------------------------------------------------------
| Class: Controller
| ---------------------------------------------------------------
|
| Main Controller file. This file will act as a base for the
| whole system
|
*/
namespace Application\Core;

class Controller extends \System\Core\Controller
{
    // Out session
    public $Session;
    
    // Our auth class
    public $Auth;
    
    // Template Class
    public $Template;
    
    // Database functions
    public $DB, $RDB;
    
    // Stats Class
    public $Statistics;

/*
| ---------------------------------------------------------------
| Constructer: __construct()
| ---------------------------------------------------------------
|
*/
    public function __construct($process_db = TRUE, $init_template = TRUE) 
    {
        // If site is updating, only allow Ajax requests
        if($GLOBALS['controller'] != 'admin_ajax' && config('site_updating')) 
            die('Site Down for maintenance. Be back soon.');
        
        // Build the Core Controller
        parent::__construct();
        
        // Process stats if we arent in ajax mode
        if($GLOBALS['controller'] != 'ajax' && $GLOBALS['controller'] != 'admin_ajax' && is_object($this->Statistics)) 
            $this->Statistics->add_hit();
        
        // Setup the selected users language
<<<<<<< HEAD
        $GLOBALS['language'] = selected_language();
=======
        $this->_init_language();
>>>>>>> d808b5d161c3619ad12405ba3a9ab359676e794e
        
        // Process DB updates
        if($process_db == TRUE) $this->_process_db();
        
        // Setup the template system
        if($init_template == TRUE) $this->_init_template();
    }
    
/*
| ---------------------------------------------------------------
<<<<<<< HEAD
| Funtion: _init_template()
=======
| Method: _init_template()
>>>>>>> d808b5d161c3619ad12405ba3a9ab359676e794e
| ---------------------------------------------------------------
|
*/
    private function _init_template() 
    {
        // Set our template path based on the users selected template
        if($this->controller == 'admin')
        {
            $this->Template->set_template_path('application/admin');
        }
        else
        {
            // Check if the user has a selected theme.
            $user = $this->Session->data['user'];
            if($user['logged_in'] == FALSE)
            {
                // Set default template path
                $this->Template->set_template_path('application/templates/'. config('default_template'));
            }
            else
            {
<<<<<<< HEAD
                if(!empty($user['selected_theme']))
                {
                    // Make sure the tempalate exists before setting the theme
                    $query = "SELECT * FROM `pcms_templates` WHERE `name`=?";
                    $template = $this->DB->query( $query, array($user['selected_theme']) )->fetch_row();
                    
                    // If the template exists, and is enabled for site use
                    if($template != FALSE && $template['status'] == 1)
                    {
                        $this->Template->set_template_path('application/templates/'. $template['name']);
                        return;
                    }
                }
=======
                // Load users selected theme if there is one selected
                if(!empty($user['selected_theme']))
                {
                    // Make sure the tempalate exists before setting the theme
                    $query  = "SELECT `status` FROM `pcms_templates` WHERE `name`=?";
                    $status = $this->DB->query( $query, array($user['selected_theme']) )->fetch_column();
                    
                    // If the template exists, and is enabled for site use
                    if($status)
                    {
                        $this->Template->set_template_path('application/templates/'. $user['selected_theme']);
                        return;
                    }
                }
                
>>>>>>> d808b5d161c3619ad12405ba3a9ab359676e794e
                // Set default template path if we are here
                $this->Template->set_template_path('application/templates/'.  config('default_template'));
            }
        }
    }
    
/*
| ---------------------------------------------------------------
<<<<<<< HEAD
| Funtion: _process_db() 
=======
| Method: _process_db() 
>>>>>>> d808b5d161c3619ad12405ba3a9ab359676e794e
| ---------------------------------------------------------------
|
*/
    private function _process_db() 
    {
        // For starts, get our current database version
        $query = "SELECT `value` FROM `pcms_versions` WHERE `key`='database'";
        $version = real_ver( $this->DB->query( $query )->fetch_column() );
        if($version < real_ver( REQ_DB_VERSION ))
        {
            $updates = array();
            $path = APP_PATH . DS .'assets'. DS .'sql'. DS .'updates';
            
            // Open the __updates directory and scan all updates
            $list = @opendir( $path );
            if($list)
            {
                while(false !== ($file = readdir($list)))
                {
                    if($file[0] != "." && !is_dir($path . DS . $file))
                    {
                        // Format should be like so "update_#.sql
                        $names = explode('_', $file);
                        $update = str_replace('.sql', '', $names[1]);
                        if(real_ver($update) > $version)
                        {
                            $updates[] = array('file' => $file, 'version' => $update);
                        }
                    }
                }
                @closedir($list);
            }
            
            // If we have updates
            if(!empty($updates))
            {
                // Order them by rev
                sort($updates);

                // Process updates
                foreach($updates as $update)
                {
                    if( !$this->DB->utilities->run_sql_file($path . DS . $update['file']) )
                    {
                        log_message('Failed to run SQL file "'. $path . DS . $update['file'] .'" on database', 'error.log');
                        break;
                    }
                    $version = $update['version'];
                }
            }
        }
        
        // Define our REAL db version now, after updates are run
        define('CMS_DB_VERSION', $version);
    }
<<<<<<< HEAD
=======
    
/*
| ---------------------------------------------------------------
| Method: _init_language()
| ---------------------------------------------------------------
|
*/
    private function _init_language() 
    {
        // Load language
        $this->Input = load_class('Input');
        $language = $this->Input->cookie('language', true);
        
        //Load the default language if the user hasnt selected a language yet
        if($language == false)
        {
            $language = default_language();
            $this->Input->set_cookie('language', $language);
        }
        else
        {
            // Check and make sure the language is installed
            if(!language_exists($language)) $language = default_language();
        }
        
        // Set globals
        $GLOBALS['language'] = $language;
    }
>>>>>>> d808b5d161c3619ad12405ba3a9ab359676e794e
}
// EOF