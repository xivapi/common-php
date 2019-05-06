<?php

namespace XIV\Constants;

class PatreonConstants
{
    const NORMAL_USER        = 0;
    const PATREON_ADVENTURER = 1;
    const PATREON_TANK       = 2;
    const PATREON_HEALER     = 3;
    const PATREON_DPS        = 4;
    const PATREON_BENEFIT    = 9;
    
    const DEFAULT_MAX           = 5;
    const DEFAULT_MAX_NOTIFY    = 20;
    const DEFAULT_TIMEOUT       = (60 * 60);
    const DEFAULT_EXPIRY        = (60 * 60 * 24 * 3);
    
    const PATREON_TIERS = [
        self::NORMAL_USER        => 'Normal User',
        self::PATREON_ADVENTURER => 'Adventurer',
        self::PATREON_TANK       => 'Tank',
        self::PATREON_HEALER     => 'Healer',
        self::PATREON_DPS        => 'DPS',
        self::PATREON_BENEFIT    => 'Friendly Benefits',
    ];
}
