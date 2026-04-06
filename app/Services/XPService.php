<?php

namespace App\Services;

use App\Core\DB;

/**
 * XP Service
 * Handles XP awarding and level calculation
 */
class XPService
{
    private $db;

    // Level thresholds (linear scaling)
    private static $levelThresholds = [
        1 => 0,
        2 => 200,
        3 => 500,
        4 => 900,
        5 => 1400,
        6 => 2000,
        7 => 2800,
        8 => 3800,
        9 => 5000,
        10 => 6500,
        11 => 8200,
        12 => 10100,
        13 => 12200,
        14 => 14500,
        15 => 17000,
        16 => 19700,
        17 => 22600,
        18 => 25700,
        19 => 29000,
        20 => 32500,
    ];

    // Avatar IDs to unlock at levels
    private static $avatarUnlocks = [
        1 => [1, 2, 3],
        5 => [4, 5, 6],
        10 => [7, 8, 9, 10],
        15 => [11, 12, 13, 14, 15],
        20 => [16],
    ];

    public function __construct()
    {
        $this->db = DB::getInstance();
    }

    /**
     * Award XP to a user based on bet result
     * 
     * @param int $userId User ID
     * @param float $betAmount Bet amount
     * @param string $result 'win' or 'loss'
     * @param float $multiplier Win multiplier (0 for loss)
     * @param int $streakMilestone Streak milestone reached (0 if none)
     * @return array{xp_gained: int, new_xp: int, new_level: int, level_up: bool}
     */
    public function award(int $userId, float $betAmount, string $result, float $multiplier = 0, int $streakMilestone = 0): array
    {
        // Calculate XP to add
        $xpGained = 0;

        // Every bet placed: +5 XP
        $xpGained += 5;

        // Win: +10 XP
        if ($result === 'win') {
            $xpGained += 10;

            // Win with multiplier >= 5x: +25 XP
            if ($multiplier >= 5.0) {
                $xpGained += 25;
            }
        }

        // Win streak milestone: +50/100/200 XP
        if ($streakMilestone === 3) {
            $xpGained += 50;
        } elseif ($streakMilestone === 5) {
            $xpGained += 100;
        } elseif ($streakMilestone === 10) {
            $xpGained += 200;
        }

        // Get current user XP and level
        $stmt = $this->db->query(
            "SELECT xp, level FROM users WHERE id = ?",
            [$userId]
        );
        $user = $stmt->fetch();

        if (!$user) {
            throw new \Exception("User not found");
        }

        $currentXp = (int) $user['xp'];
        $currentLevel = (int) $user['level'];
        $newXp = $currentXp + $xpGained;

        // Calculate new level
        $newLevel = $this->calculateLevel($newXp);
        $levelUp = $newLevel > $currentLevel;

        // Update user
        $this->db->query(
            "UPDATE users SET xp = ?, level = ? WHERE id = ?",
            [$newXp, $newLevel, $userId]
        );

        return [
            'xp_gained' => $xpGained,
            'new_xp' => $newXp,
            'new_level' => $newLevel,
            'level_up' => $levelUp,
            'previous_level' => $currentLevel
        ];
    }

    /**
     * Calculate level based on XP
     * Uses binary search through thresholds array
     * 
     * @param int $xp Total XP
     * @return int Level (1-20)
     */
    public function calculateLevel(int $xp): int
    {
        $thresholds = self::$levelThresholds;
        $levels = array_keys($thresholds);

        // Binary search
        $left = 0;
        $right = count($levels) - 1;
        $result = 1;

        while ($left <= $right) {
            $mid = (int) floor(($left + $right) / 2);
            $level = $levels[$mid];
            $threshold = $thresholds[$level];

            if ($xp >= $threshold) {
                $result = $level;
                $left = $mid + 1;
            } else {
                $right = $mid - 1;
            }
        }

        return $result;
    }

    /**
     * Get XP required for a specific level
     * 
     * @param int $level Level
     * @return int XP threshold
     */
    public function getXpForLevel(int $level): int
    {
        return self::$levelThresholds[$level] ?? end(self::$levelThresholds);
    }

    /**
     * Get XP required for next level
     * 
     * @param int $currentLevel Current level
     * @return array{current_xp: int, next_level_xp: int, xp_needed: int}
     */
    public function getXpForNextLevel(int $userId): array
    {
        $stmt = $this->db->query(
            "SELECT xp, level FROM users WHERE id = ?",
            [$userId]
        );
        $user = $stmt->fetch();

        if (!$user) {
            throw new \Exception("User not found");
        }

        $currentXp = (int) $user['xp'];
        $currentLevel = (int) $user['level'];

        if ($currentLevel >= 20) {
            return [
                'current_xp' => $currentXp,
                'next_level_xp' => $currentXp,
                'xp_needed' => 0
            ];
        }

        $nextLevelXp = $this->getXpForLevel($currentLevel + 1);
        $xpNeeded = $nextLevelXp - $currentXp;

        return [
            'current_xp' => $currentXp,
            'next_level_xp' => $nextLevelXp,
            'xp_needed' => max(0, $xpNeeded)
        ];
    }

    /**
     * Get XP required for next level
     * 
     * @param int $userId User ID
     * @return array{current_xp: int, next_level_xp: int, xp_needed: int, current_level: int}
     */
    public function getXpProgress(int $userId): array
    {
        $stmt = $this->db->query(
            "SELECT xp, level FROM users WHERE id = ?",
            [$userId]
        );
        $user = $stmt->fetch();

        if (!$user) {
            throw new \Exception("User not found");
        }

        $currentXp = (int) $user['xp'];
        $currentLevel = (int) $user['level'];

        if ($currentLevel >= 20) {
            return [
                'current_xp' => $currentXp,
                'next_level_xp' => $currentXp,
                'xp_needed' => 0,
                'current_level' => $currentLevel,
                'max_level' => true
            ];
        }

        $nextLevel = $currentLevel + 1;
        $nextLevelXp = self::$levelThresholds[$nextLevel];
        $xpNeeded = $nextLevelXp - $currentXp;

        return [
            'current_xp' => $currentXp,
            'next_level_xp' => $nextLevelXp,
            'xp_needed' => max(0, $xpNeeded),
            'current_level' => $currentLevel,
            'max_level' => false
        ];
    }

    /**
     * Get unlocked avatars for a user based on level
     * 
     * @param int $level User level
     * @return array Array of unlocked avatar IDs
     */
    public function getUnlockedAvatars(int $level): array
    {
        $unlocked = [];

        foreach (self::$avatarUnlocks as $unlockLevel => $avatars) {
            if ($level >= $unlockLevel) {
                $unlocked = array_merge($unlocked, $avatars);
            }
        }

        return array_unique($unlocked);
    }

    /**
     * Get all level thresholds
     * 
     * @return array Array of level => xp_threshold
     */
    public static function getLevelThresholds(): array
    {
        return self::$levelThresholds;
    }

    /**
     * Get avatar unlock configuration
     * 
     * @return array Array of level => [avatar_ids]
     */
    public static function getAvatarUnlocks(): array
    {
        return self::$avatarUnlocks;
    }

    /**
     * Get max level
     * 
     * @return int Maximum level
     */
    public static function getMaxLevel(): int
    {
        return max(array_keys(self::$levelThresholds));
    }
}
