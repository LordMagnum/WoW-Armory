<?php

/**
 * @package World of Warcraft Armory
 * @version Release Candidate 1
 * @revision 207
 * @copyright (c) 2009-2010 Shadez
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 **/

if(!defined('__ARMORY__')) {
    die('Direct access to this file not allowed!');
}

Class Characters extends Connector {
    
    /**
     * Player guid
     **/
    private $guid = false;
    
    /**
     * Player name
     **/
    private $name = false;
    
    /**
     * Player race
     **/
    private $race = false;
    
    /**
     * Player class
     **/
    private $class = false;
    
    /**
     * Player gender
     * (0 - male, 1 - female)
     **/
    private $gender = false;
    
    /**
     * Player level
     **/
    private $level = false;
    
    /**
     * Player model display info
     **/
    private $playerBytes = false;
    private $playerBytes2 = false;
    private $playerFlags = false;
    
    /**
     * Player title ID
     **/
    private $chosenTitle = false;
    
    /**
     * Player health value
     **/
    private $health = false;
    
    /**
     * Player powers values
     **/
    private $power1 = false;
    private $power2 = false;
    private $power3 = false;
    
    /**
     * Account ID
     * (currently not used)
     **/
    private $account = false;
    
    /**
     * Talent specs count
     **/
    private $specCount = false;
    
    /**
     * Active talent spec ID
     * (0 or 1)
     **/
    private $activeSpec = false;

    /**
     * Player faction
     * (1 - Horde, 0 - Alliance)
     **/
    private $faction = false;
    
    /**
     * Array with player stats constants
     * (depends on character level)
     **/
    private $rating = false;
     
    /**
     * Player title data
     * (prefix, suffix, titleId)
     **/
    private $character_title = array('prefix' => null, 'suffix' => null, 'titleId' => null);
    
    /**
     * Player guild ID
     **/
    private $guild_id = false;
    
    /**
     * Player guild name
     **/
    private $guild_name = false;
    
    /**
     * Player guild rank ID
     **/
    private $guild_rank_id = false;
    
    /**
     * Player guild rank name
     **/
    private $guild_rank_name = false;
    
    /**
     * $this->class text
     **/
    private $classText = false;
    
    /**
     * $this->race text
     **/
    private $raceText = false;
    
    /**
     * Is character exists?
     * @category Characters class
     * @example Characters::IsCharacter()
     * @return bool
     **/
    public function IsCharacter() {
        return self::CheckPlayer();
    }
    
    public function BuildCharacter($name) {
        if(!is_string($name)) {
            return false;
        }
        $player_data = $this->cDB->selectRow("
        SELECT
        `characters`.`guid`,
        `characters`.`account`,
        `characters`.`name`,
        `characters`.`race`,
        `characters`.`class`,
        `characters`.`gender`,
        `characters`.`level`,
        `characters`.`playerBytes`,
        `characters`.`playerBytes2`,
        `characters`.`playerFlags`,
        `characters`.`specCount`,
        `characters`.`activeSpec`,
        `characters`.`chosenTitle`,
        `characters`.`health`,
        `characters`.`power1`,
        `characters`.`power2`,
        `characters`.`power3`,
        `guild_member`.`guildid` AS `guild_id`,
        `guild`.`name` AS `guild_name`
        FROM `characters` AS `characters`
        LEFT JOIN `guild_member` AS `guild_member` ON `guild_member`.`guid`=`characters`.`guid`
        LEFT JOIN `guild` AS `guild` ON `guild`.`guildid`=`guild_member`.`guildid`
        WHERE `characters`.`name`=? LIMIT 1", $name);
        if(!$player_data || !is_array($player_data)) {
            return false;
        }
        // Is character allowed to be displayed in Armory?
        $gmLevel = false;
        $gmLevel_mangos = $this->rDB->selectCell("SELECT `gmlevel` FROM `account` WHERE `id`=?d LIMIT 1", $player_data['account']);
        $gmLevel_trinity = $this->rDB->selectCell("SELECT `gmlevel` FROM `account_access` WHERE `id`=? AND `RealmID` IN (-1, ?d)", $player_data['account'], $this->connectionData['id']);
        if($gmLevel_mangos && $gmLevel_trinity) {
            // MaNGOS doesn't have `account_access` table in `realmd` DB
            if($this->currentRealmInfo['type'] == 'trinity') {
                $gmLevel = $gmLevel_trinity;
            }
            else {
                // error?
                $gmLevel = $gmLevel_mangos;
            }
        }
        elseif($gmLevel_mangos && !$gmLevel_trinity) {
            $gmLevel = $gmLevel_mangos;
        }
        elseif($gmLevel_trinity && !$gmLevel_mangos) {
            $gmLevel = $gmLevel_trinity;
        }
        if(!$gmLevel) {
            unset($player_data);
            // Unknown account
            return false;
        }
        $allowed = ($gmLevel <= $this->armoryconfig['minGmLevelToShow']) ? true : false;
        if(!$allowed || $player_data['level'] < $this->armoryconfig['minlevel']) {
            unset($player_data);
            return false;
        }
        // Class/race/faction checks
        if($player_data['class'] >= MAX_CLASSES) {
            // Unknown class
            unset($player_data);
            return false;
        }
        elseif($player_data['race'] >= MAX_RACES) {
            // Unknown race
            unset($player_data);
            return false;
        }
        $this->faction = Utils::GetFactionId($player_data['race']);
        if($this->faction != 0 && $this->faction != 1) {
            // Unknown faction
            unset($player_data);
            return false;
        }
        // Everything correct, build class
        foreach($player_data as $pData_key => $pData_value) {
            $this->{$pData_key} = $pData_value;
        }
        // Get race and class strings
        $race_class = $this->aDB->selectRow("
        SELECT
        `armory_races`.`name_".$this->_locale."` AS `race`,
        `armory_classes`.`name_".$this->_locale."` AS `class`
        FROM `armory_races` AS `armory_races`
        LEFT JOIN `armory_classes` AS `armory_classes` ON `armory_classes`.`id`=?d
        WHERE `armory_races`.`id`=?d", $this->class, $this->race);
        $this->classText = $race_class['class'];
        $this->raceText = $race_class['race'];
        // Get title info
        if($this->chosenTitle > 0) {
            $this->__GetTitleInfo();
        }
        return true;
    }
    
    /**
     * Constructs character title info
     * @category Characters class
     * @return void
     **/
    private function __GetTitleInfo() {
        $title_data = $this->aDB->selectRow("SELECT `title_F_".$this->_locale."` AS `titleF`, `title_M_".$this->_locale."` AS `titleM`, `place` FROM `armory_titles` WHERE `id`=?d", $this->chosenTitle);
        if(!$title_data) {
            return true;
        }
        switch($this->gender) {
            case 0:
                if($title_data['place'] == 'prefix') {
                    $this->character_title['prefix'] = $title_data['titleM'];
                }
                elseif($title_data['place'] == 'suffix') {
                    $this->character_title['suffix'] = $title_data['titleM'];
                }
                break;
            case 1:
                if($title_data['place'] == 'prefix') {
                    $this->character_title['prefix'] = $title_data['titleF'];
                }
                elseif($title_data['place'] == 'suffix') {
                    $this->character_title['suffix'] = $title_data['titleF'];
                }
                break;
        }
        $this->character_title['titleId'] = $this->chosenTitle;
    }
    
    public function CheckPlayer() {
        if(!$this->guid || !$this->name) {
            return false;
        }
        return true;
    }
    
    /**
     * Returns player GUID
     * @category Characters class
     * @example Characters::GetGUID();
     * @return int
     **/
    public function GetGUID() {
        return $this->guid;
    }
    
    /**
     * Returns player name
     * @category Characters class
     * @example Characters::GetName();
     * @return string
     **/
    public function GetName() {
        return $this->name;
    }
    
    /**
     * Returns player class
     * @category Characters class
     * @example Characters::GetClass();
     * @return int
     **/
    public function GetClass() {
        return $this->class;
    }
    
    /**
     * Returns player race
     * @category Characters class
     * @example Characters::GetRace();
     * @return int
     **/
    public function GetRace() {
        return $this->race;
    }
    
    /**
     * Returns player level
     * @category Characters class
     * @example Characters::GetLevel();
     * @return int
     **/    
    public function GetLevel() {
        return $this->level;
    }
    
    /**
     * Returns player gender
     * @category Characters class
     * @example Characters::GetGender();
     * @return int
     **/
    public function GetGender() {
        return $this->gender;
    }
    
    /**
     * Returns player faction
     * @category Characters class
     * @example Characters::GetFaction();
     * @return int
     **/
    public function GetFaction() {
        return $this->faction;
    }
    
    /**
     * Returns player account ID
     * @category Characters class
     * @example Characters::GetAccountID();
     * @return int
     **/
    public function GetAccountID() {
        return $this->account;
    }
    
    /**
     * Returns active talent spec ID
     * @category Characters class
     * @example Characters::GetActiveSpec();
     * @return int
     **/
    public function GetActiveSpec() {
        return $this->activeSpec;
    }
    
    /**
     * Returns talent specs count
     * @category Characters class
     * @example Characters::GetSpecCount();
     * @return int
     **/
    public function GetSpecCount() {
        return $this->specCount;
    }
    
    /**
     * Returns array with player model info
     * @category Characters class
     * @example Characters::GetPlayerBytes();
     * @return array
     **/
    public function GetPlayerBytes() {
        return array('playerBytes' => $this->playerBytes, 'playerBytes2' => $this->playerBytes2, 'playerFlags' => $this->playerFlags);
    }
    
    /**
     * Returns player guild name
     * @category Characters class
     * @example Characters::GetGuildName();
     * @return string
     **/
    public function GetGuildName() {
        return $this->guild_name;
    }
    
    /**
     * Returns player guild ID
     * @category Characters class
     * @example Characters::GetGuildID();
     * @return int
     **/
    public function GetGuildID() {
        return $this->guild_id;
    }
    
    /**
     * Returns array with chosen title info
     * @category Characters class
     * @example Characters::GetChosenTitleInfo();
     * @return array
     **/
    public function GetChosenTitleInfo() {
        return $this->character_title;
    }
    
    /**
     * Returns text string for $this->class ID
     * @category Characters class
     * @example Characters::GetClassText();
     * @return string
     **/
    public function GetClassText() {
        return $this->classText;
    }
    
    /**
     * Returns text string for $this->race ID
     * @category Characters class
     * @example Characters::GetRaceText();
     * @return string
     **/
    public function GetRaceText() {
        return $this->raceText;
    }
    
    /**
     * Returns character URL string (r=realmName&cn=CharName&gn=GuildName)
     * @category Characters class
     * @example Characters::GetUrlString();
     * @return string
     **/
    public function GetUrlString() {
        $url = sprintf('r=%s&cn=%s', urlencode($this->currentRealmInfo['name']), urlencode($this->name));
        if($this->guild_id > 0) {
            $url .= sprintf('&gn=%s', $this->guild_name);
        }
        return $url;
    }
    
    public function GetHeader(Achievements $achievements) {
        $header = array(
            'battleGroup'  => $this->armoryconfig['defaultBGName'],
            'charUrl'      => $this->GetUrlString(),
            'class'        => $this->classText,
            'classId'      => $this->class,
            'classUrl'     => sprintf("c=%s", urlencode($this->classText)),
            'faction'      => null,
            'factionId'    => $this->faction,
            'gender'       => null,
            'genderId'     => $this->gender,
            'guildName'    => ($this->guild_id > 0) ? $this->guild_name : null,
            'guildUrl'     => ($this->guild_id > 0) ? sprintf('r=%s&gn=%s', urlencode($this->currentRealmInfo['name']), urlencode($this->guild_name)) : null,
            'lastModified' => null,
            'level'        => $this->level,
            'name'         => $this->name,
            'points'       => $achievements->CalculateAchievementPoints(),
            'prefix'       => $this->character_title['prefix'],
            'race'         => $this->raceText,
            'raceId'       => $this->race,
            'realm'        => $this->currentRealmInfo['name'],
            'suffix'       => $this->character_title['suffix'],
            'titleId'      => $this->character_title['titleId'],
        );
        return $header;
    }
    
    /**
     * Returns array with additional energy bar data (mana for paladins, mages, warlocks & hunters, etc.)
     * @category Characters class
     * @example Characters::GetSecondBar()
     * @return array
     **/
    public function GetSecondBar() {
        if(!$this->class) {
            return false;
        }        
        $mana   = 'm';
        $rage   = 'r';
        $energy = 'e';
        $runic  = 'p';
        
        $switch = array(
            '1' => $rage,
            '2' => $mana,
            '3' => $mana,
            '4' => $energy,
            '5' => $mana,
            '6' => $runic,
            '7' => $mana,
            '8' => $mana,
            '9' => $mana,
            '11'=> $mana
        );
        switch($this->class) {
            case 2:
            case 3:
            case 5:
            case 7:
            case 8:
            case 9:
            case 11:
                $data['casting']    = 0;
                $data['notCasting'] = '22';
                $data['effective']  = $this->GetMaxMana();
                $data['type']       = $switch[$this->class];
                break;
            case 1:
                $data['casting']    = '-1';
                $data['effective']  = $this->GetMaxRage();
                $data['notCasting'] = '-1';
                $data['perFive']    = '-1';
                $data['type']       = $switch[$this->class];
                break;
            case 4:
                $data['casting']   = '-1';
                $data['effective'] = $this->GetMaxEnergy();
                $data['type']      = $switch[$this->class];
                break;
            case 6:
                $data['casting']   = '-1';
                $data['effective'] = $this->GetMaxEnergy();
                $data['type']      = $switch[$this->class];
                break;
        }
        return $data;
    }
    
    /*****************
    Function from MBA
    ******************/
    
    public function talentCounting($tab, $dualSpec = false, $spec = null) {
        if(!$this->guid) {
            return false;
        }
        $pt = 0;
        if($dualSpec == true) {
            $resSpell = $this->cDB->select("
            SELECT `spell`
                FROM `character_talent`
                    WHERE `guid`=? AND `spec`=?", $this->guid, $spec);
        }
        else {
            $resSpell = $this->cDB->select("
            SELECT `spell`
                FROM `character_spell` 
    				WHERE `guid`=? AND `disabled`=0", $this->guid);
        }
        if(!$resSpell) {
            return false;
        }
		foreach($resSpell as $getSpell) {
			$spells[] = $getSpell['spell'];
		}
		$resTal = $this->aDB->select("
		SELECT `Rank_1`, `Rank_2`, `Rank_3`, `Rank_4`, `Rank_5` 
			FROM `armory_talents` 
				WHERE `TalentTab` = ?", $tab);
		foreach($resTal as $row) {
			$ranks[] = $row;
		}
		foreach($ranks as $key => $val) {
			foreach($spells as $k => $v) {
				if(in_array($v, $val)) {
					switch(array_search($v, $val)) {
						case 'Rank_1': $pt += 1; break;
						case 'Rank_2': $pt += 2; break;
						case 'Rank_3': $pt += 3; break;
						case 'Rank_4': $pt += 4; break;
						case 'Rank_5': $pt += 5; break;
					}
				}
			}
		}
        return $pt;
	}
    
    /**
     * Returns item id from $slot (head, neck, shoulder, etc.). Requires $this->guid!
     * @category Character class
     * @example Characters::getCharacterEquip('head')
     * @return int
     **/
    public function getCharacterEquip($slot) {
        if(!$this->guid) {
            return false;
        }
        switch($slot) {
            case 'head':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_1_ENTRYID);
                break;
            case 'neck':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_2_ENTRYID);
				break;
			case 'shoulder':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_3_ENTRYID);
				break;
			case 'shirt':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_4_ENTRYID);
				break;
			case 'chest':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_5_ENTRYID);
				break;
			case 'wrist':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_6_ENTRYID);
				break;
			case 'legs':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_7_ENTRYID);
				break;
			case 'boots':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_8_ENTRYID);
				break;
			case 'belt':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_9_ENTRYID);
				break;
			case 'gloves':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_10_ENTRYID);
				break;
			case 'ring1':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_11_ENTRYID);
				break;
			case 'ring2':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_12_ENTRYID);
				break;
			case 'trinket1':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_13_ENTRYID);
				break;
            case 'trinket2':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_14_ENTRYID);
				break;
			case 'back':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_15_ENTRYID);
				break;
			case 'mainhand':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_16_ENTRYID);
				break;
			case 'offhand':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_17_ENTRYID);
			    break;
			case 'relic':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_18_ENTRYID);
				break;
			case 'tabard':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_19_ENTRYID);
				break;
			default:
				return 0;
				break;
        }
    }
    
    public function GetCharacterEquipBySlot($slotID) {
        if(!$this->guid) {
            return false;
        }
        return $this->cDB->selectCell("SELECT `item_template` FROM `character_inventory` WHERE `guid`=? AND `slot`=? LIMIT 1", $this->guid, $slotID);
    }
    
    /**
     * Returns enchantment id of item contained in $slot slot. If $guid not provided, function will use $this->guid.
     * @category Character class
     * @example Characters::getCharacterEnchant('head', 100)
     * @return int
     **/
    public function getCharacterEnchant($slot, $guid = null) {
        if($guid == null) {
            $guid = $this->guid;
        }
        switch($slot) {
            case 'head':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_1_ENCHANTMENT, $guid);
                break;
            case 'neck':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_2_ENCHANTMENT, $guid);
				break;
			case 'shoulder':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_3_ENCHANTMENT, $guid);
				break;
			case 'shirt':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_4_ENCHANTMENT, $guid);
				break;
			case 'chest':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_5_ENCHANTMENT, $guid);
				break;
			case 'wrist':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_6_ENCHANTMENT, $guid);
				break;
			case 'legs':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_7_ENCHANTMENT, $guid);
				break;
			case 'boots':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_8_ENCHANTMENT, $guid);
				break;
			case 'belt':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_9_ENCHANTMENT, $guid);
				break;
			case 'gloves':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_10_ENCHANTMENT, $guid);
				break;
			case 'ring1':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_11_ENCHANTMENT, $guid);
				break;
			case 'ring2':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_12_ENCHANTMENT, $guid);
				break;
			case 'trinket1':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_13_ENCHANTMENT, $guid);
				break;
            case 'trinket2':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_14_ENCHANTMENT, $guid);
				break;
			case 'back':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_15_ENCHANTMENT, $guid);
				break;
			case 'mainhand':
            case 'stave':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_16_ENCHANTMENT, $guid);
				break;
			case 'offhand':
            case 'gun':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_17_ENCHANTMENT, $guid);
			    break;
			case 'relic':
            case 'sigil':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_18_ENCHANTMENT, $guid);
				break;
			case 'tabard':
				return $this->GetDataField(PLAYER_VISIBLE_ITEM_19_ENCHANTMENT, $guid);
				break;
			default:
				return 0;
				break;
        }
    }
		
	/*****************
	Function from MBA
	******************/
	
    //get a tab from TalentTab
    public function getTabOrBuild($type, $tabnum) {
        if($type == "tab") {
			$field = $this->aDB->selectCell("
			SELECT `id`
				FROM `armory_talenttab`
					WHERE `refmask_chrclasses` = ? AND `tab_number` = ? LIMIT 1", pow(2,($this->class-1)), $tabnum);
		}
		else {
			$field = $this->aDB->selectCell("
			SELECT `name_" . $this->_locale . "`
				FROM `armory_talenttab`
					WHERE `refmask_chrclasses` = ? AND `tab_number` = ? LIMIT 1", pow(2,($this->class-1)), $tabnum);
		}
		return $field;
	}
    
    public function GetTalentTab($tab_count = -1) {
        if(!$this->class) {
            return false;
        }
        $talentTabId = array(
            1 => array(161,164,163), // Warior
            2 => array(382,383,381), // Paladin
            3 => array(361,363,362), // Hunter
            4 => array(182,181,183), // Rogue
            5 => array(201,202,203), // Priest
            6 => array(398,399,400), // Death Knight
            7 => array(261,263,262), // Shaman
            8 => array( 81, 41, 61), // Mage
            9 => array(302,303,301), // Warlock
            11=> array(283,281,282), // Druid
        );
        $tab_class = $talentTabId[$this->class];
        if($tab_count >= 0) {
            $values = array_values($tab_class);
            return $values[$tab_count];
        }
        return $tab_class;
    }
    
    /**
     * Calculates and returns array with character talent specs. !Required $this->guid and $this->class!
     * Depends on $this->currentRealmInfo['server_type'] (mangos or trinity realm)
     * @category Character class
     * @example Characters::CalculateCharacterTalents()
     * @return array
     **/
    public function CalculateCharacterTalents() {
        if(!$this->class || !$this->guid) {
            return false;
        }
        $talentTree = array();
        $tab_class = self::GetTalentTab();
        $character_talents = $this->cDB->select("SELECT * FROM `character_talent` WHERE `guid`=?", $this->guid);
        if(!$character_talents) {
            return false;
        }
        $class_talents = $this->aDB->select("SELECT * FROM `armory_talents` WHERE `TalentTab` IN (?a) ORDER BY `TalentTab`, `Row`, `Col`", $tab_class);
        $talent_build = array();
        $talent_build[0] = null;
        $talent_build[1] = null;
        $talent_points = array();
        foreach($tab_class as $tab_val) {
            $talent_points[0][$tab_val] = 0;
            $talent_points[1][$tab_val] = 0;
        }
        $num_tabs = array();
        $i = 0;
        foreach($tab_class as $tab_key => $tab_value) {
            $num_tabs[$tab_key] = $i;
            $i++;
        }
        foreach($class_talents as $class_talent) {
            $current_found = false;
            $last_spec = 0;
            foreach($character_talents as $char_talent) {
                switch($this->currentRealmInfo['type']) {
                    case 'mangos':
                        if($char_talent['talent_id'] == $class_talent['TalentID']) {
                            $talent_ranks = $char_talent['current_rank']+1;
                            $talent_build[$char_talent['spec']] .= $talent_ranks; // not 0-4, is 1-5
                            $current_found = true;
                            $talent_points[$char_talent['spec']][$class_talent['TalentTab']] += $talent_ranks;
                        }
                        $last_spec = $char_talent['spec'];
                        break;
                    case 'trinity':
                        for($k = 1; $k < 6; $k++) {
                            if($char_talent['spell'] == $class_talent['Rank_' . $k]) {
                                $talent_build[$char_talent['spec']] .= $k;
                                $current_found = true;
                                $talent_points[$char_talent['spec']][$class_talent['TalentTab']] += $k;
                            }
                        }
                        $last_spec = $char_talent['spec'];
                        break;
                }
            }
            if(!$current_found) {
                $talent_build[$last_spec] .= 0;
            }
        }
        $talent_data = array('points' => $talent_points);
        return $talent_data;
    }
    
    public function CalculateCharacterTalentBuild() {
        if(!$this->guid || !$this->class) {
            return false;
        }
        $build_tree = array(1 => null, 2 => null);
        $tab_class = self::GetTalentTab();
        $specs_talents = array();
        $character_talents = $this->cDB->select("SELECT * FROM `character_talent` WHERE `guid`=?", $this->guid);
        $talent_data = array(0 => null, 1 => null); // Talent build
        if(!$character_talents) {
            return false;
        }
        foreach($character_talents as $_tal) {
            if($this->currentRealmInfo['type'] == 'mangos') {
                $specs_talents[$_tal['spec']][$_tal['talent_id']] = $_tal['current_rank']+1;
            }
            elseif($this->currentRealmInfo['type'] == 'trinity') {
                $specs_talents[$_tal['spec']][$_tal['spell']] = true;
            }
        }
        if($this->currentRealmInfo['type'] == 'trinity') {
            for($i = 0; $i < 3; $i++) {
                $current_tab = $this->aDB->select("SELECT * FROM `armory_talents` WHERE `TalentTab`=? ORDER BY `TalentTab`, `Row`, `Col`", $tab_class[$i]);
                if(!$current_tab) {
                    continue;
                }
                foreach($current_tab as $tab) {
                    for($j = 0; $j < 2; $j++) {
                        if(isset($specs_talents[$j][$tab['Rank_5']])) {
                            $talent_data[$j] .= 5;
                        }
                        elseif(isset($specs_talents[$j][$tab['Rank_4']])) {
                            $talent_data[$j] .= 4;
                        }
                        elseif(isset($specs_talents[$j][$tab['Rank_3']])) {
                            $talent_data[$j] .= 3;
                        }
                        elseif(isset($specs_talents[$j][$tab['Rank_2']])) {
                            $talent_data[$j] .= 2;
                        }
                        elseif(isset($specs_talents[$j][$tab['Rank_1']])) {
                            $talent_data[$j] .= 1;
                        }
                        else {
                            $talent_data[$j] .= 0;
                        }
                    }
                }
            }
        }
        elseif($this->currentRealmInfo['type'] == 'mangos') {
            for($i=0;$i<3;$i++) {
                if(!isset($tab_class[$i])) {
                    continue;
                }
                switch($this->currentRealmInfo['type']) {
                    case 'mangos':
                        $current_tab = $this->aDB->select("SELECT `TalentID`, `TalentTab`, `Row`, `Col` FROM `armory_talents` WHERE `TalentTab`=? ORDER BY `TalentTab`, `Row`, `Col`", $tab_class[$i]);
                        if(!$current_tab) {
                            continue;
                        }
                        foreach($current_tab as $tab) {
                            for($j=0;$j<2;$j++) {
                                if(isset($specs_talents[$j][$tab['TalentID']])) {
                                    $talent_data[$j] .= $specs_talents[$j][$tab['TalentID']];
                                }
                                else {
                                    $talent_data[$j] .= 0;
                                }
                            }
                        }
                        break;
                    case 'trinity':
                        $current_tab = $this->aDB->select("SELECT * FROM `armory_talents` WHERE `TalentTab`=? ORDER BY `TalentTab`, `Row`, `Col`", $tab_class[$i]);
                        if(!$current_tab) {
                            continue;
                        }
                        foreach($current_tab as $tab) {
                            for($j=0;$j<2;$j++) {
                                //if(isset($specs_talents[$j]))
                            }
                        }
                        break;
                }
            }
        }
        return $talent_data;
    }
    
    /**
     * Returns array with glyph data for all specs
     * @category Character class
     * @example Characters::GetCharacterGlyphs()
     * @return array
     **/
    public function GetCharacterGlyphs($spec = -1) {
        if(!$this->guid) {
            return false;
        }
        switch($this->currentRealmInfo['type']) {
            case 'mangos':
                if($spec >= 0) {
                    $glyphs_data = $this->cDB->select("SELECT * FROM `character_glyphs` WHERE `guid`=? AND `spec`=? ORDER BY `slot`", $this->guid, $spec);
                }
                else {
                    $glyphs_data = $this->cDB->select("SELECT * FROM `character_glyphs` WHERE `guid`=? ORDER BY `spec`, `slot`", $this->guid);
                }
                break;
            case 'trinity':
                if($spec >= 0) {
                    $glyphs_data = $this->cDB->select("SELECT * FROM `character_glyphs` WHERE `guid`=? AND `spec`=?", $this->guid, $spec);
                }
                else {
                    $glyphs_data = $this->cDB->select("SELECT * FROM `character_glyphs` WHERE `guid`=? ORDER BY `spec`", $this->guid);
                }
                break;
        }
        if(!$glyphs_data) {
            return false;
        }
        $data = array(0 => array(), 1 => array());
        $i = 0;
        foreach($glyphs_data as $glyph) {
            switch($this->currentRealmInfo['type']) {
                case 'mangos':
                    if($this->_locale == 'ru_ru' || $this->_locale == 'en_gb') {
                        $current_glyph = $this->aDB->selectRow("SELECT `name_".$this->_locale."` AS `name`, `description_".$this->_locale."` AS `effect`, `type` FROM `armory_glyphproperties` WHERE `id`=?", $glyph['glyph']);
                    }
                    else {
                        $current_glyph = $this->aDB->selectRow("SELECT `name_en_gb` AS `name`, `description_en_gb` AS `effect`, `type` FROM `armory_glyphproperties` WHERE `id`=?", $glyph['glyph']);
                    }
                    $data[$glyph['spec']][$i] = array(
                        'effect' => str_replace('"', '&quot;', $current_glyph['effect']),
                        'id'     => $glyph['glyph'],
                        'name'   => str_replace('"', '&quot;', $current_glyph['name'])
                    );
                    if($current_glyph['type'] == 0) {
                        $data[$glyph['spec']][$i]['type'] = 'major';
                    }
                    else {
                        $data[$glyph['spec']][$i]['type'] = 'minor';
                    }
                    $i++;
                    break;
                case 'trinity':
                    for($j=1;$j<7;$j++) {
                        $current_glyph = $this->aDB->selectRow("SELECT `name_" . $this->_locale ."` AS `name`, `description_" . $this->_locale . "` AS `effect`, `type` FROM `armory_glyphproperties` WHERE `id`=?", $glyph['glyph' . $j]);
                        if(!$current_glyph) {
                            continue;
                        }
                        $data[$glyph['spec']][$i] = array(
                            'effect' => str_replace('"', '&quot;', $current_glyph['effect']),
                            'id'     => $glyph['glyph' . $j],
                            'name'   => str_replace('"', '&quot;', $current_glyph['name'])
                        );
                        if($current_glyph['type'] == 0) {
                            $data[$glyph['spec']][$i]['type'] = 'major';
                        }
                        else {
                            $data[$glyph['spec']][$i]['type'] = 'minor';
                        }
                        $i++;
                    }
                    break;
            }
        }
        return $data;
    }
    
    /**
     * Returns talent tree name for selected class
     * @category Character class
     * @example Characters::ReturnTalentTreeNames(2)
     * @return string
     **/
    public function ReturnTalentTreesNames($spec) {
		return $this->aDB->selectCell("SELECT `name_".$this->_locale."` FROM `armory_talent_icons` WHERE `class`=? AND `spec`=?", $this->class, $spec);
	}
    
    /**
     * Returns icon name for selected class & talent tree
     * @category Character class
     * @example Characters::ReturnTalentTreeIcon(2)
     * @todo Move this function to Utils class
     * @return string
     **/
    public function ReturnTalentTreeIcon($tree) {
        $icon = $this->aDB->selectCell("SELECT `icon` FROM `armory_talent_icons` WHERE `class`=? AND `spec`=? LIMIT 1", $this->class, $tree);
        if($icon) {
            return $icon;
        }
        return false;
    }
    
    /**
     * Returns character lifetime honorable kills. Requires $this->guid!
     * @category Character class
     * @example Characters::getCharacterHonorKills()
     * @return int
     **/
    public function getCharacterHonorKills() {
        return $this->GetDataField(PLAYER_FIELD_LIFETIME_HONORBALE_KILLS);
    }
    
    /**
     * Returns array with character's professions (name, icon & current skill value)
     * @category Character class
     * @example Characters::extractCharacterProfessions()
     * @return array
     **/
    public function extractCharacterProfessions() {
        $skills_professions = array(164, 165, 171, 182, 186, 197, 202, 333, 393, 755, 773);
        $professions = $this->cDB->select("SELECT * FROM `character_skills` WHERE `skill` IN (?a) AND `guid`=? LIMIT 2", $skills_professions, $this->guid);
        if(!$professions) {
            return false;
        }
        $p = array();
        $i = 0;
        foreach($professions as $prof) {
            $p[$i] = $this->aDB->selectRow("SELECT `id`, `name_".$this->_locale."` AS `name`, `icon` FROM `armory_professions` WHERE `id`=? LIMIT 1", $prof['skill']);
            $p[$i]['value'] = $prof['value'];
            $p[$i]['key'] = str_replace('-sm', '', (isset($p[$i]['icon'])) ? $p[$i]['icon'] : '' );
            $p[$i]['max'] = 450;
            unset($p[$i]['icon']);
            $i++;
        }
        return $p;
    }
    
    /**
     * Returns array with character reputation (faction name, description, value)
     * @category Character class
     * @example Characters::getCharacterReputation()
     * @todo Make parent sections
     * @return array
     **/
    public function GetCharacterReputation() {
        if(!$this->guid) {
            return false;
        }
        $repData = $this->cDB->select("SELECT `faction`, `standing`, `flags` FROM `character_reputation` WHERE `guid`=?", $this->guid); 
        if(!$repData) {
            return false;
        }
        $i = 0;
        foreach($repData as $faction) {
            if(!($faction['flags']&FACTION_FLAG_VISIBLE) || $faction['flags']&(FACTION_FLAG_HIDDEN|FACTION_FLAG_INVISIBLE_FORCED)) {
                continue;
            }
            $factionReputation[$i] = $this->aDB->selectRow("SELECT `id`, `name_".$this->_locale."` AS `name`, `key` FROM `armory_faction` WHERE `id`=?", $faction['faction']);
            if($faction['standing'] > 42999) {
                $factionReputation[$i]['reputation'] = 42999;
            }
            else {
                $factionReputation[$i]['reputation'] = $faction['standing'];
            }
            $i++;
        }
        return $factionReputation;
    }
    
    /********************************************/
    /*** Grab character info from `data` blob ***/
    /********************************************/
    
    /**
     * Returns value of $fieldNum field. !Requires $this->guid or $guid as second parameter!
     * @category Characters class
     * @exapmle Characters::GetDataField(1203)
     * @return int
     **/
    public function GetDataField($fieldNum, $guid = null) {
        if($guid == null) {
            $guid = $this->guid;
        }
        if(!$guid) {
            return false;
        }
        $dataField = $fieldNum+1;
        $qData = $this->cDB->selectCell("
        SELECT CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(`data`, ' ', " . $dataField . "), ' ', '-1') AS UNSIGNED)  
            FROM `armory_character_stats` 
				WHERE `guid`=?", $guid);
        return $qData;
    }
    
    // Health value
    public function GetMaxHealth() {
        return $this->cDB->selectCell("SELECT `health` FROM `characters` WHERE `guid`=? LIMIT 1", $this->guid);
    }
    
    // Mana value
    public function GetMaxMana() {
        return $this->cDB->selectCell("SELECT `power1` FROM `characters` WHERE `guid`=? LIMIT 1", $this->guid);
    }
    
    // Rage value
    public function GetMaxRage() {
        return $this->cDB->selectCell("SELECT `power2` FROM `characters` WHERE `guid`=? LIMIT 1", $this->guid);
    }
    
    // Energy (or Runic power for DK) value
    public function GetMaxEnergy() {
        return $this->cDB->selectCell("SELECT `power3` FROM `characters` WHERE `guid`=? LIMIT 1", $this->guid);
    }
    
    public function SetRating() {
        if($this->rating) {
            return $this->rating;
        }
        else {
            $this->rating = Utils::GetRating($this->level);
            return $this->rating;
        }
    }
    
    /**
     * Returns $stat_string stat for current player
     **/
    public function GetCharacterStat($stat_string, $type = false) {
        switch($stat_string) {
            case 'strength':
                return $this->GetCharacterStrength();
                break;
            case 'agility':
                return $this->GetCharacterAgility();
                break;
            case 'stamina':
                return $this->GetCharacterStamina();
                break;
            case 'intellect':
                return $this->GetCharacterIntellect();
                break;
            case 'spirit':
                return $this->GetCharacterSpirit();
                break;
            case 'armor':
                return $this->GetCharacterArmor();
                break;                
            case 'mainHandDamage':
                return $this->GetCharacterMainHandMeleeDamage();
                break;
            case 'offHandDamage':
                return $this->GetCharacterOffHandMeleeDamage();
                break;
            case 'mainHandSpeed':
                return $this->GetCharacterMainHandMeleeHaste();
                break;
            case 'offHandSpeed':
                return $this->GetCharacterOffHandMeleeHaste();
                break;
            case 'power':
                if(!$type) {
                    return $this->GetCharacterAttackPower();
                }
                elseif($type == 1) {
                    return $this->GetCharacterRangedAttackPower();
                }
                break;
            case 'hitRating':
                if(!$type) {
                    return $this->GetCharacterMeleeHitRating();
                }
                elseif($type == 1) {
                    return $this->GetCharacterRangedHitRating();
                }
                elseif($type == 2) {
                    return $this->GetCharacterSpellHitRating();
                }
                break;
            case 'critChance':
                if(!$type) {
                    return $this->GetCharacterMeleeCritChance();
                }
                elseif($type == 1) {
                    return $this->GetCharacterRangedCritChance();
                }
                elseif($type == 2) {
                    return $this->GetCharacterSpellCritChance();
                }
                break;
            case 'expertise':
                return $this->GetCharacterMainHandMeleeSkill();
                break;
            case 'damage':
                return $this->GetCharacterRangedDamage();
                break;
            case 'speed':
                return $this->GetCharacterRangedHaste();
                break;
            case 'weaponSkill':
                return $this->GetCharacterRangedWeaponSkill();
                break;
            case 'bonusDamage':
                return $this->GetCharacterSpellBonusDamage();
                break;
            case 'bonusHealing':
                return $this->GetCharacterSpellBonusHeal();
                break;
            case 'hasteRating':
                return $this->GetCharacterSpellHaste();
                break;
            case 'penetration':
                return $this->GetCharacterSpellPenetration();
                break;
            case 'manaRegen':
                return $this->GetCharacterSpellManaRegen();
                break;
            case 'defense':
                return $this->GetCharacterDefense();
                break;
            case 'dodge':
                return $this->GetCharacterDodge();
                break;
            case 'parry':
                return $this->GetCharacterParry();
                break;
            case 'block':
                return $this->GetCharacterBlock();
                break;
            case 'resilience':
                return $this->GetCharacterResilence();
                break;
            default:
                return false;
                break;
        }
    }
    
    /**
     * Returns array with character strenght data (for <baseStats>)
     **/
    public function GetCharacterStrength() {
        $tmp_stats = array();
        $tmp_stats['bonus_strenght'] = Utils::getFloatValue($this->GetDataField(UNIT_FIELD_POSSTAT0), 0);
        $tmp_stats['negative_strenght'] = Utils::getFloatValue($this->GetDataField(UNIT_FIELD_NEGSTAT0), 0);        
        $tmp_stats['effective'] = $this->GetDataField(UNIT_FIELD_STAT0);
        $tmp_stats['attack'] = Utils::GetAttackPowerForStat(STAT_STRENGTH, $tmp_stats['effective'], $this->class);
        $tmp_stats['base'] = $tmp_stats['effective']-$tmp_stats['bonus_strenght']-$tmp_stats['negative_strenght'];
        if($this->class == CLASS_WARRIOR || $this->class == CLASS_PALADIN || $this->class == CLASS_SHAMAN) {
            $tmp_stats['block'] = max(0, $tmp_stats['effective']*BLOCK_PER_STRENGTH - 10);
        }
        else {
            $tmp_stats['block'] = -1;
        }
        $player_stats = array(
            'attack'    => $tmp_stats['attack'],
            'base'      => $tmp_stats['base'],
            'block'     => $tmp_stats['block'],
            'effective' => $tmp_stats['effective']
        );
        
        unset($tmp_stats);
        return $player_stats;
    }
    
    public function GetCharacterAgility() {
        $tmp_stats    = array();
        $rating       = $this->SetRating();
        
        $tmp_stats['effective'] = $this->GetDataField(UNIT_FIELD_STAT1);
        $tmp_stats['bonus_agility'] = Utils::getFloatValue($this->GetDataField(UNIT_FIELD_POSSTAT1), 0);
        $tmp_stats['negative_agility'] = Utils::getFloatValue($this->GetDataField(UNIT_FIELD_NEGSTAT1), 0);
        $tmp_stats['base'] = $tmp_stats['effective']-$tmp_stats['bonus_agility']-$tmp_stats['negative_agility'];
        $tmp_stats['critHitPercent'] = floor(Utils::GetCritChanceFromAgility($rating, $this->class, $tmp_stats['effective']));
        $tmp_stats['attack'] = Utils::GetAttackPowerForStat(STAT_AGILITY, $tmp_stats['effective'], $this->class);
        $tmp_stats['armor'] = $tmp_stats['effective'] * ARMOR_PER_AGILITY;
        if($tmp_stats['attack'] == 0) {
            $tmp_stats['attack'] = '-1';
        }
        $player_stats = array(
            'armor'          => $tmp_stats['armor'],
            'attack'         => $tmp_stats['attack'],
            'base'           => $tmp_stats['base'],
            'critHitPercent' => $tmp_stats['critHitPercent'],
            'effective'      => $tmp_stats['effective']
        );
        
        unset($rating);
        unset($tmp_stats);
        return $player_stats;
    }
    
    public function GetCharacterStamina() {
        $tmp_stats = array();
        
        $tmp_stats['effective'] = $this->GetDataField(UNIT_FIELD_STAT2);
        $tmp_stats['bonus_stamina'] = Utils::getFloatValue($this->GetDataField(UNIT_FIELD_POSSTAT2), 0);
        $tmp_stats['negative_stamina'] = Utils::getFloatValue($this->GetDataField(UNIT_FIELD_NEGSTAT2), 0);
        $tmp_stats['base'] = $tmp_stats['effective']-$tmp_stats['bonus_stamina']-$tmp_stats['negative_stamina'];
        
        $tmp_stats['base_stamina'] = min(20, $tmp_stats['effective']);
        $tmp_stats['more_stamina'] = $tmp_stats['effective'] - $tmp_stats['base_stamina'];
        $tmp_stats['health'] = $tmp_stats['base_stamina'] + ($tmp_stats['more_stamina'] * HEALTH_PER_STAMINA);
        $tmp_stats['petBonus'] = Utils::ComputePetBonus(2, $tmp_stats['effective'], $this->class);
        if($tmp_stats['petBonus'] == 0) {
            $tmp_stats['petBonus'] = '-1';
        }
        $player_stats = array(
            'base'      => $tmp_stats['base'],
            'effective' => $tmp_stats['effective'],
            'health'    => $tmp_stats['health'],
            'petBonus'  => $tmp_stats['petBonus']
        );
        
        unset($tmp_stats);
        return $player_stats;
    }
    
    public function GetCharacterIntellect() {
        $tmp_stats = array();
        $rating    = $this->SetRating();
        
        $tmp_stats['effective'] =$this->GetDataField(UNIT_FIELD_STAT3);
        $tmp_stats['bonus_intellect'] = Utils::getFloatValue($this->GetDataField(UNIT_FIELD_POSSTAT3), 0);
        $tmp_stats['negative_intellect'] = Utils::getFloatValue($this->GetDataField(UNIT_FIELD_NEGSTAT3), 0);
        $tmp_stats['base'] = $tmp_stats['effective']-$tmp_stats['bonus_intellect']-$tmp_stats['negative_intellect'];
        if($this->class != CLASS_WARRIOR && $this->class != CLASS_ROGUE && $this->class != CLASS_DK) {
            $tmp_stats['base_intellect'] = min(20, $tmp_stats['effective']);
            $tmp_stats['more_intellect'] = $tmp_stats['effective']-$tmp_stats['base_intellect'];
            $tmp_stats['mana'] = $tmp_stats['base_intellect']+$tmp_stats['more_intellect']*MANA_PER_INTELLECT;
            $tmp_stats['critHitPercent'] = round(Utils::GetSpellCritChanceFromIntellect($rating, $this->class, $tmp_stats['effective']), 2);
        }
        else {
            $tmp_stats['base_intellect'] = '-1';
            $tmp_stats['more_intellect'] = '-1';
            $tmp_stats['mana'] = '-1';
            $tmp_stats['critHitPercent'] = '-1';
        }
        $tmp_stats['petBonus'] = Utils::ComputePetBonus(7, $tmp_stats['effective'], $this->class);
        if($tmp_stats['petBonus'] == 0) {
            $tmp_stats['petBonus'] = '-1';
        }
        $player_stats = array(
            'base' => $tmp_stats['base'],
            'critHitPercent' => $tmp_stats['critHitPercent'],
            'effective'      => $tmp_stats['effective'],
            'mana'           => $tmp_stats['mana'],
            'petBonus'       => $tmp_stats['petBonus']
        );
        
        unset($rating);
        unset($tmp_stats);
        return $player_stats;
    }
    
    public function GetCharacterSpirit() {
        $tmp_stats = array();
        $rating    = $this->SetRating();
        
        $tmp_stats['effective'] =$this->GetDataField(UNIT_FIELD_STAT4);
        $tmp_stats['bonus_spirit'] = Utils::getFloatValue($this->GetDataField(UNIT_FIELD_POSSTAT4), 0);
        $tmp_stats['negative_spirit'] = Utils::getFloatValue($this->GetDataField(UNIT_FIELD_NEGSTAT4), 0);
        $tmp_stats['base'] = $tmp_stats['effective']-$tmp_stats['bonus_spirit']-$tmp_stats['negative_spirit'];
        $baseRatio = array(0, 0.625, 0.2631, 0.2, 0.3571, 0.1923, 0.625, 0.1724, 0.1212, 0.1282, 1, 0.1389);
        //$tmp_stats['healthRegen'] = $tmp_stats['effective'] * Utils::GetHRCoefficient($rating, $this->class);
        $tmp_stats['base_spirit'] = $tmp_stats['effective'];
        if($tmp_stats['base_spirit'] > 50) {
            $tmp_stats['base_spirit'] = 50;
        }
        $tmp_stats['more_spirit'] = $tmp_stats['effective'] - $tmp_stats['base_spirit'];
        $tmp_stats['healthRegen'] = floor($tmp_stats['base_spirit'] * $baseRatio[$this->class] + $tmp_stats['more_spirit'] * Utils::GetHRCoefficient($rating, $this->class));
        
        if($this->class != CLASS_WARRIOR && $this->class != CLASS_ROGUE && $this->class != CLASS_DK) {
            $intellect_tmp = $this->GetCharacterIntellect();
            $tmp_stats['manaRegen'] = sqrt($intellect_tmp['effective']) * $tmp_stats['effective'] * Utils::GetMRCoefficient($rating, $this->class);
            $tmp_stats['manaRegen'] = floor($tmp_stats['manaRegen']*5);
        }
        else {
            $tmp_stats['manaRegen'] = '-1';
        }
        $player_stats = array(
            'base'        => $tmp_stats['base'],
            'effective'   => $tmp_stats['effective'],
            'healthRegen' => $tmp_stats['healthRegen'],
            'manaRegen'   => $tmp_stats['manaRegen']
        );
        
        unset($rating);
        unset($tmp_stats);
        return $player_stats;
    }
    
    public function GetCharacterArmor() {
        $tmp_stats = array();
        $levelModifier = 0;        
        
        $tmp_stats['effective'] = $this->GetDataField(UNIT_FIELD_RESISTANCES);
        $tmp_stats['bonus_armor'] = Utils::getFloatValue($this->GetDataField(UNIT_FIELD_RESISTANCEBUFFMODSPOSITIVE), 0);
        $tmp_stats['negative_armor'] = Utils::getFloatValue($this->GetDataField(UNIT_FIELD_RESISTANCEBUFFMODSNEGATIVE), 0);
        $tmp_stats['base'] = $tmp_stats['effective']-$tmp_stats['bonus_armor']-$tmp_stats['negative_armor'];
        if($this->level > 59 ) {
            $levelModifier = $this->level + (4.5 * ($this->level-59));
        }
        $tmp_stats['reductionPercent'] = 0.1*$tmp_stats['effective']/(8.5*$levelModifier + 40);
    	$tmp_stats['reductionPercent'] = round($tmp_stats['reductionPercent']/(1+$tmp_stats['reductionPercent'])*100, 2);
    	if($tmp_stats['reductionPercent'] > 75) {
    	   $tmp_stats['reductionPercent'] = 75;
    	}
    	if($tmp_stats['reductionPercent'] <  0) {
    	   $tmp_stats['reductionPercent'] = 0;
    	}
        $tmp_stats['petBonus'] = Utils::ComputePetBonus(4, $tmp_stats['effective'], $this->class);
        if($tmp_stats['petBonus'] == 0) {
            $tmp_stats['petBonus'] = '-1';
        }
        $player_stats = array(
            'base'      => $tmp_stats['base'],
            'effective' => $tmp_stats['effective'],
            'percent'   => $tmp_stats['reductionPercent'],
            'petBonus'  => $tmp_stats['petBonus']
        );
        
        unset($tmp_stats);
        return $player_stats;
    }
    
    public function GetCharacterMainHandMeleeSkill() {
        $tmp_stats = array();
        $rating    = $this->SetRating();
        $character_data = $this->cDB->selectCell("SELECT `data` FROM `armory_character_stats` WHERE `guid`=?", $this->guid);
        
        $tmp_stats['melee_skill_id'] = Utils::getSkillFromItemID($this->getCharacterEquip('mainhand'));
        $tmp_stats['melee_skill'] = Utils::getSkill($tmp_stats['melee_skill_id'], $character_data);
        $tmp_stats['rating'] = $this->GetDataField(PLAYER_FIELD_COMBAT_RATING_1+20);
        $tmp_stats['additional'] = $tmp_stats['rating']/Utils::GetRatingCoefficient($rating, 2);
        $buff = $tmp_stats['melee_skill'][4]+$tmp_stats['melee_skill'][5]+intval($tmp_stats['additional']);
        $tmp_stats['value'] = $tmp_stats['melee_skill'][2]+$buff;
        
        $player_stats = array(
            'value'      => $tmp_stats['value'],
            'rating'     => $tmp_stats['rating'],
            'additional' => $tmp_stats['additional'],
            'percent'    => '0.00'
        );
        
        unset($tmp_stats);
        unset($rating);
        return $player_stats;
    }
    
    public function GetCharacterOffHandMeleeSkill() {
        return array('value' => '', 'rating' => '');
    }
    
    public function GetCharacterMainHandMeleeDamage() {
        $tmp_stats = array();
        
        $tmp_stats['min'] = Utils::getFloatValue($this->GetDataField(UNIT_FIELD_MINDAMAGE), 0);
        $tmp_stats['max'] = Utils::getFloatValue($this->GetDataField(UNIT_FIELD_MAXDAMAGE), 0);
        $tmp_stats['speed'] = round(Utils::getFloatValue($this->GetDataField(UNIT_FIELD_BASEATTACKTIME), 2)/1000, 2);
        $tmp_stats['melee_dmg'] = ($tmp_stats['min'] + $tmp_stats['max']) * 0.5;
        $tmp_stats['dps'] = 0;//round((max($tmp_stats['melee_dmg'], 1) / $tmp_stats['speed']), 1);
        if($tmp_stats['speed'] < 0.1) {
            $tmp_stats['speed'] = '0.1';
        }
        $player_stats = array(
            'dps'     => $tmp_stats['dps'],
            'max'     => $tmp_stats['max'],
            'min'     => $tmp_stats['min'],
            'percent' => 0,
            'speed'   => $tmp_stats['speed']
        );
        
        unset($tmp_stats);
        return $player_stats;
    }
    
    public function GetCharacterOffHandMeleeDamage() {
        return array('speed' => 0, 'min' => 0, 'max'  => 0, 'percent' => 0, 'dps' => '0.0');
    }
    
    public function GetCharacterMainHandMeleeHaste() {
        $tmp_stats = array();
        $rating    = $this->SetRating();
        
        $tmp_stats['value'] = round(Utils::getFloatValue($this->GetDataField(UNIT_FIELD_BASEATTACKTIME), 2)/1000, 2);
        $tmp_stats['hasteRating'] = $this->GetDataField(PLAYER_FIELD_COMBAT_RATING_1+17);
        $tmp_stats['hastePercent'] = round($tmp_stats['hasteRating'] / Utils::GetRatingCoefficient($rating, 19), 2);
        
        unset($rating);
        return $tmp_stats;
    }
    
    public function GetCharacterOffHandMeleeHaste() {
        return array('hastePercent' => 0, 'hasteRating' => 0, 'value' => 0);
    }
    
    public function GetCharacterAttackPower() {
        $tmp_stats = array();
        
        $tmp_stats['multipler_melee_ap'] = Utils::getFloatValue($this->GetDataField(UNIT_FIELD_ATTACK_POWER_MULTIPLIER), 8);
        if($tmp_stats['multipler_melee_ap'] < 0) {
            $tmp_stats['multipler_melee_ap'] = 0;
        }
        else {
            $tmp_stats['multipler_melee_ap']+=1;
        }
        $tmp_stats['base'] = $this->GetDataField(UNIT_FIELD_ATTACK_POWER) * $tmp_stats['multipler_melee_ap'];
        $tmp_stats['bonus_melee_ap'] = $this->GetDataField(UNIT_FIELD_ATTACK_POWER_MODS) * $tmp_stats['multipler_melee_ap'];
        $tmp_stats['effective'] = $tmp_stats['base'] + $tmp_stats['bonus_melee_ap'];
        $tmp_stats['increasedDps'] = floor(max($tmp_stats['effective'], 0)/14);
        
        $player_stats = array(
            'base'         => $tmp_stats['base'],
            'effective'    => $tmp_stats['effective'],
            'increasedDps' => $tmp_stats['increasedDps']
        );
        
        unset($tmp_stats);
        return $player_stats;
    }
    
    public function GetCharacterMeleeHitRating() {
        $player_stats = array();
        $rating       = $this->SetRating();

        $player_stats['value'] = $this->GetDataField(PLAYER_FIELD_COMBAT_RATING_1+5);
        $player_stats['increasedHitPercent'] = floor($player_stats['value']/Utils::GetRatingCoefficient($rating, 6));
        $player_stats['armorPenetration'] = $this->GetDataField(PLAYER_FIELD_MOD_TARGET_PHYSICAL_RESISTANCE);
        $player_stats['reducedArmorPercent'] = '0.00';
        
        unset($rating);
        return $player_stats;
    }
    
    public function GetCharacterMeleeCritChance() {
        $rating = $this->SetRating();
        $player_stats = array();
        
        $player_stats['percent'] = Utils::getFloatValue($this->GetDataField(PLAYER_CRIT_PERCENTAGE), 2);
        $player_stats['rating'] = $this->GetDataField(PLAYER_FIELD_COMBAT_RATING_1+8);
        $player_stats['plusPercent'] = floor($player_stats['rating']/Utils::GetRatingCoefficient($rating, 9));
        
        unset($rating);
        return $player_stats;
    }
    
    public function GetCharacterRangedWeaponSkill() {
        return array('value' => '-1', 'rating' => '-1');
    }
    
    public function GetCharacterRangedDamage() {
        $tmp_stats     = array();
        $rangedSkillID = Mangos::getSkillFromItemID($this->GetDataField(PLAYER_VISIBLE_ITEM_18_ENTRYID));
        
        if($rangedSkillID == SKILL_UNARMED) {
            $tmp_stats['min'] = 0;
            $tmp_stats['max'] = 0;
            $tmp_stats['speed'] = 0;
            $tmp_stats['dps'] = 0;
        }
        else {
            $tmp_stats['min'] =  Utils::getFloatValue($this->GetDataField(UNIT_FIELD_MINRANGEDDAMAGE), 0);
            $tmp_stats['max'] =  Utils::getFloatValue($this->GetDataField(UNIT_FIELD_MAXRANGEDDAMAGE), 0);
            $tmp_stats['speed'] = round( Utils::getFloatValue($this->GetDataField(UNIT_FIELD_RANGEDATTACKTIME), 2)/1000, 2);
            $tmp_stats['ranged_dps'] = ($tmp_stats['min'] + $tmp_stats['max']) * 0.5;
            if($tmp_stats['speed'] < 0.1) {
                $tmp_stats['speed'] = 0.1;
            }
            $tmp_stats['dps'] = round((max($tmp_stats['ranged_dps'], 1) / $tmp_stats['speed']));
        }
        
        $player_stats = array(
            'speed'   => $tmp_stats['speed'],
            'min'     => $tmp_stats['min'],
            'max'     => $tmp_stats['max'],
            'dps'     => $tmp_stats['dps'],
            'percent' => '0.00'
        );
        
        unset($tmp_stats);
        unset($rangedSkillID);
        return $player_stats;
    }
    
    public function GetCharacterRangedHaste() {
        $player_stats  = array();
        $rating        = $this->SetRating();
        $rangedSkillID = Mangos::getSkillFromItemID($this->GetDataField(PLAYER_VISIBLE_ITEM_18_ENTRYID));
        
        if($rangedSkillID == SKILL_UNARMED) {
            $player_stats['value'] = '0';
            $player_stats['hasteRating'] = '0';
            $player_stats['hastePercent'] = '0';
        }
        else {
            $player_stats['value'] = round(Utils::getFloatValue($this->GetDataField(UNIT_FIELD_RANGEDATTACKTIME),2)/1000, 2);
            $player_stats['hasteRating'] = round($this->GetDataField(PLAYER_FIELD_COMBAT_RATING_1+18));
            $player_stats['hastePercent'] = round($player_stats['hasteRating']/ Utils::GetRatingCoefficient($rating, 19), 2);
        }
        
        unset($rating);
        unset($rangedSkillID);
        return $player_stats;
    }
    
    public function GetCharacterRangedAttackPower() {
        $player_stats = array();
        
        $multipler =  Utils::getFloatValue($this->GetDataField(UNIT_FIELD_RANGED_ATTACK_POWER_MULTIPLIER), 8);        
        if($multipler < 0) {
            $multipler = 0;
        }
        else {
            $multipler+=1;
        }
        $effectiveStat = $this->GetDataField(UNIT_FIELD_RANGED_ATTACK_POWER)*$multipler;
        $buff = $this->GetDataField(UNIT_FIELD_RANGED_ATTACK_POWER_MODS)*$multipler;
        $multiple =  Utils::getFloatValue($this->GetDataField(UNIT_FIELD_RANGED_ATTACK_POWER_MULTIPLIER),2);
        $posBuff = 0;
        $negBuff = 0;
        if($buff > 0) {
            $posBuff = $buff;
        }
        elseif($buff < 0) {
            $negBuff = $buff;
        }
        $stat = $effectiveStat+$buff;
        $player_stats['base'] = floor($effectiveStat);
        $player_stats['effective'] = floor($stat);
        $player_stats['increasedDps'] = floor(max($stat, 0)/14);
        $player_stats['petAttack'] = floor(Utils::ComputePetBonus(0, $stat, $this->class));
        $player_stats['petSpell'] = floor(Utils::ComputePetBonus(1, $stat, $this->class));
        
        return $player_stats;
    }
    
    public function GetCharacterRangedHitRating() {
        $player_stats = array();
        $rating       = $this->SetRating();
        
        $player_stats['value'] = $this->GetDataField(PLAYER_FIELD_COMBAT_RATING_1+6);
        $player_stats['increasedHitPercent'] = floor($player_stats['value']/ Utils::GetRatingCoefficient($rating, 7));
        $player_stats['reducedArmorPercent'] = $this->GetDataField(PLAYER_FIELD_MOD_TARGET_PHYSICAL_RESISTANCE);
        $player_stats['penetration'] = '';
        
        unset($rating);
        return $player_stats;
    }
    
    public function GetCharacterRangedCritChance() {
        $player_stats = array();
        $rating       = $this->SetRating();        
        
        $player_stats['percent'] =  Utils::getFloatValue($this->GetDataField(PLAYER_RANGED_CRIT_PERCENTAGE), 2);
        $player_stats['rating'] = $this->GetDataField(PLAYER_FIELD_COMBAT_RATING_1+9);
        $player_stats['plusPercent'] = floor($player_stats['rating']/ Utils::GetRatingCoefficient($rating, 10));
        
        unset($rating);
        return $player_stats;
    }
    
    public function GetCharacterSpellBonusDamage() {
        $tmp_stats  = array();
        $holySchool = 1;
        $minModifier = Utils::GetSpellBonusDamage($holySchool, $this->guid);
        
        for ($i=1;$i<7;$i++) {
            $bonusDamage[$i] = Utils::GetSpellBonusDamage($i, $this->guid);
            $minModifier = min($minModifier, $bonusDamage);
        }
        $tmp_stats['arcane'] = $bonusDamage[6];
        $tmp_stats['fire']   = $bonusDamage[2];
        $tmp_stats['frost']  = $bonusDamage[4];
        $tmp_stats['holy']   = $bonusDamage[2];
        $tmp_stats['nature'] = $bonusDamage[3];
        $tmp_stats['shadow'] = $bonusDamage[5];
        
        $tmp_stats['attack'] = '-1';
        $tmp_stats['damage'] = '-1';
        if($this->class == 3 || $this->class == 9) {
            $shadow = Utils::GetSpellBonusDamage(5, $this->guid);
            $fire   = Utils::GetSpellBonusDamage(2, $this->guid);
            $tmp_stats['attack'] = Utils::ComputePetBonus(6, max($shadow, $fire), $this->class);
            $tmp_stats['damage'] = Utils::ComputePetBonus(5, max($shadow, $fire), $this->class);
        }
        $tmp_stats['fromType'] = '';
        
        return $tmp_stats;
    }
    
    public function GetCharacterSpellCritChance() {
        $player_stats = array();
        $spellCrit    = array();
        $rating       = $this->SetRating();
        
        $player_stats['rating'] = $this->GetDataField(PLAYER_FIELD_COMBAT_RATING_1+10);
        $player_stats['spell_crit_pct'] = $player_stats['rating']/ Utils::GetRatingCoefficient($rating, 11);
        $minCrit = $this->GetDataField(PLAYER_SPELL_CRIT_PERCENTAGE1+1);
        for($i=1;$i<7;$i++) {
            $scfield = PLAYER_SPELL_CRIT_PERCENTAGE1+$i;
            $s_crit_value = $this->GetDataField($scfield);
            $spellCrit[$i] =  Utils::getFloatValue($s_crit_value, 2);
            $minCrit = min($minCrit, $spellCrit[$i]);
        }
        $player_stats['arcane'] = $spellCrit[5];
        $player_stats['fire']   = $spellCrit[2];
        $player_stats['frost']  = $spellCrit[4];
        $player_stats['holy']   = $spellCrit[2];
        $player_stats['nature'] = $spellCrit[3];        
        $player_stats['shadow'] = $spellCrit[6];
        
        unset($rating);
        unset($spellCrit);
        unset($player_stats['spell_crit_pct']);
        return $player_stats;
    }
    
    public function GetCharacterSpellHitRating() {
        $player_stats = array();
        $rating       = $this->SetRating();
        
        $player_stats['value'] = $this->GetDataField(PLAYER_FIELD_COMBAT_RATING_1+7);
        $player_stats['increasedHitPercent'] = floor($player_stats['value']/ Utils::GetRatingCoefficient($rating, 8));
        $player_stats['penetration'] = $this->GetDataField(PLAYER_FIELD_MOD_TARGET_RESISTANCE);
        $player_stats['reducedResist'] = '0';

        unset($rating);
        return $player_stats;
    }
    
    public function GetCharacterSpellBonusHeal() {
        return array('value' => $this->GetDataField(PLAYER_FIELD_MOD_HEALING_DONE_POS));
    }
    
    public function GetCharacterSpellHaste() {
        $player_stats = array();
        $rating       = $this->SetRating();
        $player_stats['hasteRating'] = $this->GetDataField(PLAYER_FIELD_COMBAT_RATING_1+19);
        $player_stats['hastePercent'] = round($player_stats['hasteRating']/ Utils::GetRatingCoefficient($rating, 20), 2);
        
        unset($rating);
        return $player_stats;
    }
    
    public function GetCharacterSpellPenetration() {
        return array('value' => 0);
    }
    
    public function GetCharacterSpellManaRegen() {
        $player_stats = array();
        
        $player_stats['notCasting'] = $this->GetDataField(UNIT_FIELD_POWER_REGEN_FLAT_MODIFIER);
        $player_stats['casting'] = $this->GetDataField(UNIT_FIELD_POWER_REGEN_INTERRUPTED_FLAT_MODIFIER);
        $player_stats['notCasting'] =  floor(Utils::getFloatValue($player_stats['notCasting'],2)*5);
        $player_stats['casting'] =  round(Utils::getFloatValue($player_stats['casting'],2)*5, 2);
        
        return $player_stats;
    }
    
    public function GetCharacterDefense() {
        $tmp_stats = array();
        $rating    = $this->SetRating();
        $gskill = $this->getCharacterSkill(SKILL_DEFENCE);
        
        $tmp_stats['defense_rating_skill'] = $gskill['value'];
        $tmp_stats['value'] = $this->GetDataField(PLAYER_FIELD_COMBAT_RATING_1+1);
        $tmp_stats['plusDefense'] = $tmp_stats['value']/Utils::GetRatingCoefficient($rating, 2);
        $buff = intval($tmp_stats['plusDefense']);
        $tmp_stats['rating'] = $tmp_stats['plusDefense']+$buff;
        $tmp_stats['increasePercent'] = DODGE_PARRY_BLOCK_PERCENT_PER_DEFENSE * ($tmp_stats['rating'] - $this->level*5);
        $tmp_stats['increasePercent'] = max($tmp_stats['increasePercent'], 0);
        $tmp_stats['decreasePercent'] = $tmp_stats['increasePercent'];
        
        unset($rating);
        unset($gskill);
        unset($tmp_stats['defense_rating_skill']);
        return $tmp_stats;
    }
    
    public function GetCharacterDodge() {
        $tmp_stats = array();
        $rating    = $this->SetRating();
        
        $tmp_stats['percent'] = Utils::getFloatValue($this->GetDataField(PLAYER_DODGE_PERCENTAGE), 2);
        $tmp_stats['rating'] = $this->GetDataField(PLAYER_FIELD_COMBAT_RATING_1+2);
        $tmp_stats['increasePercent'] = floor($tmp_stats['rating']/Utils::GetRatingCoefficient($rating, 3));
        
        unset($rating);
        return $tmp_stats;
    }
    
    public function GetCharacterParry() {
        $tmp_stats = array();
        $rating    = $this->SetRating();
        
        $tmp_stats['percent'] = Utils::getFloatValue($this->GetDataField(PLAYER_PARRY_PERCENTAGE), 2);
        $tmp_stats['rating'] = $this->GetDataField(PLAYER_FIELD_COMBAT_RATING_1+3);
        $tmp_stats['increasePercent'] = floor($tmp_stats['rating']/Utils::GetRatingCoefficient($rating, 4));
        
        unset($rating);
        return $tmp_stats;
    }
    
    public function GetCharacterBlock() {
        $tmp_stats = array();
                
        $blockvalue = $this->GetDataField(PLAYER_BLOCK_PERCENTAGE);
        $tmp_stats['percent'] =  Utils::getFloatValue($blockvalue,2);
        $tmp_stats['increasePercent'] = $this->GetDataField(PLAYER_FIELD_COMBAT_RATING_1+4);
        $tmp_stats['rating'] = $this->GetDataField(PLAYER_SHIELD_BLOCK);
        
        return $tmp_stats;
    }
    
    public function GetCharacterResilence() {
        $tmp_stats = array();
        $rating    = $this->SetRating();
        
        $tmp_stats['melee_resilence'] = $this->GetDataField(PLAYER_FIELD_CRIT_TAKEN_MELEE_RATING);
        $tmp_stats['ranged_resilence'] = $this->GetDataField(PLAYER_FIELD_CRIT_TAKEN_RANGED_RATING);
        $tmp_stats['spell_resilence'] = $this->GetDataField(PLAYER_FIELD_CRIT_TAKEN_SPELL_RATING);        
        $tmp_stats['value'] = min($tmp_stats['melee_resilence'], $tmp_stats['ranged_resilence'], $tmp_stats['spell_resilence']);
        $tmp_stats['damagePercent'] = $tmp_stats['melee_resilence']/Utils::GetRatingCoefficient($rating, 15);
        $tmp_stats['ranged_resilence_pct'] = $tmp_stats['ranged_resilence']/Utils::GetRatingCoefficient($rating, 16);
        $tmp_stats['hitPercent'] = $tmp_stats['spell_resilence']/Utils::GetRatingCoefficient($rating, 17);
        
        $player_stats = array(
            'value'         => $tmp_stats['value'],
            'hitPercent'    => $tmp_stats['hitPercent'],
            'damagePercent' => $tmp_stats['damagePercent']
        );
        unset($rating);
        unset($tmp_stats);
        return $player_stats;
    }
    
    /**
     * Returns skill info for $skill. If $guid not provided, function will use $this->guid. Not used now.
     * @category Character class
     * @example Characters::getCharacterSkill()
     * @return array
     **/
    public function getCharacterSkill($skill, $guid=false) {
        if($guid) {
            $this->guid = $guid;
        }
        if(!$this->guid) {
            return false;
        }
        $skillInfo = $this->cDB->selectRow("SELECT * FROM `character_skill` WHERE `guid`=? AND `skill`=?", $this->guid, $skill);
        return $skillInfo;
    }
    
    /**
     * Returns data for 2x2, 3x3 and 5x5 character arena teams (if exists). If $check == true, function will return boolean type. Used by character-*.php to check show or not 'Arena' button
     * @category Character class
     * @example Characters::getCharacterArenaTeamInfo(false)
     * @return bool/array
     **/
    public function getCharacterArenaTeamInfo($check = false) {
        if(!$this->guid) {
            return false;
        }
        $arenaTeamInfo = array();
        $tmp_info = $this->cDB->select(
        "SELECT 
        `arena_team_member`.`arenateamid`,
        `arena_team_member`.`guid`,
        `arena_team_member`.`personal_rating`,
        `arena_team`.`name`,
        `arena_team`.`type`,
        `arena_team_stats`.`rating`,
        `arena_team_stats`.`rank`
        FROM `arena_team_member` AS `arena_team_member`
        LEFT JOIN `arena_team_stats` AS `arena_team_stats` ON `arena_team_stats`.`arenateamid`=`arena_team_member`.`arenateamid`
        LEFT JOIN `arena_team` AS `arena_team` ON `arena_team`.`arenateamid`=`arena_team_member`.`arenateamid`
        WHERE `arena_team_member`.`guid`=?", $this->guid);
        if(!$tmp_info) {
            return false;
        }
        if($check == true && $tmp_info) {
            return true;
        }
        for($i=0;$i<3;$i++) {
            if($tmp_info[$i]['type'] == '2') {
                $arenaTeamInfo['2x2'] = array(
                    'name' => $tmp_info[$i]['name'],
                    'rank' => $tmp_info[$i]['rank'],
                    'rating' => $tmp_info[$i]['rating'],
                    'personalrating' => $tmp_info[$i]['personal_rating']
                );
            }
            elseif($tmp_info[$i]['type'] == '3') {
                $arenaTeamInfo['3x3'] = array(
                    'name' => $tmp_info[$i]['name'],
                    'rank' => $tmp_info[$i]['rank'],
                    'rating' => $tmp_info[$i]['rating'],
                    'personalrating' => $tmp_info[$i]['personal_rating']
                );
            }
            elseif($tmp_info[$i]['type'] == '5') {
                $arenaTeamInfo['5x5'] = array(
                    'name' => $tmp_info[$i]['name'],
                    'rank' => $tmp_info[$i]['rank'],
                    'rating' => $tmp_info[$i]['rating'],
                    'personalrating' => $tmp_info[$i]['personal_rating']
                );
            }
            return $arenaTeamInfo;
        }
        return false;
    }
    
    /**
     * Returns info about last character activity. Requires MaNGOS core patch (tools/character_feed)!
     * $full used only in character-feed.php
     * @category Characters class
     * @example Characters::GetCharacterFeed()
     * @return array
     **/
    public function GetCharacterFeed($full = false) {
        if(!$this->guid) {
            return false;
        }
        if($full) {
            $data = $this->cDB->select("SELECT * FROM `character_feed_log` WHERE `guid`=? ORDER BY `date` DESC LIMIT 50", $this->guid);
        }
        else {
            $data = $this->cDB->select("SELECT * FROM `character_feed_log` WHERE `guid`=? ORDER BY `date` DESC LIMIT 10", $this->guid);
        }
        if(!$data) {
            return false;
        }
        $feed_data = array();
        $i = 0;
        // Strings
        $feed_strings = $this->aDB->select("SELECT `id`, `string_".$this->_locale."` AS `string` FROM `armory_string` WHERE `id` IN (13, 14, 15, 16, 17, 18)");
        if(!$feed_strings) {
            return false;
        }
        $_strings = array();
        foreach($feed_strings as $str) {
            $_strings[$str['id']] = $str['string'];
        }
        foreach($data as $event) {
            $event_date = strtotime($event['date']);
            if(date('d.m.Y') == date('d.m.Y', $event_date)) {
                $sort = 'today';
            }
            elseif(date('d.m.Y', $event_date) == date('d.m.Y', strtotime('yesterday'))) {
                $sort = 'yesterday';
            }
            else {
                $sort = 'earlier';
            }
            switch($event['type']) {
                case TYPE_ACHIEVEMENT_FEED:
                    $send_data = array('achievement' => $event['data'], 'date' => $event_date);
                    $achievement_info = Achievements::GetAchievementInfo($send_data);
                    if(!isset($achievement_info['title']) || !$achievement_info['title'] || empty($achievement_info['title'])) {
                        continue;
                    }
                    if(!isset($achievement_info['points'])) {
                        $achievement_info['points'] = 0;
                    }
                    $feed_data[$i]['event'] = array(
                        'type'   => 'achievement',
                        'date'   => date('d.m.Y', $event_date),
                        'time'   => date('H:i:s', $event_date),
                        'id'     => $event['data'],
                        'points' => $achievement_info['points'],
                        'sort'   => $sort
                    );
                    $achievement_info['desc'] = str_replace("'", "\'", $achievement_info['desc']);
                    $achievement_info['title'] = str_replace("'", "\'", $achievement_info['title']);
                    $tooltip = sprintf('&lt;div class=\&quot;myTable\&quot;\&gt;&lt;img src=\&quot;wow-icons/_images/51x51/%s.jpg\&quot; align=\&quot;left\&quot; class=\&quot;ach_tooltip\&quot; /\&gt;&lt;strong style=\&quot;color: #fff;\&quot;\&gt;%s (%d)&lt;/strong\&gt;&lt;br /\&gt;%s', $achievement_info['icon'], $achievement_info['title'], $achievement_info['points'], $achievement_info['desc']);
                    if($achievement_info['categoryId'] == 81) {
                        // Feats of strenght
                        $feed_data[$i]['title'] = sprintf('%s [%s].', $_strings[14], $achievement_info['title']);
                        $feed_data[$i]['desc'] = sprintf('%s [<a class="achievement staticTip" href="character-achievements.xml?r=%s&amp;cn=%s" onMouseOver="setTipText(\'%s\')">%s</a>]', $_strings[14], urlencode($this->currentRealmInfo['name']), urlencode($this->name), $tooltip, $achievement_info['title']);
                    }
                    else {
                        $points_string = sprintf($_strings[18], $achievement_info['points']);
                        $feed_data[$i]['title'] = sprintf('%s [%s].', $_strings[13], $achievement_info['title']);
                        $feed_data[$i]['desc'] = sprintf('%s [<a class="achievement staticTip" href="character-achievements.xml?r=%s&amp;cn=%s" onMouseOver="setTipText(\'%s\')">%s</a>] %s.', $_strings[13], urlencode($this->currentRealmInfo['name']), urlencode($this->name), $tooltip, $achievement_info['title'], $points_string);
                    }
                    $feed_data[$i]['tooltip'] = $tooltip;
                    break;
                case TYPE_ITEM_FEED:
                    $item = $this->wDB->selectRow("SELECT `displayid`, `InventoryType`, `name`, `Quality` FROM `item_template` WHERE `entry`=? LIMIT 1", $event['data']);
                    if(!$item) {
                        continue;
                    }
                    $item_icon = $this->aDB->selectCell("SELECT `icon` FROM `armory_icons` WHERE `displayid`=?", $item['displayid']);
                    // Is item equipped?
                    $invenory_slots = array(
                        1 => 'head',
                        2 => 'neck',
                        3 => 'shoulder',
                        4 => 'shirt',
                        5 => 'chest',
                        6 => 'belt',
                        7 => 'legs',
                        8 => 'boots',
                        9 => 'wrist',
                        10 => 'gloves',
                        11 => 'ring',
                        12 => 'trinket',
                        13 => 'mainhand',
                        14 => 'offhand',
                        15 => 'relic',
                        16 => 'back',
                        17 => 'mainhand',
                        18 => 'bag',
                        19 => 'tabard',
                        20 => 'chest',
                        21 => 'mainhand',
                        22 => 'offhand',
                        23 => 'relic',
                        26 => 'relic',
                        28 => 'relic',
                    );
                    $item_slot = -1;
                    if(isset($invenory_slots[$item['InventoryType']])) {
                        if($item['InventoryType'] == 11) {
                            $rings = array('ring1', 'ring2');
                            foreach($rings as $r_slot) {
                                $tmp_id = self::getCharacterEquip($r_slot);
                                if($tmp_id == $event['data']) {
                                    $item_slot = $item['InventoryType'];
                                }
                            }
                        }
                        elseif($item['InventoryType'] == 12) {
                            $trinkets = array('trinket1', 'trinket2');
                            foreach($trinkets as $t_slot) {
                                $tmp_id = self::getCharacterEquip($t_slot);
                                if($tmp_id == $event['data']) {
                                    $item_slot = $item['InventoryType'];
                                }
                            }
                        }
                        else {
                            $item_id = self::getCharacterEquip($invenory_slots[$item['InventoryType']]);
                            if($item_id == $event['data']) {
                                $item_slot = $item['InventoryType'];
                            }
                            else {
                                $item_slot = -1;
                            }
                        }
                    }
                    $feed_data[$i]['event'] = array(
                        'type' => 'loot',
                        'date' => date('d.m.Y', $event_date),
                        'time' => date('H:i:s', $event_date),
                        'icon' => $item_icon,
                        'id'   => $event['data'],
                        'slot' => $item_slot,
                        'sort' => $sort
                    );
                    if($this->_locale != 'en_gb' && $this->_locale != 'en_us') {
                        $item['name'] = Items::getItemName($event['data']);
                    }
                    $feed_data[$i]['title'] = sprintf('%s [%s].', $_strings[15], $item['name']);
                    $feed_data[$i]['desc'] = sprintf('%s <a class="staticTip itemToolTip" id="i=%d" href="item-info.xml?i=%d"><span class="stats_rarity%d">[%s]</span></a>.', $_strings[15], $event['data'], $event['data'], $item['Quality'], $item['name']);
                    break;
                case TYPE_BOSS_FEED:
                    // Get criterias
                    $achievement_ids = array();
                    $criterias = $this->aDB->select("SELECT `referredAchievement` FROM `armory_achievement_criteria` WHERE `data`=?", $event['data']);
                    if(!$criterias) {
                        // Search for KillCredit
                        $kc_entry = 0;
                        $KillCredit = $this->wDB->selectRow("SELECT `KillCredit1`, `KillCredit2` FROM `creature_template` WHERE `entry`=?", $event['data']);
                        for($i=1;$i<3;$i++) {
                            if($KillCredit['KillCredit'.$i] > 0) {
                                $kc_entry = $KillCredit['KillCredit'.$i];
                            }
                        }
                        if($kc_entry == 0) {
                            continue;
                        }
                        $criterias = $this->aDB->select("SELECT `referredAchievement` FROM `armory_achievement_criteria` WHERE `data`=?", $kc_entry);
                        if(!$criterias || !is_array($criterias)) {
                            continue;
                        }
                    }
                    foreach($criterias as $crit) {
                        $achievement_ids[] = $crit['referredAchievement'];
                    }
                    if(!$achievement_ids || !is_array($achievement_ids)) {
                        continue;
                    }
                    $achievement = $this->aDB->selectRow("SELECT `id`, `name_".$this->_locale."` AS `name` FROM `armory_achievement` WHERE `id` IN (?a) AND `flags`=1", $achievement_ids);
                    if(!$achievement || !is_array($achievement)) {
                        continue;
                    }
                    $feed_data[$i]['event'] = array(
                        'type' => 'bosskill',
                        'date'   => date('d.m.Y', $event_date),
                        'time'   => date('H:i:s', $event_date),
                        'id'     => $event['data'],
                        'points' => 0,                        
                        'sort'   => $sort
                    );
                    $feed_data[$i]['title'] = sprintf('%s [%s] %d %s', $_strings[16], $achievement['name'], $event['counter'], $_strings[17]);
                    $feed_data[$i]['desc'] = sprintf('%d %s.', $event['counter'], $achievement['name']);
                    break;
            }
            $i++;
        }
        return $feed_data;
    }
    
    /**
     * @todo enchantments
     **/
    public function GetCharacterItemInfo($slot) {
        if(!$this->guid) {
            return false;
        }
        $item_id = $this->getCharacterEquip($slot['slot']);
        if(!$item_id) {
            return false;
        }
        $durability = Items::getItemDurability($this->guid, $item_id);
        $gems = array(
            'g0' => Items::extractSocketInfo($this->guid, $item_id, 1),
            'g1' => Items::extractSocketInfo($this->guid, $item_id, 2),
            'g2' => Items::extractSocketInfo($this->guid, $item_id, 3)
        );
        $item_data = $this->wDB->selectRow("SELECT `name`, `displayid`, `ItemLevel`, `Quality` FROM `item_template` WHERE `entry`=?", $item_id);
        $enchantment = $this->getCharacterEnchant($slot);
        $item_info = array(
            'displayInfoId'          => $item_data['displayid'],
            'durability'             => $durability['current'],
            'icon'                   => Items::getItemIcon($item_id, $item_data['displayid']),
            'id'                     => $item_id,
            'level'                  => $item_data['ItemLevel'],
            'maxDurability'          => $durability['max'],
            'name'                   => ($this->_locale == 'en_gb' || $this->_locale == 'en_us') ? $item_data['name'] : Items::getItemName($item_id),
            'permanentEnchantIcon'   => 0,
            'permanentEnchantItemId' => 0,
            'permanentenchant'       => null,
            'pickUp'                 => 'PickUpLargeChain',
            'putDown'                => 'PutDownLArgeChain',
            'randomPropertiesId'     => 0,
            'rarity'                 => $item_data['Quality'],
            'seed'                   => 0,
            'slot'                   => $slot['slotid']
        );
        for($i=0;$i<3;$i++) {
            if($gems['g'.$i]['item'] > 0) {
                $item_info['gem'.$i.'Id'] = $gems['g'.$i]['item'];
                $item_info['gemIcon'.$i] = $gems['g'.$i]['icon'];
            }
        }
        return $item_info;
    }
    
    public function GetDBStatistics() {
        return $this->cDB->getStatistics();
    }
    
    public function ModifyMoney($amount, $type) {
        switch($type) {
            // Add money
            case 1:
                $this->money += $amount;
                break;
            // Remove money
            case 2:
                $this->money -= $amount;
                break;
        }
        return true;
    }
    
    public function LookupActiveLots() {
        if(!$this->guid || !isset($_SESSION['accountId'])) {
            return false;
        }
        return AuctionHouseHandler::LookupActiveLotsByGUID($this->guid);
    }
    
    public function CreateAuction(&$data) {
        if(!$this->guid || !isset($_SESSION['accountId'])) {
            return false;
        }
        return AuctionHouseHandler::CreateAuction($this->guid, $this->faction, $data['house_id'], $data['item_id'], $data['item_guid'], $data['count'], $data['price1'], $data['price2']);
    }
}
?>