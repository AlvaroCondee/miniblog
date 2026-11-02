<?php

declare(strict_types=1);

namespace App\Twig;

use Doctrine\DBAL\Connection;
use Twig\Attribute\AsTwigFunction;

final class SiteExtension
{
    public function __construct(private readonly Connection $db)
    {
    }

    /** @return list<array<string, mixed>> */
    #[AsTwigFunction('site_categories')]
    public function categories(): array
    {
        return $this->db->fetchAllAssociative('SELECT * FROM category ORDER BY name');
    }
}
