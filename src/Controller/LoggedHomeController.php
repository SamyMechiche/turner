<?php

namespace App\Controller;

use App\Repository\UserBookRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LoggedHomeController extends AbstractController
{
    #[Route('/logged/home', name: 'app_logged_home')]
    public function index(UserBookRepository $userBookRepository): Response
    {
        // Ensure user is logged in
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $user = $this->getUser();
        
        // Fetch all user books with their related book data
        $userBooks = $userBookRepository->createQueryBuilder('ub')
            ->innerJoin('ub.book', 'b')
            ->addSelect('b')
            ->where('ub.user = :user')
            ->setParameter('user', $user)
            ->orderBy('ub.created_at', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('logged_home/index.html.twig', [
            'userBooks' => $userBooks,
        ]);
    }
}
