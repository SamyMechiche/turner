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

        // Categorize books based on daily_goal and completion status
        $currentReadings = [];
        $toRead = [];
        $finished = [];

        foreach ($userBooks as $userBook) {
            $book = $userBook->getBook();
            $currentPage = $userBook->getCurrentPage() ?? 0;
            $totalPages = $book->getTotalPages() ?? 0;
            $dailyGoal = $userBook->getDailyGoal() ?? 0;
            $progress = $userBook->getProgress() ?? 0;

            // Check if book is finished (100% progress or current_page >= total_pages)
            if ($totalPages > 0 && ($progress >= 100 || $currentPage >= $totalPages)) {
                $finished[] = $userBook;
            } elseif ($dailyGoal > 0) {
                // Book has objectives set (daily_goal > 0) - move to Current Readings
                $currentReadings[] = $userBook;
            } else {
                // Book has no objectives set - stays in To Read
                $toRead[] = $userBook;
            }
        }

        return $this->render('logged_home/index.html.twig', [
            'currentReadings' => $currentReadings,
            'toRead' => $toRead,
            'finished' => $finished,
        ]);
    }
}
