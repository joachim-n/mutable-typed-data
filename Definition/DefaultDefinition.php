<?php

namespace MutableTypedData\Definition;

use MutableTypedData\Exception\InvalidDefinitionException;
use MutableTypedData\Data\DataItem;

/**
 * Defines a default for a data item.
 */
class DefaultDefinition {

  // TODO: constants for type!

  protected $dependencies = [];

  protected $literal;

  protected $expression = '';

  static public function create() {
    return new static();
  }

  public function setLiteral($default) {
    $this->literal = $default;

    // Remove the other settings.
    // TODO: needs test coverage!
    unset($this->expression, $this->callable);

    return $this;
  }

  public function setExpression(string $expression) {
    $this->expression = $expression;

    // Remove the other settings.
    unset($this->literal, $this->callable);

    return $this;
  }

  public function setCallable(callable $callable): self {
    $this->callable = $callable;

    // Remove the other settings.
    unset($this->literal, $this->expression);

    return $this;
  }

  /**
   * Sets the dependencies for this default.
   *
   * Dependencies ensure that a default that depends on other data's values is
   * not derived until those values are present.
   *
   * Calling this removes any existing dependencies.
   *
   * @param string ...$dependencies
   *  The address of the dependencies. These may be relative or absolute.
   *
   * @return static
   */
  public function setDependencies(string ...$dependencies): self {
    // TODO: check that these actually occur in the expression, for sanity!!
    $this->dependencies = $dependencies;
    return $this;
  }

  /**
   * Removes all the dependencies.
   *
   * @return static
   */
  public function removeDependencies(): self {
    $this->dependencies = [];
    return $this;
  }

  public function getDependencies() {
    return $this->dependencies;
  }

  public function getType(): string {
    if (isset($this->literal)) {
      return 'literal';
    }
    if (isset($this->expression)) {
      return 'expression';
    }
    if (isset($this->callable)) {
      return 'callable';
    }

    throw new InvalidDefinitionException("No actual default set!");
  }

  public function getLiteral() {
    return $this->literal;
  }

  public function getExpression() {
    return $this->expression;
  }

  /**
   * Gets the expression with relative addresses converted to absolute.
   *
   * This assumes that addresses are only used within a call to the 'get()'
   * custom Expression Language function.
   *
   * @param \MutableTypedData\Data\DataItem $data_item
   *   The data item the default is on.
   *
   * @return string
   *   The expression with the addresses converted.
   */
  public function getExpressionWithAbsoluteAddresses(DataItem $data_item) {
    $matches = [];
    preg_match_all("@get\(['\"](?<address>.+?)['\"]\)@", $this->expression, $matches);

    $relative_addresses = $matches['address'];

    $absolute_addresses = [];
    foreach ($relative_addresses as $relative_address) {
      $absolute_addresses[] = $data_item->relativeAddressToAbsolute($relative_address);
    }

    $replaced_expression = str_replace($relative_addresses, $absolute_addresses, $this->expression);

    return $replaced_expression;
  }

  public function getCallable(): callable {
    return $this->callable;
  }

  /**
   * Magic method.
   *
   * Completely kill all the properties when serialized, as it they contain
   * closures which PHP can't serialize. We need to do this here rather than
   * just zap it in the DataItem objects, because inner objects get looked at
   * first in the serialization process.
   *
   * @return array
   */
  public function __sleep() {
    return [];
  }

}
