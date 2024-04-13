<?php


namespace SilverStripe\GraphQL\Schema\Type;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\GraphQL\Schema\Exception\SchemaBuilderException;
use SilverStripe\GraphQL\Schema\Field\Argument;
use SilverStripe\GraphQL\Schema\Interfaces\ConfigurationApplier;
use SilverStripe\GraphQL\Schema\Interfaces\SchemaComponent;
use SilverStripe\GraphQL\Schema\Interfaces\SchemaValidator;
use SilverStripe\GraphQL\Schema\Interfaces\SignatureProvider;
use SilverStripe\GraphQL\Schema\Schema;
use GraphQL\Language\DirectiveLocation;

class Directive implements ConfigurationApplier, SchemaValidator, SignatureProvider, SchemaComponent
{
    use Injectable;
    use Configurable;

    private string $name;
    private ?string $description = null;
    private array $locations;
    private array $args = [];

    /**
     * @throws SchemaBuilderException
     */
    public function __construct(string $directiveName, array $config = [])
    {
        $this->setName($directiveName);
        $this->applyConfig($config);
    }

    public function applyConfig(array $config): void
    {
        Schema::assertValidConfig($config, [
            'name',
            'description',
            'locations',
            'args',
        ]);

        if (isset($config['name'])) {
            $this->setName($config['name']);
        }

        if (isset($config['description'])) {
            $this->setDescription($config['description']);
        }

        $locations = $config['locations'] ?? [DirectiveLocation::FIELD];
        $this->setLocations($locations);

        if (isset($config['args'])) {
            $this->setArgs($config['args']);
        }
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @throws SchemaBuilderException
     */
    public function setName(string $name): self
    {
        Schema::assertValidName($name);
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description)
    {
        $this->description = $description;
        return $this;
    }

    public function getLocations(): array
    {
        return $this->locations;
    }

    /**
     * @param string $argName
     * @param null $config
     * @param callable|null $callback
     * @return Directive
     */
    public function setLocations(array $locations): self
    {
        $this->locations = $locations;
        return $this;
    }

    public function getArgs(): array
    {
        return $this->args;
    }

    /**
     * @param string $argName
     * @param null $config
     * @param callable|null $callback
     * @return Directive
     */
    public function addArg(string $argName, $config, ?callable $callback = null): self
    {
        $argObj = $config instanceof Argument ? $config : Argument::create($argName, $config);
        $this->args[$argObj->getName()] = $argObj;
        if ($callback) {
            call_user_func_array($callback, [$argObj]);
        }
        return $this;
    }

    /**
     * @param array $args
     * @return $this
     * @throws SchemaBuilderException
     */
    public function setArgs(array $args): self
    {
        Schema::assertValidConfig($args);
        foreach ($args as $argName => $config) {
            if ($config === false) {
                continue;
            }
            $this->addArg($argName, $config);
        }

        return $this;
    }

    public function validate(): void
    {
    }

    public function getSignature(): string
    {
        return md5(json_encode([
            $this->getName(),
            $this->getDescription(),
        ]) ?? '');
    }
}
