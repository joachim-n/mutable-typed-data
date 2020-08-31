<?php

namespace MutableTypedData\Fixtures\Factory;

use MutableTypedData\DataItemFactory;
use MutableTypedData\ExpressionLanguage\DataAddressLanguageProvider;
use MutableTypedData\Fixtures\ExpressionLanguage\ChangeCaseExpressionLanguageProvider;

class FactoryWithChangeCaseExpressionLanguageFunctions extends DataItemFactory {

  static protected $expressionLanguageProviders = [
    DataAddressLanguageProvider::class,
    ChangeCaseExpressionLanguageProvider::class,
  ];


}
