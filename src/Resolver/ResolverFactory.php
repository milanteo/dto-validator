<?php

namespace Teoalboo\DtoValidator\Resolver;

use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ResolverFactory {
    public function __construct(private ContainerInterface $container) { }

    public function createResolver(string $className) {

        // Ottiene i riflessi del costruttore della classe
        $reflectionClass = new ReflectionClass($className);
        
        $constructor = $reflectionClass->getConstructor();

        // Se la classe non ha costruttore, la istanziamo direttamente
        if (!$constructor) {
            return new $className();
        }

        // Risolve i parametri del costruttore
        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $dependencyClass = $parameter->getType()->getName();
            $dependencies[] = $this->container->get($dependencyClass);
        }

        // Crea una nuova istanza della classe con le dipendenze risolte
        return $reflectionClass->newInstanceArgs($dependencies);
    }
}
