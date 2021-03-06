<?php
const songs = 660;
const breakdownfile = "breakdown.txt";

class FSLister {
	#Properties
	public $nsongs			= 15;		//Intended # of songs per chapter
	public $variance		= 25;		//Intended % of RNG within easiest songs
	public $encore			= 100;		//Intended chance for hard encores
	public $encorebonus		= 10;		//Intended % of RNG added to variance for hard encores
	public $superencore		= 20;		//Intended multiplier chance for much harder encores
	public $superencorebonus	= 25;		//Intended % of RNG added to variance for much harder encores
	public $resetencores		= false;	//Whether to reset encores during each encore song

	private $database;				//Instance of SQLConn
	private $breakdown;				//For reading breakdown.txt
	private $songlist;				//For interpreting breakdown.txt

	public $fslist;					//Output: Array list
	public $listHash;				//Var: Hash
	public $listID;					//Var: Unique ID
	public $listName;				//Var: Name
	public $listDesc;				//Var: Description
	public $listPass;				//Var: Password
	public $listVisits;				//Var: Visits counter
	public $listFCs;				//Ext Var: FC Array from DB
	
	#Methods
	public function __construct() {
		$this->database  = new SQLConn();
		$this->breakdown = file_get_contents(breakdownfile);	
		$this->songlist  = \Includes\notepadTable($this->breakdown, 'table', '    • ');
	}
	public function createList() {
		//Count the chapters
		$chaptercount = ceil(songs/$this->nsongs);
		$fssonglist   = $this->songlist;
		$chapters     = array();
		
		//Start looping through the chapters
		for($i=0; $i<$chaptercount; $i++) {
			//Reset encores every chapter
			$incdiff      = false;
			$superincdiff = false;
			
			//There's not enough songs to fill the chapter, so dump the remaining songs here
			if(count($fssonglist) < $this->nsongs) {
				$chapters[$i] = $fssonglist;
				break;
			}
			
			//Put songs in this chapter
			for($x=0; $x<$this->nsongs; $x++) {
				//Reset encores during each song if the option is enabled
				if($this->resetencores) {
					$incdiff      = false;
					$superincdiff = false;
				}
				
				//Encores have a chance for increased difficulty
				if($x >= ($this->nsongs - floor($this->nsongs/5))) {
					if($this->encore)      if(rand(1, (100 / $this->encore     )) == 1) $incdiff      = true;
					if($this->superencore) if(rand(1, (100 / $this->superencore)) == 1) $superincdiff = true;
					$diffbonus = $superincdiff ? $this->superencorebonus : ($incdiff ? $this->encorebonus : 0);
				} else $diffbonus = 0;
				
				//Get a song within $variance + $diffbonus of available songs
				$count   = count($fssonglist);
				$min     = $diffbonus ? ceil($count * ($diffbonus/100)) : 1;
				$max     = ceil($count * (($this->variance/100) + ($diffbonus/100)));
				$songkey = rand($min, $max) - 1;
				
				//Write the song into the chapter
				$song    = $fssonglist[$songkey];
				$song[3] = $this->findSongKey($song);
				$song[1] = ($superincdiff ? '[SUPER ENCORE] ' : ($incdiff ? '[ENCORE] ' : '')) . $song[1];
				$chapters[$i][] = $song;		//Set the new song with a prefix and store the key
				unset($fssonglist[$songkey]);		//Remove the song so it doesn't repeat
				$fssonglist = array_values($fssonglist);//Reindex the array
			}
		}
		
		//Hash and store the list
		$this->fslist = $chapters;
		$this->createHash();
		$this->storeList();
	}
	public function getList($id):bool {
		$list = $this->database->readList($id);
		if(!is_null($list)) {
			$this->listID     = $id;
			$this->listHash	  = $list[0];
			$this->listPass   = $list[1];
			$this->listName   = $list[2];
			$this->listDesc   = $list[3];
			$this->listVisits = $list[4];
			return true;
		} else {
			$this->listID     = null;
			$this->listHash	  = null;
			$this->listPass   = null;
			$this->listName   = null;
			$this->listDesc   = null;
			$this->listVisits = null;
			return false;
		}
	}
	public function readHash($hash = null): bool {
		$hash = $hash ? $hash : $this->listHash;
		
		//Check for errors
		if(!$hash) { $this->fslist = null; return false; }
		
		//Reset the list
		$this->listHash   = $hash;
		$this->fslist = array();
		
		//Read the hash by chapters
		$hashlist = explode('|', $hash);
		foreach($hashlist as $hashchapter=>$hashsongs) {
			for($i=0; $i<strlen($hashsongs); ($i = $i+4)) {
				//Encore switch
				switch($hashsongs[($i)]) {
					case 1: $prefix = '[ENCORE] '; break;
					case 2: $prefix = '[SUPER ENCORE] '; break;
					default: $prefix = '';
				}
				
				//Get the song
				$songid  = (int)substr($hashsongs, ($i+1), 3);
				$song    = $this->songlist[$songid];
				$song[1] = $prefix . $song[1];
				$song[3] = $songid;
				
				//Register the song in the chapter
				$this->fslist[$hashchapter][] = $song;
			}
		} return true;
	}

	private function createHash() {
		$this->listHash = ''; //Reset hash
		
		foreach($this->fslist as $chapter) {
			$this->listHash .= '|'; //Write chapter separator
			foreach($chapter as $song) {
				//Check for encores
				$match = preg_match('/^(\[ENCORE\] )|(\[SUPER ENCORE\] )/', $song[1], $encore);
				if($match) $encore = isset($encore[2]) ? 2 : 1;
				else $encore = 0;
				
				//Write the hash for the song
				$this->listHash .= $encore . str_pad($song[3], 3, 0, STR_PAD_LEFT);
			}
		}
		
		//Store hash
		$this->listHash = substr($this->listHash, 1);
	}
	private function storeList() {
		$this->listID   = uniqid();					//Create a unique ID for the list
		$this->listName = $this->listID;				//Default name is always the ID
		$this->listDesc = "Full Series List";				//Default description
		$this->listPass = bin2hex(openssl_random_pseudo_bytes(2));	//Random password
		if(!$this->database->storeList($this->listID, $this->listHash, $this->listPass, $this->listName, $this->listDesc))
			error("Internal error: Couldn't save your list.", true);
	}
	private function findSongKey($songarr) {
		foreach($this->songlist as $key=>$song) {
			//Match song name and game
			if($song[1] == $songarr[1] && $song[2] == $songarr[2])
				return $key;
		}
		
		//Song not found
		error("Internal error: Couldn't find a song.", true);
	}
}
?>