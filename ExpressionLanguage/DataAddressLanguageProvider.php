<?php

namespace MutableTypedData\ExpressionLanguage;

use MutableTypedData\Exception\InvalidDefinitionException;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

/**
 * Define custom Expression Language functions for working with data addresses.
 *
 * We don't use the existing methods on the data items because that would
 * make interpreting expressions in JavaScript more difficult.
 *
 * Note that since we don't compile our expressions, our custom functions don't
 * need to handle compiling.
 */
class DataAddressLanguageProvider implements ExpressionFunctionProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function getFunctions(): array {
    return [
      /**
       * Gets the data value for the given address.
       *
       * Any address that getItem() recognises may be used.
       */
      new ExpressionFunction(
        'get',
        function ($address) {
        },
        function ($arguments, $address) {
          $item = $arguments['item'];

          // TODO: catch invalid addresses here.
          $target_item = $item->getItem($address);

          // Check for self-reference, as that produces a confusing PHP error
          // for accessing DataItem->value (presumably because we're already
          // within a magic __get(), going round again doesn't work).
          if ($target_item === $item) {
            throw new InvalidDefinitionException("Expression uses a get() on an data item which refers to itself.");
          }

          return $target_item->value;
        }
      ),
    ];
  }

}
