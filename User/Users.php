<?php

namespace App\Common\User;

use App\Common\Constants\PatreonConstants;
use App\Common\Entity\User;
use App\Common\Entity\UserAlert;
use App\Common\Entity\UserSession;
use App\Common\Exceptions\ApiUnknownPrivateKeyException;
use App\Common\Repository\UserRepository;
use App\Common\ServicesThirdParty\Discord\Discord;
use Delight\Cookie\Cookie;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Users
{
    const COOKIE_SESSION_NAME = 'session';
    const COOKIE_SESSION_DURATION = (60 * 60 * 24 * 30);
    
    /** @var EntityManagerInterface */
    private $em;
    /** @var UserRepository */
    private $repository;
    /** @var SignInInterface */
    private $sso;
    
    public function __construct(EntityManagerInterface $em)
    {
        $this->em         = $em;
        $this->repository = $em->getRepository(User::class);
    }
    
    /**
     * Set the single sign in provider
     */
    public function setSsoProvider(SignInInterface $sso)
    {
        $this->sso = $sso;
        return $this;
    }
    
    /**
     * Get user repository
     */
    public function getRepository(): UserRepository
    {
        return $this->repository;
    }
    
    /**
     * Get the current logged in user
     */
    public function getUser($mustBeOnline = false): ?User
    {
        $session = Cookie::get(self::COOKIE_SESSION_NAME);
        if (!$session || $session === 'x') {
            if ($mustBeOnline) {
                throw new NotFoundHttpException();
            }
            
            return null;
        }
    
        /** @var UserSession $session */
        $session = $this->em->getRepository(UserSession::class)->findOneBy([
            'session' => $session
        ]);
        
        $user = $session ? $session->getUser() : null;
        
        if ($mustBeOnline && !$user) {
            throw new NotFoundHttpException();
        }
        
        if ($session) {
            // update the "last active" time if it's been an hour.
            $timeout = time() - (60 * 60);
            if ($session->getLastActive() < $timeout) {
                $session->setLastActive(time());
                $this->save($user, $session);
            }
        }
        
        return $user;
    }
    
    /**
     * Get a user via their API Key
     */
    public function getUserByApiKey(string $key)
    {
        $user = $this->repository->findOneBy([
            'apiPublicKey' => $key,
        ]);
        
        if (empty($user)) {
            throw new ApiUnknownPrivateKeyException();
        }
        
        return $user;
    }
    
    /**
     * Is the current user online?
     */
    public function isOnline()
    {
        return !empty($this->getUser());
    }
    
    /**
     * Sign in
     */
    public function login(): string
    {
        return $this->sso->getLoginAuthorizationUrl();
    }
    
    /**
     * Logout a user
     */
    public function logout(): void
    {
        $cookie = new Cookie(self::COOKIE_SESSION_NAME);
        $cookie->setValue('x')->setMaxAge(-1)->setPath('/')->save();
        $cookie->delete();
    }
    
    /**
     * Authenticate
     */
    public function authenticate(): User
    {
        // look for their user if they already have an account
        $sso  = $this->sso->setLoginAuthorizationState();
        $user = $this->repository->findOneBy([
            'ssoDiscordId' => $sso->id,
        ]);
        
        // handle user info during login process
        [$user, $session] = $this->handleUser($sso, $user);
        
        // set cookie
        $cookie = new Cookie(self::COOKIE_SESSION_NAME);
        $cookie->setValue($session->getSession())->setMaxAge(self::COOKIE_SESSION_DURATION)->setPath('/')->save();
        
        return $user;
    }
    
    /**
     * Set user information
     */
    public function handleUser(\stdClass $sso, User $user = null): array
    {
        $user = $user ?: new User();
        $user
            ->setSso($sso->name)
            ->setUsername($sso->username)
            ->setEmail($sso->email);
    
        $session = new UserSession($user);
    
        // set discord info
        if ($sso->name === SignInDiscord::NAME) {
            $user
                ->setSsoDiscordId($sso->id)
                ->setSsoDiscordAvatar($sso->avatar)
                ->setSsoDiscordTokenAccess($sso->tokenAccess)
                ->setSsoDiscordTokenExpires($sso->tokenExpires)
                ->setSsoDiscordTokenRefresh($sso->tokenRefresh);
        }
        
        $this->save($user, $session);
        return [
            $user,
            $session
        ];
    }
    
    /**
     * Update a user
     */
    public function save(User $user, UserSession $userSession = null): void
    {
        if ($userSession) {
            $this->em->persist($userSession);
        }
        
        $this->em->persist($user);
        $this->em->flush();
    }
    
    /**
     * Set the last url the user was on
     */
    public function setLastUrl(Request $request)
    {
        $request->getSession()->set('last_url', $request->getUri());
    }
    
    /**
     * Get the last url
     */
    public function getLastUrl(Request $request)
    {
        return $request->getSession()->get('last_url');
    }
    
    /**
     * @param User $user
     */
    public function checkPatreonTierForUser(User $user)
    {
        try {
            $response = Discord::mog()->getUserRole($user->getSsoDiscordId());
        } catch (\Exception $ex) {
            return;
        }
        
        // don't do anything if the response was not a 200
        if ($response->code != 200) {
            return;
        }
        
        $tier = $response->data;
        $tier = $tier ?: 0;
        
        // set patreon tier
        $user->setPatron($tier);
        
        /**
         * Alerts!
         */
        
        // Get Alert Limits
        $benefits = PatreonConstants::ALERT_LIMITS[$tier];
        
        // update user
        $user
            ->setAlertsMax($benefits['MAX'])
            ->setAlertsExpiry($benefits['EXPIRY_TIMEOUT'])
            ->setAlertsUpdate($benefits['UPDATE_TIMEOUT']);
        
        $this->em->persist($user);
            $this->em->flush();
        }

    /**
     * Extends the expiry time of the users alerts.
     */
    public function refreshUsersAlerts()
    {
        $user = $this->getUser(false);
        
        if ($user === null) {
            return;
        }
        
        // 1 hour timeout so we are not constantly updating this users alerts.
        $timeout = time() - (60 * 60);
        
        /** @var UserAlert $alert */
        foreach ($user->getAlerts() as $alert) {
            // ignore if expiry is above timeout
            if ($alert->getExpiry() > $timeout) {
                continue;
            }
            
            $alert->setExpiry(time() + $user->getAlertsExpiry());
            $this->em->persist($alert);
        }
        
        $this->em->flush();
    }
    
    /**
     * Get all patreons
     */
    public function getPatrons()
    {
        return [
            4 => $this->repository->findBy([ 'patron' => 4 ]),
            3 => $this->repository->findBy([ 'patron' => 3 ]),
            2 => $this->repository->findBy([ 'patron' => 2 ]),
            1 => $this->repository->findBy([ 'patron' => 1 ]),
            9 => $this->repository->findBy([ 'patron' => 9 ]),
        ];
    }
}
