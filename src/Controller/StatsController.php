<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserBook;
use App\Repository\UserBookRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StatsController extends AbstractController
{
    #[Route('/stats/{id}', name: 'app_stats', requirements: ['id' => '\d+'])]
    public function index(int $id, UserBookRepository $userBookRepository): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var User $user */
        $user = $this->getUser();

        if (!$user || $user->getId() !== $id) {
            throw $this->createAccessDeniedException();
        }

        $userBooks = $userBookRepository->createQueryBuilder('ub')
            ->addSelect('book')
            ->addSelect('notes')
            ->leftJoin('ub.book', 'book')
            ->leftJoin('ub.notes', 'notes')
            ->where('ub.user = :user')
            ->setParameter('user', $user)
            ->orderBy('ub.created_at', 'DESC')
            ->getQuery()
            ->getResult();

        $stats = $this->computeStats($userBooks);

        return $this->render('stats/index.html.twig', [
            'stats' => $stats,
            'user' => $user,
        ]);
    }

    /**
     * @param list<UserBook> $userBooks
     */
    private function computeStats(array $userBooks): array
    {
        $totalBooks = count($userBooks);
        $completedBooks = 0;
        $currentReads = 0;
        $pagesRead = 0;
        $totalPagesInCollection = 0;
        $progressSum = 0;
        $dailyGoalTotal = 0;
        $authorsCounter = [];
        $noteCount = 0;
        $longestBook = null;
        $shortestBook = null;
        $progressBuckets = [
            '0-25%' => 0,
            '25-50%' => 0,
            '50-75%' => 0,
            '75-99%' => 0,
            '100%' => 0,
        ];
        $booksByMonth = [];
        $upcomingDeadlines = [];
        $earliestEntry = null;

        foreach ($userBooks as $userBook) {
            $book = $userBook->getBook();
            $totalPages = $book?->getTotalPages() ?? 0;
            $currentPage = $userBook->getCurrentPage() ?? 0;

            $totalPagesInCollection += $totalPages;
            $pagesRead += min($currentPage, $totalPages ?: $currentPage);

            $progress = $userBook->getProgress();
            if ($progress === null && $totalPages > 0) {
                $progress = min(100, ($currentPage / $totalPages) * 100);
            }
            $progress ??= 0;
            $progressSum += $progress;

            if ($progress >= 100 || ($totalPages > 0 && $currentPage >= $totalPages)) {
                $completedBooks++;
                $progressBuckets['100%']++;
            } elseif ($progress >= 75) {
                $progressBuckets['75-99%']++;
                $currentReads++;
            } elseif ($progress >= 50) {
                $progressBuckets['50-75%']++;
                $currentReads++;
            } elseif ($progress >= 25) {
                $progressBuckets['25-50%']++;
                $currentReads++;
            } else {
                $progressBuckets['0-25%']++;
                if ($currentPage > 0) {
                    $currentReads++;
                }
            }

            if ($userBook->getDailyGoal()) {
                $dailyGoalTotal += $userBook->getDailyGoal();
            }

            if ($book?->getAuthor()) {
                $authorsCounter[$book->getAuthor()] = ($authorsCounter[$book->getAuthor()] ?? 0) + 1;
            }

            $notesCount = $userBook->getNotes()->count();
            $noteCount += $notesCount;

            if ($totalPages > 0) {
                if (!$longestBook || $totalPages > $longestBook['pages']) {
                    $longestBook = [
                        'title' => $book?->getTitle(),
                        'pages' => $totalPages,
                    ];
                }
                if (!$shortestBook || $totalPages < $shortestBook['pages']) {
                    $shortestBook = [
                        'title' => $book?->getTitle(),
                        'pages' => $totalPages,
                    ];
                }
            }

            $createdAt = $userBook->getCreatedAt();
            if ($createdAt) {
                $key = $createdAt->format('Y-m');
                $booksByMonth[$key] = [
                    'label' => $createdAt->format('M Y'),
                    'count' => ($booksByMonth[$key]['count'] ?? 0) + 1,
                ];

                if (!$earliestEntry || $createdAt < $earliestEntry) {
                    $earliestEntry = $createdAt;
                }
            }

            $deadline = $userBook->getDeadline();
            if ($deadline && $deadline >= new DateTimeImmutable('today')) {
                $upcomingDeadlines[] = [
                    'title' => $book?->getTitle(),
                    'deadline' => $deadline,
                    'remaining' => $deadline->diff(new DateTimeImmutable('today'))->days,
                ];
            }
        }

        usort(
            $upcomingDeadlines,
            fn (array $a, array $b) => $a['deadline'] <=> $b['deadline']
        );
        $upcomingDeadlines = array_slice($upcomingDeadlines, 0, 4);

        ksort($booksByMonth);
        $booksByMonth = array_values($booksByMonth);

        $favoriteAuthor = null;
        if (!empty($authorsCounter)) {
            arsort($authorsCounter);
            $favoriteAuthor = [
                'name' => array_key_first($authorsCounter),
                'count' => reset($authorsCounter),
            ];
        }

        $averageProgress = $totalBooks > 0 ? round($progressSum / $totalBooks) : 0;
        $completionRate = $totalBooks > 0 ? round(($completedBooks / $totalBooks) * 100) : 0;
        $pagesRemaining = max(0, $totalPagesInCollection - $pagesRead);

        $today = new DateTimeImmutable('today');
        $daysActive = $earliestEntry ? ($earliestEntry->diff($today)->days + 1) : 0;
        $speedScore = $daysActive > 0 ? round($pagesRead / $daysActive, 1) : 0;

        $funLabel = $this->buildFunLabel($pagesRead, $completionRate, $noteCount);

        return [
            'totals' => [
                'books' => $totalBooks,
                'completed' => $completedBooks,
                'currentReads' => $currentReads,
                'notes' => $noteCount,
                'favoriteAuthor' => $favoriteAuthor,
                'longestBook' => $longestBook,
                'shortestBook' => $shortestBook,
                'averagePages' => $totalBooks > 0 ? round($totalPagesInCollection / $totalBooks) : 0,
            ],
            'pages' => [
                'read' => $pagesRead,
                'remaining' => $pagesRemaining,
                'total' => $totalPagesInCollection,
                'dailyGoalTotal' => $dailyGoalTotal,
            ],
            'progress' => [
                'average' => $averageProgress,
                'completionRate' => $completionRate,
                'buckets' => $progressBuckets,
            ],
            'timeline' => [
                'readingSince' => $earliestEntry,
                'daysActive' => $daysActive,
                'booksByMonth' => $booksByMonth,
            ],
            'deadlines' => $upcomingDeadlines,
            'speedScore' => $speedScore,
            'funFact' => $funLabel,
        ];
    }

    private function buildFunLabel(int $pagesRead, int $completionRate, int $noteCount): string
    {
        if ($pagesRead === 0) {
            return 'Your adventure is just beginning ✨';
        }

        if ($completionRate >= 80) {
            return 'Certified Page Slayer 🔥';
        }

        if ($noteCount >= 10) {
            return 'Reflection Master — your books are practically journals!';
        }

        if ($pagesRead >= 5000) {
            return 'Epic Saga Survivor — that\'s a mountain of pages!';
        }

        return 'Steady Explorer — consistency beats speed every time.';
    }
}

