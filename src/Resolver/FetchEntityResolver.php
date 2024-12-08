<?php

namespace Teoalboo\DtoValidator\Resolver;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;

class FetchEntityResolver extends DtoProcessorResolver {

    public function __construct(
        private EntityManagerInterface $em
    ) {}

    public function process(mixed $processor, string $propertyName, mixed $dtoValue): mixed {

        if(is_array($dtoValue)){

            $expr = $this->em->getExpressionBuilder();
    
            $processed = $this->em->createQueryBuilder()
                ->select('e')
                ->from($processor->entity, 'e')
                ->where($expr->in("e.{$processor->identifier}", ':value'))
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
            ->getRepository($processor->entity)
            ->findOneBy([ $processor->identifier => $dtoValue ])
        ;

        if(is_null($processed)) {

            $this->throwError($propertyName, 'this-value-is-not-valid');
        }

        return $processed;

    }

}