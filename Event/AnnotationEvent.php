<?php

namespace danrevah\SandboxBundle\Event;

use danrevah\SandboxBundle\Annotation\AbstractApiSandboxAnnotation;
use Doctrine\Common\Annotations\Annotation;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class AnnotationEvent
 */
class AnnotationEvent extends Event
{
    /**
     * @var AbstractApiSandboxAnnotation
     */
    private $annotation;

    /**
     * @var array|Annotation[]
     */
    private $annotations;

    /**
     * AnnotationEvent constructor.
     *
     * @param AbstractApiSandboxAnnotation $annotation
     * @param array                        $annotations
     */
    public function __construct($annotation, array $annotations)
    {
        $this->annotation = $annotation;
        $this->annotations = $annotations;
    }

    /**
     * @return AbstractApiSandboxAnnotation
     */
    public function getAnnotation()
    {
        return $this->annotation;
    }

    /**
     * @param AbstractApiSandboxAnnotation $annotation
     *
     * @return $this
     */
    public function setAnnotation(AbstractApiSandboxAnnotation $annotation)
    {
        $this->annotation = $annotation;

        return $this;
    }

    /**
     * @return array|Annotation[]
     */
    public function getAnnotations()
    {
        return $this->annotations;
    }

    /**
     * @param array|Annotation[] $annotations
     *
     * @return $this
     */
    public function setAnnotations(array $annotations)
    {
        $this->annotations = $annotations;

        return $this;
    }
}
