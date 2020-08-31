<?php

namespace MutableTypedData\Definition;

/**
 * Interface for providing a data definition.
 */
interface DefinitionProviderInterface {

  public static function getDefinition(): DataDefinition;

}
