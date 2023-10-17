<?php


namespace Internetrix\GraphQLUpdateMany\Operations;

use Exception;
use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\ResolveInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\GraphQL\Schema\DataObject\FieldAccessor;
use SilverStripe\GraphQL\Schema\Exception\SchemaBuilderException;
use SilverStripe\GraphQL\Schema\Field\ModelMutation;
use SilverStripe\GraphQL\Schema\Interfaces\ModelOperation;
use SilverStripe\GraphQL\Schema\Schema;
use SilverStripe\GraphQL\Schema\Type\InputType;
use SilverStripe\GraphQL\Schema\Interfaces\InputTypeProvider;
use SilverStripe\GraphQL\Schema\Interfaces\OperationCreator;
use SilverStripe\GraphQL\Schema\Interfaces\SchemaModelInterface;
use SilverStripe\ORM\ArrayList;
use Closure;
use SilverStripe\GraphQL\Schema\DataObject\FieldReconciler;
use SilverStripe\GraphQL\Schema\Type\ModelType;

/**
 * Creates an update operation for a DataObject
 */
class UpdateManyCreator implements OperationCreator, InputTypeProvider
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
            $mutationName = 'update' . ucfirst(Schema::pluralise($typeName));
        }
        $singleUpdateOperation = 'update' . ucfirst($typeName);
        $inputTypeName = self::inputTypeName($typeName);

        return ModelMutation::create($model, $mutationName)
            ->setType("[$typeName]")
            ->setPlugins($plugins)
            ->setResolver([static::class, 'resolve'])
            ->addResolverContext('singleUpdateOperation', $singleUpdateOperation)
            ->addArg('input', "[$inputTypeName]!");
    }

    /**
     * @param array $resolverContext
     * @return Closure
     */
    public static function resolve(array $resolverContext = []): Closure
    {
        $singleUpdateOperation = $resolverContext['singleUpdateOperation'] ?? null;
        return function ($obj, array $args, array $context, ResolveInfo $info) use ($singleUpdateOperation) {
            $inputs = $args['input'];

            if(empty($inputs) ){
                throw new Exception("No inputs to update");
            }

            $list = ArrayList::create();

            try{
                $fieldDefintion = $info->schema->getMutationType()->getField($singleUpdateOperation);
            }catch(InvariantViolation $exception){
                throw $exception;
            }

            foreach($inputs as $input){
                $updatedObject = call_user_func($fieldDefintion->resolveFn, null, ['input' => $input], $context, $info);
                $list->push($updatedObject);
            }

            return $list;
        };
    }

    /**
     * @param SchemaModelInterface $model
     * @param string $typeName
     * @param array $config
     * @return array
     * @throws SchemaBuilderException
     */
    public function provideInputTypes(ModelType $modelType, array $config = []): array
    {
        $includedFields = $this->reconcileFields($config, $modelType);

        $fieldMap = [];
        foreach ($includedFields as $fieldName) {
            $fieldMap[$fieldName] = $modelType->getFieldByName($fieldName)->getType();
        }
        $inputType = InputType::create(
            self::inputTypeName($modelType->getName()),
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
     * @return UpdateManyCreator
     */
    public function setFieldAccessor(FieldAccessor $fieldAccessor): UpdateManyCreator
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
        return 'Update' . ucfirst($typeName) . 'Input';
    }
}
