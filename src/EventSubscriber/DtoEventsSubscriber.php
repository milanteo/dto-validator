<?php

namespace Teoalboo\DtoValidator\EventSubscriber;

use Teoalboo\DtoValidator\Exception\DtoFieldValidationException;
use Teoalboo\DtoValidator\Exception\DtoPayloadValidationException;
use Teoalboo\DtoValidator\Validator\DtoPayload;
use ReflectionClass;
use RuntimeException;
use stdClass;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Teoalboo\DtoValidator\BaseDto;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;

class DtoEventsSubscriber implements EventSubscriberInterface {

    private ExpressionLanguage $expression;

    public function __construct(
        private RequestStack $stack,
        private Translator $translator,
        private ValidatorInterface $validator
    ) { 

        $this->expression = new ExpressionLanguage();
    }

    public function onKernelException(ExceptionEvent $event): void {
        
        $e = $event->getThrowable();
        
        if($e instanceof DtoPayloadValidationException) {

            $event->setResponse(new JsonResponse($this->formatErrors($e->errors), $e->errorCode));

        }

    }

    public function formatErrors(string | array | DtoFieldValidationException $errors): array | string {

        if(is_array($errors)) {

            if(array_any($errors, fn($v) => $v instanceof DtoFieldValidationException)) {
            
                $payload = [];

                foreach ($errors as $exception) {
                    
                    $payload[$exception->getPropertyPath()] = $this->formatErrors($exception);
                }

                return $payload;

            } elseif(!array_is_list($errors)) {

                $payload = [];

                foreach ($errors as $key => $exceptions) {
                    
                    $payload[$key] = $this->formatErrors($exceptions);
                }

                return $payload;

            } 

            return array_map(fn($err) => $this->translator->trans($err, domain: 'validators'), $errors);

        } elseif($errors instanceof DtoFieldValidationException) {

            return $this->formatErrors($errors->getMessages());

        } 

        return $this->translator->trans($errors, domain: 'validators');

    }

    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void {

        $request = $this->stack->getCurrentRequest();

        $arguments = $event->getNamedArguments();

        $dtos = array_filter($arguments, fn($v) => is_object($v) && $v instanceof BaseDto);

        foreach ($dtos as $dto) {

            $decode = json_decode($request->getContent());

            $dto->setContent(is_object($decode) ? $decode : new stdClass());
            
            $reflection = new ReflectionClass($dto);

            [ $attribute ] = $reflection->getAttributes(DtoPayload::class) + [ null ];

            $attribute = $attribute?->newInstance() ?? new DtoPayload();

            if ($subjectRef = $attribute->subject) {

                if (is_array($subjectRef)) {
                    foreach ($subjectRef as $refKey => $ref) {

                        $subject[$refKey] = $this->getDtoSubject($ref, $request, $arguments);
                    }
                } else {
                    $subject = $this->getDtoSubject($subjectRef, $request, $arguments);
                }

                $attribute->subject = $subject;
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

        if (!\array_key_exists($subjectRef, $arguments)) {

            throw new RuntimeException(\sprintf('Could not find the subject "%s".', $subjectRef));
        }

        return $arguments[$subjectRef];
    }

    public static function getSubscribedEvents(): array {
        
        return [
            KernelEvents::EXCEPTION            => 'onKernelException',
            KernelEvents::CONTROLLER_ARGUMENTS => 'onKernelControllerArguments'
        ];
    }
}
