<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Mögliche Orte der Versionsdatei: im Deploy-Paket liegt sie in api/ (von der Pipeline mit
     * Build-Kennung geschrieben), im Repo im Wurzelordner (gepflegt von deployment.sh).
     *
     * @return list<string>
     */
    private function versionsDateien(): array
    {
        return [$this->getProjectDir().'/VERSION', \dirname($this->getProjectDir()).'/VERSION'];
    }

    /**
     * Version der Anwendung als Parameter %app.version% (Swagger-Kopf, Health).
     *
     * @return array<string, mixed>
     */
    protected function getKernelParameters(): array
    {
        $parameter = parent::getKernelParameters();
        $parameter['app.version'] = 'unbekannt';

        foreach ($this->versionsDateien() as $datei) {
            if (is_file($datei)) {
                $parameter['app.version'] = trim((string) file_get_contents($datei)) ?: 'unbekannt';
                break;
            }
        }

        return $parameter;
    }

    protected function build(ContainerBuilder $container): void
    {
        // Ändert sich die Versionsdatei, baut Symfony den Container (dev) neu auf
        foreach ($this->versionsDateien() as $datei) {
            $container->addResource(is_file($datei) ? new FileResource($datei) : new FileExistenceResource($datei));
        }
    }

    /**
     * @return list<string> An array of allowed values for APP_ENV
     */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}
