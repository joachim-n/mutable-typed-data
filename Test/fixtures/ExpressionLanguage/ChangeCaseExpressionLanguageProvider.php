<?php

namespace MutableTypedData\Fixtures\ExpressionLanguage;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

class ChangeCaseExpressionLanguageProvider implements ExpressionFunctionProviderInterface {

  public function getFunctions(): array {
    return [
      new ExpressionFunction('uppercase', function ($str) {
        return sprintf('(is_string(%1$s) ? strtoupper(%1$s) : %1$s)', $str);
      },
      function ($arguments, $str) {
        if (!is_string($str)) {
          return $str;
        }

        return strtoupper($str);
      }),
    ];
  }

}
