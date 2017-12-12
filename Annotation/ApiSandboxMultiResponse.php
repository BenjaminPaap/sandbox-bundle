<?php

namespace danrevah\SandboxBundle\Annotation;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class ApiSandboxMultiResponse extends AbstractApiSandboxAnnotation
{
    /**
     * Response Fallback
     * @var array
     * @desc If could not find any matching route, it will use this route instead
     *
     * Example:
     *     responseFallback={
     *          "type"="xml",
     *          "responseCode"=500,
     *          "resource"="@SandboxBundle/Resources/responses/token.xml"
     *     },
     */
    public $responseFallback = [];

    /**
     * Multi Response
     * @var array
     *
     * Example:
     *      multiResponse={
     *          {
     *              "responseCode":200,
     *              "type"="xml",
     *              "resource"="@SandboxBundle/Resources/responses/token.xml",
     *              "caseParams": {"some_parameter"="1", "some_parameter2"="2"}
     *          },
     *          {
     *              "responseCode":200,
     *              "type"="json",
     *              "resource"="@SandboxBundle/Resources/responses/token.json",
     *              "caseParams": {"some_parameter"="3", "some_parameter2"="4"}
     *          }
     *      }
     */
    public $multiResponse = [];
}
