<?php

namespace App\Common\User;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use XIV\Constants\UserConstants;
use XIV\Utils\Random;

/**
 * @ORM\Table(
 *     name="users_sessions",
 *     indexes={
 *          @ORM\Index(name="session", columns={"session"})
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
     * @var string
     * @ORM\Column(type="string", length=255, unique=true)
     */
    protected $session;
    /**
     * @var User
     * @ORM\ManyToOne(targetEntity="User", inversedBy="sessions")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    protected $user;

    public function __construct(User $user)
    {
        $this->id    = Uuid::uuid4();
        $this->added = time();
        $this->user  = $user;
        $this->generateSession();
    }
    
    public function generateSession()
    {
        $this->session = Random::randomSecureString(UserConstants::SESSION_LENGTH);
        return;
    }
}
