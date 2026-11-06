<?php

class Basic {
	
	protected $MySQL;
	protected $strTableName;
	protected $strTableKey;
	protected $intTableKeyValue;
	protected $strTablePrefix;
	protected $arrObjInfo;
	
	public function __construct($sqlConnection, $tableName, $tableKey) {
		$this->MySQL = $sqlConnection;
		$this->strTableName = $this->MySQL->get_tablePrefix().$tableName;
		$this->strTableKey = $tableKey;
	}
	
	public function select($intIDNum, $numericIDOnly = true) {
		$returnVal = false;
		if (!$numericIDOnly) {
			$checkID = true;
		} else {
			$checkID = is_numeric($intIDNum);
		}

		if ($checkID) {
			$stmt = $this->MySQL->prepare("SELECT * FROM " . $this->strTableName . " WHERE " . $this->strTableKey . " = ?");
			if ($stmt) {
				$stmt = $this->MySQL->bindParams($stmt, [$intIDNum]);
				if ($stmt) {
					$execOk = $stmt->execute();
					if ($execOk) {
						$result = null;
						if (method_exists($stmt, 'get_result')) {
							$result = $stmt->get_result();
							if ($result && $result->num_rows > 0) {
								$this->arrObjInfo = $result->fetch_assoc();
								$returnVal = true;
								$this->intTableKeyValue = $intIDNum;
							}
						} else {
							$meta = $stmt->result_metadata();
							if ($meta) {
								$fields = [];
								$row = [];
								$fieldsRef = [];
								while ($f = $meta->fetch_field()) {
									$fields[] = $f->name;
									$fieldsRef[] = &$row[$f->name];
								}
								call_user_func_array(array($stmt, 'bind_result'), $fieldsRef);
								if ($stmt->fetch()) {
									$arr = array();
									foreach ($fields as $fname) {
										$arr[$fname] = $row[$fname];
									}
									$this->arrObjInfo = $arr;
									$returnVal = true;
									$this->intTableKeyValue = $intIDNum;
								}
							}
						}
					}
				} else {
					$result = $this->MySQL->query("SELECT * FROM " . $this->strTableName . " WHERE " . $this->strTableKey . " = '" . $this->MySQL->real_escape_string($intIDNum) . "'");
					if ($result && $result->num_rows > 0) {
						$this->arrObjInfo = $result->fetch_assoc();
						$returnVal = true;
						$this->intTableKeyValue = $intIDNum;
					}
				}
			} else {
				$result = $this->MySQL->query("SELECT * FROM " . $this->strTableName . " WHERE " . $this->strTableKey . " = '" . $this->MySQL->real_escape_string($intIDNum) . "'");
				if ($result && $result->num_rows > 0) {
					$this->arrObjInfo = $result->fetch_assoc();
					$returnVal = true;
					$this->intTableKeyValue = $intIDNum;
				}
			}
		}

		return $returnVal;
	}

	public function addNew($arrColumns, $arrValues) {
		$returnVal = false;

		if (!is_array($arrColumns) || count($arrColumns) == 0) {
			$this->MySQL->displayError("basic.php - addNew (no columns provided)");
			return false;
		}

		$sqlColumns = implode(",", $arrColumns);
		$sqlValues = rtrim(str_repeat("?, ", count($arrColumns)), ", ");

		$stmt = $this->MySQL->prepare("INSERT INTO " . $this->strTableName . " (" . $sqlColumns . ") VALUES (" . $sqlValues . ")");

		if ($stmt === false) {
			$this->MySQL->displayError("basic.php - addNew (prepare failed)");
			return false;
		}

		if (is_array($arrValues)) {
			foreach ($arrValues as $key => $value) {
				$temp = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
				$temp = trim($temp);
				$arrValues[$key] = $temp;
			}
			$stmt = $this->MySQL->bindParams($stmt, $arrValues);
		}

		if (!$stmt) {
			$this->MySQL->displayError("basic.php - addNew (bindParams failed)");
			return false;
		}

		$execOk = $stmt->execute();
		if ($execOk) {
			$insertId = null;
			if (isset($this->MySQL->insert_id) && $this->MySQL->insert_id) {
				$insertId = $this->MySQL->insert_id;
			} elseif (isset($this->MySQL->mysqli) && isset($this->MySQL->mysqli->insert_id) && $this->MySQL->mysqli->insert_id) {
				$insertId = $this->MySQL->mysqli->insert_id;
			} elseif (isset($stmt->insert_id) && $stmt->insert_id) {
				$insertId = $stmt->insert_id;
			}

			if ($insertId) {
				$this->select($insertId);
				$returnVal = true;
			} else {
				$this->select($stmt->insert_id ?? 0, false);
				$returnVal = true;
			}
		} else {
			$this->MySQL->displayError("basic.php - addNew (execute failed)");
			$returnVal = false;
		}

		return $returnVal;
	}

	public function update($arrTableColumns, $arrColumnValues) {
		
		$returnVal = false;
		if(is_array($arrTableColumns) AND is_array($arrColumnValues) AND $this->intTableKeyValue != null) {
		
			if(count($arrTableColumns) == count($arrColumnValues)) {
			
				$combinedArray = array_combine($arrTableColumns, $arrColumnValues);
				
				$arrQuery = array();
				$arrValues = array();

				foreach ($combinedArray as $key => $value) {
					$temp = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
					$temp = trim($temp);

					$arrQuery[]  = $key . " = ?";
					$arrValues[] = $temp;
				}

				
				$updateQuery = implode(", ", $arrQuery);
		
				$stmt = $this->MySQL->prepare("UPDATE " . $this->strTableName . " SET " . $updateQuery . " WHERE " . $this->strTableKey . " = ?");
                $arrValues[] = $this->intTableKeyValue;
                $stmt = $this->MySQL->bindParams($stmt, $arrValues);
				
				if($stmt->execute()) {
					$this->select($this->intTableKeyValue);
					$returnVal = true;
					
				}else {
					$this->MySQL->displayError("basic.php - update");
				}
			}
		}
		
		return $returnVal;
	}
	
	
	public function delete() {
		$returnVal = false;
		if($this->intTableKeyValue != "") {
			$result = $this->MySQL->query("DELETE FROM ".$this->strTableName." WHERE ".$this->strTableKey." = '".$this->intTableKeyValue."'");
			if(!$this->MySQL->error) {
				$returnVal = true;
			}else {
				$this->MySQL->displayError("basic.php");
			}
		}
		return $returnVal;
	}
	
	
	
	public function get_info($returnSingleValue = "") {
		if ($returnSingleValue === "") {
			return $this->arrObjInfo;
		}
		return isset($this->arrObjInfo[$returnSingleValue]) ? $this->arrObjInfo[$returnSingleValue] : "";
	}
	
	public function set_tableName($tableName) {
		$this->strTableName = $tableName;
	}
	
	public function set_tableKey($tableKey) {
		$this->strTableKey = $tableKey;
	}
	
	public function get_tableKey() {
		return $this->strTableKey;	
	}
	
	public function set_tablePrefix($tablePrefix) {
		$this->strTablePrefix = $tablePrefix;
	}
	
	public function get_tablePrefix() {
		return $this->strTablePrefix;
	}
	
	public function get_keyvalue() {
		return $this->intTableKeyValue;
	}
	
	public function get_MySQL() {
		return $this->MySQL;	
	}
}

class Tournament extends Basic {
	
	public $objPlayer;
	public $objTeam;
	public $objMatch;
	public $objManager;
	protected $objMember;
	protected $arrTeamIDs;
	protected $arrRoundsPerTeams;
	
	public function __construct($sqlConnection) {

		$this->MySQL = $sqlConnection;
		$this->strTableName = $this->MySQL->get_tablePrefix()."tournaments";
		$this->strTableKey = "tournament_id";
		
		$this->objPlayer = new Basic($sqlConnection, "tournamentplayers", "tournamentplayer_id");
		$this->objTeam = new Basic($sqlConnection, "tournamentteams", "tournamentteam_id");
		$this->objMatch = new Basic($sqlConnection, "tournamentmatch", "tournamentmatch_id");
		$this->objManager = new Basic($sqlConnection, "tournament_managers", "tournamentmanager_id");
		
		$this->arrRoundsPerTeams = array(
				1 => 2,
				2 => 4, 
				3 => 8,
				4 => 16,
				5 => 32,
				6 => 64,
				7 => 128,
				8 => 256);
		
	}
	
	public function addNew($arrColumns, $arrValues) {
		$returnVal = false;

		$txStarted = false;
		if (method_exists($this->MySQL, 'begin_transaction')) {
			$this->MySQL->begin_transaction();
			$txStarted = true;
		}

		try {
			if (!is_array($arrColumns) || count($arrColumns) == 0) {
				$this->MySQL->displayError("tournament.php - addNew (no columns provided)");
				return false;
			}

			$sqlColumns = implode(",", $arrColumns);
			$sqlValues = rtrim(str_repeat("?, ", count($arrColumns)), ", ");

			$stmt = $this->MySQL->prepare("INSERT INTO " . $this->strTableName . " (" . $sqlColumns . ") VALUES (" . $sqlValues . ")");
			if ($stmt === false) {
				$this->MySQL->displayError("tournament.php - addNew (prepare failed)");
				return false;
			}

			foreach ($arrValues as $key => $value) {
				$temp = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
				$temp = trim($temp);
				$arrValues[$key] = $temp;
			}

			$stmt = $this->MySQL->bindParams($stmt, $arrValues);
			if (!$stmt) {
				$this->MySQL->displayError("tournament.php - addNew (bindParams failed)");
				return false;
			}

			$execOk = $stmt->execute();
			if (!$execOk) {
				$this->MySQL->displayError("tournament.php - addNew (execute failed)");
				return false;
			}

			$insertId = null;
			if (!empty($this->MySQL->insert_id)) {
				$insertId = $this->MySQL->insert_id;
			} elseif (!empty($this->MySQL->mysqli->insert_id)) {
				$insertId = $this->MySQL->mysqli->insert_id;
			} elseif (!empty($stmt->insert_id)) {
				$insertId = $stmt->insert_id;
			}

			if ($insertId) {
				$this->select($insertId);
			} else {
				$this->select($stmt->insert_id ?? 0, false);
			}

			if ($this->intTableKeyValue != "") {
				$this->arrTeamIDs = array();
				$countErrors = 0;
				$arrSeeds = range(1, intval($this->arrObjInfo['maxteams']));

				if (isset($this->arrObjInfo['seedtype']) && $this->arrObjInfo['seedtype'] == 2) {
					shuffle($arrSeeds);
				} elseif (isset($this->arrObjInfo['seedtype']) && $this->arrObjInfo['seedtype'] == 3) {
					$arrSeeds = array_fill(0, intval($this->arrObjInfo['maxteams']), 0);
				}

				for ($i = 0; $i < intval($this->arrObjInfo['maxteams']); $i++) {
					$teamNumber = $i + 1;
					$teamName = "Team " . $teamNumber;

					$seedValue = $arrSeeds[$i] ?? 0;
					$ok = $this->objTeam->addNew(
						array("tournament_id", "seed", "name"),
						array($this->arrObjInfo['tournament_id'], $seedValue, $teamName)
					);
					if (!$ok) {
						$countErrors++;
						break;
					}

					$this->arrTeamIDs[] = $this->objTeam->get_info("tournamentteam_id");
				}

				if ($countErrors == 0) {
					if (empty($this->arrObjInfo['seedtype']) || $this->arrObjInfo['seedtype'] != 3) {
						$ok = $this->resetMatches();
						if (!$ok) $countErrors++;
					}
				}

				if ($countErrors == 0) {
					$returnVal = true;
				}
			}

			if ($returnVal) {
				if ($txStarted) $this->MySQL->commit();
			} else {
				if ($txStarted) {
					$this->MySQL->rollback();
				} else {
					$tID = intval($this->arrObjInfo['tournament_id'] ?? 0);
					if ($tID) $this->cleanupTournament($tID);
				}
			}
		} catch (Exception $e) {
			if ($txStarted) $this->MySQL->rollback();
			$tID = intval($this->arrObjInfo['tournament_id'] ?? 0);
			if ($tID) $this->cleanupTournament($tID);
			$returnVal = false;
		}

		return $returnVal;
	}


	protected function cleanupTournament($tournamentID) {
		$pref = $this->MySQL->get_tablePrefix();
		$tID = intval($tournamentID);
		if ($tID <= 0) return false;
		$this->MySQL->query("DELETE FROM {$pref}tournamentplayers WHERE tournament_id = '{$tID}'");
		$this->MySQL->query("DELETE FROM {$pref}tournamentmatch WHERE tournament_id = '{$tID}'");
		$this->MySQL->query("DELETE FROM {$pref}tournamentteams WHERE tournament_id = '{$tID}'");
		$this->MySQL->query("DELETE FROM {$pref}tournaments WHERE tournament_id = '{$tID}'");
		return true;
	}

	public function select($intIDNum, $numericIDOnly = true) {
		$returnVal = parent::select($intIDNum, $numericIDOnly);
		return $returnVal;
	}

	public function update($arrColumns, $arrValues) {
		$returnVal = false;

		$arrOriginalInfo = $this->arrObjInfo;
		$result = parent::update($arrColumns, $arrValues);

		if ($result) {
			$returnVal = true;
			$arrNewInfo = $this->arrObjInfo;

			if (isset($arrOriginalInfo['maxteams']) && isset($arrNewInfo['maxteams']) && $arrOriginalInfo['maxteams'] > $arrNewInfo['maxteams']) {
				$numToRemove = intval($arrOriginalInfo['maxteams']) - intval($arrNewInfo['maxteams']);
				$arrCurrentTeams = $this->getTeams(true, "ORDER BY seed DESC");
				$arrRemovedTeams = array();

				for ($i = 0; $i < $numToRemove; $i++) {
					$arrRemovedTeams[] = $arrCurrentTeams[$i] ?? null;
					if (isset($arrCurrentTeams[$i])) {
						$this->objTeam->select($arrCurrentTeams[$i]);
						$this->objTeam->delete();
						$pref = $this->MySQL->get_tablePrefix();
						$this->MySQL->query("DELETE FROM {$pref}tournamentplayers WHERE team_id = '" . intval($arrCurrentTeams[$i]) . "'");
					}
				}

				$this->MySQL->query("DELETE FROM " . $this->MySQL->get_tablePrefix() . "tournamentmatch WHERE tournament_id = '" . intval($this->intTableKeyValue) . "'");
				$this->resetMatches();
			} elseif (isset($arrOriginalInfo['maxteams']) && isset($arrNewInfo['maxteams']) && $arrOriginalInfo['maxteams'] < $arrNewInfo['maxteams']) {
				$numToAdd = intval($arrNewInfo['maxteams']) - intval($arrOriginalInfo['maxteams']);
				$nextSeed = intval($arrOriginalInfo['maxteams']) + 1;

				for ($i = 0; $i < $numToAdd; $i++) {
					$this->objTeam->addNew(array("tournament_id", "seed"), array($this->intTableKeyValue, $nextSeed));
					$nextSeed++;
				}

				$this->MySQL->query("DELETE FROM " . $this->MySQL->get_tablePrefix() . "tournamentmatch WHERE tournament_id = '" . intval($this->intTableKeyValue) . "'");
				$this->resetMatches();
			}
		}

		return $returnVal;
	}

	public function resetMatches() {
		$returnVal = false;

		if ($this->intTableKeyValue == "") return false;

		$totalRounds = $this->getTotalRounds();
		if ($totalRounds <= 0) return false;

		$startDate = isset($this->arrObjInfo['startdate']) ? intval($this->arrObjInfo['startdate']) : intval($this->getStartDate());
		$roundGap = isset($this->arrObjInfo['TPR']) ? intval($this->arrObjInfo['TPR']) : intval($this->getTPR());
		$matchGap = isset($this->arrObjInfo['time_between_matches']) ? intval($this->arrObjInfo['time_between_matches']) : intval($this->getTimeBetweenMatches());

		if ($roundGap < 0) $roundGap = 0;
		if ($matchGap < 0) $matchGap = 0;

		$matchesThisIteration = 1;
		$nextMatchID = array();

		for ($i = 1; $i <= $totalRounds; $i++) {
			if ($i == 1) {
				$matchesThisIteration = 1;
			} else {
				$matchesThisIteration *= 2;
			}

			$roundNumber = $totalRounds - ($i - 1);

			$roundBaseTime = $startDate + ($totalRounds - $i) * $roundGap;

			$nextMatchIndex = 1;
			$totalMatchesAdded = 0;

			for ($slot = 1; $slot <= $matchesThisIteration; $slot++) {
				$arrColumns = array("round", "tournament_id");
				$arrValues = array($roundNumber, $this->arrObjInfo['tournament_id']);

				if ($i != 1) {
					$arrColumns[] = "nextmatch_id";
					$parentId = $nextMatchID[$i - 1][$nextMatchIndex] ?? 0;
					$arrValues[] = $parentId;
				}

				$matchStart = $roundBaseTime + (($slot - 1) * $matchGap);

				$arrColumns[] = "match_start";
				$arrValues[] = $matchStart;

				$this->objMatch->addNew($arrColumns, $arrValues);
				$nextMatchID[$i][$slot] = $this->objMatch->get_info("tournamentmatch_id");

				$totalMatchesAdded++;
				if ($totalMatchesAdded >= 2) {
					$nextMatchIndex++;
					$totalMatchesAdded = 0;
				}
			}
		}

		$arrTeams = $this->getTeams(true, "ORDER BY seed");
		$numTeams = count($arrTeams);
		$maxArrTeamsIndex = $numTeams - 1;
		$arrMatches = $this->getMatches(1);
		$counter = 0;

		foreach ($arrMatches as $matchID) {
			$team1ID = $arrTeams[$counter] ?? null;
			$team2Index = $maxArrTeamsIndex - $counter;
			$team2ID = $arrTeams[$team2Index] ?? null;

			$arrColumns = array("team1_id", "team2_id");
			$arrValues = array($team1ID, $team2ID);

			$this->objMatch->select($matchID);
			$this->objMatch->update($arrColumns, $arrValues);

			$counter++;
		}

		if ($counter != 0) $returnVal = true;

		return $returnVal;
	}

	public function getTeams($blnRefresh=false, $strOrderBy= "") {
		if ($blnRefresh) $this->arrTeamIDs = array();
		if (!is_array($this->arrTeamIDs)) $this->arrTeamIDs = array();

		if (count($this->arrTeamIDs) == 0) {
			$teamArr = array();
			$pref = $this->MySQL->get_tablePrefix();

			$allowedOrder = '';
			if (!empty($strOrderBy) && preg_match('/^ORDER\s+BY\s+[\w\.,\s]+$/i', $strOrderBy)) {
				$allowedOrder = ' ' . $strOrderBy;
			}

			$q = "SELECT * FROM {$pref}tournamentteams WHERE tournament_id = '" . intval($this->intTableKeyValue) . "'" . $allowedOrder;
			$result = $this->MySQL->query($q);
			while ($row = $result->fetch_assoc()) {
				$teamArr[] = $row['tournamentteam_id'];
			}
			$this->arrTeamIDs = $teamArr;
		}
		return $this->arrTeamIDs;
	}

	public function getPlayers($getPlayerIDs=false) {
		$returnArr = array();
		if ($this->intTableKeyValue != "") {
			$pref = $this->MySQL->get_tablePrefix();
			$query = "SELECT * FROM {$pref}tournamentplayers WHERE tournament_id = '" . intval($this->intTableKeyValue) . "'";
			$result = $this->MySQL->query($query);
			while ($row = $result->fetch_array()) {
				if ($getPlayerIDs) {
					$returnArr[] = $row['tournamentplayer_id'];
				} else {
					if ($row['member_id'] != 0) {
						$returnArr[] = $row['member_id'];
					} else {
						$returnArr[] = $row['displayname'];
					}
				}
			}
		}
		return $returnArr;
	}

	public function getTournamentPlayerID($memberID) {
		if ($this->intTableKeyValue == "") return false;
		$pref = $this->MySQL->get_tablePrefix();
		if (is_numeric($memberID)) {
			$query = "SELECT * FROM {$pref}tournamentplayers WHERE tournament_id = '" . intval($this->intTableKeyValue) . "' AND member_id = '" . intval($memberID) . "'";
		} else {
			$memberIDEscaped = $this->MySQL->real_escape_string($memberID);
			$query = "SELECT * FROM {$pref}tournamentplayers WHERE tournament_id = '" . intval($this->intTableKeyValue) . "' AND displayname = '" . $memberIDEscaped . "'";
		}
		$result = $this->MySQL->query($query);
		$row = $result->fetch_assoc();
		if ($row) return $row['tournamentplayer_id'];
		return false;
	}

	public function getTeamPlayers($teamID, $returnPlayerID=false) {
		$returnArr = array();
		if ($this->intTableKeyValue != "") {
			$pref = $this->MySQL->get_tablePrefix();
			$result = $this->MySQL->query("SELECT tournamentplayer_id, member_id FROM {$pref}tournamentplayers WHERE team_id = '" . intval($teamID) . "'");
			while ($row = $result->fetch_assoc()) {
				if ($returnPlayerID) {
					$returnArr[] = $row['tournamentplayer_id'];
				} else {
					$returnArr[] = $row['member_id'];
				}
			}
		}
		return $returnArr;
	}

	public function getUnfilledTeams() {
		$returnArr = array();
		if ($this->intTableKeyValue != "") {
			$this->getTeams(true);
			foreach ($this->arrTeamIDs as $teamID) {
				if (count($this->getTeamPlayers($teamID)) < intval($this->arrObjInfo['playersperteam'])) {
					$returnArr[] = $teamID;
				}
			}
		}
		return $returnArr;
	}

	public function getFirstZeroMembersTeam() {
		$FirstZeroMembersTeam = 0;
		if ($this->intTableKeyValue != "") {
			$pref = $this->MySQL->get_tablePrefix();
			$result = $this->MySQL->query("SELECT * FROM {$pref}tournamentteams WHERE tournament_id = '" . intval($this->intTableKeyValue) . "' AND origin_team = '0' LIMIT 1");
			if ($result && $result->num_rows > 0) {
				$row = $result->fetch_assoc();
				$FirstZeroMembersTeam = $row['tournamentteam_id'];
			}
		}
		return $FirstZeroMembersTeam;
	}

	public function countTeams() {
		$returnArr = array();
		if ($this->intTableKeyValue != "") {
			$this->getTeams(true);
			foreach ($this->arrTeamIDs as $teamID) {
				if (count($this->getTeamPlayers($teamID)) > 0) {
					$returnArr[] = $teamID;
				}
			}
		}
		$numberOfTeams = count($returnArr);
		return $numberOfTeams;
	}

	public function checkForTeam($team_id) {
		$teamExist = false;
		if ($this->intTableKeyValue != "") {
			$pref = $this->MySQL->get_tablePrefix();
			$result = $this->MySQL->query("SELECT * FROM {$pref}tournamentteams WHERE tournament_id = '" . intval($this->intTableKeyValue) . "' AND origin_team = '" . intval($team_id) . "'");
			if ($result && $result->num_rows > 0) $teamExist = true;
		}
		return $teamExist;
	}

	public function getMatches($intRoundNumber, $intTeamID=0) {
		$returnArr = array();
		if ($this->intTableKeyValue != "") {
			$pref = $this->MySQL->get_tablePrefix();
			$round = intval($intRoundNumber);
			$filterByTeam = "";
			if ($intTeamID != 0 && is_numeric($intTeamID)) {
				$filterByTeam = " AND (team1_id = '" . intval($intTeamID) . "' OR team2_id = '" . intval($intTeamID) . "') ";
			}
			$q = "SELECT * FROM {$pref}tournamentmatch WHERE round = '" . $round . "' AND tournament_id = '" . intval($this->intTableKeyValue) . "'" . $filterByTeam . " ORDER BY tournamentmatch_id";
			$result = $this->MySQL->query($q);
			while ($row = $result->fetch_assoc()) {
				$returnArr[] = $row['tournamentmatch_id'];
			}
		}
		return $returnArr;
	}

	public function getTeamIDBySeed($intSeed, $blnSelectTeam=true) {
		$returnVal = false;
		if ($this->intTableKeyValue != "") {
			$pref = $this->MySQL->get_tablePrefix();
			$result = $this->MySQL->query("SELECT * FROM {$pref}tournamentteams WHERE tournament_id = '" . intval($this->intTableKeyValue) . "' AND seed = '" . intval($intSeed) . "'");
			if ($result && $result->num_rows > 0) {
				$row = $result->fetch_assoc();
				$returnVal = $row['tournamentteam_id'];
			}
			if ($blnSelectTeam && $returnVal) $this->objTeam->select($returnVal);
		}
		return $returnVal;
	}

	public function getNextMatchTeamSpot($intTeamID, $intMatchID=0) {
		$returnVal = false;
		if ($intMatchID != 0 && is_numeric($intMatchID)) {
			$this->objMatch->select($intMatchID);
		}
		if ($this->intTableKeyValue != "" && $this->objMatch->get_info("tournamentmatch_id") != "") {
			$nextMatchID = $this->objMatch->get_info("nextmatch_id");
			$thisMatchID = $this->objMatch->get_info("tournamentmatch_id");
			$pref = $this->MySQL->get_tablePrefix();
			$result = $this->MySQL->query("SELECT * FROM {$pref}tournamentmatch WHERE tournamentmatch_id != '" . intval($thisMatchID) . "' AND nextmatch_id = '" . intval($nextMatchID) . "' ORDER BY tournamentmatch_id");
			$row = $result->fetch_assoc();
			if ($row['outcome'] == 1) {
				$returnVal = "team1_id";
			} elseif ($row['outcome'] == 2) {
				$returnVal = "team2_id";
			} else {
				if ($row['tournamentmatch_id'] > $thisMatchID) {
					$returnVal = "team1_id";
				} else {
					$returnVal = "team2_id";
				}
			}
		}
		return $returnVal;
	}

	public function getTournamentWinner() {
		$returnVal = false;
		if ($this->intTableKeyValue != "") {
			$pref = $this->MySQL->get_tablePrefix();
			$result = $this->MySQL->query("SELECT * FROM {$pref}tournamentmatch WHERE nextmatch_id = '0' AND tournament_id = '" . intval($this->intTableKeyValue) . "'");
			$row = $result->fetch_assoc();
			if ($row) {
				if ($row['outcome'] == 1) {
					$returnVal = $row['team1_id'];
				} elseif ($row['outcome'] == 2) {
					$returnVal = $row['team2_id'];
				}
			}
		}
		return $returnVal;
	}

	public function getTotalRounds($intMaxTeams="") {
		if ($intMaxTeams == "") {
			$intMaxTeams = $this->arrObjInfo['maxteams'] ?? 0;
		}
		return array_search($intMaxTeams, $this->arrRoundsPerTeams);
	}

	public function getStartDate() {
		$returnVal = false;
		if ($this->intTableKeyValue != "") {
			if (isset($this->arrObjInfo['startdate'])) return $this->arrObjInfo['startdate'];
			$pref = $this->MySQL->get_tablePrefix();
			$result = $this->MySQL->query("SELECT startdate FROM {$pref}tournaments WHERE tournament_id = '" . intval($this->intTableKeyValue) . "' LIMIT 1");
			if ($result) {
				$row = $result->fetch_assoc();
				$returnVal = $row['startdate'] ?? false;
			}
		}
		return $returnVal;
	}

	public function getTPR() {
		$returnVal = false;
		if ($this->intTableKeyValue != "") {
			if (isset($this->arrObjInfo['TPR'])) return $this->arrObjInfo['TPR'];
			$pref = $this->MySQL->get_tablePrefix();
			$result = $this->MySQL->query("SELECT TPR FROM {$pref}tournaments WHERE tournament_id = '" . intval($this->intTableKeyValue) . "' LIMIT 1");
			if ($result) {
				$row = $result->fetch_assoc();
				$returnVal = $row['TPR'] ?? false;
			}
		}
		return $returnVal;
	}
	
	public function getTimeBetweenMatches() {
		$returnVal = false;
		if ($this->intTableKeyValue != "") {
			if (isset($this->arrObjInfo['time_between_matches'])) return $this->arrObjInfo['time_between_matches'];
			$pref = $this->MySQL->get_tablePrefix();
			$result = $this->MySQL->query("SELECT time_between_matches FROM {$pref}tournaments WHERE tournament_id = '" . intval($this->intTableKeyValue) . "' LIMIT 1");
			if ($result) {
				$row = $result->fetch_assoc();
				$returnVal = $row['time_between_matches'] ?? false;
			}
		}
		return $returnVal;
	}

	public function delete() {
		$returnVal = false;
		if ($this->intTableKeyValue != "") {
			$this->cleanupTournament(intval($this->intTableKeyValue));
			$returnVal = true;
		}
		return $returnVal;
	}

}

?>