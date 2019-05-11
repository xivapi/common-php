<?php

namespace App\Common\Entity;

use App\Common\Constants\PatreonConstants;
use App\Common\Constants\UserConstants;
use App\Common\User\SignInDiscord;
use App\Common\Utils\Random;
use App\Service\API\ApiRequest;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Table(
 *     name="users",
 *     indexes={
 *          @ORM\Index(name="added", columns={"added"}),
 *          @ORM\Index(name="sso", columns={"sso"}),
 *          @ORM\Index(name="username", columns={"username"}),
 *          @ORM\Index(name="email", columns={"email"}),
 *          @ORM\Index(name="is_banned", columns={"is_banned"}),
 *          @ORM\Index(name="api_public_key", columns={"api_public_key"}),
 *          @ORM\Index(name="sso_discord_id", columns={"sso_discord_id"}),
 *          @ORM\Index(name="sso_discord_avatar", columns={"sso_discord_avatar"}),
 *          @ORM\Index(name="sso_discord_token_expires", columns={"sso_discord_token_expires"}),
 *          @ORM\Index(name="sso_discord_token_access", columns={"sso_discord_token_access"}),
 *          @ORM\Index(name="sso_discord_token_refresh", columns={"sso_discord_token_refresh"}),
 *          @ORM\Index(name="sso_discord_avatar", columns={"sso_discord_avatar"})
 *     }
 * )
 * @ORM\Entity(repositoryClass="App\Common\Repository\UserRepository")
 */
class User
{
    /**
     * @var string
     * @ORM\Id
     * @ORM\Column(type="guid")
     */
    private $id;
    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    private $added;
    /**
     * @var bool
     * @ORM\Column(type="boolean", name="is_banned", options={"default" : 0})
     */
    private $banned = UserConstants::BANNED;
    /**
     * @var string
     * @ORM\Column(type="text", nullable=true)
     */
    private $notes;
    /**
     * The name of the SSO provider
     * @var string
     * @ORM\Column(type="string", length=32)
     */
    private $sso;
    /**
     * Username provided by the SSO provider (updates on token refresh)
     * @var string
     * @ORM\Column(type="string", length=64)
     */
    private $username;
    /**
     * Email provided by the SSO token, this is considered "unique", if someone changes their
     * email then this would in-affect create a new account.
     * @var string
     * @ORM\Column(type="string", length=128)
     */
    private $email;
    /**
     * Either provided by SSO provider or default
     *
     *  DISCORD: https://cdn.discordapp.com/avatars/<USER ID>/<AVATAR ID>.png?size=256
     *
     * @var string
     * @ORM\Column(type="string", length=60, nullable=true)
     */
    private $avatar = UserConstants::AVATAR;
    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    private $patron = UserConstants::DEFAULT_PATRON;
    /**
     * @ORM\OneToMany(targetEntity="UserSession", mappedBy="user")
     */
    private $sessions;
    /**
     * @var string
     * @ORM\Column(type="text", nullable=true)
     */
    private $permissions;
    
    // -- mogboard
    
    /**
     * @ORM\OneToMany(targetEntity="UserList", mappedBy="user")
     */
    private $lists;
    /**
     * @ORM\OneToMany(targetEntity="UserReport", mappedBy="user")
     */
    private $reports;
    /**
     * @ORM\OneToMany(targetEntity="UserCharacter", mappedBy="user")
     */
    private $characters;
    /**
     * @ORM\OneToMany(targetEntity="UserRetainer", mappedBy="user")
     * @ORM\OrderBy({"added" = "DESC"})
     */
    private $retainers;
    /**
     * @ORM\OneToMany(targetEntity="UserAlert", mappedBy="user")
     */
    private $alerts;
    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    private $alertsMax = 0;
    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    private $alertsExpiry = 0;
    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    private $alertsUpdate = false;
    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    private $alertsNotificationCount = 0;
    
    // -- discord sso

    /**
     * @var string
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $ssoDiscordId;
    /**
     * @var string
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $ssoDiscordAvatar;
    /**
     * @var int
     * @ORM\Column(type="integer", nullable=true)
     */
    private $ssoDiscordTokenExpires = 0;
    /**
     * @var string
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $ssoDiscordTokenAccess;
    /**
     * @var string
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $ssoDiscordTokenRefresh;
    
    // -- xivapi
    
    /**
     * User has 1 Key
     * @var string
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    private $apiPublicKey = null;
    /**
     * Google Analytics Key
     * @var string
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    private $apiAnalyticsKey = null;
    /**
     * @var int
     * @ORM\Column(type="integer", options={"default" : 0})
     */
    private $apiRateLimit = ApiRequest::MAX_RATE_LIMIT_KEY;
    
    // ------------------------

    public function __construct()
    {
        $this->id           = Uuid::uuid4();
        $this->added        = time();
        $this->sessions     = new ArrayCollection();
        $this->apiPublicKey = Random::randomAccessKey();
        
        $this->alertsMax    = PatreonConstants::ALERT_DEFAULTS['MAX'];
        $this->alertsExpiry = PatreonConstants::ALERT_DEFAULTS['EXPIRY_TIMEOUT'];
    
        $this->alerts       = new ArrayCollection();
        $this->lists        = new ArrayCollection();
        $this->reports      = new ArrayCollection();
        $this->characters   = new ArrayCollection();
        $this->retainers    = new ArrayCollection();
    }

    public function getAvatar(): string
    {
        if ($this->sso == SignInDiscord::NAME && $this->ssoDiscordAvatar) {
            $this->avatar = sprintf("https://cdn.discordapp.com/avatars/%s/%s.png?size=256",
                $this->ssoDiscordId,
                $this->ssoDiscordAvatar
            );
        }

        return $this->avatar;
    }
    
    public function getId(): string
    {
        return $this->id;
    }
    
    public function setId(string $id)
    {
        $this->id = $id;
        
        return $this;
    }
    
    public function getAdded(): int
    {
        return $this->added;
    }
    
    public function setAdded(int $added)
    {
        $this->added = $added;
        
        return $this;
    }
    
    public function isBanned(): bool
    {
        return $this->banned;
    }
    
    public function setBanned(bool $banned)
    {
        $this->banned = $banned;
        
        return $this;
    }
    
    public function getNotes(): string
    {
        return $this->notes;
    }
    
    public function setNotes(string $notes)
    {
        $this->notes = $notes;
        
        return $this;
    }
    
    public function getSso(): string
    {
        return $this->sso;
    }
    
    public function setSso(string $sso)
    {
        $this->sso = $sso;
        
        return $this;
    }
    
    public function getUsername(): string
    {
        return $this->username;
    }
    
    public function setUsername(string $username)
    {
        $this->username = $username;
        
        return $this;
    }
    
    public function getEmail(): string
    {
        return $this->email;
    }
    
    public function setEmail(string $email)
    {
        $this->email = $email;
        
        return $this;
    }
    
    public function getPatron(): int
    {
        return $this->patron;
    }
    
    public function setPatron(int $patron)
    {
        $this->patron = $patron;
        
        return $this;
    }
    
    public function getSessions()
    {
        return $this->sessions;
    }
    
    public function setSessions($sessions)
    {
        $this->sessions = $sessions;
        
        return $this;
    }
    
    public function getPermissions(): array
    {
        return $this->permissions ? explode(',', $this->permissions) : [];
    }
    
    public function setPermissions(string $permissions)
    {
        $this->permissions = $permissions;
        
        return $this;
    }
    
    public function getLists()
    {
        return $this->lists;
    }
    
    public function setLists($lists)
    {
        $this->lists = $lists;
        
        return $this;
    }
    
    public function getReports()
    {
        return $this->reports;
    }
    
    public function setReports($reports)
    {
        $this->reports = $reports;
        
        return $this;
    }
    
    public function getCharacters()
    {
        return $this->characters;
    }
    
    public function setCharacters($characters)
    {
        $this->characters = $characters;
        
        return $this;
    }
    
    public function getRetainers()
    {
        return $this->retainers;
    }
    
    public function setRetainers($retainers)
    {
        $this->retainers = $retainers;
        
        return $this;
    }
    
    public function getAlerts()
    {
        return $this->alerts;
    }
    
    public function setAlerts($alerts)
    {
        $this->alerts = $alerts;
        
        return $this;
    }
    
    public function getAlertsMax(): int
    {
        return $this->alertsMax;
    }
    
    public function setAlertsMax(int $alertsMax)
    {
        $this->alertsMax = $alertsMax;
        
        return $this;
    }
    
    public function getAlertsExpiry(): int
    {
        return $this->alertsExpiry;
    }
    
    public function setAlertsExpiry(int $alertsExpiry)
    {
        $this->alertsExpiry = $alertsExpiry;
        
        return $this;
    }
    
    public function isAlertsUpdate(): bool
    {
        return $this->alertsUpdate;
    }
    
    public function setAlertsUpdate(bool $alertsUpdate)
    {
        $this->alertsUpdate = $alertsUpdate;
        
        return $this;
    }
    
    public function getAlertsNotificationCount(): int
    {
        return $this->alertsNotificationCount;
    }
    
    public function setAlertsNotificationCount(int $alertsNotificationCount)
    {
        $this->alertsNotificationCount = $alertsNotificationCount;
        
        return $this;
    }
    
    public function getSsoDiscordId(): string
    {
        return $this->ssoDiscordId;
    }
    
    public function setSsoDiscordId(string $ssoDiscordId)
    {
        $this->ssoDiscordId = $ssoDiscordId;
        
        return $this;
    }
    
    public function getSsoDiscordAvatar(): string
    {
        return $this->ssoDiscordAvatar;
    }
    
    public function setSsoDiscordAvatar(string $ssoDiscordAvatar)
    {
        $this->ssoDiscordAvatar = $ssoDiscordAvatar;
        
        return $this;
    }
    
    public function getSsoDiscordTokenExpires(): int
    {
        return $this->ssoDiscordTokenExpires;
    }
    
    public function setSsoDiscordTokenExpires(int $ssoDiscordTokenExpires)
    {
        $this->ssoDiscordTokenExpires = $ssoDiscordTokenExpires;
        
        return $this;
    }
    
    public function getSsoDiscordTokenAccess(): string
    {
        return $this->ssoDiscordTokenAccess;
    }
    
    public function setSsoDiscordTokenAccess(string $ssoDiscordTokenAccess)
    {
        $this->ssoDiscordTokenAccess = $ssoDiscordTokenAccess;
        
        return $this;
    }
    
    public function getSsoDiscordTokenRefresh(): string
    {
        return $this->ssoDiscordTokenRefresh;
    }
    
    public function setSsoDiscordTokenRefresh(string $ssoDiscordTokenRefresh)
    {
        $this->ssoDiscordTokenRefresh = $ssoDiscordTokenRefresh;
        
        return $this;
    }
    
    public function getApiPublicKey(): string
    {
        return $this->apiPublicKey;
    }
    
    public function setApiPublicKey(string $apiPublicKey)
    {
        $this->apiPublicKey = $apiPublicKey;
        
        return $this;
    }
    
    public function getApiAnalyticsKey(): string
    {
        return $this->apiAnalyticsKey;
    }
    
    public function setApiAnalyticsKey(string $apiAnalyticsKey)
    {
        $this->apiAnalyticsKey = $apiAnalyticsKey;
        
        return $this;
    }
    
    public function getApiRateLimit(): int
    {
        return $this->apiRateLimit;
    }
    
    public function setApiRateLimit(int $apiRateLimit)
    {
        $this->apiRateLimit = $apiRateLimit;
        
        return $this;
    }
}
