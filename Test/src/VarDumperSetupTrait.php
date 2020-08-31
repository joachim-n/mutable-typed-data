<?php

namespace MutableTypedData\Test;

use MutableTypedData\Data\DataItem;
use Symfony\Component\VarDumper\Cloner\Stub;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\VarDumper;

/**
 * Configures Symfony's dumper to skip noisy properties in DataItem objects.
 */
trait VarDumperSetupTrait {

  protected function setUpVarDumper() {
    VarDumper::setHandler(function ($var) {
        $casters = [
          DataItem::class => function($object, $array, Stub $stub, $isNested, $filter) {
            foreach ([
              'properties',
              'definition',
              'parent',
              'watchAddresses',
              'watchNames',
            ] as $key) {
              unset($array["\0*\0" . $key]);
            }
            return $array;
          },
        ];

        $cloner = new VarCloner();

        $cloner->addCasters($casters);

        $dumper = 'cli' === PHP_SAPI ? new CliDumper() : new HtmlDumper();

        $dumper->dump($cloner->cloneVar($var));
    });
  }

}