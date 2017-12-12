<?php

namespace danrevah\SandboxBundle\Event;

/**
 * Class SandboxEvents
 */
final class SandboxEvents
{
    /**
     * Gets fired when the annotation was read on the controller
     */
    const ANNOTATION_READ = 'annotation.read';

    /**
     * Gets fired after the parameters were processed and before they are verified
     */
    const PARAMETERS_READ = 'parameters.read';
}
