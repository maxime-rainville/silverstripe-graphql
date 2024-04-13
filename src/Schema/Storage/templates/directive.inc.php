<?php
/* @var object $scope */
/* @var \SilverStripe\GraphQL\Schema\Type\Directive $directive */
/* @var array $globals */
?>
<?php $directive = $scope; ?>
namespace <?=$globals['namespace']; ?>;

use GraphQL\Type\Definition\Directive;

// @type:<?=$directive->getName(); ?>

class <?=$globals['obfuscator']->obfuscate($directive->getName()) ?> extends Directive
{
    public function __construct()
    {
        parent::__construct([
            'name' => '<?=$directive->getName() ?>',
        <?php if (!empty($directive->getDescription())) : ?>
            'description' => '<?=addslashes($directive->getDescription()); ?>',
        <?php endif; ?>

        'locations' => [
        <?php foreach ($directive->getLocations() as $location) : ?>
            '<?=$location; ?>',
        <?php endforeach; ?>
        ], // locations
        <?php if (!empty($directive->getArgs())) : ?>
            'args' => [
            <?php foreach ($directive->getArgs() as $arg) : ?>
                [
                    'name' => '<?=$arg->getName(); ?>',
                    'type' => <?=$arg->getEncodedType()->encode(); ?>,
                <?php if ($arg->getDefaultValue() !== null) : ?>
                    'defaultValue' => <?=var_export($arg->getDefaultValue(), true); ?>,
                <?php endif; ?>
                ], // arg
            <?php endforeach; ?>
            ], // args
        <?php endif; ?>
        ]);
    }
}
