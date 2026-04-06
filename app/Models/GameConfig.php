<?php
/**
 * BetVibe - GameConfig Model
 * Represents game configuration
 */

namespace App\Models;

class GameConfig extends BaseModel
{
    protected string $table = 'game_config';
    protected array $fillable = [
        'game_slug',
        'display_name',
        'is_enabled',
        'win_ratio',
        'min_bet',
        'max_bet',
        'round_duration',
        'extra_config',
        'updated_at'
    ];

    /**
     * Find game config by slug
     */
    public function getBySlug(string $slug): ?array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT * FROM {$this->table} WHERE game_slug = :game_slug LIMIT 1"
        );
        $stmt->execute(['game_slug' => $slug]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get all enabled games
     */
    public function getEnabledGames(): array
    {
        $stmt = $this->getConnection()->query(
            "SELECT * FROM {$this->table} WHERE is_enabled = 1 ORDER BY display_name ASC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Check if game is enabled
     */
    public function isEnabled(string $slug): bool
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT is_enabled FROM {$this->table} WHERE game_slug = :game_slug LIMIT 1"
        );
        $stmt->execute(['game_slug' => $slug]);
        $result = $stmt->fetch();
        return $result && $result['is_enabled'] == 1;
    }

    /**
     * Enable a game
     */
    public function enableGame(string $slug): bool
    {
        $stmt = $this->getConnection()->prepare(
            "UPDATE {$this->table} SET is_enabled = 1, updated_at = NOW() WHERE game_slug = :game_slug"
        );
        return $stmt->execute(['game_slug' => $slug]);
    }

    /**
     * Disable a game
     */
    public function disableGame(string $slug): bool
    {
        $stmt = $this->getConnection()->prepare(
            "UPDATE {$this->table} SET is_enabled = 0, updated_at = NOW() WHERE game_slug = :game_slug"
        );
        return $stmt->execute(['game_slug' => $slug]);
    }

    /**
     * Update win ratio for a game
     */
    public function updateWinRatio(string $slug, float $winRatio): bool
    {
        $stmt = $this->getConnection()->prepare(
            "UPDATE {$this->table} 
             SET win_ratio = :win_ratio, updated_at = NOW() 
             WHERE game_slug = :game_slug"
        );
        return $stmt->execute([
            'win_ratio' => $winRatio,
            'game_slug' => $slug
        ]);
    }

    /**
     * Update bet limits for a game
     */
    public function updateBetLimits(string $slug, float $minBet, float $maxBet): bool
    {
        $stmt = $this->getConnection()->prepare(
            "UPDATE {$this->table} 
             SET min_bet = :min_bet, max_bet = :max_bet, updated_at = NOW() 
             WHERE game_slug = :game_slug"
        );
        return $stmt->execute([
            'min_bet' => $minBet,
            'max_bet' => $maxBet,
            'game_slug' => $slug
        ]);
    }

    /**
     * Update extra config for a game
     */
    public function updateExtraConfig(string $slug, array $config): bool
    {
        $jsonConfig = json_encode($config);
        $stmt = $this->getConnection()->prepare(
            "UPDATE {$this->table} 
             SET extra_config = :extra_config, updated_at = NOW() 
             WHERE game_slug = :game_slug"
        );
        return $stmt->execute([
            'extra_config' => $jsonConfig,
            'game_slug' => $slug
        ]);
    }

    /**
     * Get extra config for a game
     */
    public function getExtraConfig(string $slug): array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT extra_config FROM {$this->table} WHERE game_slug = :game_slug LIMIT 1"
        );
        $stmt->execute(['game_slug' => $slug]);
        $result = $stmt->fetch();

        if ($result && !empty($result['extra_config'])) {
            return json_decode($result['extra_config'], true) ?: [];
        }

        return [];
    }

    /**
     * Validate bet amount against game limits
     */
    public function validateBetAmount(string $slug, float $amount): bool
    {
        $game = $this->getBySlug($slug);

        if (!$game || !$game['is_enabled']) {
            return false;
        }

        return $amount >= $game['min_bet'] && $amount <= $game['max_bet'];
    }

    /**
     * Get all enabled games (alias for getEnabledGames)
     */
    public function getAllEnabled(): array
    {
        return $this->getEnabledGames();
    }

    /**
     * Update game configuration
     */
    public function updateConfig(string $slug, array $data): void
    {
        $allowedFields = [
            'display_name',
            'is_enabled',
            'win_ratio',
            'min_bet',
            'max_bet',
            'round_duration',
            'extra_config'
        ];

        $updates = [];
        $params = ['game_slug' => $slug];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                if ($key === 'extra_config' && is_array($value)) {
                    $updates[] = "{$key} = :{$key}";
                    $params[$key] = json_encode($value);
                } else {
                    $updates[] = "{$key} = :{$key}";
                    $params[$key] = $value;
                }
            }
        }

        if (empty($updates)) {
            return;
        }

        $updates[] = "updated_at = NOW()";
        $sql = "UPDATE {$this->table} SET " . implode(', ', $updates) . " WHERE game_slug = :game_slug";

        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
    }
}
