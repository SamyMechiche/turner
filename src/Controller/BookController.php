<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\Note;
use App\Entity\UserBook;
use App\Form\BookFormType;
use App\Form\NoteFormType;
use App\Form\UserBookFormType;
use App\Repository\BookRepository;
use App\Repository\UserBookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class BookController extends AbstractController
{
    public function __construct(private CsrfTokenManagerInterface $csrfTokenManager)
    {
    }

    #[Route('/book/add', name: 'app_book_add')]
    public function add(
        Request $request,
        EntityManagerInterface $entityManager,
        BookRepository $bookRepository,
        UserBookRepository $userBookRepository
    ): Response {
        // Ensure user is logged in
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $user = $this->getUser();
        $book = new Book();
        $form = $this->createForm(BookFormType::class, $book);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check if book already exists in database
            $existingBook = $bookRepository->findExistingBook(
                $book->getTitle(),
                $book->getAuthor()
            );

            if ($existingBook) {
                // Use existing book
                $book = $existingBook;
            } else {
                // Persist new book
                $entityManager->persist($book);
                $entityManager->flush(); // Flush to get the book ID
            }

            // Check if user already has this book in their collection
            $existingUserBook = $userBookRepository->createQueryBuilder('ub')
                ->where('ub.user = :user')
                ->andWhere('ub.book = :book')
                ->setParameter('user', $user)
                ->setParameter('book', $book)
                ->getQuery()
                ->getOneOrNullResult();

            if ($existingUserBook) {
                $this->addFlash('warning', 'This book is already in your collection!');
                return $this->redirectToRoute('app_book_add');
            }

            // Create UserBook relationship
            $userBook = new UserBook();
            $userBook->setUser($user);
            $userBook->setBook($book);
            
            $entityManager->persist($userBook);
            $entityManager->flush();

            $this->addFlash('success', 'Book added to your collection successfully!');
            return $this->redirectToRoute('app_logged_home');
        }

        return $this->render('book/add.html.twig', [
            'bookForm' => $form,
        ]);
    }

    #[Route('/book/{id}', name: 'app_book_show', requirements: ['id' => '\d+'])]
    public function show(
        int $id,
        BookRepository $bookRepository,
        UserBookRepository $userBookRepository
    ): Response {
        // Ensure user is logged in
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $user = $this->getUser();
        
        // Find the book
        $book = $bookRepository->find($id);
        
        if (!$book) {
            throw $this->createNotFoundException('Book not found');
        }
        
        // Find the UserBook relationship for this user and book
        $userBook = $userBookRepository->createQueryBuilder('ub')
            ->innerJoin('ub.book', 'b')
            ->addSelect('b')
            ->leftJoin('ub.notes', 'n')
            ->addSelect('n')
            ->where('ub.user = :user')
            ->andWhere('ub.book = :book')
            ->setParameter('user', $user)
            ->setParameter('book', $book)
            ->orderBy('n.created_at', 'DESC')
            ->getQuery()
            ->getOneOrNullResult();

        // Create form for setting objectives
        $form = null;
        $noteForm = null;
        if ($userBook) {
            $form = $this->createForm(UserBookFormType::class, $userBook, [
                'book' => $book,
                'userBook' => $userBook,
            ]);
            $noteForm = $this->createForm(NoteFormType::class, new Note(), [
                'action' => $this->generateUrl('app_book_add_note', ['id' => $book->getId()]),
            ]);
        }

        return $this->render('book/show.html.twig', [
            'book' => $book,
            'userBook' => $userBook,
            'form' => $form?->createView(),
            'noteForm' => $noteForm?->createView(),
        ]);
    }

    #[Route('/book/{id}/update', name: 'app_book_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(
        int $id,
        Request $request,
        BookRepository $bookRepository,
        UserBookRepository $userBookRepository,
        EntityManagerInterface $entityManager
    ): Response {
        // Ensure user is logged in
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $user = $this->getUser();
        
        // Find the book
        $book = $bookRepository->find($id);
        
        if (!$book) {
            throw $this->createNotFoundException('Book not found');
        }
        
        // Find or create UserBook relationship
        $userBook = $userBookRepository->createQueryBuilder('ub')
            ->where('ub.user = :user')
            ->andWhere('ub.book = :book')
            ->setParameter('user', $user)
            ->setParameter('book', $book)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$userBook) {
            // Create new UserBook if it doesn't exist
            $userBook = new UserBook();
            $userBook->setUser($user);
            $userBook->setBook($book);
        }

        $form = $this->createForm(UserBookFormType::class, $userBook, [
            'book' => $book,
            'userBook' => $userBook,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $objectiveType = $form->get('objective_type')->getData();
            $currentPage = $userBook->getCurrentPage() ?? 0;
            $totalPages = $book->getTotalPages();
            $remainingPages = max(0, $totalPages - $currentPage);

            // Calculate based on objective type
            if ($objectiveType === 'daily_goal' && $userBook->getDailyGoal() && $remainingPages > 0) {
                // Calculate deadline based on daily goal
                $daysNeeded = ceil($remainingPages / $userBook->getDailyGoal());
                $today = new \DateTimeImmutable('today');
                $deadline = $today->modify("+{$daysNeeded} days");
                $userBook->setDeadline($deadline);
            } elseif ($objectiveType === 'deadline' && $userBook->getDeadline() && $remainingPages > 0) {
                // Calculate daily goal based on deadline
                $today = new \DateTimeImmutable('today');
                $deadline = $userBook->getDeadline();
                $daysRemaining = max(1, $today->diff($deadline)->days);
                
                $dailyGoal = ceil($remainingPages / $daysRemaining);
                $userBook->setDailyGoal($dailyGoal);
            } elseif ($remainingPages <= 0) {
                // Book is already finished, clear objectives
                $userBook->setDailyGoal(null);
                $userBook->setDeadline(null);
            }

            // Calculate progress percentage
            if ($currentPage > 0 && $totalPages > 0) {
                $progress = ($currentPage / $totalPages) * 100;
                $userBook->setProgress($progress);
            }

            $entityManager->persist($userBook);
            $entityManager->flush();

            $this->addFlash('success', 'Objectives updated successfully!');
            return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
        }

        // If form has errors, redirect back with flash message
        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Please check your input and try again.');
        }

        return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
    }

    #[Route('/book/{id}/note', name: 'app_book_add_note', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function addNote(
        int $id,
        Request $request,
        BookRepository $bookRepository,
        UserBookRepository $userBookRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $book = $bookRepository->find($id);

        if (!$book) {
            throw $this->createNotFoundException('Book not found');
        }

        $user = $this->getUser();

        $userBook = $userBookRepository->findOneBy([
            'user' => $user,
            'book' => $book,
        ]);

        if (!$userBook) {
            $this->addFlash('error', 'This book is not in your collection yet.');
            return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
        }

        $note = new Note();
        $form = $this->createForm(NoteFormType::class, $note);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $note->setUserBook($userBook);
            $entityManager->persist($note);
            $entityManager->flush();

            $this->addFlash('success', 'Note added successfully!');
        } else {
            $this->addFlash('error', 'Could not save your note. Please try again.');
        }

        return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
    }

    #[Route('/book/{id}/progress/add-daily', name: 'app_book_add_daily', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function addDailyProgress(
        int $id,
        Request $request,
        BookRepository $bookRepository,
        UserBookRepository $userBookRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['message' => 'Invalid request.'], Response::HTTP_BAD_REQUEST);
        }

        $book = $bookRepository->find($id);

        if (!$book) {
            return new JsonResponse(['message' => 'Book not found.'], Response::HTTP_NOT_FOUND);
        }

        $userBook = $userBookRepository->findOneBy([
            'user' => $this->getUser(),
            'book' => $book,
        ]);

        if (!$userBook) {
            return new JsonResponse(['message' => 'Book not in collection.'], Response::HTTP_BAD_REQUEST);
        }

        $tokenValue = $request->headers->get('X-CSRF-TOKEN');
        $token = new CsrfToken('add_daily_' . $userBook->getId(), $tokenValue);
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            return new JsonResponse(['message' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        $dailyGoal = $userBook->getDailyGoal();
        $totalPages = $book->getTotalPages();

        if (!$dailyGoal || !$totalPages) {
            return new JsonResponse(['message' => 'Daily goal or total pages missing.'], Response::HTTP_BAD_REQUEST);
        }

        $currentPage = $userBook->getCurrentPage() ?? 0;
        $remainingPages = max(0, $totalPages - $currentPage);

        if ($totalPages > 0) {
            $progress = ($currentPage / $totalPages) * 100;
        } else {
            $progress = null;
        }

        if ($remainingPages === 0) {
            return new JsonResponse([
                'currentPage' => $currentPage,
                'totalPages' => $totalPages,
                'progressPercent' => $progress !== null ? round($progress) : null,
                'isComplete' => true,
                'message' => 'You have already finished this book.',
            ], Response::HTTP_OK);
        }

        $pagesToAdd = min($dailyGoal, $remainingPages);
        $currentPage += $pagesToAdd;
        $userBook->setCurrentPage($currentPage);

        if ($totalPages > 0) {
            $progress = ($currentPage / $totalPages) * 100;
            $userBook->setProgress($progress);
        } else {
            $progress = null;
        }

        $entityManager->persist($userBook);
        $entityManager->flush();

        return new JsonResponse([
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'progressPercent' => $progress !== null ? round($progress) : null,
            'isComplete' => $currentPage >= $totalPages,
        ]);
    }
}

