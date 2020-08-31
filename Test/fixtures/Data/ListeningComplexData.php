<?php

namespace MutableTypedData\Fixtures\Data;

use MutableTypedData\Data\ComplexData;
use MutableTypedData\Definition\DataDefinition;

/**
 * Custom data type for use in testBubbling().
 *
 * We need this in order to assert the calls to onChange(), because we can't
 * add a mock for a data object (and Prophecy doesn't do partial mocks anyway).
 * The DataItemFactory takes class names for custom types, so this can't be an
 * anonymous class (without mucking about with its constructor to create one as
 * a template to then get the class name of). Instead, we pass in a Prophecy
 * mock to be our listener, and pass through the call to it.
 */
class ListeningComplexData extends ComplexData {

  protected $listener;

  public function setListener($listener) {
    $this->listener = $listener;
  }

  public function onChange(DataDefinition $definition, string $name, $value) {
    $this->listener->onChange($definition, $name, $value);

    parent::onChange($definition, $name, $value);
  }

}