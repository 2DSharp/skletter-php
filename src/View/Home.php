<?php
/*
 * This file is part of Skletter <https://github.com/2DSharp/Skletter>.
 *
 * (c) Dedipyaman Das <2d@twodee.me>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Skletter\View;


use Greentea\Exception\TemplatingException;
use Skletter\Model\Service\LoginManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class Home extends AbstractView
{
    private $templating;
    private $loginManager;
    private $session;

    private $pageMap = [
        'Temp' => 'registration.twig'
    ];

    private $paramMap = [

    ];


    public function __construct(\Twig\Environment $twig, LoginManager $loginManager, SessionInterface $session)
    {
        $this->templating = $twig;
        $this->loginManager = $loginManager;
        $this->session = $session;
    }

    /**
     * @param Request $request
     * @return Response
     * @throws \Twig\Error\Error
     */
    private function showLoggedOutHome(Request $request): Response
    {

        $html = $this->createHTMLFromTemplate($this->templating, 'pages/home.twig',
            ['title' => 'Skletter - Home']);
        return $this->respond($request, $html);
    }


    private function getParams(string $status): array
    {
        switch ($status) {
            case 'Temp':
                return [
                    'title' => 'Confirm your email - Skletter',
                    'status' => 'Temp',
                    'email' => $this->session->get('email')
                ];
        }
    }
    /**
     * @param string $status
     * @param Request $request
     * @return Response
     * @throws \Twig\Error\Error
     */
    private function showLoggedInHome(string $status, Request $request)
    {
        $html = $this->createHTMLFromTemplate($this->templating,
            'pages/' . $this->pageMap[$status],
            $this->getParams($status));
        return $this->respond($request, $html);
    }

    /**
     * @param Request $request
     * @return Response
     * @throws \Twig\Error\Error
     */
    public function main(Request $request): Response
    {
        if ($this->loginManager->isLoggedIn()) {
            return $this->showLoggedInHome($this->session->get('status'), $request);
        }
            // Need to manage unauthorized post requests sent to this
        return $this->showLoggedOutHome($request);
    }

    /**
     * @param Request $request
     * @param string $method
     * @return Response
     * @throws TemplatingException
     */
    public function createResponse(Request $request, string $method): Response
    {
        try {
            return $this->{$method}($request);
        } catch (\Twig\Error\Error $e) {
            throw new TemplatingException($e->getMessage(), $e->getCode(), $e);
        }
    }

}