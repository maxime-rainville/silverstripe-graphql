<?php


namespace SilverStripe\GraphQL\Schema\DataObject;

use GraphQL\Type\Definition\ResolveInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\GraphQL\QueryHandler\QueryHandler;
use SilverStripe\GraphQL\Schema\Exception\SchemaBuilderException;
use SilverStripe\GraphQL\Schema\Field\ModelMutation;
use SilverStripe\GraphQL\Schema\Interfaces\ModelOperation;
use SilverStripe\GraphQL\Schema\Schema;
use SilverStripe\GraphQL\Schema\Type\InputType;
use SilverStripe\GraphQL\Schema\Interfaces\InputTypeProvider;
use SilverStripe\GraphQL\Schema\Interfaces\OperationCreator;
use SilverStripe\GraphQL\Schema\Exception\PermissionsException;
use SilverStripe\GraphQL\Schema\Interfaces\SchemaModelInterface;
use Closure;
use SilverStripe\ORM\DataObject;

/**
 * Creates a "create" mutation for a DataObject
 */
class CreateCreator implements OperationCreator, InputTypeProvider
{
    use Configurable;
    use Injectable;
    use FieldReconciler;

    private static $dependencies = [
        'FieldAccessor' => '%$' . FieldAccessor::class,
    ];

    /**
     * @var FieldAccessor
     */
    private $fieldAccessor;

    /**
     * @param SchemaModelInterface $model
     * @param string $typeName
     * @param array $config
     * @return ModelOperation|null
     * @throws SchemaBuilderException
     */
    public function createOperation(
        SchemaModelInterface $model,
        string $typeName,
        array $config = []
    ): ?ModelOperation {
        $plugins = $config['plugins'] ?? [];
        $mutationName = $config['name'] ?? null;
        if (!$mutationName) {
            $mutationName = 'create' . ucfirst($typeName);
        }
        $inputTypeName = self::inputTypeName($typeName);

        return ModelMutation::create($model, $mutationName)
            ->setType($typeName)
            ->setPlugins($plugins)
            ->setDefaultResolver([static::class, 'resolve'])
            ->setResolverContext([
                'dataClass' => $model->getSourceClass(),
            ])
            ->addArg('input', "{$inputTypeName}!");
    }

    /**
     * @param array $resolverContext
     * @return Closure
     */
    public static function resolve(array $resolverContext = []): Closure
    {
        $dataClass = $resolverContext['dataClass'] ?? null;
        return function ($obj, $args = [], $context = [], ResolveInfo $info) use ($dataClass) {
            if (!$dataClass) {
                return null;
            }
            $singleton = Injector::inst()->get($dataClass);
            if (!$singleton->canCreate($context[QueryHandler::CURRENT_USER], $context)) {
                throw new PermissionsException("Cannot create {$dataClass}");
            }

            $fieldAccessor = FieldAccessor::singleton();
            /** @var DataObject $newObject */
            $newObject = Injector::inst()->create($dataClass);
            $update = [];
            foreach ($args['input'] as $fieldName => $value) {
                $update[$fieldAccessor->normaliseField($newObject, $fieldName)] = $value;
            }
            $newObject->update($update);

            // Save and return
            $newObject->write();
            $newObject = DataObject::get_by_id($dataClass, $newObject->ID);

            return $newObject;
        };
    }

    /**
     * @param SchemaModelInterface $model
     * @param string $typeName
     * @param array $config
     * @return array
     * @throws SchemaBuilderException
     */
    public function provideInputTypes(SchemaModelInterface $model, string $typeName, array $config = []): array
    {
        $dataObject = Injector::inst()->get($model->getSourceClass());
        $includedFields = $this->reconcileFields($config, $dataObject, $this->getFieldAccessor());
        $fieldMap = [];
        foreach ($includedFields as $fieldName) {
            $type = $model->getField($fieldName)->getType();
            if ($type) {
                $fieldMap[$fieldName] = $type;
            }
        }
        $inputType = InputType::create(
            self::inputTypeName($typeName),
            [
                'fields' => $fieldMap
            ]
        );

        return [$inputType];
    }

    /**
     * @return FieldAccessor
     */
    public function getFieldAccessor(): FieldAccessor
    {
        return $this->fieldAccessor;
    }

    /**
     * @param FieldAccessor $fieldAccessor
     * @return CreateCreator
     */
    public function setFieldAccessor(FieldAccessor $fieldAccessor): self
    {
        $this->fieldAccessor = $fieldAccessor;
        return $this;
    }


    /**
     * @param string $typeName
     * @return string
     */
    private static function inputTypeName(string $typeName): string
    {
        return 'Create' . ucfirst($typeName) . 'Input';
    }
}
