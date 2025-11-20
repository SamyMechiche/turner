<?php

namespace App\Controller;

use App\Repository\UserBookRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CollectionController extends AbstractController
{
    #[Route('/collection', name: 'app_collection')]
    public function index(Request $request, UserBookRepository $userBookRepository): Response
    {
        // Ensure user is logged in
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $user = $this->getUser();
        
        // Get sort parameter from request, default to date_desc (newest first)
        $sort = $request->query->get('sort', 'date_desc');
        
        // Build query
        $queryBuilder = $userBookRepository->createQueryBuilder('ub')
            ->innerJoin('ub.book', 'b')
            ->addSelect('b')
            ->where('ub.user = :user')
            ->setParameter('user', $user);
        
        // Apply sorting based on parameter
        switch ($sort) {
            case 'date_asc':
                $queryBuilder->orderBy('ub.created_at', 'ASC');
                break;
            case 'date_desc':
                $queryBuilder->orderBy('ub.created_at', 'DESC');
                break;
            case 'name_asc':
                $queryBuilder->orderBy('b.title', 'ASC');
                break;
            case 'name_desc':
                $queryBuilder->orderBy('b.title', 'DESC');
                break;
            default:
                // Default to date_desc if invalid sort parameter
                $queryBuilder->orderBy('ub.created_at', 'DESC');
                $sort = 'date_desc';
        }
        
        $userBooks = $queryBuilder->getQuery()->getResult();

        return $this->render('collection/index.html.twig', [
            'userBooks' => $userBooks,
            'currentSort' => $sort,
        ]);
    }
}

