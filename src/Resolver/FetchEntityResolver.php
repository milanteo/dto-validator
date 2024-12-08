<?php

namespace Teoalboo\DtoValidator\Resolver;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;

class FetchEntityResolver extends DtoResolver {

    public function __construct(
        private EntityManagerInterface $em
    ) {}

    public function resolve(mixed $resolver, string $propertyName, mixed $dtoValue): mixed {

        if(is_array($dtoValue)){

            $expr = $this->em->getExpressionBuilder();
    
            $processed = $this->em->createQueryBuilder()
                ->select('e')
                ->from($resolver->entity, 'e')
                ->where($expr->in("e.{$resolver->identifier}", ':value'))
                ->setParameter('value', $dtoValue)
                ->getQuery()
                ->getResult()
            ;

            if(count($dtoValue) != count($processed)) {

                $this->throwError($propertyName, 'one-or-more-of-the-given-values-is-invalid');
            }

            return new ArrayCollection($processed);

        } 

        $processed = $this->em
            ->getRepository($resolver->entity)
            ->findOneBy([ $resolver->identifier => $dtoValue ])
        ;

        if(is_null($processed)) {

            $this->throwError($propertyName, 'this-value-is-not-valid');
        }

        return $processed;

    }

}