<?php

namespace danrevah\SandboxBundle\Annotation;

use danrevah\SandboxBundle\Enum\ApiSandboxResponseTypeEnum;

/**
 * Class AbstractApiSandboxAnnotation
 */
class AbstractApiSandboxAnnotation
{
    // Default response type is JSON
    public $type = ApiSandboxResponseTypeEnum::JSON_RESPONSE;

    // Response code to output, default is 200
    public $responseCode = 200;

    /**
     * Request parameters object
     * @var array
     *
     * Example:
     *      parameters = {
     *          {"name"="param1", "required"=true},
     *          {"name"="param2", "required"=false}
     *      }
     */
    public $parameters = [];

    /**
     * Request parameters resource
     *
     * Example:
     *     parametersResource="@AppBundle/Resources/sandbox/postParameters.json"
     *
     * @var string
     */
    public $parametersResource = null;
}
