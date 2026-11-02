<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FrontendController extends AbstractController
{
    private const SELECT = <<<'SQL'
        SELECT e.*, u.display_name AS author, c.name AS category, c.slug AS category_slug
        FROM entry e
        LEFT JOIN user u ON u.id = e.user_id
        LEFT JOIN category c ON c.id = e.category_id
        SQL;

    public function __construct(private readonly Connection $db)
    {
    }

    #[Route('/', name: 'homepage', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('frontend/main.html.twig', [
            'entries' => $this->db->fetchAllAssociative(self::SELECT." WHERE e.status = 'published' ORDER BY e.created_at DESC, e.id DESC"),
            'category' => null,
        ]);
    }

    #[Route('/categoria/{slug}', name: 'category', methods: ['GET'])]
    public function category(string $slug): Response
    {
        $category = $this->db->fetchAssociative('SELECT * FROM category WHERE slug = ?', [$slug]);
        if (false === $category) {
            throw $this->createNotFoundException();
        }

        return $this->render('frontend/main.html.twig', [
            'entries' => $this->db->fetchAllAssociative(
                self::SELECT." WHERE e.status = 'published' AND e.category_id = ? ORDER BY e.created_at DESC, e.id DESC",
                [$category['id']],
            ),
            'category' => $category,
        ]);
    }

    #[Route('/entrada/{id}', name: 'entry_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $entry = $this->db->fetchAssociative(self::SELECT." WHERE e.id = ? AND e.status = 'published'", [$id]);
        if (false === $entry) {
            throw $this->createNotFoundException();
        }

        return $this->render('frontend/show.html.twig', ['entry' => $entry]);
    }
}
