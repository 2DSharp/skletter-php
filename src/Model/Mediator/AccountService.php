<?php
/*
 * This file is part of Skletter <https://github.com/2DSharp/Skletter>.
 *
 * (c) Dedipyaman Das <2d@twodee.me>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Skletter\Model\Mediator;

use Skletter\Factory\CookieFactory;
use Skletter\Model\Entity\CurrentUser;
use Skletter\Model\LocalService\SessionManager;
use Skletter\Model\RemoteService\DTO\Error;
use Skletter\Model\RemoteService\DTO\LoginMetadata;
use Skletter\Model\RemoteService\DTO\UserDTO;
use Skletter\Model\RemoteService\Romeo\RomeoClient;
use Skletter\Model\ValueObject\Result;

/**
 * A mediator between the Remote services to enable registration
 * Class RegistrationService
 * @package Skletter\Model\Service
 */
class AccountService
{
    const CONFIRMATION_TOKEN = 0;
    const CONFIRMATION_PIN = 1;
    /**
     * Remote user management service
     * @var RomeoClient $userService
     */
    private RomeoClient $userService;
    /**
     * Local session management service
     * @var SessionManager $session
     */
    private SessionManager $session;

    /**
     * AccountService constructor.
     * @param RomeoClient $userService
     * @param SessionManager $session
     */
    public function __construct(RomeoClient $userService, SessionManager $session)
    {
        $this->userService = $userService;
        $this->session = $session;
    }

    /**
     * Create a new user account
     *
     * @param array $data
     * @return Result
     */
    public function register(array $data)
    {
        try {
            $user = new UserDTO();
            $user->name = $data['name'];
            $user->username = $data['username'];
            $user->email = $data['email'];
            $user->password = $data['password'];
            $user->ipAddr = $data['ip-address'];

            $returnedUser = $this->userService->registerNew($user);

            if ($returnedUser->notification != null) {
                return new Result(false, $returnedUser->notification->errors);
            }

            return new Result(true);
        } catch (\TException $e) {
            $err = new Error();
            $err->message = "Something went wrong. Try again.";
            return new Result(false, ["global" => $err]);
        }
    }

    /**
     * @param string $identifier
     * @param string $password
     * @param array $params
     * @return Result
     * @throws \Exception
     */
    public function loginWithPassword(string $identifier, string $password, array $params): Result
    {
        try {
            $meta = new LoginMetadata();
            $meta->ipAddr = $params['ip-address'];
            $meta->headers = $params['user-agent'];
            $meta->localSessionId = $this->session->getId();

            $cookieDTO = $this->userService->loginWithPassword($identifier, $password, $meta);
            if ($cookieDTO->notification != null) {
                return new Result(false, $cookieDTO->notification->errors);
            }
            $user = CurrentUser::buildFromDTO($cookieDTO->user);
            $this->session->storeLoginDetails($user);

            $cookie = CookieFactory::createSignedCookie($cookieDTO, "persistence", $params['user-agent']);

            $result = new Result(true);
            $result->setValueObject($cookie);

            return $result;
        } catch (\TException $e) {
            $err = new Error();
            $err->message = "Something went wrong. Try again.";
            return new Result(false, ["global" => $err]);
        }
    }

    public function confirmAccount(int $id, string $token, int $type)
    {
        try {
            switch ($type) {
                case self::CONFIRMATION_PIN:
                    $dto = $this->userService->verifyPin($id, $token);
                    $user = CurrentUser::buildFromDTO($dto);
                    $this->session->storeLoginDetails($user);
                    break;
                case self::CONFIRMATION_TOKEN:
                    $dto = $this->userService->verifyToken($id, $token);
                    break;
            }

            if ($dto->notification != null) {
                return new Result(false, $dto->notification->errors);
            }

            $result = new Result(true);
            $result->setValueObject($dto);

            return $result;
        } catch (\TException $e) {
            $err = new Error();
            $err->message = "Something went wrong. Try again.";
            return new Result(false, ["global" => $err]);
        }
    }

    public function getSessionUser()
    {
        return $this->session->getLoginDetails();
    }

    public function updateProfilePicture(int $id, string $imageId)
    {
        $this->userService->updateProfileImage($id, $imageId);
    }

    public function getProfilePicture(string $username): string
    {
        return $this->userService->getProfileImage($username);
    }
}