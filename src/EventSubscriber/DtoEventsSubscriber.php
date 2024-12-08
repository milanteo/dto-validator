<?php

namespace Teoalboo\DtoValidator\EventSubscriber;

use Teoalboo\DtoValidator\Exception\DtoFieldValidationException;
use Teoalboo\DtoValidator\Exception\DtoPayloadValidationException;
use Teoalboo\DtoValidator\Validator\DtoPayload;
use ReflectionClass;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DtoEventsSubscriber implements EventSubscriberInterface {

    public function __construct(
        private Translator $translator,
        private ValidatorInterface $validator
    ) { }

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

        $arguments = array_filter($event->getNamedArguments(), fn($v) => is_object($v));

        foreach ($arguments as $argument) {
            
            $reflection = new ReflectionClass($argument);

            [ $payload ] = $reflection->getAttributes(DtoPayload::class) + [ null ];

            if($payload = $payload?->newInstance()) {

                $subject = null;

                if($payload->subject) {
                    
                    [ $payload->subject => $subject ] = $event->getNamedArguments() + [ $payload->subject => null ];

                }

                $this->validator->validate($argument, new DtoPayload(
                    errorCode: $payload->errorCode, 
                    content:   $payload->content, 
                    fields:    $payload->fields, 
                    subject:   $subject
                ));
            }

        }
        
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION            => 'onKernelException',
            KernelEvents::CONTROLLER_ARGUMENTS => 'onKernelControllerArguments'
        ];
    }
}
