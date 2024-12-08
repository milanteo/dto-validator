<?php

namespace Teoalboo\DtoValidator\Validator;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class NotExistValidator extends ConstraintValidator {

    public function __construct(
        private EntityManagerInterface $em
    ) { }

    public function validate(mixed $value, Constraint $constraint): void
    {
        /* @var NotExist $constraint */

        if (null === $value || '' === $value) {
            return;
        }

        if(is_array($value)){

            $expr = $this->em->getExpressionBuilder();

            $processed = $this->em->createQueryBuilder()
                ->select('e')
                ->from($constraint->entity, 'e')
                ->where($expr->in("e.{$constraint->identifier}", ':value'))
                ->setParameter('value', $value)
                ->getQuery()
                ->getResult()
            ;

            if($processed) {

                $this->context->buildViolation('one-or-more-of-the-given-values-is-invalid')->addViolation();
            }

        } 

        $processed = $this->em
            ->getRepository($constraint->entity)
            ->findOneBy([ $constraint->identifier => $value ])
        ;

        if(!is_null($processed)) {

            $this->context->buildViolation('this-value-is-not-valid')->addViolation();
        }

    }
}
