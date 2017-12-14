<?php
/**
 * Incoming
 *
 * @author    Trevor Suarez (Rican7)
 * @copyright (c) Trevor Suarez
 * @link      https://github.com/Rican7/incoming
 * @license   MIT
 */

declare(strict_types=1);

namespace Incoming;

use Incoming\Hydrator\Builder;
use Incoming\Hydrator\BuilderFactory;
use Incoming\Hydrator\Exception\UnresolvableHydratorException;
use Incoming\Hydrator\Hydrator;
use Incoming\Hydrator\HydratorFactory;
use Incoming\Transformer\StructureBuilderTransformer;
use Incoming\Transformer\Transformer;

/**
 * A default implementation of both the `ModelProcessor` and `TypeProcessor`
 * for processing input data with an optional input transformation phase and
 * automatic hydrator and builder resolution
 */
class Processor implements ModelProcessor, TypeProcessor
{

    /**
     * Properties
     */

    /**
     * An input transformer to pre-process the input data before hydration
     *
     * @var Transformer
     */
    private $input_transformer;

    /**
     * A factory for building hydrators for a given model
     *
     * @var HydratorFactory
     */
    private $hydrator_factory;

    /**
     * A factory for building builders for a given model
     *
     * @var BuilderFactory
     */
    private $builder_factory;

    /**
     * A configuration flag that denotes whether hydration should always be run
     * after building a new model when processing specified types
     *
     * @var bool
     */
    private $always_hydrate_after_building = false;


    /**
     * Methods
     */

    /**
     * Constructor
     *
     * @param Transformer|null $input_transformer The input transformer
     * @param HydratorFactory|null $hydrator_factory A hydrator factory
     * @param BuilderFactory|null $builder_factory A builder factory
     * @param bool $always_hydrate_after_building A configuration flag that
     *  denotes whether hydration should always be run after building a new
     *  model when processing specified types
     */
    public function __construct(
        Transformer $input_transformer = null,
        HydratorFactory $hydrator_factory = null,
        BuilderFactory $builder_factory = null,
        bool $always_hydrate_after_building = false
    ) {
        $this->input_transformer = $input_transformer ?: new StructureBuilderTransformer();
        $this->hydrator_factory = $hydrator_factory;
        $this->builder_factory = $builder_factory;
        $this->always_hydrate_after_building = $always_hydrate_after_building;
    }

    /**
     * Get the input transformer
     *
     * @return Transformer The input transformer
     */
    public function getInputTransformer(): Transformer
    {
        return $this->input_transformer;
    }

    /**
     * Set the input transformer
     *
     * @param Transformer $input_transformer The input transformer
     * @return $this This instance
     */
    public function setInputTransformer(Transformer $input_transformer): self
    {
        $this->input_transformer = $input_transformer;

        return $this;
    }

    /**
     * Get the hydrator factory
     *
     * @return HydratorFactory|null The hydrator factory
     */
    public function getHydratorFactory()
    {
        return $this->hydrator_factory;
    }

    /**
     * Set the hydrator factory
     *
     * @param HydratorFactory|null $hydrator_factory The hydrator factory
     * @return $this This instance
     */
    public function setHydratorFactory(HydratorFactory $hydrator_factory = null): self
    {
        $this->hydrator_factory = $hydrator_factory;

        return $this;
    }

    /**
     * Get the builder factory
     *
     * @return BuilderFactory|null The builder factory
     */
    public function getBuilderFactory()
    {
        return $this->builder_factory;
    }

    /**
     * Set the builder factory
     *
     * @param BuilderFactory|null $builder_factory The builder factory
     * @return $this This instance
     */
    public function setBuilderFactory(BuilderFactory $builder_factory = null): self
    {
        $this->builder_factory = $builder_factory;

        return $this;
    }

    /**
     * Get the value of the configuration flag that denotes whether hydration
     * should always be run after building a new model when processing
     * specified types
     *
     * @return bool
     */
    public function getAlwaysHydrateAfterBuilding(): bool
    {
        return $this->always_hydrate_after_building;
    }

    /**
     * Set the value of the configuration flag that denotes whether hydration
     * should always be run after building a new model when processing
     * specified types
     *
     * @param bool $always_hydrate_after_building Whether or not to always
     *  hydrate after building a new model when processing types
     * @return $this
     */
    public function setAlwaysHydrateAfterBuilding(bool $always_hydrate_after_building): self
    {
        $this->always_hydrate_after_building = $always_hydrate_after_building;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * If a hydrator isn't provided, an attempt will be made to automatically
     * resolve and build an appropriate hydrator from the provided factory
     *
     * @param mixed $input_data The input data
     * @param mixed $model The model to hydrate
     * @param Hydrator|null $hydrator The hydrator to use
     * @return mixed The hydrated model
     */
    public function processForModel($input_data, $model, Hydrator $hydrator = null)
    {
        $input_data = $this->transformInput($input_data);

        return $this->hydrateModel($input_data, $model, $hydrator);
    }

    /**
     * {@inheritdoc}
     *
     * If a hydrator isn't provided, an attempt will be made to automatically
     * resolve and build an appropriate hydrator from the provided factory
     *
     * @param mixed $input_data The input data
     * @param string $type The type to build
     * @param Builder $builder The builder to use in the process
     * @param Hydrator $hydrator An optional hydrator to use in the process,
     *  after the type is built, to aid in the full hydration of the resulting
     *  model
     * @return mixed The built model
     */
    public function processForType($input_data, string $type, Builder $builder, Hydrator $hydrator = null)
    {
        $input_data = $this->transformInput($input_data);

        if (null === $builder) {
            $builder = $this->getBuilderForModel($model);
        }

        $model = $builder->build($input_data);

        if (null !== $hydrator || $this->always_hydrate_after_building) {
            $model = $this->hydrateModel($input_data, $model, $hydrator);
        }

        return $model;
    }

    /**
     * Transform the input data
     *
     * @param mixed $input_data The input data
     * @return mixed The resulting transformed data
     */
    protected function transformInput($input_data)
    {
        return $this->input_transformer->transform($input_data);
    }

    /**
     * Hydrate a model from incoming data
     *
     * If a hydrator isn't provided, an attempt will be made to automatically
     * resolve and build an appropriate hydrator from the provided factory
     *
     * @param mixed $input_data The input data
     * @param mixed $model The model to hydrate
     * @param Hydrator|null $hydrator The hydrator to use
     * @return mixed The hydrated model
     */
    protected function hydrateModel($input_data, $model, Hydrator $hydrator = null)
    {
        if (null === $hydrator) {
            $hydrator = $this->getHydratorForModel($model);
        }

        return $hydrator->hydrate($input_data, $model);
    }

    /**
     * Get a Hydrator for a given model
     *
     * @param mixed $model The model to get a hydrator for
     * @throws UnresolvableHydratorException If a hydrator can't be resolved for
     *  the given model
     * @return Hydrator The resulting hydrator
     */
    protected function getHydratorForModel($model): Hydrator
    {
        if (null === $this->hydrator_factory) {
            throw UnresolvableHydratorException::forModel($model);
        }

        return $this->hydrator_factory->buildForModel($model);
    }

    /**
     * Get a Builder for a given model
     *
     * @param string $type The type to get a builder for
     * @throws UnresolvableBuilderException If a builder can't be resolved for
     *  the given model
     * @return Builder The resulting builder
     */
    protected function getBuilderForType(string $type): Builder
    {
        if (null === $this->builder_factory) {
            throw UnresolvableBuilderException::forType($type);
        }

        return $this->builder_factory->buildForModel($type);
    }
}
