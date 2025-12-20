<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ChartUrlExtension extends AbstractExtension
{
    private string $publicDir;

    public function __construct(string $projectDir)
    {
        // On récupère le chemin vers /public
        $this->publicDir = $projectDir . '/public';
    }

    public function getFunctions(): array
    {
        return [
            // On crée une fonction Twig nommée 'chart_url'
            new TwigFunction('chart_url', [$this, 'getChartUrl']),
        ];
    }

    public function getChartUrl(string $path): string
    {
        $fullPath = $this->publicDir . $path;

        if (file_exists($fullPath)) {
            // On ajoute le timestamp de modification du fichier
            return $path . '?v=' . filemtime($fullPath);
        }

        return $path;
    }
}
