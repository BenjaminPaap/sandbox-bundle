<?php

namespace danrevah\SandboxBundle\Annotation;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class ApiSandboxResponse extends AbstractApiSandboxAnnotation
{
    /**
     * Resource path for response
     * @var string
     *
     * Example:
     *      resource="@SandboxBundle/Resources/responses/token.json"
     */
    public $resource;
}
