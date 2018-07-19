<?php

namespace Internetrix\GraphQLUpdateMany\Scaffolders;

use Exception;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\Scaffolding\Interfaces\ResolverInterface;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Update;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\ListQueryScaffolder;
use SilverStripe\GraphQL\Scaffolding\Traits\DataObjectTypeTrait;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObjectSchema;
use SilverStripe\ORM\FieldType\DBField;

/**
 * Scaffolds a generic update many operation for DataObjects.
 */
class UpdateMany extends ListQueryScaffolder implements ResolverInterface
{
    use DataObjectTypeTrait;

    protected $isNested = true;

    /**
     * UpdateManyOperationScaffolder constructor.
     *
     * @param string $dataObjectClass
     */
    public function __construct($dataObjectClass)
    {
        $this->dataObjectClass = $dataObjectClass;

        parent::__construct(
            'updateMany'.ucfirst($this->typeName()),
            $this->typeName(),
            $this
        );
    }

    /**
     * @param Manager $manager
     */
    public function addToManager(Manager $manager)
    {
        $manager->addType($this->generateInputType($manager));

        $this->extend('onBeforeAddToManager', $this, $manager);
        $manager->addMutation(function () use ($manager) {
            return $this->scaffold($manager);
        }, $this->getName());

        parent::addToManager($manager);
    }

    /**
     * Use a list of generated Input types
     *
     * @param Manager $manager
     * @return array
     */
    protected function createDefaultArgs(Manager $manager)
    {
        return [
            'Input' => [
                'type' => Type::nonNull(Type::listOf($manager->getType($this->inputTypeName()))),
            ],
        ];
    }

    /**
     * Based on the args provided, create an Input type to add to the Manager.
     * @param Manager $manager
     * @return InputObjectType
     */
    protected function generateInputType(Manager $manager)
    {
        return new InputObjectType([
            'name' => $this->inputTypeName(),
            'fields' => function () use ($manager) {
                $fields = [];
                $instance = $this->getDataObjectInstance();

                // Setup default input args.. Placeholder!
                $schema = Injector::inst()->get(DataObjectSchema::class);
                $db = $schema->fieldSpecs($this->dataObjectClass);

//                unset($db['ID']);

                foreach ($db as $dbFieldName => $dbFieldType) {
                    /** @var DBField $result */
                    $result = $instance->obj($dbFieldName);
                    // Skip complex fields, e.g. composite, as that would require scaffolding a new input type.
                    if (!$result->isInternalGraphQLType()) {
                        continue;
                    }
                    $arr = [
                        'type' => $result->getGraphQLType($manager),
                    ];
                    $fields[$dbFieldName] = $arr;
                }
                return $fields;
            }
        ]);
    }

    /**
     * @return string
     */
    protected function inputTypeName()
    {
        return $this->typeName().'UpdateInputType';
    }

    public function resolve($object, $args, $context, $info)
    {
        $list = ArrayList::create();

        if(empty($args['Input'])){
            throw new Exception("No inputs to update");
        }

        foreach($args['Input'] as $input){
            //delegate to the corresponding update resolver
            $updateScaffolder = new Update($this->dataObjectClass);

            if(!(isset($input['ID']) && $input['ID'])){
                throw new Exception("Each input must include an ID");
            }
            $id = $input['ID'];
            unset($input['ID']);
            $updateArgs = [
                'ID' => $id,
                'Input' => $input
            ];
            $updatedObject = $updateScaffolder->resolve(null, $updateArgs, $context, null);
            $list->push($updatedObject);
        }

        // Extension points that return false should kill the write operation
        $results = $this->extend('augmentMutation', $obj, $args, $context, $info);
        if (in_array(false, $results, true)) {
            return $obj;
        }

        return $list;
    }
}
