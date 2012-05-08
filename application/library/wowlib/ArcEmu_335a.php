<?php
/* 
| --------------------------------------------------------------
| 
| Plexis
|
| --------------------------------------------------------------
|
| Author:       Tony Hudgins
| Copyright:    Copyright (c) 2011, Steven Wilson
| License:      GNU GPL v3
|
*/
namespace Application\Library\Wowlib;

class ArcEmu_335a
{
    // Our DB Connections
    protected $DB;
    protected $RDB;
    protected $CDB;
    protected $WDB;
    
    // remote access
    protected $ra_info = NULL;
    
    // Out realm and realm info arrays
    protected $realm;
    protected $realm_info;
    
    //  huge array of zones, classes, races, and genders
    protected $info;
    

/*
| ---------------------------------------------------------------
| Constructer
| ---------------------------------------------------------------
|
*/
    public function __construct($realm_id)
    {
        // Load the Loader class
        $this->load = load_class('Loader');
        
        // Load the Database and Realm database connections
        $this->DB = $this->load->database('DB');
        $this->RDB = $this->load->database('RDB');
        $this->realm = $this->load->realm();
        
        // Get our DB info
        $query = "SELECT * FROM `pcms_realms` WHERE `id`=?";
        $realm = $this->DB->query( $query, array($realm_id))->fetch_row();
        
        // Turn our connection info into an array
        $world = unserialize($realm['world_db']);
        $char = unserialize($realm['char_db']);
        
        // Disable error reporting
        load_class('Debug')->silent_mode(true);
        
        // Set the connections into the connection variables
        $this->CDB = $this->load->database($char);
        $this->WDB = $this->load->database($world);
        
        // Restore error reporting
        load_class('Debug')->silent_mode(false);
        if(!$this->CDB ||!$this->WDB)
        {
            throw new \Exception('Failed to load database connections.');
            return;
        }
        
        // Finally set our class realm variable
        $this->realm_info = $realm;
        
        // Oh, 1 more thing... Build our wow info array
        $this->construct_info();
    }
    
/*
| -------------------------------------------------------------------------------------------------
|                               CHARACTER DATABASE FUNCTIONS
| -------------------------------------------------------------------------------------------------
*/
    
/*
| ---------------------------------------------------------------
| Method: list_characters
| ---------------------------------------------------------------
|
| This method is used to list all the characters from the characters
| database.
|
| @Param: (Int) $limit - The number of results we are recieveing
| @Param: (Int) $start - The result we start from (example: $start = 50
|   would return results 50-100)
| @Retrun: (Array): An array of characters
|
*/
    public function list_characters($limit = 50, $start = 0)
    {
        // Build our query, and query the database
        $query = "SELECT `guid`, `name`, `race`, `gender`, `class`, `level`, `zoneId` FROM `characters` LIMIT ".$start.", ".$limit;
        $list = $this->CDB->query( $query )->fetch_array();
        
        // If we have a false return, then there was nothing to select
        if($list === FALSE)
        {
            return array();
        }
        return $list;
    }
    
/*
| ---------------------------------------------------------------
| Method: character_online
| ---------------------------------------------------------------
|
| This method is used to determine if a character is online or not
|
| @Param: (Int) $id - The character id we are looking up
| @Retrun:(Bool): TRUE if the cahracter is online, FALSE otherwise
|
*/     
    public function character_online($id)
    {
        // Build our query
        $query = "SELECT `online` FROM `characters` WHERE `guid`=?";
        $online = $this->CDB->query( $query, array($id) )->fetch_column();
        if($online == FALSE)
        {
            return FALSE;
        }
        
        // If we are here, we have the characters status
        return $online;
    }
    
/*
| ---------------------------------------------------------------
| Method: character_name_exists
| ---------------------------------------------------------------
|
| This method is used to determine if a character name is available
|
| @Param: (Int) $name - The character name we are looking up
| @Retrun:(Bool): TRUE if the name is available, FALSE otherwise
|
*/     
    public function character_name_exists($name)
    {
        // Build our query
        $query = "SELECT `guid` FROM `characters` WHERE `name`=?";
        $exists = $this->CDB->query( $query, array($name) )->fetch_column();
        if($exists !== FALSE)
        {
            return TRUE;
        }
        
        // If we are here, the name is available
        return FALSE;
    }
    
/*
| ---------------------------------------------------------------
| Method: get_character_info
| ---------------------------------------------------------------
|
| This method is used to return an array of character information
|
| @Param: (Int) $id - The character ID
| @Retrun:(Array): False if the character doesnt exist, Otherwise
|   array(
|       'account' => The account ID the character belongs too
|       'id' => Character Id
|       'name' => The characters name
|       'race' => The characters race id
|       'class' => The characters class id
|       'gender' => Gender
|       'level' => Level
|       'money' => characters money
|       'xp' => Characters current level expierience
|       'online' => 1 if character online, 0 otherwise
|       'zone' => The zone ID the character is in
|   );
|
*/  
    public function get_character_info($id)
    {
        // Build our query
        $query = "SELECT `guid` as `id`, `acct`, `name`, `race`, `class`, `gender`, `level`, `money`, `xp`, `online`, `zone` FROM `characters` WHERE `guid`=?";
        $account = $this->CDB->query( $query, array($id) )->fetch_row();
        if($account == FALSE)
        {
            return FALSE;
        }
        
        // If we are here, then we have the account ID. return it
        return $account;
    }
    
/*
| ---------------------------------------------------------------
| Method: set_character_info
| ---------------------------------------------------------------
|
| This method is used to set an array of character information
|
| @Param: (Int) $id - The character ID
| @Param:(Array): $info - an array of fields to set... This includes
| these fields (NOTE: you need not set all of these, just the ones
|   you are updating)
|   array(
|       'account' => The account ID the character belongs too
|       'name' => The characters name
|       'gender' => Gender
|       'level' => Level
|       'money' => characters money
|       'xp' => Characters current level expierience
|   );
|
*/  
    public function set_character_info($id, $info)
    {
        // First we check to make sure the character exists!
        $query = "SELECT `acct`, `name`, `gender`, `level`, `money`, `xp` FROM `characters` WHERE `guid`=?";
        $char = $this->CDB->query( $query, array($id) )->fetch_row();
        if($char === false)
        {
            // Character doesnt exist or is online
            return false;
        }
        else
        {
            // If the name changed, check to make sure a different char doesnt have that name
            if(isset($info['name']))
            {
                if($char['name'] != $info['name'])
                {
                    if($this->character_name_exists($info['name'])) return false;
                }
            }
            
            // Build our data array ( 'column_name' => $info['infoid'] )
            // We need to check if each field is set, if not, use $char default
            $data = array(
                'account'   => (isset($info['account'])) ? $info['account'] : $char['account'],
                'name'      => (isset($info['name']))    ? $info['name']    : $char['name'],
                'gender'    => (isset($info['gender']))  ? $info['gender']  : $char['gender'],
                'level'     => (isset($info['level']))   ? $info['level']   : $char['level'],
                'money'     => (isset($info['money']))   ? $info['money']   : $char['money'],
                'xp'        => (isset($info['xp']))      ? $info['xp']      : $char['xp']
            );
            
            // Update the 'characters' table, SET 'name' => $new_name WHERE guid(id) => $id
            return $this->CDB->update('characters', $data, "`guid`=".$id);
        }
    }
    
/*
| ---------------------------------------------------------------
| Method: get_character_account_id
| ---------------------------------------------------------------
|
| This method is used to get the account id tied to the character
|
| @Param: (Int) $id - The character id we are looking up
| @Retrun: (Int): the account id on success, FALSE otherwise
|
*/     
    public function get_character_account_id($id)
    {
        // Build our query
        $query = "SELECT `acct` FROM `characters` WHERE `guid`=?";
        $account = $this->CDB->query( $query, array($id) )->fetch_column();
        if($account == FALSE)
        {
            return FALSE;
        }
        
        // If we are here, then we have the account ID. return it
        return $account;
    }
    
/*
| ---------------------------------------------------------------
| Method: get_character_name
| ---------------------------------------------------------------
|
| This method is used to get the characters name
|
| @Param: (Int) $id - The character id we are looking up
| @Retrun: (Int): Returns the characters name on success, FALSE otherwise
|
*/     
    public function get_character_name($id)
    {
        // Build our query
        $query = "SELECT `name` FROM `characters` WHERE `guid`=?";
        $result = $this->CDB->query( $query, array($id) )->fetch_column();
        if($result == FALSE)
        {
            return FALSE;
        }
        
        // If we are here, then we have the name. return it
        return $result;
    }
    
/*
| ---------------------------------------------------------------
| Method: get_character_level
| ---------------------------------------------------------------
|
| This method is used to get the level of a character
|
| @Param: (Int) $id - The character id we are looking up
| @Retrun: (Int): The characters level on success, FALSE otherwise
|
*/     
    public function get_character_level($id)
    {
        // Build our query
        $query = "SELECT `level` FROM `characters` WHERE `guid`=?";
        $result = $this->CDB->query( $query, array($id) )->fetch_column();
        if($result == FALSE)
        {
            return FALSE;
        }
        
        // If we are here, then we have the result. return it
        return $result;
    }
    
/*
| ---------------------------------------------------------------
| Method: get_character_race
| ---------------------------------------------------------------
|
| This method is used to get the race ID of a character
|
| @Param: (Int) $id - The character id we are looking up
| @Retrun: (Int): The characters race ID on success, FALSE otherwise
|
*/     
    public function get_character_race($id)
    {
        // Build our query
        $query = "SELECT `race` FROM `characters` WHERE `guid`=?";
        $result = $this->CDB->query( $query, array($id) )->fetch_column();
        if($result == FALSE)
        {
            return FALSE;
        }
        
        // If we are here, then we have the result. return it
        return $result;
    }
    
/*
| ---------------------------------------------------------------
| Method: get_character_class
| ---------------------------------------------------------------
|
| This method is used to get the class ID of a character
|
| @Param: (Int) $id - The character id we are looking up
| @Retrun: (Int): The characters class ID on success, FALSE otherwise
|
*/     
    public function get_character_class($id)
    {
        // Build our query
        $query = "SELECT `class` FROM `characters` WHERE `guid`=?";
        $result = $this->CDB->query( $query, array($id) )->fetch_column();
        if($result == FALSE)
        {
            return FALSE;
        }
        
        // If we are here, then we have the result. return it
        return $result;
    }
    
/*
| ---------------------------------------------------------------
| Method: get_character_gender
| ---------------------------------------------------------------
|
| This method is used to get the gender of a character
|
| @Param: (Int) $id - The character id we are looking up
| @Retrun: (Int): The characters gender (0=male, 1=female) on success, 
|   FALSE otherwise
|
*/  
    public function get_character_gender($id)
    {
        // Build our query
        $query = "SELECT `gender` FROM `characters` WHERE `guid`=?";
        $result = $this->CDB->query( $query, array($id) )->fetch_column();
        if($result == FALSE)
        {
            return FALSE;
        }
        
        // If we are here, then we have the result. return it
        return $result;
    }

/*
| ---------------------------------------------------------------
| Method: get_character_faction
| ---------------------------------------------------------------
|
| Gets the faction for character id.
|
| @Param: (Int) $id - The character id we are looking up
| @Retrun: (Int): Returns 1 = Ally, 0 = horde on success, 
|   FALSE otherwise (use the "===" to tell 0 from false)
|
*/ 
    public function get_character_faction($id)
    {
        // Frist we make an array of alliance race's
        $ally = array("1", "3", "4", "7", "11");
        
        // Get our characters current race
        $row = $this->get_character_race($id);
        if($row == FALSE)
        {
            return FALSE;
        }
        else
        {
            // Now we check to see if the characters race is in the array we made before
            if(in_array($row, $ally))
            {
                // Return that the race is alliance
                return 1;
            } 
            else 
            {
                // Race is Horde
                return 0;
            }
        }
    }

/*
| ---------------------------------------------------------------
| Method: get_character_gold
| ---------------------------------------------------------------
|
| Returns the amount of gold a character has.
|
| @Param: (Int) $id - The character id we are looking up
| @Retrun: (Int): Returns the amount on success, FALSE otherwise
|
*/     
    public function get_character_gold($id)
    {
        // First we check to make sure the character exists!
        $query = "SELECT `gold` FROM `characters` WHERE `guid`=?";
        $gold = $this->CDB->query( $query, array($id) )->fetch_column();
        if($gold == FALSE)
        {
            return FALSE;
        }
        else
        {
            return $gold;
        }
    }

/*
| ---------------------------------------------------------------
| Method: get_online_count
| ---------------------------------------------------------------
|
| Returns the amount of characters currently online
|
| @Param: (Int) $faction - Faction ID, 1 = Ally, 2 = Horde, 0 = Both
| @Retrun: (Int): Returns the amount on success, FALSE otherwise
|
*/     
    public function get_online_count($faction = 0)
    {
        // Alliance
        if($faction == 1)
        {
            $query = "SELECT COUNT(*) FROM `characters` WHERE `online`='1' AND (`race` = 1 OR `race` = 3 OR `race` = 4 OR `race` = 7 OR `race` = 11)";
        }

        // Horde
        elseif($faction == 2)
        {
            $query = "SELECT COUNT(*) FROM `characters` WHERE `online`='1' AND (`race` = 2 OR `race` = 5 OR `race` = 6 OR `race` = 8 OR `race` = 10)";
        }

        // Both factions
        else
        {
            $query = "SELECT COUNT(*) FROM `characters` WHERE `online`='1'";
        }
        
        // Return the query result
        return $this->CDB->query( $query )->fetch_column();
    }

/*
| ---------------------------------------------------------------
| Method: get_online_list
| ---------------------------------------------------------------
|
| This method returns a list of characters online
|
| @Param: (Int) $limit - The number of results we are recieveing
| @Param: (Int) $start - The result we start from (example: $start = 50
|   would return results 50-100)
| @Param: (Int) $faction - Faction ID, 1 = Ally, 2 = Horde, 0 = Both
| @Retrun: (Array): An array of characters
|
*/     
    public function get_online_list($limit = 100, $start = 0, $faction = 0)
    {
        // Alliance Only
        if($faction == 1)
        {
            $query = "SELECT `guid`, `name`, `race`, `class`, `gender`, `level`, `zoneId`  FROM `characters` WHERE `online`='1' AND 
                (`race` = 1 OR `race` = 3 OR `race` = 4 OR `race` = 7 OR `race` = 11) LIMIT $start, $limit";
        }
        
        // Horde Only
        elseif($faction == 2)
        {
            $query = "SELECT `guid`, `name`, `race`, `class`, `gender`, `level`, `zoneId`  FROM `characters` WHERE `online`='1' AND 
                (`race` = 2 OR `race` = 5 OR `race` = 6 OR `race` = 8 OR `race` = 10) LIMIT $start, $limit";
        }
        
        // Both factions
        else
        {
            $query = "SELECT `guid`, `name`, `race`, `class`, `gender`, `level`, `zoneId`  FROM `characters` WHERE `online`='1' LIMIT $start, $limit";
        }
        
        // Return the query result
        return $this->CDB->query( $query )->fetch_array();
    }
    
/*
| ---------------------------------------------------------------
| Method: get_online_list
| ---------------------------------------------------------------
|
| This method returns a list of characters online
|
| @Retrun: (Array): An array of characters
|
*/     
    public function get_online_list_datatables()
    {
        $ajax = $this->load->model("Ajax_Model", "ajax");
  
        /* 
        * Dwsc: Array of database columns which should be read and sent back to DataTables. 
        * Format: id, name, character level, race ID, class ID, Gender ID, and Zone ID
        */
        $cols = array( 'guid', 'name', 'level', 'race', 'class', 'gender', 'zoneId' );
        
        /* Character ID column name */
        $index = "guid";
        
        /* characters table name to use */
        $table = "characters";
        
        /* add where */
        $where = '`online` = 1';
        
        /* Process the request */
        return $ajax->process_datatables($cols, $index, $table, $where, $this->CDB);
    }
    
/*
| ---------------------------------------------------------------
| Method: get_character_list_datatables
| ---------------------------------------------------------------
|
| This method returns a list of characters

| @Retrun: (Array): An array of characters
|
*/     
    public function get_character_list_datatables()
    {
        $ajax = $this->load->model("Ajax_Model", "ajax");
  
        /* 
        * Dwsc: Array of database columns which should be read and sent back to DataTables. 
        * Format: id, name, character level, race ID, class ID, Gender ID, and Zone ID, Account ID, and status
        */
        $cols = array( 'guid', 'name', 'level', 'race', 'class', 'gender', 'zoneId', 'account', 'online' );
        
        /* Character ID column name */
        $index = "guid";
        
        /* characters table name to use */
        $table = "characters";
        
        /* where statment */
        $where = '';
        
        /* Process the request */
        return $ajax->process_datatables($cols, $index, $table, $where, $this->CDB);
    }
    }

/*
| ---------------------------------------------------------------
| Method: get_faction_top_kills
| ---------------------------------------------------------------
|
| This method returns a list of the top chacters with kills
|
| @Param: (Int) $faction - Faction ID, 1 = Ally, 2 = Horde, 0 = Both
| @Param: (Int) $limit - The number of results we are recieveing
| @Param: (Int) $start - The result we start from (example: $start = 50
|   would return results 50-100)
| @Retrun: (Array): An array of characters ORDERED by kills
|
*/      
    function get_faction_top_kills($faction, $limit, $start)
	{
		// Alliance
		if($faction == 1)
		{			
			$row = "SELECT `guid`, `name`, `race`, `class`, `gender`, `level` FROM `characters` WHERE `killsLifeTime` > 0 AND (
				`race` = 1 OR `race` = 3 OR `race` = 4 OR `race` = 7 OR `race` = 11) ORDER BY `killsLifeTime` DESC LIMIT $start, $limit";
		}
		else # Horde
		{			
			$row = "SELECT `guid`, `name`, `race`, `class`, `gender`, `level` FROM `characters` WHERE `killsLifeTime` > 0 AND (
				`race` = 2 OR `race` = 5 OR `race` = 6 OR `race` = 8 OR `race` = 10) ORDER BY `killsLifeTime` DESC LIMIT $start, $limit";
		}
		
        // Return the query result
        return $this->CDB->query( $query )->fetch_array();
	}

/*
| ---------------------------------------------------------------
| Method: set_character_level
| ---------------------------------------------------------------
|
| This method is used to set a characters level
|
| @Param: (Int) $id - The character id we are updating
| @Param: (Int) $new_level - The characters new level
| @Retrun: (Bool): True on success, FALSE otherwise
|
*/    
    public function set_character_level($id, $new_level)
    {
        // First we check to make sure the character exists!
        $query = "SELECT `online` FROM `characters` WHERE `guid`=?";
        $online = $this->CDB->query( $query, array($id) )->fetch_column();
        if($online === FALSE)
        {
            // Character doesnt exist if we get a staight up FALSE
            return FALSE;
        }
        elseif($online == 1)
        {
            // Cant change an online players leve
            return FALSE;
        }
        else
        {
            // Update the 'characters' table, SET 'level' => $new_level WHERE guid(id) => $id
            return $this->CDB->update('characters', array('level' => $new_level), "`guid`=".$id);
        }
    }
    
/*
| ---------------------------------------------------------------
| Method: set_character_name
| ---------------------------------------------------------------
|
| This method is used to set a characters name
|
| @Param: (Int) $id - The character id we are updating
| @Param: (Int) $new_name - The characters new name
| @Retrun: (Bool): True on success, FALSE otherwise
|
*/    
    public function set_character_name($id, $new_name)
    {
        // First we check to make sure the character exists!
        $query = "SELECT `online` FROM `characters` WHERE `guid`=?";
        $online = $this->CDB->query( $query, array($id) )->fetch_column();
        if($online === FALSE)
        {
            // Character doesnt exist if we get a staight up FALSE
            return FALSE;
        }
        elseif($online == 1)
        {
            // Cant change an online players name
            return FALSE;
        }
        else
        {
            // Update the 'characters' table, SET 'name' => $new_name WHERE guid(id) => $id
            return $this->CDB->update('characters', array('level' => $new_level), "`guid`=".$id);
        }
    }
    
/*
| ---------------------------------------------------------------
| Method: set_character_name
| ---------------------------------------------------------------
|
| This method is used to set a characters account ID
|
| @Param: (Int) $id - The character id we are updating
| @Param: (Int) $account - The new account id
| @Retrun: (Bool): True on success, FALSE otherwise
|
*/    
    public function set_character_account_id($id, $account)
    {
        // First we check to make sure the character exists!
        $query = "SELECT `online` FROM `characters` WHERE `guid`=?";
        $online = $this->CDB->query( $query, array($id) )->fetch_column();
        if($online === FALSE)
        {
            // Character doesnt exist if we get a staight up FALSE
            return FALSE;
        }
        elseif($online == 1)
        {
            // Cant change an online players qccount
            return FALSE;
        }
        else
        {
            // Update the 'characters' table, SET 'name' => $new_name WHERE guid(id) => $id
            return $this->CDB->update('characters', array('acct' => $account), "`guid`=".$id);
        }
    }
    
/*
| ---------------------------------------------------------------
| Method: adjust_character_level
| ---------------------------------------------------------------
|
| This method is used to adjust a characters level by the $mod
|
| @Param: (Int) $id - The character id we are updating
| @Param: (Int) $mod - The characters modification amount to level
| @Retrun: (Bool): True on success, FALSE otherwise
|
*/
    public function adjust_character_level($id, $mod)
    {
        // First we check to make sure the character exists!
        $query = "SELECT `level` FROM `characters` WHERE `guid`=?";
        $lvl = $this->CDB->query( $query, array($id) )->fetch_column();
        if($lvl == FALSE)
        {
            return FALSE;
        }
        else
        {
            // Adjust the level
            $newlvl = $lvl + $mod;

            // Update the 'characters' table, SET 'level' => $new_level WHERE guid(id) => $id
            return $this->CDB->update('characters', array('level' => $new_level), "`guid`=".$id);
        }
    }

/*
| ---------------------------------------------------------------
| Method: adjust_character_gold
| ---------------------------------------------------------------
|
| This method is used to adjust a characters gold by the $mod
|
| @Param: (Int) $id - The character id we are updating
| @Param: (Int) $mod - The characters modification amount to gold
| @Retrun: (Bool): True on success, FALSE otherwise
|
*/ 
    public function adjust_character_gold($id, $mod)
    {
        // First we check to make sure the character exists!
        $query = "SELECT `gold` FROM `characters` WHERE `guid`=?";
        $gold = $this->CDB->query( $query, array($id) )->fetch_column();
        if($gold == FALSE)
        {
            return FALSE;
        }
        else
        {
            // Adjust the gold
            $new = $gold + $mod;

            // Update the 'characters' table, SET 'level' => $new_level WHERE guid(id) => $id
            return $this->CDB->update('characters', array('gold' => $new), "`guid`=".$id);
        }
    }
    
    
/*
| -------------------------------------------------------------------------------------------------
|                               WORLD DATABASE FUNCTIONS
| -------------------------------------------------------------------------------------------------
*/




/*
| -------------------------------------------------------------------------------------------------
|                               AT LOGIN FLAGS
| -------------------------------------------------------------------------------------------------
*/


/*
| ---------------------------------------------------------------
| Method: get_available_login_flags()
| ---------------------------------------------------------------
|
| This method is used to return a list of "at login" flags this
| core / revision is able to do. Please note, the functions must
| exist!
|
| @Retrun: (Array): An array of true / false flags
|
*/ 
    public function get_available_login_flags()
    {
        return array(
            'rename' => false,
            'customize' => false,
            'change_race' => false,
            'change_faction' => false,
            'reset_spells' => false,
            'reset_talents' => false,
            'reset_pet_talents' => false
        );
    }
    
/*
| ---------------------------------------------------------------
| Method: flag_to_bit()
| ---------------------------------------------------------------
|
| This method is used to return the bitmask flag for the givin flag 
| name
|
| @Param: (String) $flag - The flag name we are getting the bit for
| @Retrun: (Int | Bool): The bitmask on success, False otherwise
|
*/
    public function flag_to_bit($flag)
    {
        // only list available flags
        $flags = array();
        
        return (isset($flags[ $flag ])) ? $flags[ $flag ] : false;
    }
    
/*
| ---------------------------------------------------------------
| Method: set_login_flag()
| ---------------------------------------------------------------
|
| This method is used to return a list of "at login" flags this
| core / revision is able to do. Please note, the functions must
| exist!
|
| @Param: (Int) $id - The character id
| @Param: (String) $name - The flag name we are settings
| @Param: (Bool) $status - True to enable flag, false to remove it
| @Retrun: (Bool): True on success, False otherwise
|
*/ 
    public function set_login_flag($id, $name, $status)
    {
        // Not sure if arcemu supports this!
        return false;
        
        // First, get current login flags
        $query = "SELECT `at_login` FROM `characters` WHERE `guid`=?";
        $flags = $this->CDB->query( $query, array($id) )->fetch_column();
        
        // Make sure we didnt get a false return!
        if( $flags === false ) return false;
        
        // Convert flags to an int, and get our bit id
        $flags  = (int) $flags;
        $flagid = (int) $this->flag_to_bit($name);
        
        // Make sure this feature is supported
        if($flagid == 0) return false;
        
        // Determine if the flag is already enabled before enabling it again
        if ($status == true)
        {
            // Check, if the flag is set, return true
            if($flags != 0 && ($flags & $flagid)) return true;
            
            // Set new flag
            $newflags = $flagid + $flags;
        }
        else
        {
            // If disabling a flag, return true if its already disabled
            if($flags == 0 || ( !($flags & $flagid) )) return true;
            
            // Set new flag
            $newflags = $flags - $flagid;
        }
        
        // Update the database setting the new flag
        return $this->CDB->update('characters', array('at_login' => $newflags), "`guid`=$id");
    }
    
/*
| ---------------------------------------------------------------
| Method: has_login_flag()
| ---------------------------------------------------------------
|
| This method is used to return a if a character has the specified
| login flag enabled
|
| @Param: (Int) $id - The character id
| @Param: (String) $name - The flag name we are getting
| @Retrun: (Bool): True if the character has the flag, False otherwise
|
*/ 
    public function has_login_flag($id, $name)
    {
        // Not sure if arcemu supports this!
        return false;
        
        // First, get current login flags
        $query = "SELECT `at_login` FROM `characters` WHERE `guid`=?";
        $flags = $this->CDB->query( $query, array($id) )->fetch_column();
        
        // Is there any flags set?
        if( $flags == false ) return false;
        
        // Convert flags to an int, and get our bit id
        $flags  = (int) $flags;
        $flagid = (int) $this->flag_to_bit($name);
        
        // Make sure this feature is supported
        if($flagid == 0) return false;
        
        // Check, if the flag is set, return true
        return ($flags & $flagid) ? true : false;
    }
    
/*
| ---------------------------------------------------------------
| Method: get_login_flags()
| ---------------------------------------------------------------
|
| This method is used to return all login flags the character has
|
| @Param: (Int) $id - The character id
| @Retrun: (Array): An array of true / false flags
|
*/ 
    public function get_login_flags($id)
    {
        // Not sure if arcemu supports this!
        return array();
        
        // Build the dummy array
        $flags = array();
        $supported = $this->get_available_login_flags();
        foreach($supported as $key => $flag)
        {
            $flags[$key] = false;
        }
        
        // First, get current login flags
        $query = "SELECT `at_login` FROM `characters` WHERE `guid`=?";
        $cflags = $this->CDB->query( $query, array($id) )->fetch_column();
        
        // Is there any flags set?
        if( $cflags == false ) return $flags;
        
        // Determine if each flag is true or false
        foreach($flags as $key => $flag)
        {
            $bit = $this->flag_to_bit($key);
            $flags[$key] = ($cflags & $bit) ? true : false;
        }
        
        return $flags;
    }



/*
| -------------------------------------------------------------------------------------------------
|                               HELPER FUNCTIONS
| -------------------------------------------------------------------------------------------------
*/


    function race_to_text($id)
    {
        // Check if the race is set, if not then Unknown
        if(isset($this->info['race'][$id]))
        {
            return $this->info['race'][$id];
        }
        return "Unknown";
    }

    function class_to_text($id)
    {
        // Check if the class is set, if not then Unknown
        if(isset($this->info['class'][$id]))
        {
            return $this->info['class'][$id];
        }
        return "Unknown";
    }

    function gender_to_text($id)
    {
        // Check if the gender is set, if not then Unknown
        if(isset($this->info['gender'][$id]))
        {
            return $this->info['gender'][$id];
        }
        return "Unknown";
    }
    
    
    public function zone_to_text($id)
    {
        // Check if the zone is set, if not then Unknown
        if(isset($this->info['zones'][$id]))
        {
            return $this->info['zones'][$id];
        }
        return "Unknown Zone";
    }
    
    protected function construct_info()
    {
		$this->info = array(
			'race' => array(
                1 => 'Human',
                2 => 'Orc',
                3 => 'Dwarf',
                4 => 'Night Elf',
                5 => 'Undead',
                6 => 'Tauren',
                7 => 'Gnome',
                8 => 'Troll',
                9 => 'Goblin',
                10 => 'Bloodelf',
                11 => 'Dranei'
            ),
            'class' => array(
                1 => 'Warrior',
                2 => 'Paladin',
                3 => 'Hunter',
                4 => 'Rogue',
                5 => 'Priest',
				6 => 'Death_Knight',
                7 => 'Shaman',
                8 => 'Mage',
                9 => 'Warlock',
                11 => 'Druid'
            ),
            'gender' => array(
                0 => 'Male',
                1 => 'Female',
                2 => 'None'
            ),
            'zones' => array(
                1 => 'Dun Morogh',
                2 => 'Longshore',
                3 => 'Badlands',
                4 => 'Blasted Lands',
                7 => 'Blackwater Cove',
                8 => 'Swamp of Sorrows',
                9 => 'Northshire Valley',
                10 => 'Duskwood',
                11 => 'Wetlands',
                12 => 'Elwynn Forest',
                13 => 'The World Tree',
                14 => 'Durotar',
                15 => 'Dustwallow Marsh',
                16 => 'Azshara',
                17 => 'The Barrens',
                18 => 'Crystal Lake',
                19 => 'Zul`Gurub',
                20 => 'Moonbrook',
                21 => 'Kul Tiras',
                22 => 'Programmer Isle',
                23 => 'Northshire River',
                24 => 'Northshire Abbey',
                25 => 'Blackrock Mountain',
                26 => 'Lighthouse',
                28 => 'Western Plaguelands',
                30 => 'Nine',
                32 => 'The Cemetary',
                33 => 'Stranglethorn Vale',
                34 => 'Echo Ridge Mine',
                35 => 'Booty Bay',
                36 => 'Alterac Mountains',
                37 => 'Lake Nazferiti',
                38 => 'Loch Modan',
                40 => 'Westfall',
                41 => 'Deadwind Pass',
                42 => 'Darkshire',
                43 => 'Wild Shore',
                44 => 'Redridge Mountains',
                45 => 'Arathi Highlands',
                46 => 'Burning Steppes',
                47 => 'The Hinterlands',
                49 => 'Dead Man`s Hole',
                51 => 'Searing Gorge',
                53 => 'Thieves Camp',
                54 => 'Jasperlode Mine',
                55 => 'Valley of Heroes UNUSED',
                56 => 'Heroes` Vigil',
                57 => 'Fargodeep Mine',
                59 => 'Northshire Vineyards',
                60 => 'Forest`s Edge',
                61 => 'Thunder Falls',
                62 => 'Brackwell Pumpkin Patch',
                63 => 'The Stonefield Farm',
                64 => 'The Maclure Vineyards',
                65 => 'Dragonblight',
                66 => 'Zul`Drak',
                67 => 'The Storm Peaks',
                68 => 'Lake Everstill',
                69 => 'Lakeshire',
                70 => 'Stonewatch',
                71 => 'Stonewatch Falls',
                72 => 'The Dark Portal',
                73 => 'The Tainted Scar',
                74 => 'Pool of Tears',
                75 => 'Stonard',
                76 => 'Fallow Sanctuary',
                77 => 'Anvilmar',
                80 => 'Stormwind Mountains',
                81 => 'Jeff NE Quadrant Changed',
                82 => 'Jeff NW Quadrant',
                83 => 'Jeff SE Quadrant',
                84 => 'Jeff SW Quadrant',
                85 => 'Tirisfal Glades',
                86 => 'Stone Cairn Lake',
                87 => 'Goldshire',
                88 => 'Eastvale Logging Camp',
                89 => 'Mirror Lake Orchard',
                91 => 'Tower of Azora',
                92 => 'Mirror Lake',
                93 => 'Vul`Gol Ogre Mound',
                94 => 'Raven Hill',
                95 => 'Redridge Canyons',
                96 => 'Tower of Ilgalar',
                97 => 'Alther`s Mill',
                98 => 'Rethban Caverns',
                99 => 'Rebel Camp',
                100 => 'Nesingwary`s Expedition',
                101 => 'Kurzen`s Compound',
                102 => 'Ruins of Zul`Kunda',
                103 => 'Ruins of Zul`Mamwe',
                104 => 'The Vile Reef',
                105 => 'Mosh`Ogg Ogre Mound',
                106 => 'The Stockpile',
                107 => 'Saldean`s Farm',
                108 => 'Sentinel Hill',
                109 => 'Furlbrow`s Pumpkin Farm',
                111 => 'Jangolode Mine',
                113 => 'Gold Coast Quarry',
                115 => 'Westfall Lighthouse',
                116 => 'Misty Valley',
                117 => 'Grom`gol Base Camp',
                118 => 'Whelgar`s Excavation Site',
                120 => 'Westbrook Garrison',
                121 => 'Tranquil Gardens Cemetery',
                122 => 'Zuuldaia Ruins',
                123 => 'Bal`lal Ruins',
                125 => 'Kal`ai Ruins',
                126 => 'Tkashi Ruins',
                127 => 'Balia`mah Ruins',
                128 => 'Ziata`jai Ruins',
                129 => 'Mizjah Ruins',
                130 => 'Silverpine Forest',
                131 => 'Kharanos',
                132 => 'Coldridge Valley',
                133 => 'Gnomeregan',
                134 => 'Gol`Bolar Quarry',
                135 => 'Frostmane Hold',
                136 => 'The Grizzled Den',
                137 => 'Brewnall Village',
                138 => 'Misty Pine Refuge',
                139 => 'Eastern Plaguelands',
                141 => 'Teldrassil',
                142 => 'Ironband`s Excavation Site',
                143 => 'Mo`grosh Stronghold',
                144 => 'Thelsamar',
                145 => 'Algaz Gate',
                146 => 'Stonewrought Dam',
                147 => 'The Farstrider Lodge',
                148 => 'Darkshore',
                149 => 'Silver Stream Mine',
                150 => 'Menethil Harbor',
                151 => 'Designer Island',
                152 => 'The Bulwark',
                153 => 'Ruins of Lordaeron',
                154 => 'Deathknell',
                155 => 'Night Web`s Hollow',
                156 => 'Solliden Farmstead',
                157 => 'Agamand Mills',
                158 => 'Agamand Family Crypt',
                159 => 'Brill',
                160 => 'Whispering Gardens',
                161 => 'Terrace of Repose',
                162 => 'Brightwater Lake',
                163 => 'Gunther`s Retreat',
                164 => 'Garren`s Haunt',
                165 => 'Balnir Farmstead',
                166 => 'Cold Hearth Manor',
                167 => 'Crusader Outpost',
                168 => 'The North Coast',
                169 => 'Whispering Shore',
                170 => 'Lordamere Lake',
                172 => 'Fenris Isle',
                173 => 'Faol`s Rest',
                186 => 'Dolanaar',
                187 => 'Darnassus UNUSED',
                188 => 'Shadowglen',
                189 => 'Steelgrill`s Depot',
                190 => 'Hearthglen',
                192 => 'Northridge Lumber Camp',
                193 => 'Ruins of Andorhal',
                195 => 'School of Necromancy',
                196 => 'Uther`s Tomb',
                197 => 'Sorrow Hill',
                198 => 'The Weeping Cave',
                199 => 'Felstone Field',
                200 => 'Dalson`s Tears',
                201 => 'Gahrron`s Withering',
                202 => 'The Writhing Haunt',
                203 => 'Mardenholde Keep',
                204 => 'Pyrewood Village',
                205 => 'Dun Modr',
                206 => 'Utgarde Keep',
                207 => 'The Great Sea',
                208 => 'Unused Ironcladcove',
                209 => 'Shadowfang Keep',
                210 => 'Icecrown',
                211 => 'Iceflow Lake',
                212 => 'Helm`s Bed Lake',
                213 => 'Deep Elem Mine',
                214 => 'The Great Sea',
                215 => 'Mulgore',
                219 => 'Alexston Farmstead',
                220 => 'Red Cloud Mesa',
                221 => 'Camp Narache',
                222 => 'Bloodhoof Village',
                223 => 'Stonebull Lake',
                224 => 'Ravaged Caravan',
                225 => 'Red Rocks',
                226 => 'The Skittering Dark',
                227 => 'Valgan`s Field',
                228 => 'The Sepulcher',
                229 => 'Olsen`s Farthing',
                230 => 'The Greymane Wall',
                231 => 'Beren`s Peril',
                232 => 'The Dawning Isles',
                233 => 'Ambermill',
                235 => 'Fenris Keep',
                236 => 'Shadowfang Keep',
                237 => 'The Decrepit Ferry',
                238 => 'Malden`s Orchard',
                239 => 'The Ivar Patch',
                240 => 'The Dead Field',
                241 => 'The Rotting Orchard',
                242 => 'Brightwood Grove',
                243 => 'Forlorn Rowe',
                244 => 'The Whipple Estate',
                245 => 'The Yorgen Farmstead',
                246 => 'The Cauldron',
                247 => 'Grimesilt Dig Site',
                249 => 'Dreadmaul Rock',
                250 => 'Ruins of Thaurissan',
                251 => 'Flame Crest',
                252 => 'Blackrock Stronghold',
                253 => 'The Pillar of Ash',
                254 => 'Blackrock Mountain',
                255 => 'Altar of Storms',
                256 => 'Aldrassil',
                257 => 'Shadowthread Cave',
                258 => 'Fel Rock',
                259 => 'Lake Al`Ameth',
                260 => 'Starbreeze Village',
                261 => 'Gnarlpine Hold',
                262 => 'Ban`ethil Barrow Den',
                263 => 'The Cleft',
                264 => 'The Oracle Glade',
                265 => 'Wellspring River',
                266 => 'Wellspring Lake',
                267 => 'Hillsbrad Foothills',
                268 => 'Azshara Crater',
                269 => 'Dun Algaz',
                271 => 'Southshore',
                272 => 'Tarren Mill',
                275 => 'Durnholde Keep',
                276 => 'UNUSED Stonewrought Pass',
                277 => 'The Foothill Caverns',
                278 => 'Lordamere Internment Camp',
                279 => 'Dalaran Crater',
                280 => 'Strahnbrad',
                281 => 'Ruins of Alterac',
                282 => 'Crushridge Hold',
                283 => 'Slaughter Hollow',
                284 => 'The Uplands',
                285 => 'Southpoint Tower',
                286 => 'Hillsbrad Fields',
                287 => 'Hillsbrad',
                288 => 'Azurelode Mine',
                289 => 'Nethander Stead',
                290 => 'Dun Garok',
                293 => 'Thoradin`s Wall',
                294 => 'Eastern Strand',
                295 => 'Western Strand',
                296 => 'South Seas UNUSED',
                297 => 'Jaguero Isle',
                298 => 'Baradin Bay',
                299 => 'Menethil Bay',
                300 => 'Misty Reed Strand',
                301 => 'The Savage Coast',
                302 => 'The Crystal Shore',
                303 => 'Shell Beach',
                305 => 'North Tide`s Run',
                306 => 'South Tide`s Run',
                307 => 'The Overlook Cliffs',
                308 => 'The Forbidding Sea',
                309 => 'Ironbeard`s Tomb',
                310 => 'Crystalvein Mine',
                311 => 'Ruins of Aboraz',
                312 => 'Janeiro`s Point',
                313 => 'Northfold Manor',
                314 => 'Go`Shek Farm',
                315 => 'Dabyrie`s Farmstead',
                316 => 'Boulderfist Hall',
                317 => 'Witherbark Village',
                318 => 'Drywhisker Gorge',
                320 => 'Refuge Pointe',
                321 => 'Hammerfall',
                322 => 'Blackwater Shipwrecks',
                323 => 'O`Breen`s Camp',
                324 => 'Stromgarde Keep',
                325 => 'The Tower of Arathor',
                326 => 'The Sanctum',
                327 => 'Faldir`s Cove',
                328 => 'The Drowned Reef',
                330 => 'Thandol Span',
                331 => 'Ashenvale',
                332 => 'The Great Sea',
                333 => 'Circle of East Binding',
                334 => 'Circle of West Binding',
                335 => 'Circle of Inner Binding',
                336 => 'Circle of Outer Binding',
                337 => 'Apocryphan`s Rest',
                338 => 'Angor Fortress',
                339 => 'Lethlor Ravine',
                340 => 'Kargath',
                341 => 'Camp Kosh',
                342 => 'Camp Boff',
                343 => 'Camp Wurg',
                344 => 'Camp Cagg',
                345 => 'Agmond`s End',
                346 => 'Hammertoe`s Digsite',
                347 => 'Dustbelch Grotto',
                348 => 'Aerie Peak',
                349 => 'Wildhammer Keep',
                350 => 'Quel`Danil Lodge',
                351 => 'Skulk Rock',
                352 => 'Zun`watha',
                353 => 'Shadra`Alor',
                354 => 'Jintha`Alor',
                355 => 'The Altar of Zul',
                356 => 'Seradane',
                357 => 'Feralas',
                358 => 'Brambleblade Ravine',
                359 => 'Bael Modan',
                360 => 'The Venture Co. Mine',
                361 => 'Felwood',
                362 => 'Razor Hill',
                363 => 'Valley of Trials',
                364 => 'The Den',
                365 => 'Burning Blade Coven',
                366 => 'Kolkar Crag',
                367 => 'Sen`jin Village',
                368 => 'Echo Isles',
                369 => 'Thunder Ridge',
                370 => 'Drygulch Ravine',
                371 => 'Dustwind Cave',
                372 => 'Tiragarde Keep',
                373 => 'Scuttle Coast',
                374 => 'Bladefist Bay',
                375 => 'Deadeye Shore',
                377 => 'Southfury River',
                378 => 'Camp Taurajo',
                379 => 'Far Watch Post',
                380 => 'The Crossroads',
                381 => 'Boulder Lode Mine',
                382 => 'The Sludge Fen',
                383 => 'The Dry Hills',
                384 => 'Dreadmist Peak',
                385 => 'Northwatch Hold',
                386 => 'The Forgotten Pools',
                387 => 'Lushwater Oasis',
                388 => 'The Stagnant Oasis',
                390 => 'Field of Giants',
                391 => 'The Merchant Coast',
                392 => 'Ratchet',
                393 => 'Darkspear Strand',
                394 => 'Grizzly Hills',
                395 => 'Grizzlemaw',
                396 => 'Winterhoof Water Well',
                397 => 'Thunderhorn Water Well',
                398 => 'Wildmane Water Well',
                399 => 'Skyline Ridge',
                400 => 'Thousand Needles',
                401 => 'The Tidus Stair',
                403 => 'Shady Rest Inn',
                404 => 'Bael`dun Digsite',
                405 => 'Desolace',
                406 => 'Stonetalon Mountains',
                407 => 'Orgrimmar UNUSED',
                408 => 'Gillijim`s Isle',
                409 => 'Island of Doctor Lapidis',
                410 => 'Razorwind Canyon',
                411 => 'Bathran`s Haunt',
                412 => 'The Ruins of Ordil`Aran',
                413 => 'Maestra`s Post',
                414 => 'The Zoram Strand',
                415 => 'Astranaar',
                416 => 'The Shrine of Aessina',
                417 => 'Fire Scar Shrine',
                418 => 'The Ruins of Stardust',
                419 => 'The Howling Vale',
                420 => 'Silverwind Refuge',
                421 => 'Mystral Lake',
                422 => 'Fallen Sky Lake',
                424 => 'Iris Lake',
                425 => 'Moonwell',
                426 => 'Raynewood Retreat',
                427 => 'The Shady Nook',
                428 => 'Night Run',
                429 => 'Xavian',
                430 => 'Satyrnaar',
                431 => 'Splintertree Post',
                432 => 'The Dor`Danil Barrow Den',
                433 => 'Falfarren River',
                434 => 'Felfire Hill',
                435 => 'Demon Fall Canyon',
                436 => 'Demon Fall Ridge',
                437 => 'Warsong Lumber Camp',
                438 => 'Bough Shadow',
                439 => 'The Shimmering Flats',
                440 => 'Tanaris',
                441 => 'Lake Falathim',
                442 => 'Auberdine',
                443 => 'Ruins of Mathystra',
                444 => 'Tower of Althalaxx',
                445 => 'Cliffspring Falls',
                446 => 'Bashal`Aran',
                447 => 'Ameth`Aran',
                448 => 'Grove of the Ancients',
                449 => 'The Master`s Glaive',
                450 => 'Remtravel`s Excavation',
                452 => 'Mist`s Edge',
                453 => 'The Long Wash',
                454 => 'Wildbend River',
                455 => 'Blackwood Den',
                456 => 'Cliffspring River',
                457 => 'The Veiled Sea',
                458 => 'Gold Road',
                459 => 'Scarlet Watch Post',
                460 => 'Sun Rock Retreat',
                461 => 'Windshear Crag',
                463 => 'Cragpool Lake',
                464 => 'Mirkfallon Lake',
                465 => 'The Charred Vale',
                466 => 'Valley of the Bloodfuries',
                467 => 'Stonetalon Peak',
                468 => 'The Talon Den',
                469 => 'Greatwood Vale',
                470 => 'Thunder Bluff UNUSED',
                471 => 'Brave Wind Mesa',
                472 => 'Fire Stone Mesa',
                473 => 'Mantle Rock',
                474 => 'Hunter Rise UNUSED',
                475 => 'Spirit RiseUNUSED',
                476 => 'Elder RiseUNUSED',
                477 => 'Ruins of Jubuwal',
                478 => 'Pools of Arlithrien',
                479 => 'The Rustmaul Dig Site',
                480 => 'Camp E`thok',
                481 => 'Splithoof Crag',
                482 => 'Highperch',
                483 => 'The Screeching Canyon',
                484 => 'Freewind Post',
                485 => 'The Great Lift',
                486 => 'Galak Hold',
                487 => 'Roguefeather Den',
                488 => 'The Weathered Nook',
                489 => 'Thalanaar',
                490 => 'Un`Goro Crater',
                491 => 'Razorfen Kraul',
                492 => 'Raven Hill Cemetery',
                493 => 'Moonglade',
                495 => 'Howling Fjord',
                496 => 'Brackenwall Village',
                497 => 'Swamplight Manor',
                498 => 'Bloodfen Burrow',
                499 => 'Darkmist Cavern',
                500 => 'Moggle Point',
                501 => 'Beezil`s Wreck',
                502 => 'Witch Hill',
                503 => 'Sentry Point',
                504 => 'North Point Tower',
                505 => 'West Point Tower',
                506 => 'Lost Point',
                507 => 'Bluefen',
                508 => 'Stonemaul Ruins',
                509 => 'The Den of Flame',
                510 => 'The Dragonmurk',
                511 => 'Wyrmbog',
                512 => 'Blackhoof Village',
                513 => 'Theramore Isle',
                514 => 'Foothold Citadel',
                515 => 'Ironclad Prison',
                516 => 'Dustwallow Bay',
                517 => 'Tidefury Cove',
                518 => 'Dreadmurk Shore',
                536 => 'Addle`s Stead',
                537 => 'Fire Plume Ridge',
                538 => 'Lakkari Tar Pits',
                539 => 'Terror Run',
                540 => 'The Slithering Scar',
                541 => 'Marshal`s Refuge',
                542 => 'Fungal Rock',
                543 => 'Golakka Hot Springs',
                556 => 'The Loch',
                576 => 'Beggar`s Haunt',
                596 => 'Kodo Graveyard',
                597 => 'Ghost Walker Post',
                598 => 'Sar`theris Strand',
                599 => 'Thunder Axe Fortress',
                600 => 'Bolgan`s Hole',
                602 => 'Mannoroc Coven',
                603 => 'Sargeron',
                604 => 'Magram Village',
                606 => 'Gelkis Village',
                607 => 'Valley of Spears',
                608 => 'Nijel`s Point',
                609 => 'Kolkar Village',
                616 => 'Hyjal',
                618 => 'Winterspring',
                636 => 'Blackwolf River',
                637 => 'Kodo Rock',
                638 => 'Hidden Path',
                639 => 'Spirit Rock',
                640 => 'Shrine of the Dormant Flame',
                656 => 'Lake Elune`ara',
                657 => 'The Harborage',
                676 => 'Outland',
                696 => 'Craftsmen`s Terrace UNUSED',
                697 => 'Tradesmen`s Terrace UNUSED',
                698 => 'The Temple Gardens UNUSED',
                699 => 'Temple of Elune UNUSED',
                700 => 'Cenarion Enclave UNUSED',
                701 => 'Warrior`s Terrace UNUSED',
                702 => 'Rut`theran Village',
                716 => 'Ironband`s Compound',
                717 => 'The Stockade',
                718 => 'Wailing Caverns',
                719 => 'Blackfathom Deeps',
                720 => 'Fray Island',
                721 => 'Gnomeregan',
                722 => 'Razorfen Downs',
                736 => 'Ban`ethil Hollow',
                796 => 'Scarlet Monastery',
                797 => 'Jerod`s Landing',
                798 => 'Ridgepoint Tower',
                799 => 'The Darkened Bank',
                800 => 'Coldridge Pass',
                801 => 'Chill Breeze Valley',
                802 => 'Shimmer Ridge',
                803 => 'Amberstill Ranch',
                804 => 'The Tundrid Hills',
                805 => 'South Gate Pass',
                806 => 'South Gate Outpost',
                807 => 'North Gate Pass',
                808 => 'North Gate Outpost',
                809 => 'Gates of Ironforge',
                810 => 'Stillwater Pond',
                811 => 'Nightmare Vale',
                812 => 'Venomweb Vale',
                813 => 'The Bulwark',
                814 => 'Southfury River',
                815 => 'Southfury River',
                816 => 'Razormane Grounds',
                817 => 'Skull Rock',
                818 => 'Palemane Rock',
                819 => 'Windfury Ridge',
                820 => 'The Golden Plains',
                821 => 'The Rolling Plains',
                836 => 'Dun Algaz',
                837 => 'Dun Algaz',
                838 => 'North Gate Pass',
                839 => 'South Gate Pass',
                856 => 'Twilight Grove',
                876 => 'GM Island',
                877 => 'Delete ME',
                878 => 'Southfury River',
                879 => 'Southfury River',
                880 => 'Thandol Span',
                881 => 'Thandol Span',
                896 => 'Purgation Isle',
                916 => 'The Jansen Stead',
                917 => 'The Dead Acre',
                918 => 'The Molsen Farm',
                919 => 'Stendel`s Pond',
                920 => 'The Dagger Hills',
                921 => 'Demont`s Place',
                922 => 'The Dust Plains',
                923 => 'Stonesplinter Valley',
                924 => 'Valley of Kings',
                925 => 'Algaz Station',
                926 => 'Bucklebree Farm',
                927 => 'The Shining Strand',
                928 => 'North Tide`s Hollow',
                936 => 'Grizzlepaw Ridge',
                956 => 'The Verdant Fields',
                976 => 'Gadgetzan',
                977 => 'Steamwheedle Port',
                978 => 'Zul`Farrak',
                979 => 'Sandsorrow Watch',
                980 => 'Thistleshrub Valley',
                981 => 'The Gaping Chasm',
                982 => 'The Noxious Lair',
                983 => 'Dunemaul Compound',
                984 => 'Eastmoon Ruins',
                985 => 'Waterspring Field',
                986 => 'Zalashji`s Den',
                987 => 'Land`s End Beach',
                988 => 'Wavestrider Beach',
                989 => 'Uldum',
                990 => 'Valley of the Watchers',
                991 => 'Gunstan`s Post',
                992 => 'Southmoon Ruins',
                996 => 'Render`s Camp',
                997 => 'Render`s Valley',
                998 => 'Render`s Rock',
                999 => 'Stonewatch Tower',
                1000 => 'Galardell Valley',
                1001 => 'Lakeridge Highway',
                1002 => 'Three Corners',
                1016 => 'Direforge Hill',
                1017 => 'Raptor Ridge',
                1018 => 'Black Channel Marsh',
                1019 => 'The Green Belt',
                1020 => 'Mosshide Fen',
                1021 => 'Thelgen Rock',
                1022 => 'Bluegill Marsh',
                1023 => 'Saltspray Glen',
                1024 => 'Sundown Marsh',
                1025 => 'The Green Belt',
                1036 => 'Angerfang Encampment',
                1037 => 'Grim Batol',
                1038 => 'Dragonmaw Gates',
                1039 => 'The Lost Fleet',
                1056 => 'Darrow Hill',
                1057 => 'Thoradin`s Wall',
                1076 => 'Webwinder Path',
                1097 => 'The Hushed Bank',
                1098 => 'Manor Mistmantle',
                1099 => 'Camp Mojache',
                1100 => 'Grimtotem Compound',
                1101 => 'The Writhing Deep',
                1102 => 'Wildwind Lake',
                1103 => 'Gordunni Outpost',
                1104 => 'Mok`Gordun',
                1105 => 'Feral Scar Vale',
                1106 => 'Frayfeather Highlands',
                1107 => 'Idlewind Lake',
                1108 => 'The Forgotten Coast',
                1109 => 'East Pillar',
                1110 => 'West Pillar',
                1111 => 'Dream Bough',
                1112 => 'Jademir Lake',
                1113 => 'Oneiros',
                1114 => 'Ruins of Ravenwind',
                1115 => 'Rage Scar Hold',
                1116 => 'Feathermoon Stronghold',
                1117 => 'Ruins of Solarsal',
                1118 => 'Lower Wilds UNUSED',
                1119 => 'The Twin Colossals',
                1120 => 'Sardor Isle',
                1121 => 'Isle of Dread',
                1136 => 'High Wilderness',
                1137 => 'Lower Wilds',
                1156 => 'Southern Barrens',
                1157 => 'Southern Gold Road',
                1176 => 'Zul`Farrak',
                1196 => 'Utgarde Pinnacle',
                1216 => 'Timbermaw Hold',
                1217 => 'Vanndir Encampment',
                1218 => 'TESTAzshara',
                1219 => 'Legash Encampment',
                1220 => 'Thalassian Base Camp',
                1221 => 'Ruins of Eldarath ',
                1222 => 'Hetaera`s Clutch',
                1223 => 'Temple of Zin-Malor',
                1224 => 'Bear`s Head',
                1225 => 'Ursolan',
                1226 => 'Temple of Arkkoran',
                1227 => 'Bay of Storms',
                1228 => 'The Shattered Strand',
                1229 => 'Tower of Eldara',
                1230 => 'Jagged Reef',
                1231 => 'Southridge Beach',
                1232 => 'Ravencrest Monument',
                1233 => 'Forlorn Ridge',
                1234 => 'Lake Mennar',
                1235 => 'Shadowsong Shrine',
                1236 => 'Haldarr Encampment',
                1237 => 'Valormok',
                1256 => 'The Ruined Reaches',
                1276 => 'The Talondeep Path',
                1277 => 'The Talondeep Path',
                1296 => 'Rocktusk Farm',
                1297 => 'Jaggedswine Farm',
                1316 => 'Razorfen Downs',
                1336 => 'Lost Rigger Cove',
                1337 => 'Uldaman',
                1338 => 'Lordamere Lake',
                1339 => 'Lordamere Lake',
                1357 => 'Gallows` Corner',
                1377 => 'Silithus',
                1397 => 'Emerald Forest',
                1417 => 'Sunken Temple',
                1437 => 'Dreadmaul Hold',
                1438 => 'Nethergarde Keep',
                1439 => 'Dreadmaul Post',
                1440 => 'Serpent`s Coil',
                1441 => 'Altar of Storms',
                1442 => 'Firewatch Ridge',
                1443 => 'The Slag Pit',
                1444 => 'The Sea of Cinders',
                1445 => 'Blackrock Mountain',
                1446 => 'Thorium Point',
                1457 => 'Garrison Armory',
                1477 => 'The Temple of Atal`Hakkar',
                1497 => 'Undercity',
                1517 => 'Uldaman',
                1518 => 'Not Used Deadmines',
                1519 => 'Stormwind City',
                1537 => 'Ironforge',
                1557 => 'Splithoof Hold',
                1577 => 'The Cape of Stranglethorn',
                1578 => 'Southern Savage Coast',
                1579 => 'Unused The Deadmines 002',
                1580 => 'Unused Ironclad Cove 003',
                1581 => 'The Deadmines',
                1582 => 'Ironclad Cove',
                1583 => 'Blackrock Spire',
                1584 => 'Blackrock Depths',
                1597 => 'Raptor Grounds UNUSED',
                1598 => 'Grol`dom Farm UNUSED',
                1599 => 'Mor`shan Base Camp',
                1600 => 'Honor`s Stand UNUSED',
                1601 => 'Blackthorn Ridge UNUSED',
                1602 => 'Bramblescar UNUSED',
                1603 => 'Agama`gor UNUSED',
                1617 => 'Valley of Heroes',
                1637 => 'Orgrimmar',
                1638 => 'Thunder Bluff',
                1639 => 'Elder Rise',
                1640 => 'Spirit Rise',
                1641 => 'Hunter Rise',
                1657 => 'Darnassus',
                1658 => 'Cenarion Enclave',
                1659 => 'Craftsmen`s Terrace',
                1660 => 'Warrior`s Terrace',
                1661 => 'The Temple Gardens',
                1662 => 'Tradesmen`s Terrace',
                1677 => 'Gavin`s Naze',
                1678 => 'Sofera`s Naze',
                1679 => 'Corrahn`s Dagger',
                1680 => 'The Headland',
                1681 => 'Misty Shore',
                1682 => 'Dandred`s Fold',
                1683 => 'Growless Cave',
                1684 => 'Chillwind Point',
                1697 => 'Raptor Grounds',
                1698 => 'Bramblescar',
                1699 => 'Thorn Hill',
                1700 => 'Agama`gor',
                1701 => 'Blackthorn Ridge',
                1702 => 'Honor`s Stand',
                1703 => 'The Mor`shan Rampart',
                1704 => 'Grol`dom Farm',
                1717 => 'Razorfen Kraul',
                1718 => 'The Great Lift',
                1737 => 'Mistvale Valley',
                1738 => 'Nek`mani Wellspring',
                1739 => 'Bloodsail Compound',
                1740 => 'Venture Co. Base Camp',
                1741 => 'Gurubashi Arena',
                1742 => 'Spirit Den',
                1757 => 'The Crimson Veil',
                1758 => 'The Riptide',
                1759 => 'The Damsel`s Luck',
                1760 => 'Venture Co. Operations Center',
                1761 => 'Deadwood Village',
                1762 => 'Felpaw Village',
                1763 => 'Jaedenar',
                1764 => 'Bloodvenom River',
                1765 => 'Bloodvenom Falls',
                1766 => 'Shatter Scar Vale',
                1767 => 'Irontree Woods',
                1768 => 'Irontree Cavern',
                1769 => 'Timbermaw Hold',
                1770 => 'Shadow Hold',
                1771 => 'Shrine of the Deceiver',
                1777 => 'Itharius`s Cave',
                1778 => 'Sorrowmurk',
                1779 => 'Draenil`dur Village',
                1780 => 'Splinterspear Junction',
                1797 => 'Stagalbog',
                1798 => 'The Shifting Mire',
                1817 => 'Stagalbog Cave',
                1837 => 'Witherbark Caverns',
                1857 => 'Thoradin`s Wall',
                1858 => 'Boulder`gor',
                1877 => 'Valley of Fangs',
                1878 => 'The Dustbowl',
                1879 => 'Mirage Flats',
                1880 => 'Featherbeard`s Hovel',
                1881 => 'Shindigger`s Camp',
                1882 => 'Plaguemist Ravine',
                1883 => 'Valorwind Lake',
                1884 => 'Agol`watha',
                1885 => 'Hiri`watha',
                1886 => 'The Creeping Ruin',
                1887 => 'Bogen`s Ledge',
                1897 => 'The Maker`s Terrace',
                1898 => 'Dustwind Gulch',
                1917 => 'Shaol`watha',
                1937 => 'Noonshade Ruins',
                1938 => 'Broken Pillar',
                1939 => 'Abyssal Sands',
                1940 => 'Southbreak Shore',
                1941 => 'Caverns of Time',
                1942 => 'The Marshlands',
                1943 => 'Ironstone Plateau',
                1957 => 'Blackchar Cave',
                1958 => 'Tanner Camp',
                1959 => 'Dustfire Valley',
                1977 => 'Zul`Gurub',
                1978 => 'Misty Reed Post',
                1997 => 'Bloodvenom Post ',
                1998 => 'Talonbranch Glade ',
                2017 => 'Stratholme',
                2037 => 'Quel`thalas',
                2057 => 'Scholomance',
                2077 => 'Twilight Vale',
                2078 => 'Twilight Shore',
                2079 => 'Alcaz Island',
                2097 => 'Darkcloud Pinnacle',
                2098 => 'Dawning Wood Catacombs',
                2099 => 'Stonewatch Keep',
                2100 => 'Maraudon',
                2101 => 'Stoutlager Inn',
                2102 => 'Thunderbrew Distillery',
                2103 => 'Menethil Keep',
                2104 => 'Deepwater Tavern',
                2117 => 'Shadow Grave',
                2118 => 'Brill Town Hall',
                2119 => 'Gallows` End Tavern',
                2137 => 'The Pools of VisionUNUSED',
                2138 => 'Dreadmist Den',
                2157 => 'Bael`dun Keep',
                2158 => 'Emberstrife`s Den',
                2159 => 'Onyxia`s Lair',
                2160 => 'Windshear Mine',
                2161 => 'Roland`s Doom',
                2177 => 'Battle Ring',
                2197 => 'The Pools of Vision',
                2198 => 'Shadowbreak Ravine',
                2217 => 'Broken Spear Village',
                2237 => 'Whitereach Post',
                2238 => 'Gornia',
                2239 => 'Zane`s Eye Crater',
                2240 => 'Mirage Raceway',
                2241 => 'Frostsaber Rock',
                2242 => 'The Hidden Grove',
                2243 => 'Timbermaw Post',
                2244 => 'Winterfall Village',
                2245 => 'Mazthoril',
                2246 => 'Frostfire Hot Springs',
                2247 => 'Ice Thistle Hills',
                2248 => 'Dun Mandarr',
                2249 => 'Frostwhisper Gorge',
                2250 => 'Owl Wing Thicket',
                2251 => 'Lake Kel`Theril',
                2252 => 'The Ruins of Kel`Theril',
                2253 => 'Starfall Village',
                2254 => 'Ban`Thallow Barrow Den',
                2255 => 'Everlook',
                2256 => 'Darkwhisper Gorge',
                2257 => 'Deeprun Tram',
                2258 => 'The Fungal Vale',
                2259 => 'UNUSEDThe Marris Stead',
                2260 => 'The Marris Stead',
                2261 => 'The Undercroft',
                2262 => 'Darrowshire',
                2263 => 'Crown Guard Tower',
                2264 => 'Corin`s Crossing',
                2265 => 'Scarlet Base Camp',
                2266 => 'Tyr`s Hand',
                2267 => 'The Scarlet Basilica',
                2268 => 'Light`s Hope Chapel',
                2269 => 'Browman Mill',
                2270 => 'The Noxious Glade',
                2271 => 'Eastwall Tower',
                2272 => 'Northdale',
                2273 => 'Zul`Mashar',
                2274 => 'Mazra`Alor',
                2275 => 'Northpass Tower',
                2276 => 'Quel`Lithien Lodge',
                2277 => 'Plaguewood',
                2278 => 'Scourgehold',
                2279 => 'Stratholme',
                2280 => 'DO NOT USE',
                2297 => 'Darrowmere Lake',
                2298 => 'Caer Darrow',
                2299 => 'Darrowmere Lake',
                2300 => 'Caverns of Time',
                2301 => 'Thistlefur Village',
                2302 => 'The Quagmire',
                2303 => 'Windbreak Canyon',
                2317 => 'South Seas',
                2318 => 'The Great Sea',
                2319 => 'The Great Sea',
                2320 => 'The Great Sea',
                2321 => 'The Great Sea',
                2322 => 'The Veiled Sea',
                2323 => 'The Veiled Sea',
                2324 => 'The Veiled Sea',
                2325 => 'The Veiled Sea',
                2326 => 'The Veiled Sea',
                2337 => 'Razor Hill Barracks',
                2338 => 'South Seas',
                2339 => 'The Great Sea',
                2357 => 'Bloodtooth Camp',
                2358 => 'Forest Song',
                2359 => 'Greenpaw Village',
                2360 => 'Silverwing Outpost',
                2361 => 'Nighthaven',
                2362 => 'Shrine of Remulos',
                2363 => 'Stormrage Barrow Dens',
                2364 => 'The Great Sea',
                2365 => 'The Great Sea',
                2366 => 'The Black Morass',
                2367 => 'Old Hillsbrad Foothills',
                2368 => 'Tarren Mill',
                2369 => 'Southshore',
                2370 => 'Durnholde Keep',
                2371 => 'Dun Garok',
                2372 => 'Hillsbrad Fields',
                2373 => 'Eastern Strand',
                2374 => 'Nethander Stead',
                2375 => 'Darrow Hill',
                2376 => 'Southpoint Tower',
                2377 => 'Thoradin`s Wall',
                2378 => 'Western Strand',
                2379 => 'Azurelode Mine',
                2397 => 'The Great Sea',
                2398 => 'The Great Sea',
                2399 => 'The Great Sea',
                2400 => 'The Forbidding Sea',
                2401 => 'The Forbidding Sea',
                2402 => 'The Forbidding Sea',
                2403 => 'The Forbidding Sea',
                2404 => 'Tethris Aran',
                2405 => 'Ethel Rethor',
                2406 => 'Ranazjar Isle',
                2407 => 'Kormek`s Hut',
                2408 => 'Shadowprey Village',
                2417 => 'Blackrock Pass',
                2418 => 'Morgan`s Vigil',
                2419 => 'Slither Rock',
                2420 => 'Terror Wing Path',
                2421 => 'Draco`dar',
                2437 => 'Ragefire Chasm',
                2457 => 'Nightsong Woods',
                2477 => 'The Veiled Sea',
                2478 => 'Morlos`Aran',
                2479 => 'Emerald Sanctuary',
                2480 => 'Jadefire Glen',
                2481 => 'Ruins of Constellas',
                2497 => 'Bitter Reaches',
                2517 => 'Rise of the Defiler',
                2518 => 'Lariss Pavilion',
                2519 => 'Woodpaw Hills',
                2520 => 'Woodpaw Den',
                2521 => 'Verdantis River',
                2522 => 'Ruins of Isildien',
                2537 => 'Grimtotem Post',
                2538 => 'Camp Aparaje',
                2539 => 'Malaka`jin',
                2540 => 'Boulderslide Ravine',
                2541 => 'Sishir Canyon',
                2557 => 'Dire Maul',
                2558 => 'Deadwind Ravine',
                2559 => 'Diamondhead River',
                2560 => 'Ariden`s Camp',
                2561 => 'The Vice',
                2562 => 'Karazhan',
                2563 => 'Morgan`s Plot',
                2577 => 'Dire Maul',
                2597 => 'Alterac Valley',
                2617 => 'Scrabblescrew`s Camp',
                2618 => 'Jadefire Run',
                2619 => 'Thondroril River',
                2620 => 'Thondroril River',
                2621 => 'Lake Mereldar',
                2622 => 'Pestilent Scar',
                2623 => 'The Infectis Scar',
                2624 => 'Blackwood Lake',
                2625 => 'Eastwall Gate',
                2626 => 'Terrorweb Tunnel',
                2627 => 'Terrordale',
                2637 => 'Kargathia Keep',
                2657 => 'Valley of Bones',
                2677 => 'Blackwing Lair',
                2697 => 'Deadman`s Crossing',
                2717 => 'Molten Core',
                2737 => 'The Scarab Wall',
                2738 => 'Southwind Village',
                2739 => 'Twilight Base Camp',
                2740 => 'The Crystal Vale',
                2741 => 'The Scarab Dais',
                2742 => 'Hive`Ashi',
                2743 => 'Hive`Zora',
                2744 => 'Hive`Regal',
                2757 => 'Shrine of the Fallen Warrior',
                2777 => 'UNUSED Alterac Valley',
                2797 => 'Blackfathom Deeps',
                2817 => 'Crystalsong Forest',
                2837 => 'The Master`s Cellar',
                2838 => 'Stonewrought Pass',
                2839 => 'Alterac Valley',
                2857 => 'The Rumble Cage',
                2877 => 'Chunk Test',
                2897 => 'Zoram`gar Outpost',
                2917 => 'Hall of Legends',
                2918 => 'Champions` Hall',
                2937 => 'Grosh`gok Compound',
                2938 => 'Sleeping Gorge',
                2957 => 'Irondeep Mine',
                2958 => 'Stonehearth Outpost',
                2959 => 'Dun Baldar',
                2960 => 'Icewing Pass',
                2961 => 'Frostwolf Village',
                2962 => 'Tower Point',
                2963 => 'Coldtooth Mine',
                2964 => 'Winterax Hold',
                2977 => 'Iceblood Garrison',
                2978 => 'Frostwolf Keep',
                2979 => 'Tor`kren Farm',
                3017 => 'Frost Dagger Pass',
                3037 => 'Ironstone Camp',
                3038 => 'Weazel`s Crater',
                3039 => 'Tahonda Ruins',
                3057 => 'Field of Strife',
                3058 => 'Icewing Cavern',
                3077 => 'Valor`s Rest',
                3097 => 'The Swarming Pillar',
                3098 => 'Twilight Post',
                3099 => 'Twilight Outpost',
                3100 => 'Ravaged Twilight Camp',
                3117 => 'Shalzaru`s Lair',
                3137 => 'Talrendis Point',
                3138 => 'Rethress Sanctum',
                3139 => 'Moon Horror Den',
                3140 => 'Scalebeard`s Cave',
                3157 => 'Boulderslide Cavern',
                3177 => 'Warsong Labor Camp',
                3197 => 'Chillwind Camp',
                3217 => 'The Maul',
                3237 => 'The Maul UNUSED',
                3257 => 'Bones of Grakkarond',
                3277 => 'Warsong Gulch',
                3297 => 'Frostwolf Graveyard',
                3298 => 'Frostwolf Pass',
                3299 => 'Dun Baldar Pass',
                3300 => 'Iceblood Graveyard',
                3301 => 'Snowfall Graveyard',
                3302 => 'Stonehearth Graveyard',
                3303 => 'Stormpike Graveyard',
                3304 => 'Icewing Bunker',
                3305 => 'Stonehearth Bunker',
                3306 => 'Wildpaw Ridge',
                3317 => 'Revantusk Village',
                3318 => 'Rock of Durotan',
                3319 => 'Silverwing Grove',
                3320 => 'Warsong Lumber Mill',
                3321 => 'Silverwing Hold',
                3337 => 'Wildpaw Cavern',
                3338 => 'The Veiled Cleft',
                3357 => 'Yojamba Isle',
                3358 => 'Arathi Basin',
                3377 => 'The Coil',
                3378 => 'Altar of Hir`eek',
                3379 => 'Shadra`zaar',
                3380 => 'Hakkari Grounds',
                3381 => 'Naze of Shirvallah',
                3382 => 'Temple of Bethekk',
                3383 => 'The Bloodfire Pit',
                3384 => 'Altar of the Blood God',
                3397 => 'Zanza`s Rise',
                3398 => 'Edge of Madness',
                3417 => 'Trollbane Hall',
                3418 => 'Defiler`s Den',
                3419 => 'Pagle`s Pointe',
                3420 => 'Farm',
                3421 => 'Blacksmith',
                3422 => 'Lumber Mill',
                3423 => 'Gold Mine',
                3424 => 'Stables',
                3425 => 'Cenarion Hold',
                3426 => 'Staghelm Point',
                3427 => 'Bronzebeard Encampment',
                3428 => 'Ahn`Qiraj',
                3429 => 'Ruins of Ahn`Qiraj',
                3430 => 'Eversong Woods',
                3431 => 'Sunstrider Isle',
                3432 => 'Shrine of Dath`Remar',
                3433 => 'Ghostlands',
                3434 => 'Scarab Terrace',
                3435 => 'General`s Terrace',
                3436 => 'The Reservoir',
                3437 => 'The Hatchery',
                3438 => 'The Comb',
                3439 => 'Watchers` Terrace',
                3440 => 'Scarab Terrace',
                3441 => 'General`s Terrace',
                3442 => 'The Reservoir',
                3443 => 'The Hatchery',
                3444 => 'The Comb',
                3445 => 'Watchers` Terrace',
                3446 => 'Twilight`s Run',
                3447 => 'Ortell`s Hideout',
                3448 => 'Scarab Terrace',
                3449 => 'General`s Terrace',
                3450 => 'The Reservoir',
                3451 => 'The Hatchery',
                3452 => 'The Comb',
                3453 => 'Watchers` Terrace',
                3454 => 'Ruins of Ahn`Qiraj',
                3455 => 'The North Sea',
                3456 => 'Naxxramas',
                3457 => 'Karazhan',
                3459 => 'City',
                3460 => 'Golden Strand',
                3461 => 'Sunsail Anchorage',
                3462 => 'Fairbreeze Village',
                3463 => 'Magisters Gate',
                3464 => 'Farstrider Retreat',
                3465 => 'North Sanctum',
                3466 => 'West Sanctum',
                3467 => 'East Sanctum',
                3468 => 'Saltheril`s Haven',
                3469 => 'Thuron`s Livery',
                3470 => 'Stillwhisper Pond',
                3471 => 'The Living Wood',
                3472 => 'Azurebreeze Coast',
                3473 => 'Lake Elrendar',
                3474 => 'The Scorched Grove',
                3475 => 'Zeb`Watha',
                3476 => 'Tor`Watha',
                3477 => 'Azjol-Nerub',
                3478 => 'Gates of Ahn`Qiraj',
                3479 => 'The Veiled Sea',
                3480 => 'Duskwither Grounds',
                3481 => 'Duskwither Spire',
                3482 => 'The Dead Scar',
                3483 => 'Hellfire Peninsula',
                3484 => 'The Sunspire',
                3485 => 'Falthrien Academy',
                3486 => 'Ravenholdt Manor',
                3487 => 'Silvermoon City',
                3488 => 'Tranquillien',
                3489 => 'Suncrown Village',
                3490 => 'Goldenmist Village',
                3491 => 'Windrunner Village',
                3492 => 'Windrunner Spire',
                3493 => 'Sanctum of the Sun',
                3494 => 'Sanctum of the Moon',
                3495 => 'Dawnstar Spire',
                3496 => 'Farstrider Enclave',
                3497 => 'An`daroth',
                3498 => 'An`telas',
                3499 => 'An`owyn',
                3500 => 'Deatholme',
                3501 => 'Bleeding Ziggurat',
                3502 => 'Howling Ziggurat',
                3503 => 'Shalandis Isle',
                3504 => 'Toryl Estate',
                3505 => 'Underlight Mines',
                3506 => 'Andilien Estate',
                3507 => 'Hatchet Hills',
                3508 => 'Amani Pass',
                3509 => 'Sungraze Peak',
                3510 => 'Amani Catacombs',
                3511 => 'Tower of the Damned',
                3512 => 'Zeb`Sora',
                3513 => 'Lake Elrendar',
                3514 => 'The Dead Scar',
                3515 => 'Elrendar River',
                3516 => 'Zeb`Tela',
                3517 => 'Zeb`Nowa',
                3518 => 'Nagrand',
                3519 => 'Terokkar Forest',
                3520 => 'Shadowmoon Valley',
                3521 => 'Zangarmarsh',
                3522 => 'Blade`s Edge Mountains',
                3523 => 'Netherstorm',
                3524 => 'Azuremyst Isle',
                3525 => 'Bloodmyst Isle',
                3526 => 'Ammen Vale',
                3527 => 'Crash Site',
                3528 => 'Silverline Lake',
                3529 => 'Nestlewood Thicket',
                3530 => 'Shadow Ridge',
                3531 => 'Skulking Row',
                3532 => 'Dawning Lane',
                3533 => 'Ruins of Silvermoon',
                3534 => 'Feth`s Way',
                3535 => 'Hellfire Citadel',
                3536 => 'Thrallmar',
                3537 => 'Borean Tundra',
                3538 => 'Honor Hold',
                3539 => 'The Stair of Destiny',
                3540 => 'Twisting Nether',
                3541 => 'Forge Camp: Mageddon',
                3542 => 'The Path of Glory',
                3543 => 'The Great Fissure',
                3544 => 'Plain of Shards',
                3545 => 'Hellfire Citadel',
                3546 => 'Expedition Armory',
                3547 => 'Throne of Kil`jaeden',
                3548 => 'Forge Camp: Rage',
                3549 => 'Invasion Point: Annihilator',
                3550 => 'Borune Ruins',
                3551 => 'Ruins of Sha`naar',
                3552 => 'Temple of Telhamat',
                3553 => 'Pools of Aggonar',
                3554 => 'Falcon Watch',
                3555 => 'Mag`har Post',
                3556 => 'Den of Haal`esh',
                3557 => 'The Exodar',
                3558 => 'Elrendar Falls',
                3559 => 'Nestlewood Hills',
                3560 => 'Ammen Fields',
                3561 => 'The Sacred Grove',
                3562 => 'Hellfire Ramparts',
                3563 => 'Hellfire Citadel',
                3564 => 'Emberglade',
                3565 => 'Cenarion Refuge',
                3566 => 'Moonwing Den',
                3567 => 'Pod Cluster',
                3568 => 'Pod Wreckage',
                3569 => 'Tides` Hollow',
                3570 => 'Wrathscale Point',
                3571 => 'Bristlelimb Village',
                3572 => 'Stillpine Hold',
                3573 => 'Odesyus` Landing',
                3574 => 'Valaar`s Berth',
                3575 => 'Silting Shore',
                3576 => 'Azure Watch',
                3577 => 'Geezle`s Camp',
                3578 => 'Menagerie Wreckage',
                3579 => 'Traitor`s Cove',
                3580 => 'Wildwind Peak',
                3581 => 'Wildwind Path',
                3582 => 'Zeth`Gor',
                3583 => 'Beryl Coast',
                3584 => 'Blood Watch',
                3585 => 'Bladewood',
                3586 => 'The Vector Coil',
                3587 => 'The Warp Piston',
                3588 => 'The Cryo-Core',
                3589 => 'The Crimson Reach',
                3590 => 'Wrathscale Lair',
                3591 => 'Ruins of Loreth`Aran',
                3592 => 'Nazzivian',
                3593 => 'Axxarien',
                3594 => 'Blacksilt Shore',
                3595 => 'The Foul Pool',
                3596 => 'The Hidden Reef',
                3597 => 'Amberweb Pass',
                3598 => 'Wyrmscar Island',
                3599 => 'Talon Stand',
                3600 => 'Bristlelimb Enclave',
                3601 => 'Ragefeather Ridge',
                3602 => 'Kessel`s Crossing',
                3603 => 'Tel`athion`s Camp',
                3604 => 'The Bloodcursed Reef',
                3605 => 'Hyjal Past',
                3606 => 'Hyjal Summit',
                3607 => 'Serpentshrine Cavern',
                3608 => 'Vindicator`s Rest',
                3609 => 'Unused3',
                3610 => 'Burning Blade Ruins',
                3611 => 'Clan Watch',
                3612 => 'Bloodcurse Isle',
                3613 => 'Garadar',
                3614 => 'Skysong Lake',
                3615 => 'Throne of the Elements',
                3616 => 'Laughing Skull Ruins',
                3617 => 'Warmaul Hill',
                3618 => 'Gruul`s Lair',
                3619 => 'Auren Ridge',
                3620 => 'Auren Falls',
                3621 => 'Lake Sunspring',
                3622 => 'Sunspring Post',
                3623 => 'Aeris Landing',
                3624 => 'Forge Camp: Fear',
                3625 => 'Forge Camp: Hate',
                3626 => 'Telaar',
                3627 => 'Northwind Cleft',
                3628 => 'Halaa',
                3629 => 'Southwind Cleft',
                3630 => 'Oshu`gun',
                3631 => 'Spirit Fields',
                3632 => 'Shamanar',
                3633 => 'Ancestral Grounds',
                3634 => 'Windyreed Village',
                3635 => 'Unused2',
                3636 => 'Elemental Plateau',
                3637 => 'Kil`sorrow Fortress',
                3638 => 'The Ring of Trials',
                3639 => 'Silvermyst Isle',
                3640 => 'Daggerfen Village',
                3641 => 'Umbrafen Village',
                3642 => 'Feralfen Village',
                3643 => 'Bloodscale Enclave',
                3644 => 'Telredor',
                3645 => 'Zabra`jin',
                3646 => 'Quagg Ridge',
                3647 => 'The Spawning Glen',
                3648 => 'The Dead Mire',
                3649 => 'Sporeggar',
                3650 => 'Ango`rosh Grounds',
                3651 => 'Ango`rosh Stronghold',
                3652 => 'Funggor Cavern',
                3653 => 'Serpent Lake',
                3654 => 'The Drain',
                3655 => 'Umbrafen Lake',
                3656 => 'Marshlight Lake',
                3657 => 'Portal Clearing',
                3658 => 'Sporewind Lake',
                3659 => 'The Lagoon',
                3660 => 'Blades` Run',
                3661 => 'Blade Tooth Canyon',
                3662 => 'Commons Hall',
                3663 => 'Derelict Manor',
                3664 => 'Huntress of the Sun',
                3665 => 'Falconwing Square',
                3666 => 'Halaani Basin',
                3667 => 'Hewn Bog',
                3668 => 'Boha`mu Ruins',
                3669 => 'The Stadium',
                3670 => 'The Overlook',
                3671 => 'Broken Hill',
                3672 => 'Mag`hari Procession',
                3673 => 'Nesingwary Safari',
                3674 => 'Cenarion Thicket',
                3675 => 'Tuurem',
                3676 => 'Veil Shienor',
                3677 => 'Veil Skith',
                3678 => 'Veil Shalas',
                3679 => 'Skettis',
                3680 => 'Blackwind Valley',
                3681 => 'Firewing Point',
                3682 => 'Grangol`var Village',
                3683 => 'Stonebreaker Hold',
                3684 => 'Allerian Stronghold',
                3685 => 'Bonechewer Ruins',
                3686 => 'Veil Lithic',
                3687 => 'Olembas',
                3688 => 'Auchindoun',
                3689 => 'Veil Reskk',
                3690 => 'Blackwind Lake',
                3691 => 'Lake Ere`Noru',
                3692 => 'Lake Jorune',
                3693 => 'Skethyl Mountains',
                3694 => 'Misty Ridge',
                3695 => 'The Broken Hills',
                3696 => 'The Barrier Hills',
                3697 => 'The Bone Wastes',
                3698 => 'Nagrand Arena',
                3699 => 'Laughing Skull Courtyard',
                3700 => 'The Ring of Blood',
                3701 => 'Arena Floor',
                3702 => 'Blade`s Edge Arena',
                3703 => 'Shattrath City',
                3704 => 'The Shepherd`s Gate',
                3705 => 'Telaari Basin',
                3706 => 'The Dark Portal',
                3707 => 'Alliance Base',
                3708 => 'Horde Encampment',
                3709 => 'Night Elf Village',
                3710 => 'Nordrassil',
                3711 => 'Sholazar Basin',
                3712 => 'Area 52',
                3713 => 'The Blood Furnace',
                3714 => 'The Shattered Halls',
                3715 => 'The Steamvault',
                3716 => 'The Underbog',
                3717 => 'The Slave Pens',
                3718 => 'Swamprat Post',
                3719 => 'Bleeding Hollow Ruins',
                3720 => 'Twin Spire Ruins',
                3721 => 'The Crumbling Waste',
                3722 => 'Manaforge Ara',
                3723 => 'Arklon Ruins',
                3724 => 'Cosmowrench',
                3725 => 'Ruins of Enkaat',
                3726 => 'Manaforge B`naar',
                3727 => 'The Scrap Field',
                3728 => 'The Vortex Fields',
                3729 => 'The Heap',
                3730 => 'Manaforge Coruu',
                3731 => 'The Tempest Rift',
                3732 => 'Kirin`Var Village',
                3733 => 'The Violet Tower',
                3734 => 'Manaforge Duro',
                3735 => 'Voidwind Plateau',
                3736 => 'Manaforge Ultris',
                3737 => 'Celestial Ridge',
                3738 => 'The Stormspire',
                3739 => 'Forge Base: Oblivion',
                3740 => 'Forge Base: Gehenna',
                3741 => 'Ruins of Farahlon',
                3742 => 'Socrethar`s Seat',
                3743 => 'Legion Hold',
                3744 => 'Shadowmoon Village',
                3745 => 'Wildhammer Stronghold',
                3746 => 'The Hand of Gul`dan',
                3747 => 'The Fel Pits',
                3748 => 'The Deathforge',
                3749 => 'Coilskar Cistern',
                3750 => 'Coilskar Point',
                3751 => 'Sunfire Point',
                3752 => 'Illidari Point',
                3753 => 'Ruins of Baa`ri',
                3754 => 'Altar of Sha`tar',
                3755 => 'The Stair of Doom',
                3756 => 'Ruins of Karabor',
                3757 => 'Ata`mal Terrace',
                3758 => 'Netherwing Fields',
                3759 => 'Netherwing Ledge',
                3760 => 'The Barrier Hills',
                3761 => 'The High Path',
                3762 => 'Windyreed Pass',
                3763 => 'Zangar Ridge',
                3764 => 'The Twilight Ridge',
                3765 => 'Razorthorn Trail',
                3766 => 'Orebor Harborage',
                3767 => 'Blades` Run',
                3768 => 'Jagged Ridge',
                3769 => 'Thunderlord Stronghold',
                3770 => 'Blade Tooth Canyon',
                3771 => 'The Living Grove',
                3772 => 'Sylvanaar',
                3773 => 'Bladespire Hold',
                3774 => 'Gruul`s Lair',
                3775 => 'Circle of Blood',
                3776 => 'Bloodmaul Outpost',
                3777 => 'Bloodmaul Camp',
                3778 => 'Draenethyst Mine',
                3779 => 'Trogma`s Claim',
                3780 => 'Blackwing Coven',
                3781 => 'Grishnath',
                3782 => 'Veil Lashh',
                3783 => 'Veil Vekh',
                3784 => 'Forge Camp: Terror',
                3785 => 'Forge Camp: Wrath',
                3786 => 'Ogri`la',
                3787 => 'Forge Camp: Anger',
                3788 => 'The Low Path',
                3789 => 'Shadow Labyrinth',
                3790 => 'Auchenai Crypts',
                3791 => 'Sethekk Halls',
                3792 => 'Mana-Tombs',
                3793 => 'Felspark Ravine',
                3794 => 'Valley of Bones',
                3795 => 'Sha`naari Wastes',
                3796 => 'The Warp Fields',
                3797 => 'Fallen Sky Ridge',
                3798 => 'Haal`eshi Gorge',
                3799 => 'Stonewall Canyon',
                3800 => 'Thornfang Hill',
                3801 => 'Mag`har Grounds',
                3802 => 'Void Ridge',
                3803 => 'The Abyssal Shelf',
                3804 => 'The Legion Front',
                3805 => 'Zul`Aman',
                3806 => 'Supply Caravan',
                3807 => 'Reaver`s Fall',
                3808 => 'Cenarion Post',
                3809 => 'Southern Rampart',
                3810 => 'Northern Rampart',
                3811 => 'Gor`gaz Outpost',
                3812 => 'Spinebreaker Post',
                3813 => 'The Path of Anguish',
                3814 => 'East Supply Caravan',
                3815 => 'Expedition Point',
                3816 => 'Zeppelin Crash',
                3817 => 'Testing',
                3818 => 'Bloodscale Grounds',
                3819 => 'Darkcrest Enclave',
                3820 => 'Eye of the Storm',
                3821 => 'Warden`s Cage',
                3822 => 'Eclipse Point',
                3823 => 'Isle of Tribulations',
                3824 => 'Bloodmaul Ravine',
                3825 => 'Dragons` End',
                3826 => 'Daggermaw Canyon',
                3827 => 'Vekhaar Stand',
                3828 => 'Ruuan Weald',
                3829 => 'Veil Ruuan',
                3830 => 'Raven`s Wood',
                3831 => 'Death`s Door',
                3832 => 'Vortex Pinnacle',
                3833 => 'Razor Ridge',
                3834 => 'Ridge of Madness',
                3835 => 'Dustquill Ravine',
                3836 => 'Magtheridon`s Lair',
                3837 => 'Sunfury Hold',
                3838 => 'Spinebreaker Mountains',
                3839 => 'Abandoned Armory',
                3840 => 'The Black Temple',
                3841 => 'Darkcrest Shore',
                3842 => 'Tempest Keep',
                3844 => 'Mok`Nathal Village',
                3845 => 'Tempest Keep',
                3846 => 'The Arcatraz',
                3847 => 'The Botanica',
                3848 => 'The Arcatraz',
                3849 => 'The Mechanar',
                3850 => 'Netherstone',
                3851 => 'Midrealm Post',
                3852 => 'Tuluman`s Landing',
                3854 => 'Protectorate Watch Post',
                3855 => 'Circle of Blood Arena',
                3856 => 'Elrendar Crossing',
                3857 => 'Ammen Ford',
                3858 => 'Razorthorn Shelf',
                3859 => 'Silmyr Lake',
                3860 => 'Raastok Glade',
                3861 => 'Thalassian Pass',
                3862 => 'Churning Gulch',
                3863 => 'Broken Wilds',
                3864 => 'Bash`ir Landing',
                3865 => 'Crystal Spine',
                3866 => 'Skald',
                3867 => 'Bladed Gulch',
                3868 => 'Gyro-Plank Bridge',
                3869 => 'Mage Tower',
                3870 => 'Blood Elf Tower',
                3871 => 'Draenei Ruins',
                3872 => 'Fel Reaver Ruins',
                3873 => 'The Proving Grounds',
                3874 => 'Eco-Dome Farfield',
                3875 => 'Eco-Dome Skyperch',
                3876 => 'Eco-Dome Sutheron',
                3877 => 'Eco-Dome Midrealm',
                3878 => 'Ethereum Staging Grounds',
                3879 => 'Chapel Yard',
                3880 => 'Access Shaft Zeon',
                3881 => 'Trelleum Mine',
                3882 => 'Invasion Point: Destroyer',
                3883 => 'Camp of Boom',
                3884 => 'Spinebreaker Pass',
                3885 => 'Netherweb Ridge',
                3886 => 'Derelict Caravan',
                3887 => 'Refugee Caravan',
                3888 => 'Shadow Tomb',
                3889 => 'Veil Rhaze',
                3890 => 'Tomb of Lights',
                3891 => 'Carrion Hill',
                3892 => 'Writhing Mound',
                3893 => 'Ring of Observance',
                3894 => 'Auchenai Grounds',
                3895 => 'Cenarion Watchpost',
                3896 => 'Aldor Rise',
                3897 => 'Terrace of Light',
                3898 => 'Scryer`s Tier',
                3899 => 'Lower City',
                3900 => 'Invasion Point: Overlord',
                3901 => 'Allerian Post',
                3902 => 'Stonebreaker Camp',
                3903 => 'Boulder`mok',
                3904 => 'Cursed Hollow',
                3905 => 'Coilfang Reservoir',
                3906 => 'The Bloodwash',
                3907 => 'Veridian Point',
                3908 => 'Middenvale',
                3909 => 'The Lost Fold',
                3910 => 'Mystwood',
                3911 => 'Tranquil Shore',
                3912 => 'Goldenbough Pass',
                3913 => 'Runestone Falithas',
                3914 => 'Runestone Shan`dor',
                3915 => 'Fairbridge Strand',
                3916 => 'Moongraze Woods',
                3917 => 'Auchindoun',
                3918 => 'Toshley`s Station',
                3919 => 'Singing Ridge',
                3920 => 'Shatter Point',
                3921 => 'Arklonis Ridge',
                3922 => 'Bladespire Outpost',
                3923 => 'Gruul`s Lair',
                3924 => 'Northmaul Tower',
                3925 => 'Southmaul Tower',
                3926 => 'Shattered Plains',
                3927 => 'Oronok`s Farm',
                3928 => 'The Altar of Damnation',
                3929 => 'The Path of Conquest',
                3930 => 'Eclipsion Fields',
                3931 => 'Bladespire Grounds',
                3932 => 'Sketh`lon Base Camp',
                3933 => 'Sketh`lon Wreckage',
                3934 => 'Town Square',
                3935 => 'Wizard Row',
                3936 => 'Deathforge Tower',
                3937 => 'Slag Watch',
                3938 => 'Sanctum of the Stars',
                3939 => 'Dragonmaw Fortress',
                3940 => 'The Fetid Pool',
                3941 => 'Test',
                3942 => 'Razaan`s Landing',
                3943 => 'Invasion Point: Cataclysm',
                3944 => 'The Altar of Shadows',
                3945 => 'Netherwing Pass',
                3946 => 'Wayne`s Refuge',
                3947 => 'The Scalding Pools',
                3948 => 'Brian and Pat Test',
                3949 => 'Magma Fields',
                3950 => 'Crimson Watch',
                3951 => 'Evergrove',
                3952 => 'Wyrmskull Bridge',
                3953 => 'Scalewing Shelf',
                3954 => 'Wyrmskull Tunnel',
                3955 => 'Hellfire Basin',
                3956 => 'The Shadow Stair',
                3957 => 'Sha`tari Outpost',
                3958 => 'Sha`tari Base Camp',
                3959 => 'Black Temple',
                3960 => 'Soulgrinder`s Barrow',
                3961 => 'Sorrow Wing Point',
                3962 => 'Vim`gol`s Circle',
                3963 => 'Dragonspine Ridge',
                3964 => 'Skyguard Outpost',
                3965 => 'Netherwing Mines',
                3966 => 'Dragonmaw Base Camp',
                3967 => 'Dragonmaw Skyway',
                3968 => 'Ruins of Lordaeron',
                3969 => 'Rivendark`s Perch',
                3970 => 'Obsidia`s Perch',
                3971 => 'Insidion`s Perch',
                3972 => 'Furywing`s Perch',
                3973 => 'Blackwind Landing',
                3974 => 'Veil Harr`ik',
                3975 => 'Terokk`s Rest',
                3976 => 'Veil Ala`rak',
                3977 => 'Upper Veil Shil`ak',
                3978 => 'Lower Veil Shil`ak',
                3979 => 'The Frozen Sea',
                3980 => 'Daggercap Bay',
                3981 => 'Valgarde',
                3982 => 'Wyrmskull Village',
                3983 => 'Utgarde Keep',
                3984 => 'Nifflevar',
                3985 => 'Falls of Ymiron',
                3986 => 'Echo Reach',
                3987 => 'The Isle of Spears',
                3988 => 'Kamagua',
                3989 => 'Garvan`s Reef',
                3990 => 'Scalawag Point',
                3991 => 'New Agamand',
                3992 => 'The Ancient Lift',
                3993 => 'Westguard Turret',
                3994 => 'Halgrind',
                3995 => 'The Laughing Stand',
                3996 => 'Baelgun`s Excavation Site',
                3997 => 'Explorers` League Outpost',
                3998 => 'Westguard Keep',
                3999 => 'Steel Gate',
                4000 => 'Vengeance Landing',
                4001 => 'Baleheim',
                4002 => 'Skorn',
                4003 => 'Fort Wildervar',
                4004 => 'Vileprey Village',
                4005 => 'Ivald`s Ruin',
                4006 => 'Gjalerbron',
                4007 => 'Tomb of the Lost Kings',
                4008 => 'Shartuul`s Transporter',
                4009 => 'Illidari Training Grounds',
                4010 => 'Mudsprocket',
                4018 => 'Camp Winterhoof',
                4019 => 'Development Land',
                4020 => 'Mightstone Quarry',
                4021 => 'Bloodspore Plains',
                4022 => 'Gammoth',
                4023 => 'Amber Ledge',
                4024 => 'Coldarra',
                4025 => 'The Westrift',
                4026 => 'The Transitus Stair',
                4027 => 'Coast of Echoes',
                4028 => 'Riplash Strand',
                4029 => 'Riplash Ruins',
                4030 => 'Coast of Idols',
                4031 => 'Pal`ea',
                4032 => 'Valiance Keep',
                4033 => 'Winterfin Village',
                4034 => 'The Borean Wall',
                4035 => 'The Geyser Fields',
                4036 => 'Fizzcrank Pumping Station',
                4037 => 'Taunka`le Village',
                4038 => 'Magnamoth Caverns',
                4039 => 'Coldrock Quarry',
                4040 => 'Njord`s Breath Bay',
                4041 => 'Kaskala',
                4042 => 'Transborea',
                4043 => 'The Flood Plains',
                4046 => 'Direhorn Post',
                4047 => 'Nat`s Landing',
                4048 => 'Ember Clutch',
                4049 => 'Tabetha`s Farm',
                4050 => 'Derelict Strand',
                4051 => 'The Frozen Glade',
                4052 => 'The Vibrant Glade',
                4053 => 'The Twisted Glade',
                4054 => 'Rivenwood',
                4055 => 'Caldemere Lake',
                4056 => 'Utgarde Catacombs',
                4057 => 'Shield Hill',
                4058 => 'Lake Cauldros',
                4059 => 'Cauldros Isle',
                4060 => 'Bleeding Vale',
                4061 => 'Giants` Run',
                4062 => 'Apothecary Camp',
                4063 => 'Ember Spear Tower',
                4064 => 'Shattered Straits',
                4065 => 'Gjalerhorn',
                4066 => 'Frostblade Peak',
                4067 => 'Plaguewood Tower',
                4068 => 'West Spear Tower',
                4069 => 'North Spear Tower',
                4070 => 'Chillmere Coast',
                4071 => 'Whisper Gulch',
                4072 => 'Sub zone',
                4073 => 'Winter`s Terrace',
                4074 => 'The Waking Halls',
                4075 => 'Sunwell Plateau',
                4076 => 'Reuse Me 7',
                4077 => 'Sorlof`s Strand',
                4078 => 'Razorthorn Rise',
                4079 => 'Frostblade Pass',
                4080 => 'Isle of Quel`Danas',
                4081 => 'The Dawnchaser',
                4082 => 'The Sin`loren',
                4083 => 'Silvermoon`s Pride',
                4084 => 'The Bloodoath',
                4085 => 'Shattered Sun Staging Area',
                4086 => 'Sun`s Reach Sanctum',
                4087 => 'Sun`s Reach Harbor',
                4088 => 'Sun`s Reach Armory',
                4089 => 'Dawnstar Village',
                4090 => 'The Dawning Square',
                4091 => 'Greengill Coast',
                4092 => 'The Dead Scar',
                4093 => 'The Sun Forge',
                4094 => 'Sunwell Plateau',
                4095 => 'Magisters` Terrace',
                4096 => 'Clayt�n`s WoWEdit Land',
                4097 => 'Winterfin Caverns',
                4098 => 'Glimmer Bay',
                4099 => 'Winterfin Retreat',
                4100 => 'The Culling of Stratholme',
                4101 => 'Sands of Nasam',
                4102 => 'Krom`s Landing',
                4103 => 'Nasam`s Talon',
                4104 => 'Echo Cove',
                4105 => 'Beryl Point',
                4106 => 'Garrosh`s Landing',
                4107 => 'Warsong Jetty',
                4108 => 'Fizzcrank Airstrip',
                4109 => 'Lake Kum`uya',
                4110 => 'Farshire Fields',
                4111 => 'Farshire',
                4112 => 'Farshire Lighthouse',
                4113 => 'Unu`pe',
                4114 => 'Death`s Stand',
                4115 => 'The Abandoned Reach',
                4116 => 'Scalding Pools',
                4117 => 'Steam Springs',
                4118 => 'Talramas',
                4119 => 'Festering Pools',
                4120 => 'The Nexus',
                4121 => 'Transitus Shield',
                4122 => 'Bor`gorok Outpost',
                4123 => 'Magmoth',
                4124 => 'The Dens of Dying',
                4125 => 'Temple City of En`kilah',
                4126 => 'The Wailing Ziggurat',
                4127 => 'Steeljaw`s Caravan',
                4128 => 'Naxxanar',
                4129 => 'Warsong Hold',
                4130 => 'Plains of Nasam',
                4131 => 'Magisters` Terrace',
                4132 => 'Ruins of Eldra`nath',
                4133 => 'Charred Rise',
                4134 => 'Blistering Pool',
                4135 => 'Spire of Blood',
                4136 => 'Spire of Decay',
                4137 => 'Spire of Pain',
                4138 => 'Frozen Reach',
                4139 => 'Parhelion Plaza',
                4140 => 'The Dead Scar',
                4141 => 'Torp`s Farm',
                4142 => 'Warsong Granary',
                4143 => 'Warsong Slaughterhouse',
                4144 => 'Warsong Farms Outpost',
                4145 => 'West Point Station',
                4146 => 'North Point Station',
                4147 => 'Mid Point Station',
                4148 => 'South Point Station',
                4149 => 'D.E.H.T.A. Encampment',
                4150 => 'Kaw`s Roost',
                4151 => 'Westwind Refugee Camp',
                4152 => 'Moa`ki Harbor',
                4153 => 'Indu`le Village',
                4154 => 'Snowfall Glade',
                4155 => 'The Half Shell',
                4156 => 'Surge Needle',
                4157 => 'Moonrest Gardens',
                4158 => 'Stars` Rest',
                4159 => 'Westfall Brigade Encampment',
                4160 => 'Lothalor Woodlands',
                4161 => 'Wyrmrest Temple',
                4162 => 'Icemist Falls',
                4163 => 'Icemist Village',
                4164 => 'The Pit of Narjun',
                4165 => 'Agmar`s Hammer',
                4166 => 'Lake Indu`le',
                4167 => 'Obsidian Dragonshrine',
                4168 => 'Ruby Dragonshrine',
                4169 => 'Fordragon Hold',
                4170 => 'Kor`kron Vanguard',
                4171 => 'The Court of Skulls',
                4172 => 'Angrathar the Wrathgate',
                4173 => 'Galakrond`s Rest',
                4174 => 'The Wicked Coil',
                4175 => 'Bronze Dragonshrine',
                4176 => 'The Mirror of Dawn',
                4177 => 'Wintergarde Keep',
                4178 => 'Wintergarde Mine',
                4179 => 'Emerald Dragonshrine',
                4180 => 'New Hearthglen',
                4181 => 'Crusader`s Landing',
                4182 => 'Sinner`s Folly',
                4183 => 'Azure Dragonshrine',
                4184 => 'Path of the Titans',
                4185 => 'The Forgotten Shore',
                4186 => 'Venomspite',
                4187 => 'The Crystal Vice',
                4188 => 'The Carrion Fields',
                4189 => 'Onslaught Base Camp',
                4190 => 'Thorson`s Post',
                4191 => 'Light`s Trust',
                4192 => 'Frostmourne Cavern',
                4193 => 'Scarlet Point',
                4194 => 'Jintha`kalar',
                4195 => 'Ice Heart Cavern',
                4196 => 'Drak`Tharon Keep',
                4197 => 'Wintergrasp',
                4198 => 'Kili`ua`s Atoll',
                4199 => 'Silverbrook',
                4200 => 'Vordrassil`s Heart',
                4201 => 'Vordrassil`s Tears',
                4202 => 'Vordrassil`s Tears',
                4203 => 'Vordrassil`s Limb',
                4204 => 'Amberpine Lodge',
                4205 => 'Solstice Village',
                4206 => 'Conquest Hold',
                4207 => 'Voldrune',
                4208 => 'Granite Springs',
                4209 => 'Zeb`Halak',
                4210 => 'Drak`Tharon Keep',
                4211 => 'Camp Oneqwah',
                4212 => 'Eastwind Shore',
                4213 => 'The Broken Bluffs',
                4214 => 'Boulder Hills',
                4215 => 'Rage Fang Shrine',
                4216 => 'Drakil`jin Ruins',
                4217 => 'Blackriver Logging Camp',
                4218 => 'Heart`s Blood Shrine',
                4219 => 'Hollowstone Mine',
                4220 => 'Dun Argol',
                4221 => 'Thor Modan',
                4222 => 'Blue Sky Logging Grounds',
                4223 => 'Maw of Neltharion',
                4224 => 'The Briny Pinnacle',
                4225 => 'Glittering Strand',
                4226 => 'Iskaal',
                4227 => 'Dragon`s Fall',
                4228 => 'The Oculus',
                4229 => 'Prospector`s Point',
                4230 => 'Coldwind Heights',
                4231 => 'Redwood Trading Post',
                4232 => 'Vengeance Pass',
                4233 => 'Dawn`s Reach',
                4234 => 'Naxxramas',
                4235 => 'Heartwood Trading Post',
                4236 => 'Evergreen Trading Post',
                4237 => 'Spruce Point Post',
                4238 => 'White Pine Trading Post',
                4239 => 'Aspen Grove Post',
                4240 => 'Forest`s Edge Post',
                4241 => 'Eldritch Heights',
                4242 => 'Venture Bay',
                4243 => 'Wintergarde Crypt',
                4244 => 'Bloodmoon Isle',
                4245 => 'Shadowfang Tower',
                4246 => 'Wintergarde Mausoleum',
                4247 => 'Duskhowl Den',
                4248 => 'The Conquest Pit',
                4249 => 'The Path of Iron',
                4250 => 'Ruins of Tethys',
                4251 => 'Silverbrook Hills',
                4252 => 'The Broken Bluffs',
                4253 => '7th Legion Front',
                4254 => 'The Dragon Wastes',
                4255 => 'Ruins of Drak`Zin',
                4256 => 'Drak`Mar Lake',
                4257 => 'Dragonspine Tributary',
                4258 => 'The North Sea',
                4259 => 'Drak`ural',
                4260 => 'Thorvald`s Camp',
                4261 => 'Ghostblade Post',
                4262 => 'Ashwood Post',
                4263 => 'Lydell`s Ambush',
                4264 => 'Halls of Stone',
                4265 => 'The Nexus',
                4266 => 'Harkor`s Camp',
                4267 => 'Vordrassil Pass',
                4268 => 'Ruuna`s Camp',
                4269 => 'Shrine of Scales',
                4270 => 'Drak`atal Passage',
                4271 => 'Utgarde Pinnacle',
                4272 => 'Halls of Lightning',
                4273 => 'Ulduar',
                4275 => 'The Argent Stand',
                4276 => 'Altar of Sseratus',
                4277 => 'Azjol-Nerub',
                4278 => 'Drak`Sotra Fields',
                4279 => 'Drak`Sotra',
                4280 => 'Drak`Agal',
                4281 => 'Acherus: The Ebon Hold',
                4282 => 'The Avalanche',
                4283 => 'The Lost Lands',
                4284 => 'Nesingwary Base Camp',
                4285 => 'The Seabreach Flow',
                4286 => 'The Bones of Nozronn',
                4287 => 'Kartak`s Hold',
                4288 => 'Sparktouched Haven',
                4289 => 'The Path of the Lifewarden',
                4290 => 'River`s Heart',
                4291 => 'Rainspeaker Canopy',
                4292 => 'Frenzyheart Hill',
                4293 => 'Wildgrowth Mangal',
                4294 => 'Heb`Valok',
                4295 => 'The Sundered Shard',
                4296 => 'The Lifeblood Pillar',
                4297 => 'Mosswalker Village',
                4298 => 'Plaguelands: The Scarlet Enclave',
                4299 => 'Kolramas',
                4300 => 'Waygate',
                4302 => 'The Skyreach Pillar',
                4303 => 'Hardknuckle Clearing',
                4304 => 'Sapphire Hive',
                4306 => 'Mistwhisper Refuge',
                4307 => 'The Glimmering Pillar',
                4308 => 'Spearborn Encampment',
                4309 => 'Drak`Tharon Keep',
                4310 => 'Zeramas',
                4311 => 'Reliquary of Agony',
                4312 => 'Ebon Watch',
                4313 => 'Thrym`s End',
                4314 => 'Voltarus',
                4315 => 'Reliquary of Pain',
                4316 => 'Rageclaw Den',
                4317 => 'Light`s Breach',
                4318 => 'Pools of Zha`Jin',
                4319 => 'Zim`Abwa',
                4320 => 'Amphitheater of Anguish',
                4321 => 'Altar of Rhunok',
                4322 => 'Altar of Har`koa',
                4323 => 'Zim`Torga',
                4324 => 'Pools of Jin`Alai',
                4325 => 'Altar of Quetz`lun',
                4326 => 'Heb`Drakkar',
                4327 => 'Drak`Mabwa',
                4328 => 'Zim`Rhuk',
                4329 => 'Altar of Mam`toth',
                4342 => 'Acherus: The Ebon Hold',
                4343 => 'New Avalon',
                4344 => 'New Avalon Fields',
                4345 => 'New Avalon Orchard',
                4346 => 'New Avalon Town Hall',
                4347 => 'Havenshire',
                4348 => 'Havenshire Farms',
                4349 => 'Havenshire Lumber Mill',
                4350 => 'Havenshire Stables',
                4351 => 'Scarlet Hold',
                4352 => 'Chapel of the Crimson Flame',
                4353 => 'Light`s Point Tower',
                4354 => 'Light`s Point',
                4355 => 'Crypt of Remembrance',
                4356 => 'Death`s Breach',
                4357 => 'The Noxious Glade',
                4358 => 'Tyr`s Hand',
                4359 => 'King`s Harbor',
                4360 => 'Scarlet Overlook',
                4361 => 'Light`s Hope Chapel',
                4362 => 'Sinner`s Folly',
                4363 => 'Pestilent Scar',
                4364 => 'Browman Mill',
                4365 => 'Havenshire Mine',
                4366 => 'Ursoc`s Den',
                4367 => 'The Blight Line',
                4368 => 'The Bonefields',
                4369 => 'Dorian`s Outpost',
                4371 => 'Mam`toth Crater',
                4372 => 'Zol`Maz Stronghold',
                4373 => 'Zol`Heb',
                4374 => 'Rageclaw Lake',
                4375 => 'Gundrak',
                4376 => 'The Savage Thicket',
                4377 => 'New Avalon Forge',
                4378 => 'Dalaran Arena',
                4379 => 'Valgarde',
                4380 => 'Westguard Inn',
                4381 => 'Waygate',
                4382 => 'The Shaper`s Terrace',
                4383 => 'Lakeside Landing',
                4384 => 'Strand of the Ancients',
                4385 => 'Bittertide Lake',
                4386 => 'Rainspeaker Rapids',
                4387 => 'Frenzyheart River',
                4388 => 'Wintergrasp River',
                4389 => 'The Suntouched Pillar',
                4390 => 'Frigid Breach',
                4391 => 'Swindlegrin`s Dig',
                4392 => 'The Stormwright`s Shelf',
                4393 => 'Death`s Hand Encampment',
                4394 => 'Scarlet Tavern',
                4395 => 'Dalaran',
                4396 => 'Nozzlerust Post',
                4399 => 'Farshire Mine',
                4400 => 'The Mosslight Pillar',
                4401 => 'Saragosa`s Landing',
                4402 => 'Vengeance Lift',
                4403 => 'Balejar Watch',
                4404 => 'New Agamand Inn',
                4405 => 'Passage of Lost Fiends',
                4406 => 'The Ring of Valor',
                4407 => 'Hall of the Frostwolf',
                4408 => 'Hall of the Stormpike',
                4411 => 'Stormwind Harbor',
                4412 => 'The Makers` Overlook',
                4413 => 'The Makers` Perch',
                4414 => 'Scarlet Tower',
                4415 => 'The Violet Hold',
                4416 => 'Gundrak',
                4417 => 'Onslaught Harbor',
                4418 => 'K3',
                4419 => 'Snowblind Hills',
                4420 => 'Snowblind Terrace',
                4421 => 'Garm',
                4422 => 'Brunnhildar Village',
                4423 => 'Sifreldar Village',
                4424 => 'Valkyrion',
                4425 => 'The Forlorn Mine',
                4426 => 'Bor`s Breath River',
                4427 => 'Argent Vanguard',
                4428 => 'Frosthold',
                4429 => 'Grom`arsh Crash-Site',
                4430 => 'Temple of Storms',
                4431 => 'Engine of the Makers',
                4432 => 'The Foot Steppes',
                4433 => 'Dragonspine Peaks',
                4434 => 'Nidavelir',
                4435 => 'Narvir`s Cradle',
                4436 => 'Snowdrift Plains',
                4437 => 'Valley of Ancient Winters',
                4438 => 'Dun Niffelem',
                4439 => 'Frostfield Lake',
                4440 => 'Thunderfall',
                4441 => 'Camp Tunka`lo',
                4442 => 'Brann`s Base-Camp',
                4443 => 'Gate of Echoes',
                4444 => 'Plain of Echoes',
                4445 => 'Ulduar',
                4446 => 'Terrace of the Makers',
                4447 => 'Gate of Lightning',
                4448 => 'Path of the Titans',
                4449 => 'Uldis',
                4450 => 'Loken`s Bargain',
                4451 => 'Bor`s Fall',
                4452 => 'Bor`s Breath',
                4453 => 'Rohemdal Pass',
                4454 => 'The Storm Foundry',
                4455 => 'Hibernal Cavern',
                4456 => 'Voldrune Dwelling',
                4457 => 'Torseg`s Rest',
                4458 => 'Sparksocket Minefield',
                4459 => 'Ricket`s Folly',
                4460 => 'Garm`s Bane',
                4461 => 'Garm`s Rise',
                4462 => 'Crystalweb Cavern',
                4463 => 'Temple of Life',
                4464 => 'Temple of Order',
                4465 => 'Temple of Winter',
                4466 => 'Temple of Invention',
                4467 => 'Death`s Rise',
                4468 => 'The Dead Fields',
                4469 => 'Dargath`s Demise',
                4470 => 'The Hidden Hollow',
                4471 => 'Bernau`s Happy Fun Land',
                4472 => 'Frostgrip`s Hollow',
                4473 => 'The Frigid Tomb',
                4474 => 'Twin Shores',
                4475 => 'Zim`bo`s Hideout',
                4476 => 'Abandoned Camp',
                4477 => 'The Shadow Vault',
                4478 => 'Coldwind Pass',
                4479 => 'Winter`s Breath Lake',
                4480 => 'The Forgotten Overlook',
                4481 => 'Jintha`kalar Passage',
                4482 => 'Arriga Footbridge',
                4483 => 'The Lost Passage',
                4484 => 'Bouldercrag`s Refuge',
                4485 => 'The Inventor`s Library',
                4486 => 'The Frozen Mine',
                4487 => 'Frostfloe Deep',
                4488 => 'The Howling Hollow',
                4489 => 'Crusader Forward Camp',
                4490 => 'Stormcrest',
                4491 => 'Bonesnap`s Camp',
                4492 => 'Ufrang`s Hall',
                4493 => 'The Obsidian Sanctum',
                4494 => 'Ahn`kahet: The Old Kingdom',
                4495 => 'Fjorn`s Anvil',
                4496 => 'Jotunheim',
                4497 => 'Savage Ledge',
                4498 => 'Halls of the Ancestors',
                4499 => 'The Blighted Pool',
                4500 => 'The Eye of Eternity',
                4501 => 'The Argent Vanguard',
                4502 => 'Mimir`s Workshop',
                4503 => 'Ironwall Dam',
                4504 => 'Valley of Echoes',
                4505 => 'The Breach',
                4506 => 'Scourgeholme',
                4507 => 'The Broken Front',
                4508 => 'Mord`rethar: The Death Gate',
                4509 => 'The Bombardment',
                4510 => 'Aldur`thar: The Desolation Gate',
                4511 => 'The Skybreaker',
                4512 => 'Orgrim`s Hammer',
                4513 => 'Ymirheim',
                4514 => 'Saronite Mines',
                4515 => 'The Conflagration',
                4516 => 'Ironwall Rampart',
                4517 => 'Weeping Quarry',
                4518 => 'Corp`rethar: The Horror Gate',
                4519 => 'The Court of Bones',
                4520 => 'Malykriss: The Vile Hold',
                4521 => 'Cathedral of Darkness',
                4522 => 'Icecrown Citadel',
                4523 => 'Icecrown Glacier',
                4524 => 'Valhalas',
                4525 => 'The Underhalls',
                4526 => 'Njorndar Village',
                4527 => 'Balargarde Fortress',
                4528 => 'Kul`galar Keep',
                4529 => 'The Crimson Cathedral',
                4530 => 'Sanctum of Reanimation',
                4531 => 'The Fleshwerks',
                4532 => 'Vengeance Landing Inn',
                4533 => 'Sindragosa`s Fall',
                4534 => 'Wildervar Mine',
                4535 => 'The Pit of the Fang',
                4536 => 'Frosthowl Cavern',
                4537 => 'The Valley of Lost Hope',
                4538 => 'The Sunken Ring',
                4539 => 'The Broken Temple',
                4540 => 'The Valley of Fallen Heroes',
                4541 => 'Vanguard Infirmary',
                4542 => 'Hall of the Shaper',
                4543 => 'Temple of Wisdom',
                4544 => 'Death`s Breach',
                4545 => 'Abandoned Mine',
                4546 => 'Ruins of the Scarlet Enclave',
                4547 => 'Halls of Stone',
                4548 => 'Halls of Lightning',
                4549 => 'The Great Tree',
                4550 => 'The Mirror of Twilight',
                4551 => 'The Twilight Rivulet',
                4552 => 'The Decrepit Flow',
                4553 => 'Forlorn Woods',
                4554 => 'Ruins of Shandaral',
                4555 => 'The Azure Front',
                4556 => 'Violet Stand',
                4557 => 'The Unbound Thicket',
                4558 => 'Sunreaver`s Command',
                4559 => 'Windrunner`s Overlook',
                4560 => 'The Underbelly',
                4564 => 'Krasus` Landing',
                4567 => 'The Violet Hold',
                4568 => 'The Eventide',
                4569 => 'Sewer Exit Pipe',
                4570 => 'Circle of Wills',
                4571 => 'Silverwing Flag Room',
                4572 => 'Warsong Flag Room',
                4575 => 'Wintergrasp Fortress',
                4576 => 'Central Bridge',
                4577 => 'Eastern Bridge',
                4578 => 'Western Bridge',
                4579 => 'Dubra`Jin',
                4580 => 'Crusaders` Pinnacle',
                4581 => 'Flamewatch Tower',
                4582 => 'Winter`s Edge Tower',
                4583 => 'Shadowsight Tower',
                4584 => 'The Cauldron of Flames',
                4585 => 'Glacial Falls',
                4586 => 'Windy Bluffs',
                4587 => 'The Forest of Shadows',
                4588 => 'Blackwatch',
                4589 => 'The Chilled Quagmire',
                4590 => 'The Steppe of Life',
                4591 => 'Silent Vigil',
                4592 => 'Gimorak`s Den',
                4593 => 'The Pit of Fiends',
                4594 => 'Battlescar Spire',
                4595 => 'Hall of Horrors',
                4596 => 'The Circle of Suffering',
                4597 => 'Rise of Suffering',
                4598 => 'Krasus` Landing',
                4599 => 'Sewer Exit Pipe',
                4601 => 'Dalaran Island',
                4602 => 'Force Interior',
                4603 => 'Vault of Archavon',
                4604 => 'Gate of the Red Sun',
                4605 => 'Gate of the Blue Sapphire',
                4606 => 'Gate of the Green Emerald',
                4607 => 'Gate of the Purple Amethyst',
                4608 => 'Gate of the Yellow Moon',
                4609 => 'Courtyard of the Ancients',
                4610 => 'Landing Beach',
                4611 => 'Westspark Workshop',
                4612 => 'Eastspark Workshop',
                4613 => 'Dalaran City',
                4614 => 'The Violet Citadel Spire',
                4615 => 'Naz`anak: The Forgotten Depths',
                4616 => 'Sunreaver`s Sanctuary',
                4617 => 'Elevator',
                4618 => 'Antonidas Memorial',
                4619 => 'The Violet Citadel',
                4620 => 'Magus Commerce Exchange',
                4621 => 'UNUSED',
                4622 => 'First Legion Forward Camp',
                4623 => 'Hall of the Conquered Kings',
                4624 => 'Befouled Terrace',
                4625 => 'The Desecrated Altar',
                4626 => 'Shimmering Bog',
                4627 => 'Fallen Temple of Ahn`kahet',
                4628 => 'Halls of Binding',
                4629 => 'Winter`s Heart',
                4630 => 'The North Sea',
                4631 => 'The Broodmother`s Nest',
                4632 => 'Dalaran Floating Rocks',
                4633 => 'Raptor Pens',
                4635 => 'Drak`Tharon Keep',
                4636 => 'The Noxious Pass',
                4637 => 'Vargoth`s Retreat',
                4638 => 'Violet Citadel Balcony',
                4639 => 'Band of Variance',
                4640 => 'Band of Acceleration',
                4641 => 'Band of Transmutation',
                4642 => 'Band of Alignment',
                4646 => 'Ashwood Lake',
                4650 => 'Iron Concourse',
                4652 => 'Formation Grounds',
                4653 => 'Razorscale`s Aerie',
                4654 => 'The Colossal Forge',
                4655 => 'The Scrapyard',
                4656 => 'The Conservatory of Life',
                4657 => 'The Archivum',
                4658 => 'Argent Tournament Grounds',
                4665 => 'Expedition Base Camp',
                4666 => 'Sunreaver Pavilion',
                4667 => 'Silver Covenant Pavilion',
                4668 => 'The Cooper Residence',
                4669 => 'The Ring of Champions',
                4670 => 'The Aspirants` Ring',
                4671 => 'The Argent Valiants` Ring',
                4672 => 'The Alliance Valiants` Ring',
                4673 => 'The Horde Valiants` Ring',
                4674 => 'Argent Pavilion',
                4676 => 'Sunreaver Pavilion',
                4677 => 'Silver Covenant Pavilion',
                4679 => 'The Forlorn Cavern',
                4688 => 'claytonio test area',
                4692 => 'Quel`Delar`s Rest'
            )
		);
    }
}
?>