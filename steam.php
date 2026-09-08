<?php

/*
 * SteamID conversions.
 *
 * Three notations describe the same account:
 *
 *   Steam2   STEAM_X:Y:Z     X = universe, Y = 0|1, Z = account number
 *   Steam3   [U:1:W]         W = Z * 2 + Y
 *   Steam64  7656119...      76561197960265728 + W
 *
 * Every pattern below is anchored. An unanchored pattern is not a validity
 * check -- it only asks whether a SteamID is hiding somewhere inside the
 * input -- and the callers here (the public search box in index.php and the
 * Add/Edit form via verify_steamid.php) rely on these functions to reject
 * junk rather than convert it into a SteamID nobody owns.
 *
 * The 64-bit arithmetic goes through bcmath end to end. The values exceed
 * PHP_INT_MAX on a 32-bit build, and mixing native ints with bcadd() silently
 * loses precision there.
 */

class Steam
{
	/** Base of the "individual" account range: STEAM_0:0:0 as a Steam64 id. */
	private const STEAM64_BASE = '76561197960265728';

	private const RE_STEAM2  = '/^STEAM_([0-5]):([01]):(\d+)$/';
	/* The conditional subpattern makes the brackets all-or-nothing: `[U:1:5]`
	   and `U:1:5` are both accepted, `[U:1:5` and `U:1:5]` are not. */
	private const RE_STEAM3  = '/^(\[)?U:1:(\d+)(?(1)\])$/';
	private const RE_STEAM64 = '/^7656119\d{10}$/';

	private static function resolveInputID(string $steamid): string
	{
		switch (true) {
			case preg_match(self::RE_STEAM2, $steamid) === 1:
				return 'Steam2';
			case preg_match(self::RE_STEAM3, $steamid) === 1:
				return 'Steam3';
			case preg_match(self::RE_STEAM64, $steamid) === 1:
				return 'Steam64';
			default:
				throw new InvalidArgumentException('Invalid SteamID input!');
		}
	}

	/**
	 * Normalises any of the three notations to Steam2.
	 *
	 * @throws InvalidArgumentException when the input is not a SteamID, or
	 *         when a well-formed input falls outside the individual range.
	 */
	public static function convertSteamID(string $steamid): string
	{
		$steamid = trim($steamid);
		$type = self::resolveInputID($steamid);

		$converted = match ($type) {
			'Steam2'  => $steamid,
			'Steam3'  => self::SteamID3_To_SteamID($steamid),
			'Steam64' => self::SteamID64_To_SteamID($steamid),
		};

		if ($converted === false) {
			throw new InvalidArgumentException('Invalid SteamID input!');
		}

		return $converted;
	}

	/** @return string|false */
	public static function SteamID_To_SteamID3(string $steamid32)
	{
		if (preg_match(self::RE_STEAM2, $steamid32, $res) !== 1) {
			return false;
		}

		/* W = Z * 2 + Y */
		return '[U:1:' . bcadd(bcmul($res[3], '2'), $res[2]) . ']';
	}

	/** @return string|false */
	public static function SteamID3_To_SteamID(string $steamid3)
	{
		if (preg_match(self::RE_STEAM3, $steamid3, $matches) !== 1) {
			return false;
		}

		$w = $matches[2];
		$y = bcmod($w, '2');
		$z = bcdiv(bcsub($w, $y), '2', 0);

		return "STEAM_0:$y:$z";
	}

	/** @return string|false */
	public static function SteamID_To_SteamID64(string $steamid32)
	{
		if (preg_match(self::RE_STEAM2, $steamid32, $res) !== 1) {
			return false;
		}

		/* 76561197960265728 + Z * 2 + Y */
		return bcadd(self::STEAM64_BASE, bcadd(bcmul($res[3], '2'), $res[2]));
	}

	/** @return string|false */
	public static function SteamID64_To_SteamID(string $steamid64)
	{
		if (preg_match(self::RE_STEAM64, $steamid64) !== 1) {
			return false;
		}

		$w = bcsub($steamid64, self::STEAM64_BASE);
		if (bccomp($w, '0') < 0) {
			/* Below STEAM_0:0:0 -- a group, lobby or otherwise non-individual
			   id. Returning "" here (as this used to) makes a caller that
			   tests for false accept an empty SteamID as the login identity. */
			return false;
		}

		$y = bcmod($w, '2');
		$z = bcdiv(bcsub($w, $y), '2', 0);

		return "STEAM_0:$y:$z";
	}

	/**
	 * @return array{success: bool, steamID2?: string, error?: string}
	 */
	public static function verifyAndConvertSteamID(string $steamid): array
	{
		try {
			return ['success' => true, 'steamID2' => self::convertSteamID($steamid)];
		} catch (InvalidArgumentException $e) {
			return ['success' => false, 'error' => $e->getMessage()];
		}
	}
}
