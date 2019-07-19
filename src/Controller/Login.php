<?php
/*
 * This file is part of Skletter <https://github.com/2DSharp/Skletter>.
 *
 * (c) Dedipyaman Das <2d@twodee.me>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Skletter\Controller;


use Greentea\Core\Controller;
use Phypes\Exception\EmptyRequiredValue;
use Phypes\Exception\InvalidValue;
use Phypes\Type\Password;
use Phypes\Type\StringRequired;
use Skletter\Exception\InvalidIdentifier;
use Skletter\Exception\PasswordMismatch;
use Skletter\Exception\UserDoesNotExistException;
use Skletter\Model\DTO\LoginState;
use Skletter\Model\Entity\StandardIdentity;
use Skletter\Model\Service\IdentityMap;
use Skletter\Model\Service\LoginManager;
use Symfony\Component\HttpFoundation\Request;

class Login implements Controller
{
    /**
     * LoginManager service to handle authentication and log in system
     * @var LoginManager $loginManager
     */
    private $loginManager;
    /**
     * Service to look up identity based on some identifier and build a valid instance
     * @var IdentityMap $identityLookup
     */
    private $identityLookup;
    /**
     * Data Transfer Object to carry forward the login data to the view
     * @var LoginState
     */
    private $state;

    public function __construct(LoginManager $loginManager, IdentityMap $identityLookup, LoginState $state)
    {
        $this->loginManager = $loginManager;
        $this->identityLookup = $identityLookup;
        $this->state = $state;
    }

    /**
     * @param Request $request
     * @request_type POST
     * @throws \Phypes\Exception\InvalidRule
     */
    public function attemptLogin(Request $request)
    {
        try {
            $identifier = $request->request->get('identity');
            $rawPassword = new Password(new StringRequired($request->request->get('password')));
            /**
             * @var StandardIdentity $identity
             */
            $identity = $this->identityLookup->getStandardIdentity($identifier);

            // Set session data, log stuff, update db
            $this->loginManager->loginWithPassword($identity, $rawPassword);

            $this->state->setIdentity($identity);

        } catch (UserDoesNotExistException | InvalidIdentifier $exception) {
            $this->state->setError('The username or email you have entered does not belong to any account.');

        } catch (PasswordMismatch | InvalidValue $e) {
            $this->state->setError('The password you entered is incorrect');
        } catch (EmptyRequiredValue $e) {
            $this->state->setError('You must fill in the all the fields');
        }

    }

    public function handleRequest(Request $request, string $method): void
    {
        $this->{$method}($request);
    }
}