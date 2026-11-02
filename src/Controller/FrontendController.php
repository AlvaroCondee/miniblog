<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FrontendController extends AbstractController
{
    #[Route('/', name: 'homepage', methods: ['GET'])]
    public function index(Connection $db): Response
    {
        return $this->render('frontend/main.html.twig', [
            'entries' => $db->fetchAllAssociative('SELECT * FROM entry ORDER BY created_at DESC, id DESC'),
        ]);
    }
}
