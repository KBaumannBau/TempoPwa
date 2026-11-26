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
     * Possible locations of the version file: in the deploy package it sits in api/ (written by the pipeline
     * with the build id), in the repo in the root folder (maintained by deployment.sh).
     *
     * @return list<string>
     */
    private function versionFiles(): array
    {
        return [$this->getProjectDir().'/VERSION', \dirname($this->getProjectDir()).'/VERSION'];
    }

    /**
     * Application version as parameter %app.version% (Swagger header, health).
     *
     * @return array<string, mixed>
     */
    protected function getKernelParameters(): array
    {
        $parameters = parent::getKernelParameters();
        $parameters['app.version'] = 'unknown';

        foreach ($this->versionFiles() as $file) {
            if (is_file($file)) {
                $parameters['app.version'] = trim((string) file_get_contents($file)) ?: 'unknown';
                break;
            }
        }

        return $parameters;
    }

    protected function build(ContainerBuilder $container): void
    {
        // When the version file changes, Symfony rebuilds the container (dev)
        foreach ($this->versionFiles() as $file) {
            $container->addResource(is_file($file) ? new FileResource($file) : new FileExistenceResource($file));
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
