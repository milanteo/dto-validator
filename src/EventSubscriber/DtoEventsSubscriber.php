<?php

namespace Teoalboo\DtoValidator\EventSubscriber;

use Teoalboo\DtoValidator\Exception\DtoPayloadValidationException;
use Teoalboo\DtoValidator\Attribute\DtoPayload;
use ReflectionFunction;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;
use Teoalboo\DtoValidator\BaseDto;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;
use Teoalboo\DtoValidator\Service\DtoValidatorService;

class DtoEventsSubscriber implements EventSubscriberInterface {

    private ExpressionLanguage $expression;

    public function __construct(
        private RequestStack $stack,
        private TranslatorInterface $translator,
        private DtoValidatorService $validator
    ) { 

        $this->expression = new ExpressionLanguage();
    }

    public static function getSubscribedEvents(): array {
        
        return [
            KernelEvents::EXCEPTION            => 'onKernelException',
            KernelEvents::CONTROLLER_ARGUMENTS => 'onKernelControllerArguments'
        ];
    }

    public function onKernelException(ExceptionEvent $event): void {
        
        $e = $event->getThrowable();
        
        if($e instanceof DtoPayloadValidationException) {

            $event->setResponse(new JsonResponse(data: $this->formatErrors($e->errors), status: $e->errorCode));

        }

    }

    public function formatErrors(string | array $errors): array | string {

        if(is_array($errors)) {

            if(!array_is_list($errors)) {

                $payload = [];

                foreach ($errors as $key => $exceptions) {
                    
                    $payload[$key] = $this->formatErrors($exceptions);
                }

                return $payload;

            } 

            return array_map(fn($err) => $this->translator->trans($err, domain: 'validators'), $errors);

        }

        return $this->translator->trans($errors, domain: 'validators');

    }

    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void {

        $request = $this->stack->getCurrentRequest();

        $namedArguments = $event->getNamedArguments();

        $reflector = new ReflectionFunction($event->getController()(...));

        $dtoParams = array_filter($reflector->getParameters(), fn($p) => is_subclass_of(
            $namedArguments[$p->getName()], 
            BaseDto::class
        ));

        foreach ($dtoParams as $dtoParam) {

            $dto = $namedArguments[$dtoParam->getName()];
            
            [ $attribute ] = $dtoParam->getAttributes(DtoPayload::class) + [ null ];

            $attribute = $attribute?->newInstance() ?? new DtoPayload();
            
            $dto = $this->validator->populate($dto, $attribute);

            if ($subjectRef = $attribute->getSubject()) {

                if (is_array($subjectRef)) {
                    foreach ($subjectRef as $refKey => $ref) {

                        $subject[$refKey] = $this->getDtoSubject($ref, $request, $namedArguments);
                    }
                } else {
                    $subject = $this->getDtoSubject($subjectRef, $request, $namedArguments);
                }

                $attribute->setSubject($subject);
            }

            $this->validator->validate($dto, $attribute);

        }
        
    }

    public function getDtoSubject(string|Expression $subjectRef, Request $request, array $arguments): mixed {

        if ($subjectRef instanceof Expression) {

            $this->expression ??= new ExpressionLanguage();

            return $this->expression->evaluate($subjectRef, [
                'request' => $request,
                'args' => $arguments,
            ]);
        }

        if (!array_key_exists($subjectRef, $arguments)) {

            throw new RuntimeException(sprintf('Could not find the subject "%s".', $subjectRef));
        }

        return $arguments[$subjectRef];
    }

    

}
