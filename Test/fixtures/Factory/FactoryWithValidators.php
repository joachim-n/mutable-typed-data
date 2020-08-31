<?php

namespace MutableTypedData\Fixtures\Factory;

use MutableTypedData\DataItemFactory;

class FactoryWithValidators extends DataItemFactory {

  /**
   * {@inheritdoc}
   */
  static protected $validators = [
    'colour' => \MutableTypedData\Fixtures\Validator\Colour::class,
  ];

}
