<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LoggedHomeController extends AbstractController
{
    #[Route('/logged/home', name: 'app_logged_home')]
    public function index(): Response
    {
        return $this->render('logged_home/index.html.twig', [
            'controller_name' => 'LoggedHomeController',
        ]);
    }
}
