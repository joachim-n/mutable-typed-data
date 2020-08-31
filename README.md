Mutable Typed Data
==================

Mutable Typed Data is a system for typed and structured data. It is loosely
based on Drupal's Typed Data API, with the main difference that the structure
of data can change dynamically, based on the values that are set.

## Disclaimer

This works, there are tests, but more polish is needed. Releasing now because
I've been working on this since the start of lockdown 5 months ago and I'm
burnt out by it!

## Structure types

Each piece of data is represented by an object which holds the data as well as
its definition.

Data objects can be simple, holding just a scalar value:

```
$simple_data->value = 42;
$simple_data->value = 'cake';
$simple_data->value = TRUE;
```

or complex, with child properties which are in turn simple:

```
$complex_data->alpha = 42;
$complex_data->beta = 'cake;
$complex_data->gamma = TRUE;
```

or can be complex themselves:

```
$complex_data->child->grandchild->alpha = 42;
```

Both simple or complex data may be multiple-valued:

```
$multiple_simple_data[] = 'adding new value';
$multiple_simple_data[0] = 'changing existing value';

$multiple_complex_data[]->alpha = 'adding new value';
$multiple_complex_data[0]->beta = 'changing existing value';
```

Complex data can be mutable, which means that its child properties change in
response to a controlling property's value:

```
$animal->type = 'mammal';
$animal->gestation_period = '12 months';
$animal->type = 'reptile';
// The gestation_period property is removed; other properties may now have been
added.
```

## Accessing data

Simple data is always accessed with the 'value' property:

```
$simple_data->value = 'cake';
print $simple_data->value;
```

Complex data is accessed with the child property names, which can be chained
until reaching the simple values at the tips of the structure:

```
$complex_data->alpha->value = 'cake';
print $complex_data->alpha->value;

$complex_data->also_complex->value = 'cake';
print $complex_data->also_complex->alpha->value;
```

As a shorthand, simple child values can be set with just the property name:

```
$complex_data->alpha = 'cake';
```

Multiple data can be used as a numeric array:

```
$multiple_simple_data[0] = 'zero';
$multiple_simple_data[1] = 'one';
$multiple_simple_data[] = 'append';
```

Note that the deltas must remain consistent:

```
$multiple_simple_data[0] = 'zero';
$multiple_simple_data[42] = 'delta too high'; // throws Exception
```

Whether multiple data is simple or complex, accessing the array produces a
data object:

```
print $multiple_simple_data[0]->value;
print $multiple_complex_data[0]->alpha->value;
```

### Iterating

All data objects can be iterated over.

Simple data has no effect:

```
foreach ($simple_data as $item) {
   // Nothing.
}
```

Complex data iterates over its child properties:

```
foreach ($complex_data as $name => $item) {
   print $item->value;
}
```

Multiple data iterates over the delta items:

```
foreach ($multiple_data as $delta => $item) {
   print $item->value;
}
```

Because iterating simple items has no effect, iterating data items like this is
safe to do recursively into the data structure.

Alternatively, the items() method on data objects allows iteration that is
agnostic of cardinality:

```
foreach ($simple_data->items() as $item) {
   print $item->value; // Same as $simple_data->value
}

foreach ($multiple_simple_data->items() as $item) {
   print $item->value;
}
```
This is useful when working with a property that may be either single or
multiple, as it gives all the values in either case. However, it should not be
used recursively, as iterating on the value in the iteration for a simple item
will cause an infinite loop.

## Default values

Each data item can have a default defined for it. A default can serve different
purposes:

- propose a value to the user when they are prompted to enter data
- provide a value when the user doesn't enter one.

UIs should not force a user to enter a required value if there is a default, as
MTB's validation will set the default on data that is required but empty.

A default value can be defined as:

- a literal value such as 42 or 'cake'
- an expression written in Symfony Expression Language
- a PHP callable, such as a class method or an anonymous function.

Expression and callable defaults can use the values of other data items, and
thus have dependencies.

The following example illustrates how a default is used in a UI:

1. The UI instantiates the data property. The default is not yet applied.
2. The UI attempts to get a value for the data. If the default's dependencies
   are satisfied, it is applied.
3. The user is presented with either the empty value or the default if it was
   applied.
4. The user submits the data. If the property is set to required, the UI should
   nonetheless allow the user to leave the data empty, as the default can supply
   the value.
5. The consumer of the data fetches the value. If it is still empty, the default
   is applied.

### Expression language and JavaScript

MTD extends Symfony Expression Language with custom functions for getting data
values with addresses. Further custom functions can be added by subclassing
\MutableTypedData\DataItemFactory.

Using an expression is particularly suited to data that is shown in web UIs, as
it's possible to parse a small subset of Expression Language into JavaScript,
and thus show the user default values dynamically based on other values they
have entered. See Drupal's Module Builder for an example of doing this.

## Data definition

Data is defined with a fluent interface:

```
$definition = \MutableTypedData\Definition\DataDefinition::create('string')
   ->setLabel('Label')
   ->setRequired(TRUE);
```

Complex data is defined by nesting properties:

```
$definition = \MutableTypedData\Definition\DataDefinition::create('complex')
   ->setLabel('Label')
   ->setProperties([
      'child_property_alpha' => \MutableTypedData\Definition\DataDefinition::create('string')
         ->setLabel('Alpha')
         ->setRequired(TRUE),
      'child_property_beta' => \MutableTypedData\Definition\DataDefinition::create('string')
         ->setLabel('Beta'),
   ]);
```

### Options

Options can be defined with an array, or with objects, which allow options to
have descriptions as well as labels:

```
$definition = \MutableTypedData\Definition\DataDefinition::create('string')
   ->setLabel('Label')
   ->setOptionsArray([
      'green' => 'Emerald',
      'red' => 'Magenta',
      'grey' => 'Grey',
   ]);

$definition = \MutableTypedData\Definition\DataDefinition::create('string')
   ->setLabel('Label')
   ->setOptions(
      \MutableTypedData\Definition\OptionDefinition::create('green', 'Emerald', 'A lovely shade of green'),
      \MutableTypedData\Definition\OptionDefinition::create('red', 'Magenta', 'A deep red'),
      \MutableTypedData\Definition\OptionDefinition::create('grey', 'Grey', 'Not very colourful')
   );
```

### Mutable data

Mutable data needs a single property to control the variants, and then a
definition for each variant:

```
$definition = \MutableTypedData\Definition\DataDefinition::create('mutable')
   ->setLabel('Label')
   ->setProperties([
   'type' => DataDefinition::create('string')
      ->setLabel('Type')
   ])
   ->setVariants([
    'alpha' => VariantDefinition::create()
      ->setLabel('Alpha')
      ->setProperties([
        'alpha_one' => DataDefinition::create('string')
        ->setLabel('A1'),
        'alpha_two' => DataDefinition::create('string')
        ->setLabel('A2'),
      ]),
    'beta' => VariantDefinition::create()
      ->setLabel('Beta')
      ->setProperties([
        'beta_one' => DataDefinition::create('string')
        ->setLabel('B1'),
        'beta_two' => DataDefinition::create('string')
        ->setLabel('B2'),
      ]),
   ]);
```

The variants automatically define the options for the type property.

If the values for the type property don't match up to variant names (typically
because several option values of the type property correspond to the same
variant), then define the options with setOptions() and use setVariantMapping().
