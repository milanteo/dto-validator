<?php

namespace Teoalboo\DtoValidator;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

class DtoValidatorBundle extends AbstractBundle {

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void {
        
        $container->import('./services.yaml');
    }
    
}