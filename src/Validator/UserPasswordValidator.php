<?php

namespace Teoalboo\DtoValidator\Validator;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Validator\Constraints\AbstractComparisonValidator;

class UserPasswordValidator extends AbstractComparisonValidator {

    public function __construct(
        ?PropertyAccessorInterface $propertyAccessor = null,
        private UserPasswordHasherInterface $hasher
    ) {

        parent::__construct($propertyAccessor);
    }

    protected function compareValues(mixed $password, mixed $user): bool {

        return !$user instanceof User || $this->hasher->isPasswordValid($user, $password);
    }
}
