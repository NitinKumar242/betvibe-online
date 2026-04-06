<?php

/**
 * Avatar Configuration
 * Defines all available avatars and their unlock levels
 */

$avatars = [
    [
        'id' => 1,
        'name' => 'Cool Cat',
        'emoji' => '🐱',
        'unlock_level' => 1,
        'rarity' => 'common'
    ],
    [
        'id' => 2,
        'name' => 'Happy Dog',
        'emoji' => '🐕',
        'unlock_level' => 1,
        'rarity' => 'common'
    ],
    [
        'id' => 3,
        'name' => 'Chill Panda',
        'emoji' => '🐼',
        'unlock_level' => 1,
        'rarity' => 'common'
    ],
    [
        'id' => 4,
        'name' => 'Lucky Rabbit',
        'emoji' => '🐰',
        'unlock_level' => 5,
        'rarity' => 'uncommon'
    ],
    [
        'id' => 5,
        'name' => 'Wise Owl',
        'emoji' => '🦉',
        'unlock_level' => 5,
        'rarity' => 'uncommon'
    ],
    [
        'id' => 6,
        'name' => 'Playful Fox',
        'emoji' => '🦊',
        'unlock_level' => 5,
        'rarity' => 'uncommon'
    ],
    [
        'id' => 7,
        'name' => 'Majestic Lion',
        'emoji' => '🦁',
        'unlock_level' => 10,
        'rarity' => 'rare'
    ],
    [
        'id' => 8,
        'name' => 'Fierce Tiger',
        'emoji' => '🐯',
        'unlock_level' => 10,
        'rarity' => 'rare'
    ],
    [
        'id' => 9,
        'name' => 'Mystical Dragon',
        'emoji' => '🐉',
        'unlock_level' => 10,
        'rarity' => 'rare'
    ],
    [
        'id' => 10,
        'name' => 'Golden Phoenix',
        'emoji' => '🦅',
        'unlock_level' => 10,
        'rarity' => 'rare'
    ],
    [
        'id' => 11,
        'name' => 'Cosmic Bear',
        'emoji' => '🐻',
        'unlock_level' => 15,
        'rarity' => 'epic'
    ],
    [
        'id' => 12,
        'name' => 'Thunder Wolf',
        'emoji' => '🐺',
        'unlock_level' => 15,
        'rarity' => 'epic'
    ],
    [
        'id' => 13,
        'name' => 'Ocean King',
        'emoji' => '🐋',
        'unlock_level' => 15,
        'rarity' => 'epic'
    ],
    [
        'id' => 14,
        'name' => 'Fire Spirit',
        'emoji' => '🔥',
        'unlock_level' => 15,
        'rarity' => 'epic'
    ],
    [
        'id' => 15,
        'name' => 'Ice Queen',
        'emoji' => '❄️',
        'unlock_level' => 15,
        'rarity' => 'epic'
    ],
    [
        'id' => 16,
        'name' => 'Legendary Crown',
        'emoji' => '👑',
        'unlock_level' => 20,
        'rarity' => 'legendary'
    ],
];

$unlockLevels = [
    1 => [1, 2, 3],
    5 => [4, 5, 6],
    10 => [7, 8, 9, 10],
    15 => [11, 12, 13, 14, 15],
    20 => [16],
];

return [
    'avatars' => $avatars,
    'unlock_levels' => $unlockLevels,
    'rarity_colors' => [
        'common' => '#9ca3af',      // Gray
        'uncommon' => '#22c55e',    // Green
        'rare' => '#3b82f6',        // Blue
        'epic' => '#a855f7',        // Purple
        'legendary' => '#f59e0b',   // Gold
    ],
];
