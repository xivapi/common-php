<?php

namespace XIV\User;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use XIV\Constants\RateLimitConstants;
use XIV\Constants\UserConstants;
use XIV\Utils\Random;

/**
 * @ORM\Table(
 *     name="users",
 *     indexes={
 *          @ORM\Index(name="added", columns={"added"}),
 *          @ORM\Index(name="sso", columns={"sso"}),
 *          @ORM\Index(name="session", columns={"session"}),
 *          @ORM\Index(name="username", columns={"username"}),
 *          @ORM\Index(name="email", columns={"email"}),
 *          @ORM\Index(name="is_banned", columns={"is_banned"}),
 *          @ORM\Index(name="api_public_key", columns={"api_public_key"}),
 *          @ORM\Index(name="api_endpoint_access_suspended", columns={"api_endpoint_access_suspended"}),
 *          @ORM\Index(name="sso_discord_id", columns={"sso_discord_id"}),
 *          @ORM\Index(name="sso_discord_avatar", columns={"sso_discord_avatar"}),
 *          @ORM\Index(name="sso_discord_token_expires", columns={"sso_discord_token_expires"}),
 *          @ORM\Index(name="sso_discord_token_access", columns={"sso_discord_token_access"}),
 *          @ORM\Index(name="sso_discord_token_refresh", columns={"sso_discord_token_refresh"}),
 *          @ORM\Index(name="sso_discord_avatar", columns={"sso_discord_avatar"})
 *     }
 * )
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 */
class User
{
    /**
     * @var string
     * @ORM\Id
     * @ORM\Column(type="guid")
     */
    protected $id;
    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $added;
    /**
     * @var bool
     * @ORM\Column(type="boolean", name="is_banned", options={"default" : 0})
     */
    protected $banned = UserConstants::BANNED;
    /**
     * @var string
     * @ORM\Column(type="text", nullable=true)
     */
    protected $notes;
    /**
     * The name of the SSO provider
     * @var string
     * @ORM\Column(type="string", length=32)
     */
    protected $sso;
    /**
     * A random hash saved to cookie to retrieve the token
     * @var string
     * @ORM\Column(type="string", length=255, unique=true, nullable=true)
     */
    protected $session;
    /**
     * Username provided by the SSO provider (updates on token refresh)
     * @var string
     * @ORM\Column(type="string", length=64)
     */
    protected $username;
    /**
     * Email provided by the SSO token, this is considered "unique", if someone changes their
     * email then this would in-affect create a new account.
     * @var string
     * @ORM\Column(type="string", length=128)
     */
    protected $email;
    /**
     * Either provided by SSO provider or default
     *
     *  DISCORD: https://cdn.discordapp.com/avatars/<USER ID>/<AVATAR ID>.png?size=256
     *
     * @var string
     * @ORM\Column(type="string", length=60, nullable=true)
     */
    protected $avatar = UserConstants::AVATAR;
    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $patron = UserConstants::DEFAULT_PATRON;
    
    // -- discord sso

    /**
     * @var string
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    protected $ssoDiscordId;
    /**
     * @var string
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    protected $ssoDiscordAvatar;
    /**
     * @var int
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $ssoDiscordTokenExpires = 0;
    /**
     * @var string
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    protected $ssoDiscordTokenAccess;
    /**
     * @var string
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    protected $ssoDiscordTokenRefresh;
    
    // ------------------------

    public function __construct()
    {
        $this->id           = Uuid::uuid4();
        $this->added        = time();
        
        $this->generateSession();
    }

    public function generateSession()
    {
        $this->session = Random::randomSecureString(UserConstants::SESSION_LENGTH);
        return;
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
    
    public function getSession(): string
    {
        return $this->session;
    }
    
    public function setSession(string $session)
    {
        $this->session = $session;
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
}
