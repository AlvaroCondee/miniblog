<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FrontendController extends AbstractController
{
    private const PER_PAGE = 10;

    private const FROM = <<<'SQL'
        FROM entry e
        LEFT JOIN user u ON u.id = e.user_id
        LEFT JOIN category c ON c.id = e.category_id
        WHERE e.status = 'published'
        SQL;

    private const COLUMNS = 'e.*, u.display_name AS author, c.name AS category, c.slug AS category_slug';

    public function __construct(private readonly Connection $db)
    {
    }

    #[Route('/', name: 'homepage', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->render('frontend/main.html.twig', [
            'category' => null,
            'query' => null,
            'route' => 'homepage',
            'route_params' => [],
        ] + $this->paginate($request, '', []));
    }

    #[Route('/categoria/{slug}', name: 'category', methods: ['GET'])]
    public function category(Request $request, string $slug): Response
    {
        $category = $this->db->fetchAssociative('SELECT * FROM category WHERE slug = ?', [$slug]);
        if (false === $category) {
            throw $this->createNotFoundException();
        }

        return $this->render('frontend/main.html.twig', [
            'category' => $category,
            'query' => null,
            'route' => 'category',
            'route_params' => ['slug' => $slug],
        ] + $this->paginate($request, ' AND e.category_id = ?', [$category['id']]));
    }

    #[Route('/buscar', name: 'search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $data = ['entries' => [], 'page' => 1, 'pages' => 1];

        if (mb_strlen($q) >= 2) {
            // Escapamos los comodines de LIKE para que se busque el texto literal
            $like = '%'.addcslashes($q, '%_\\').'%';
            $data = $this->paginate(
                $request,
                " AND (e.title LIKE ? ESCAPE '\\' OR e.content LIKE ? ESCAPE '\\')",
                [$like, $like],
            );
        }

        return $this->render('frontend/main.html.twig', [
            'category' => null,
            'query' => $q,
            'route' => 'search',
            'route_params' => ['q' => $q],
        ] + $data);
    }

    #[Route('/entrada/{id}', name: 'entry_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $entry = $this->db->fetchAssociative(
            'SELECT '.self::COLUMNS.' '.self::FROM.' AND e.id = ?',
            [$id],
        );
        if (false === $entry) {
            throw $this->createNotFoundException();
        }

        return $this->render('frontend/show.html.twig', ['entry' => $entry]);
    }

    #[Route('/rss', name: 'rss', methods: ['GET'])]
    public function rss(): Response
    {
        $response = $this->render('frontend/rss.xml.twig', [
            'entries' => $this->db->fetchAllAssociative(
                'SELECT '.self::COLUMNS.' '.self::FROM.' ORDER BY e.created_at DESC, e.id DESC LIMIT 20',
            ),
        ]);
        $response->headers->set('Content-Type', 'application/rss+xml; charset=UTF-8');

        return $response;
    }

    #[Route('/contacto', name: 'contact', methods: ['GET'])]
    public function contact(): Response
    {
        return $this->render('frontend/contact.html.twig');
    }

    /**
     * @param list<mixed> $params
     *
     * @return array{entries: list<array<string, mixed>>, page: int, pages: int}
     */
    private function paginate(Request $request, string $extraWhere, array $params): array
    {
        $total = (int) $this->db->fetchOne('SELECT COUNT(*) '.self::FROM.$extraWhere, $params);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($pages, max(1, $request->query->getInt('page', 1)));

        $entries = $this->db->fetchAllAssociative(
            'SELECT '.self::COLUMNS.' '.self::FROM.$extraWhere.' ORDER BY e.created_at DESC, e.id DESC LIMIT '.self::PER_PAGE.' OFFSET '.(($page - 1) * self::PER_PAGE),
            $params,
        );

        return ['entries' => $entries, 'page' => $page, 'pages' => $pages];
    }
}
