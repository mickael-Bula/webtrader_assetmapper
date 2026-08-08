<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Attribute\AsTwigFunction;

/**
 * @noinspection PhpUnused La classe est instanciée dynamiquement par Twig.
 */
class ChartUrlExtension
{
    private string $publicDir;

    public function __construct(string $projectDir)
    {
        // On prépare le chemin vers le dossier public
        $this->publicDir = $projectDir . '/public';
    }

    #[AsTwigFunction('chart_url')]
    public function getChartUrl(string $path): string
    {
        $fullPath = $this->publicDir . $path;

        if (file_exists($fullPath)) {
            // filemtime renvoie le timestamp de modification (ex: 1734730000)
            return $path . '?v=' . filemtime($fullPath);
        }

        // Si le fichier n'existe pas, on renvoie le chemin brut pour éviter de casser l'image
        return $path;
    }
}
