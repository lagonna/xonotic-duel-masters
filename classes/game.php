<?php

class Game {
	private $MySQL;
	private $strTableName;
	private $strTableKey;
	private $intTableKeyValue;
	private $arrObjInfo = [];

	function __construct($sqlConnection, $tablePrefix = "") {
		$this->MySQL = $sqlConnection;
		$this->strTableName = $tablePrefix . "gamesplayed";
		$this->strTableKey = "gamesplayed_id";
	}

	public function select($intIDNum, $numericIDOnly = true) {
		$intIDNum = $numericIDOnly ? intval($intIDNum) : $this->MySQL->real_escape_string($intIDNum);

		$query = "SELECT * FROM " . $this->strTableName . " WHERE " . $this->strTableKey . " = '" . $intIDNum . "' LIMIT 1";
		$result = $this->MySQL->query($query);

		if ($result && $result->num_rows > 0) {
			$this->arrObjInfo = $result->fetch_assoc();
			$this->intTableKeyValue = $this->arrObjInfo[$this->strTableKey];
			return true;
		}

		$this->arrObjInfo = [];
		$this->intTableKeyValue = null;
		return false;
	}

	public function addNew($arrColumns, $arrValues) {
		if (!is_array($arrColumns) || !is_array($arrValues) || count($arrColumns) == 0 || count($arrColumns) != count($arrValues)) {
			return false;
		}

		$columns = implode(", ", $arrColumns);
		$placeholders = rtrim(str_repeat("?, ", count($arrColumns)), ", ");
		$stmt = $this->MySQL->prepare("INSERT INTO " . $this->strTableName . " (" . $columns . ") VALUES (" . $placeholders . ")");

		if (!$stmt) {
			return false;
		}

		$types = str_repeat("s", count($arrValues)); // assume string for simplicity
		$stmt->bind_param($types, ...$arrValues);

		if ($stmt->execute()) {
			$insertId = $this->MySQL->insert_id;
			return $this->select($insertId);
		}

		return false;
	}

	public function countMembers() {
		if (!isset($this->intTableKeyValue)) {
			return 0;
		}
		$query = "SELECT * FROM " . $this->strTableName . "_members WHERE " . $this->strTableKey . " = '" . intval($this->intTableKeyValue) . "'";
		$result = $this->MySQL->query($query);
		return $result ? $result->num_rows : 0;
	}

    public function getAllGames() {
        $returnArr = [];
        $query = "SELECT * FROM " . $this->strTableName . " ORDER BY " . $this->strTableKey;

        $result = $this->MySQL->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $returnArr[intval($row[$this->strTableKey])] = $row;
            }
        }

        return $returnArr;
    }
    

	public function getMembersWhoPlayThisGame($tablePrefix = "") {
		$returnArr = [];
		if (isset($this->intTableKeyValue)) {
			$membersGamesTable = $tablePrefix . "gamesplayed_members";
			$membersTable = $tablePrefix . "members";
			$query = "SELECT " . $membersGamesTable . ".member_id 
					  FROM " . $membersGamesTable . "
					  INNER JOIN " . $membersTable . " 
					  ON " . $membersGamesTable . ".member_id = " . $membersTable . ".member_id 
					  WHERE " . $membersGamesTable . "." . $this->strTableKey . " = '" . intval($this->intTableKeyValue) . "' 
					  AND " . $membersTable . ".disabled = '0'";

			$result = $this->MySQL->query($query);

			if ($result) {
				while ($row = $result->fetch_assoc()) {
					$returnArr[] = $row['member_id'];
				}
			}
		}
		return $returnArr;
	}

	public function getGameList() {
		$returnArr = [];
		$query = "SELECT " . $this->strTableKey . " FROM " . $this->strTableName . " ORDER BY " . $this->strTableKey;

		$result = $this->MySQL->query($query);
		if ($result) {
			while ($row = $result->fetch_assoc()) {
				$returnArr[] = $row[$this->strTableKey];
			}
		}

		return $returnArr;
	}

	public function get_info($key = null) {
		if ($key === null) {
			return $this->arrObjInfo;
		}
		return $this->arrObjInfo[$key] ?? null;
	}
}

?>
