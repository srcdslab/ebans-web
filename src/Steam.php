<?php
class Steam
{
	public static function SteamID_To_SteamID3($steamid32) 
	{
		if(preg_match('/^STEAM_[01]\:[01]\:(.*)$/', $steamid32, $res))
		{
			$st = '[U:1:';
			$st .= $res[1] * 2 + intval(substr( $steamid32, 8, 1));
			$st .= ']';
			return $st;
		}

		return false;
	}

	public static function SteamID3_To_SteamID($steamid3)
	{
		if(preg_match("/U:1:(\d+)/", $steamid3)) 
		{
			$steam3 = preg_replace("/U:1:(\d+)/", "$1", $steamid3);
			$A = $steam3 % 2;
			$B = intval($steam3 / 2);
			return "STEAM_0:" . $A . ":" . $B;
		}
		return false;
	}

	public static function SteamID_To_SteamID64($steamid32)
	{
		if(preg_match('/^STEAM_0\:1|0\:(.*)$/', $steamid32, $res))
		{
			list(, $m1, $m2) = explode(':', $steamid32, 3);
			list($steam_cid,) = explode('.', bcadd((((int)$m2 * 2) + $m1), '76561197960265728'), 2);
			return $steam_cid;
		}

		return false;
	}

	public static function SteamID64_To_SteamID($steamid64)
	{
		$pattern = "/^(7656119)([0-9]{10})$/";
		if(preg_match($pattern, $steamid64, $match))
		{
			$const1 = 7960265728;
			$const2 = "STEAM_0:";
			$steam32 = '';
			if($const1 <= $match[2])
			{
				$a = ($match[2] - $const1) % 2;
				$b = ($match[2] - $const1 - $a) / 2;
				$steam32 = $const2 . $a . ':' . $b;
			}

			return $steam32;
		}

		return false;
	}

	public static function GetSteamProfile($steamid64, $attribute)
	{
		if(preg_match('/^\d+$/', $steamid64)) $xml = simplexml_load_file("https://steamcommunity.com/profiles/".$steamid64."?xml=1");
		else $xml = simplexml_load_file("https://steamcommunity.com/id/".$steamid64."?xml=1");

		$SteamProfileAttribute = array();

		$attributes = explode(', ', $attribute);

		foreach ($attributes as $key => $attr_key) 
		{
			$SteamProfileAttribute += [$attr_key => $xml->$attr_key];
		}

		return $SteamProfileAttribute;
	}
}
?>
