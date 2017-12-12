<?php
namespace danrevah\SandboxBundle\Managers;

use danrevah\SandboxBundle\Annotation\AbstractApiSandboxAnnotation;
use danrevah\SandboxBundle\Annotation\ApiSandboxMultiResponse;
use danrevah\SandboxBundle\Annotation\ApiSandboxResponse;
use danrevah\SandboxBundle\Annotation\ParameterProviderInterface;
use danrevah\SandboxBundle\Enum\ApiSandboxResponseTypeEnum;
use danrevah\SandboxBundle\Event\AnnotationEvent;
use danrevah\SandboxBundle\Event\SandboxEvents;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Collections\ArrayCollection;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

class SandboxResponseManager {

    /**
     * @var \Symfony\Component\HttpKernel\KernelInterface
     */
    private $kernel;
    /**
     * @var \Doctrine\Common\Annotations\AnnotationReader
     */
    private $annotationsReader;

    /**
     * @var boolean
     */
    private $forceMode;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @param KernelInterface $kernel
     * @param boolean $forceMode
     * @param \Doctrine\Common\Annotations\AnnotationReader $annotationsReader
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct($kernel, $forceMode, AnnotationReader $annotationsReader, EventDispatcherInterface $dispatcher)
    {
        $this->kernel = $kernel;
        $this->annotationsReader = $annotationsReader;
        $this->forceMode = $forceMode;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Getting a response controller by Annotations
     *
     * @param $object
     * @param String $method
     * @param ParameterBag $request
     * @param ParameterBag $query
     * @param ArrayCollection $rawRequest
     * @return callable
     * @throws \RuntimeException
     * @throws \Exception
     */
    public function getResponseController(
        $object,
        $method,
        ParameterBag $request,
        ParameterBag $query,
        ArrayCollection $rawRequest
    ) {
        $reader = $this->annotationsReader;
        $reflectionMethod = new ReflectionMethod($object, $method);

        // Step [1] - Single Response Annotation
        /** @var \StdClass $apiResponseAnnotation */
        $annotations = $reader->getMethodAnnotations($reflectionMethod);

        $annotation = $this->findAnnotation($annotations);

        $event = new AnnotationEvent($annotation, $annotations);
        $this->dispatcher->dispatch(SandboxEvents::ANNOTATION_READ, $event);

        if ( ! $annotation ) {
            // Disabled exception, continue to real controller
            $forceMode = $this->forceMode;

            if ($forceMode) {
                throw new \Exception(sprintf(
                    'Entity class %s does not have required annotation ApiSandboxResponse or ApiResponseMultiAnnotation',
                    get_class($object)
                ));
            } else {
                // Fall back to the REAL Controller
                return false;
            }
        }

        $this->getParameters($annotation);

        $event = new AnnotationEvent($annotation, $annotations);
        $this->dispatcher->dispatch(SandboxEvents::PARAMETERS_READ, $event);

        // Validating with Annotation syntax
        $this->validateParamsArray($annotation->parameters, $rawRequest, $request, $query);

        // Single response annotation is checked first
        if ($annotation instanceof ApiSandboxResponse) {
            $responsePath = $annotation->resource;
            $type = $annotation->type;
            $statusCode = $annotation->responseCode;
        } else {
            // Get response
            /** @var ApiSandboxMultiResponse $annotation */
            list($responsePath, $type, $statusCode) = $this->getResource(
                $annotation,
                $rawRequest,
                $request,
                $query
            );
        }

        if ($responsePath === null && $annotation instanceof ApiSandboxMultiResponse) {
            list($type, $statusCode, $responsePath) = $this->extractRealParams($annotation, $type, $statusCode);
        }

        list($controller, $content) = $this->getControllerResponseByResource($responsePath, $type, $statusCode);

        return [$controller, $content, $type, $statusCode];
    }

    /**
     * @param ApiSandboxMultiResponse $apiResponseMultiAnnotation
     * @param $streamParams
     * @param $request
     * @param $query
     * @throws \RuntimeException
     * @return array
     */
    private function getResource(
        ApiSandboxMultiResponse $apiResponseMultiAnnotation,
        ArrayCollection $streamParams,
        ParameterBag $request,
        ParameterBag $query
    ) {
        // parent type, and responseCode
        $type = $apiResponseMultiAnnotation->type;
        $responseCode = $apiResponseMultiAnnotation->responseCode;
        $resourcePath = null;

        foreach ($apiResponseMultiAnnotation->multiResponse as $resource) {

            if ( ! isset($resource['caseParams']) || ! isset($resource['resource'])) {
                throw new RunTimeException('Each multi response must have caseParams and resource property');
            }

            $validateCaseParams = $this->countCaseParamsFromQuery($streamParams, $request, $query, $resource);

            // Found a match route params
            if (count($resource['caseParams']) == $validateCaseParams) {
                list($type, $responseCode, $resourcePath) = $this->extractResource($resource, $type, $responseCode);
                // If found route break loop
                break;
            }
        }

        return [$resourcePath, $type, $responseCode];
    }

    /**
     * @param $apiDocParams
     * @param $rawRequest
     * @param $request
     * @param $query
     * @throws \InvalidArgumentException
     */
    private function validateParamsArray(
        $apiDocParams,
        ArrayCollection $rawRequest,
        ParameterBag $request,
        ParameterBag $query
    ) {
        // search for missing required parameters and throw exception if there's anything missing
        foreach ($apiDocParams as $options) {
            if ($request->has($options['name'])) {
                $value = $request->get($options['name']);
            } elseif ($query->has($options['name'])) {
                $value = $query->get($options['name']);
            } elseif ($rawRequest->containsKey($options['name'])) {
                $value = $rawRequest->get($options['name']);
            } else {
                // Validating if required parameters are missing
                if (isset($options['required']) && $options['required'] && empty($value) ) {
                    throw new \InvalidArgumentException(sprintf('Missing parameter "%s".', $options['name']));
                }
            }

            if (isset($options['format'])) {
                if (-1 == preg_match('@^'.$options['format'].'$@', $value)) {
                    throw new \InvalidArgumentException(sprintf('Value "%s" does not match format "%s"', $value, $options['format']));
                }
            }
        }
    }

    /**
     * @param $responsePath
     * @param $type
     * @param $statusCode
     * @return callable
     * @throws \RuntimeException
     */
    private function getControllerResponseByResource($responsePath, $type, $statusCode)
    {
        $path = $this->kernel->locateResource($responsePath);
        $content = file_get_contents($path);

        // Override controller with fake response
        switch (strtolower($type)) {
            // JSON
            case ApiSandboxResponseTypeEnum::JSON_RESPONSE:
                $content = json_decode($content, 1);

                $controller = function() use ($content, $statusCode) {
                    return new JsonResponse($content, $statusCode);
                };
                break;

            // XML
            case ApiSandboxResponseTypeEnum::XML_RESPONSE:
                $controller = function() use ($content, $statusCode) {
                    $response = new Response($content, $statusCode);
                    $response->headers->set('Content-Type', 'text/xml');
                    return $response;
                };
                break;

            // Unknown
            default:
               throw new RuntimeException('Unknown type of SandboxApiResponse');
        }
        return [$controller, $content];
    }

    /**
     * @param ApiSandboxMultiResponse $apiResponseMultiAnnotation
     * @param $type
     * @param $statusCode
     * @throws \RuntimeException
     * @return array
     */
    private function extractRealParams(ApiSandboxMultiResponse $apiResponseMultiAnnotation, $type, $statusCode)
    {
        // If didn't find route path, fall to responseFallback
        if (empty($apiResponseMultiAnnotation->responseFallback) || ! isset($apiResponseMultiAnnotation->responseFallback['resource'])) {
            throw new RuntimeException('Missing `responseFallback` is not set properly in the Sandbox annotation');
        }

        return array(
            isset($apiResponseMultiAnnotation->responseFallback['type']) ? $apiResponseMultiAnnotation->responseFallback['type'] : $type,
            isset($apiResponseMultiAnnotation->responseFallback['responseCode']) ? $apiResponseMultiAnnotation->responseFallback['responseCode'] : $statusCode,
            $apiResponseMultiAnnotation->responseFallback['resource']
        );
    }

    /**
     * @param ArrayCollection $streamParams
     * @param ParameterBag $request
     * @param ParameterBag $query
     * @param $resource
     * @return int
     */
    private function countCaseParamsFromQuery(ArrayCollection $streamParams, ParameterBag $request, ParameterBag $query, $resource)
    {
        $validateCaseParams = 0;

        // Validate Params with GET, POST, and RAW
        foreach ($resource['caseParams'] as $paramName => $paramValue) {
            if ($this->existsInQuery($paramName, $paramValue, $streamParams, $request, $query)) {
                $validateCaseParams++;
            }
        }
        return $validateCaseParams;
    }

    /**
     * @param $paramName
     * @param $paramValue
     * @param $streamParams
     * @param $request
     * @param $query
     * @return bool
     */
    private function existsInQuery($paramName, $paramValue, ArrayCollection $streamParams, ParameterBag $request, ParameterBag $query)
    {
        return ($query->get($paramName) == $paramValue) || $request->get($paramName) == $paramValue || $streamParams->get($paramName) == $paramValue;
    }

    /**
     * @param $resource
     * @param $type
     * @param $responseCode
     * @return array
     */
    private function extractResource($resource, $type, $responseCode)
    {
        // Override parent type if has child type
        if (isset($resource['type'])) {
            $type = $resource['type'];
        }
        if (isset($resource['responseCode'])) {
            $responseCode = $resource['responseCode'];
        }
        $resourcePath = $resource['resource'];

        return array($type, $responseCode, $resourcePath);
    }

    /**
     * @param array $annotations
     *
     * @return AbstractApiSandboxAnnotation|null
     */
    private function findAnnotation(array $annotations)
    {
        foreach ($annotations as $annotation) {
            if ($annotation instanceof AbstractApiSandboxAnnotation) {
                return $annotation;
            }
        }

        return null;
    }

    /**
     * Load parameters from resource or array
     *
     * @param AbstractApiSandboxAnnotation $annotation
     */
    private function getParameters(AbstractApiSandboxAnnotation $annotation)
    {
        if (!empty($annotation->parametersResource)) {
            $path = $this->kernel->locateResource($annotation->parametersResource);
            $content = file_get_contents($path);

            $annotation->parameters = json_decode($content, true);
        }
    }
}
