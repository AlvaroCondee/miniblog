<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Subida de imágenes desde el editor (protocolo "simpleUpload" de CKEditor 5). */
#[IsGranted('ROLE_USER')]
final class UploadController extends AbstractController
{
    private const MAX_SIZE = '3M';
    private const MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct(#[Autowire('%kernel.project_dir%/public/uploads')] private readonly string $uploadDir)
    {
    }

    #[Route('/admin/upload', name: 'upload', methods: ['POST'])]
    public function upload(Request $request, ValidatorInterface $validator): JsonResponse
    {
        if (!$this->isCsrfTokenValid('upload', (string) $request->headers->get('X-CSRF-TOKEN'))) {
            return $this->error('Sesión caducada. Recarga la página.', 403);
        }

        $file = $request->files->get('upload');
        if (!$file instanceof UploadedFile) {
            return $this->error('No se ha recibido ninguna imagen.');
        }

        if (!$file->isValid()) {
            // Normalmente: supera upload_max_filesize / post_max_size de php.ini
            $max = min(3 * 1024 * 1024, UploadedFile::getMaxFilesize());

            return $this->error(sprintf('No se ha podido subir la imagen. Tamaño máximo: %s MB.', round($max / 1048576, 1)));
        }

        $violations = $validator->validate($file, new Assert\Image(
            maxSize: self::MAX_SIZE,
            mimeTypes: self::MIME_TYPES,
            maxSizeMessage: 'La imagen supera el máximo de 3 MB.',
            mimeTypesMessage: 'Solo se admiten imágenes JPG, PNG, GIF o WebP.',
        ));
        if (\count($violations) > 0) {
            return $this->error($violations[0]->getMessage());
        }

        $dir = $this->uploadDir.'/'.date('Y/m');
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return $this->error('No se puede guardar la imagen en el servidor.', 500);
        }

        // Nombre aleatorio y extensión deducida del contenido real, nunca del nombre original
        $name = bin2hex(random_bytes(8)).'.'.$file->guessExtension();
        $file->move($dir, $name);

        return new JsonResponse(['url' => $request->getBasePath().'/uploads/'.date('Y/m').'/'.$name]);
    }

    private function error(string $message, int $status = 400): JsonResponse
    {
        return new JsonResponse(['error' => ['message' => $message]], $status);
    }
}
