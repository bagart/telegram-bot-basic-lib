<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotBasic\DevTool;

use BAGArt\TelegramBot\TgApiServices\TgApiProperty;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class DTOGenerator
{
    public array $result = [];

    public function __construct(
        public string $jsonPath = '/tmp/tg_dto_schema.json',
        public string $dtoDir = __DIR__.'/../../TelegramBot',
        public string $namespace = 'BAGArt\\TelegramBot',
        public bool $full = false,
    ) {
    }

    public function generate(): array
    {
        $schema = $this->loadSchema();
        $this->prepareDirectories($this->full);

        $types = [];
        foreach ($schema['types'] as $dto => $dtoMeta) {
            $types[$dto] = [
                'orig' => $dto,
                'description' => $this->getDescription($dtoMeta['description']['markdown'] ?? null),
                'dto' => $this->generateDtoClass(
                    entityName: $dto,
                    path: 'type',
                    dtoMeta: $dtoMeta,
                ),
            ];
            echo 't';
        }
        $this->generateDtoListEnum($types, 'Types');

        $methods = [];
        foreach ($schema['methods'] as $dto => $dtoMeta) {
            $methods[$dto] = [
                'orig' => $dto,
                'description' => $this->getDescription($dtoMeta['description']['markdown'] ?? null),
                'dto' => $this->generateDtoClass(
                    entityName: $dto,
                    path: 'method',
                    dtoMeta: $dtoMeta,
                ),
            ];
            echo 'm';
        }
        $this->generateDtoListEnum($methods, 'Methods');

        $result = $this->result;
        $this->result = [];

        return $result;
    }

    private function loadSchema(): array
    {
        if (!file_exists($this->jsonPath)) {
            throw new RuntimeException("File not found: {$this->jsonPath}");
        }

        $data = json_decode(file_get_contents($this->jsonPath), true);

        if (!$data) {
            throw new RuntimeException('Invalid JSON');
        }

        return $data;
    }

    private function prepareDirectories(bool $full): void
    {
        $dirs = [
            "{$this->dtoDir}/TgApi/Methods/DTO",
            "{$this->dtoDir}/TgApi/Methods/Enum",
            "{$this->dtoDir}/TgApi/Types/DTO",
            "{$this->dtoDir}/TgApi/Types/Enum",
        ];

        foreach ($dirs as $dir) {
            $needToCreate = false;
            if (!is_dir($dir)) {
                $needToCreate = true;
            } elseif ($full) {
                $this->rmdir($dir);
                $needToCreate = true;
            }

            if ($needToCreate) {
                mkdir($dir, 0777, true);
            }
        }
    }

    private function rmdir(string $dirPath): bool
    {
        if ($dirPath !== '' && is_dir($dirPath)) {
            $dirObj = new RecursiveDirectoryIterator(
                $dirPath,
                RecursiveDirectoryIterator::SKIP_DOTS
            );
            $files = new RecursiveIteratorIterator($dirObj, RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $path) {
                $path->isDir() && !$path->isLink()
                    ? rmdir($path->getPathname())
                    : unlink($path->getPathname());
            }
            rmdir($dirPath);

            return true;
        }

        return false;
    }

    private function getDescription(?string $description): ?string
    {
        if ($description === null) {
            return null;
        }
        $description = str_replace(
            ["\r", "\n", "'"],
            ['', '; ', '"'],
            trim($description)
        );
        if (trim($description, '"_-~.;,()[]') === '') {
            return null;
        }

        return $description;
    }

    private function generateGetReturnTypesMethod(array $dtoMeta): array
    {
        if (!isset($dtoMeta['returnType'])) {
            return ['', ''];
        }

        [
            $origReturnTypes,
            $phpReturnTypes,
            $returnType,
            $returnLiterals,
            $returnNullable,
        ] = $this->getTypes(['type' => $dtoMeta['returnType']]);

        if ($returnNullable) {
            $phpReturnTypes['null'] = 'null';
        }

        if (!$phpReturnTypes || $phpReturnTypes === ['null' => 'null']) {
            return ['', ''];
        }

        [$phpTypesTmpl, $getReturnTypesFqcns] = $this->getPhpTypesTemplate($phpReturnTypes);
        $useFqcnLines = $getReturnTypesFqcns
            ? "\n".implode("\n", array_map(fn ($f) => 'use '.ltrim($f, '\\').';', $getReturnTypesFqcns))
            : '';

        $return = <<<PHP

    public static function getReturnTypes(): array
    {
        return [
            $phpTypesTmpl,
        ];
    }

PHP;

        return [$return, $useFqcnLines];
    }

    private function generateDtoClass(
        string $entityName,
        string $path,
        array $dtoMeta,
    ): string {
        $returnTypeMethod = null;
        $returnTypeUse = '';
        $useFqcnLines = null;
        if ($path === 'type') {
            $entityScope = 'Type';
            $namespace = "{$this->namespace}\\TgApi\\{$entityScope}s\\DTO";
            $classname = $this->pascal($entityName).'TypeDTO';
            $fieldsProperty = 'fields';
        } else {
            $entityScope = 'Method';
            $namespace = "{$this->namespace}\\TgApi\\{$entityScope}s\\DTO";
            $classname = $this->pascal($entityName).'MethodDTO';
            $fieldsProperty = 'parameters';
            [$returnTypeMethod, $returnTypeUse] = $this->generateGetReturnTypesMethod($dtoMeta);
        }
        $entityScopes = "{$entityScope}s";
        $fileName = "{$this->dtoDir}/TgApi/$entityScopes/DTO/$classname.php";
        $dtoDescription = $this->getDescription($dtoMeta['description']['markdown'] ?? null);
        $todo = [];
        if (!empty($dtoMeta['oneOf'])) {
            echo "[WARNING] $classname is contract. not implemented yet.\n";
            $todo[] = "#[Todo('Is contract but not implemented yet.')]";
        }

        $urlHashLink = mb_strtolower($this->pascal($this->pascal($entityName)));
        if (!empty($dtoMeta[$fieldsProperty])) {
            $propertiesNReq = [];
            $propertiesReq = [];

            $fieldFormat = [];
            foreach ($dtoMeta[$fieldsProperty] as $fieldMeta) {
                $tgPropName = $fieldMeta['name'];
                $propPhpName = $this->pascalProp($fieldMeta['name']);
                $propertyDescription = $this->getDescription($fieldMeta['description']['markdown'] ?? null);

                [
                    $origTypes,
                    $phpTypes,
                    $type,
                    $literals,
                    $nullable,
                ] = $this->getTypes($fieldMeta, $fieldMeta['required'] ?? null);

                $default = $nullable ? 'null' : null;

                if (count($literals) === 1) {
                    if (count($phpTypes) !== 1) {
                        throw new RuntimeException('Unexpected default type count:'.json_encode($fieldMeta));
                    }
                    $default = array_first($literals);
                    $default = match (array_first($phpTypes)) {
                        'string' => "'$default'",
                        'int' => $default,
                        'bool' => $default ? 'true' : 'false',
                        default => throw new RuntimeException(
                            'Unexpected default field type: '.json_encode($fieldMeta)
                        )
                    };
                } elseif ($literals !== []) {
                    if (count($phpTypes) !== 1) {
                        throw new RuntimeException('Unexpected enum field type count:'.json_encode($fieldMeta));
                    }
                    $enumType = array_first($phpTypes);
                    foreach ($literals as $key => $value) {
                        $literals[$key] = match (array_first($phpTypes)) {
                            'string' => "'$value'",
                            'int' => $value,
                            // bool: ignore?
                            default => throw new RuntimeException(
                                'Unexpected default field type: '.json_encode($fieldMeta)
                            )
                        };
                    }
                    $type = $this->generateEnum(
                        $tgPropName,
                        $entityScope,
                        $entityName,
                        $literals,
                        $enumType,
                        $propertyDescription,
                    );
                    if ($nullable) {
                        $type = "?$type";
                    }
                    $phpTypes = [$type];
                }

                if ($default === null) {
                    if ($propertyDescription) {
                        $propertiesReq[] = "#[Description('$propertyDescription')]";
                    }
                    $propertiesReq[] = "public {$type} \${$propPhpName},";
                } else {
                    if ($propertyDescription) {
                        $propertiesNReq[] = "#[Description('$propertyDescription')]";
                    }
                    $propertiesNReq[] = "public {$type} \${$propPhpName} = $default,";
                }

                $fieldFormat[$tgPropName] = new TgApiProperty(
                    property: $propPhpName,
                    tgPropName: $tgPropName,
                    types: array_values($phpTypes),
                    tgTypes: $origTypes,
                    nullable: $nullable,
                    required: $fieldMeta['required'] ?? false,
                );
            }
            $properties = implode("\n        ", array_merge($propertiesReq, $propertiesNReq));

            $tgPropMetas = json_encode($fieldFormat);

            $todo = $todo ? "\n".implode("\n", $todo) : null;

            $code = <<<PHP
<?php

declare(strict_types=1);

namespace $namespace;
$returnTypeUse$useFqcnLines
use {$this->namespace}\\Contracts\\TgApi\\TgApiEntityEnumContract;
use {$this->namespace}\\Contracts\\TgApi\\TgApi{$entityScope}DTOContract;
use {$this->namespace}\\TgApi\\TgApiEntityScopeEnum;
use {$this->namespace}\\TgApi\\$entityScopes\\TgApi{$entityScopes}Enum;
use {$this->namespace}\\TgApiServices\\TgApiProperty;
$todo
#[Warning('File is auto-generated. Use DtoGenerator to change')]
#[Description('$dtoDescription')]
#[See('https://core.telegram.org/bots/api#$urlHashLink')]
class $classname implements TgApi{$entityScope}DTOContract
{
    public readonly TgApiEntityEnumContract \$dto;

    public readonly TgApiEntityScopeEnum \$entityScope;

    public function __construct(
        $properties
    ) {
        \$this->dto = static::tgApiEntity();
        \$this->entityScope = static::tgEntityScope();
    }
$returnTypeMethod
    public static function tgApiEntity(): TgApi{$entityScopes}Enum
    {
        return TgApi{$entityScopes}Enum::$entityName;
    }

    public static function tgEntityScope(): TgApiEntityScopeEnum
    {
        return TgApiEntityScopeEnum::$entityScope;
    }

    /** @return TgApiProperty[] */
    public static function tgPropertyMetas(): array
    {
        \$metaByProp = json_decode(
            <<<'XJSON'
$tgPropMetas
XJSON,
            true,
            20,
            JSON_THROW_ON_ERROR
        );

        \$result = [];
        foreach (\$metaByProp as \$tgPropName => \$propertyMeta) {
            \$result[\$tgPropName] = new TgApiProperty(...\$propertyMeta);
        }

        return \$result;
    }
}

PHP;
        } else {
            $todo = $todo ? "\n".implode("\n", $todo) : null;

            $code = <<<PHP
<?php

declare(strict_types=1);

namespace $namespace;
$returnTypeUse
use {$this->namespace}\\Contracts\\TgApi\\TgApi{$entityScope}DTOContract;
use {$this->namespace}\\TgApi\\TgApiEntityScopeEnum;
use {$this->namespace}\\TgApi\\$entityScopes\\TgApi{$entityScopes}Enum;
$todo
#[Warning('File is auto-generated. Use DtoGenerator to change')]
#[Description('$dtoDescription')]
#[See('https://core.telegram.org/bots/api#$urlHashLink')]
class $classname implements TgApi{$entityScope}DTOContract
{
    public readonly TgApi{$entityScopes}Enum \$dto;

    public readonly TgApiEntityScopeEnum \$entityScope;

    public function __construct()
    {
        \$this->dto = static::tgApiEntity();
        \$this->entityScope = static::tgEntityScope();
    }

    public static function tgApiEntity(): TgApi{$entityScopes}Enum
    {
        return TgApi{$entityScopes}Enum::$entityName;
    }

    public static function tgEntityScope(): TgApiEntityScopeEnum
    {
        return TgApiEntityScopeEnum::$entityScope;
    }
$returnTypeMethod
    public static function tgPropertyMetas(): array
    {
        return [];
    }
}

PHP;
        }
        $originalFileName = $fileName;
        $fileName = realpath($fileName) ?: $originalFileName;
        $pathResult = str_replace(
            str_replace('\\', '/', realpath(dirname(__DIR__, 6))).'/',
            '',
            str_replace('\\', '/', $fileName)
        );
        $this->result[file_exists($fileName) ? 'Updated' : 'Created'][$path][] = $pathResult;

        file_put_contents($fileName, $code);

        return "\\$namespace\\$classname";
    }

    private function pascal(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $name)));
    }

    private function getTypes(array $fieldMeta, bool $required = true): array
    {
        $origTypes = [];
        $types = $fieldMeta['type']['types'] ?? [$fieldMeta['type']];
        $phpTypes = [];
        if (!$required) {
            $phpTypes['null'] = 'null';
        }

        $literals = [];
        foreach ($types as $typeNode) {
            if (empty($typeNode['type']) || !is_string($typeNode['type'])) {
                throw new RuntimeException('Invalid field type: '.json_encode($fieldMeta));
            }
            if (isset($typeNode['literal'])) {
                $literals[] = $typeNode['literal'];
            }
            $origTypes[] = $typeNode;
            $type = $this->resolveType($typeNode);
            if (is_array($type)) {
                $phpTypes[$type['type']][] = $type['of'];
            } else {
                $phpTypes[$type] = $type;
            }
        }
        $literals = array_unique($literals);

        $nullable = isset($phpTypes['null']);
        unset($phpTypes['null']);
        if (count($phpTypes) === 2 && isset($phpTypes['string'])) {
            if (isset($phpTypes['int'])) {
                unset($phpTypes['int']);
            } else {
                throw new RuntimeException('Unexpected TYPE combination: '.json_encode($phpTypes));
            }
        }
        ksort($phpTypes);
        $type = implode('|', array_keys($phpTypes));
        if ($type === '') {
            throw new RuntimeException('Unexpected field type:'.json_encode($fieldMeta));
        } elseif (count($phpTypes) === 1 && $nullable) {
            $type = "?$type";
        } elseif ($nullable) {
            $type .= '|null';
        }

        return [
            $origTypes,
            $phpTypes,
            $type,
            $literals,
            $nullable,
        ];
    }

    private function resolveType(array $typeNode): string|array
    {
        if ($typeNode['type'] === 'array') {
            $of = $this->resolveType($typeNode['of']);
            if (is_array($of)) {
                // @todo probably is mixed[][]
                if ($of['type'] === 'array') {
                    $of = $of['of'];
                }
            }

            return [
                'type' => 'array',
                'of' => $of,
            ];
        }

        return match ($typeNode['type']) {
            'null' => 'null',
            'int32' => 'int',
            'bool' => 'bool',
            'str', 'int53', 'input-file', 'float' => 'string',
            'api-type' => "\\{$this->namespace}\\TgApi\\Types\\DTO\\".$this->pascal($typeNode['name']).'TypeDTO',
            'union' => 'mixed',
            default => throw new RuntimeException('Unexpected TYPE from:'.json_encode($typeNode))
        };
    }

    private function pascalProp(string $name): string
    {
        return lcfirst(mb_substr($name, 0, 1)).mb_substr($this->pascal($name), 1);
    }

    private function generateEnum(
        string $propOrigName,
        string $entityName,
        string $dto,
        array $literals,
        string $enumType,
        ?string $propertyDescription,
    ): string {
        $cases = [];
        foreach ($literals as $v) {
            $vPrepared = match ($enumType) {
                'string' => var_export(trim($v, "'\" \n\r\t\v\0"), true),
                'int' => $v,
                default => throw new RuntimeException("Unknown enum type: $enumType"),
            };

            if ($propOrigName === 'active_period') {
                if (!is_numeric($v)) {
                    throw new RuntimeException("Invalid value for active_period: $propOrigName");
                }
                if (0 === ($v % (24 * 60 * 60))) {
                    $case = 'PERIOD_'.($v / (24 * 60 * 60)).'D';
                } elseif (0 === ($v % (60 * 60))) {
                    $case = 'PERIOD_'.($v / 60 / 60).'H';
                } else {
                    $case = "PERIOD_$v";
                }
            } elseif ($dto === 'giftPremiumSubscription' && $propOrigName === 'star_count') {
                $case = match ($v) {
                    1000 => 'STARS_3M',
                    1500 => 'STARS_6M',
                    2500 => 'STARS_12M',
                    default => "STARS_$v",
                };
            } else {
                $case = strtoupper(preg_replace('/[^a-z0-9]+/i', '_', trim((string)$v, "'\" \n\r\t\v\0")));
                if (is_numeric($case)) {
                    $case = 'X'.$case;
                } elseif ($case === '_') {
                    $case = 'X'.bin2hex($v);
                }
            }
            $cases[] = "    case $case = $vPrepared;";
        }
        $cases = implode("\n", $cases);
        $enumClassName = (in_array($propOrigName, [
                'mime_type',
                'type',
            ], true) ? $this->pascal($dto).'Prop' : null)
            ."{$this->pascal($propOrigName)}Enum";
        $enumNamespace = "{$this->namespace}\\TgApi\\{$entityName}s\\Enum";
        $urlHashLink = mb_strtolower($this->pascal($dto));
        $propertyDescriptionTmpl = $propertyDescription !== null
            ? "#[Description('$propertyDescription')]\n"
            : null;
        $code = <<<PHP
<?php

declare(strict_types=1);

namespace $enumNamespace;

use {$this->namespace}\\Contracts\\TgApi\\TgApiEnumContract;

#[Warning('File is auto-generated. Use DtoGenerator to change')]
{$propertyDescriptionTmpl}#[See('https://core.telegram.org/bots/api#$urlHashLink')]
enum $enumClassName: $enumType implements TgApiEnumContract
{
$cases
}

PHP;
        $fileName = "{$this->dtoDir}/TgApi/{$entityName}s/Enum/{$enumClassName}.php";

        $resultParth = str_replace(
            str_replace('\\', '/', realpath(dirname(__DIR__, 6))).'/',
            '',
            str_replace('\\', '/', $fileName)
        );
        $this->result[file_exists($fileName) ? 'Updated' : 'Created'][$entityName.'Enum'][] = $resultParth;

        if (file_exists($fileName) && !in_array($propOrigName, [
                'parse_mode',
                'text_parse_mode',
                'active_period',
                'currency',
                'style',
                'thumbnail_mime_type',
            ], true)) {
            if ($this->full) {
                $fileName = "{$this->dtoDir}/TgApi/{$entityName}s/Enum/{$enumClassName}NeedToCheck"
                    .rand(10000, 99999).'.php';
                echo "\nDouble use of same Enum. Need to check manual: $fileName\n";
            }
        }
        file_put_contents($fileName, $code);

        return "\\$enumNamespace\\$enumClassName";
    }

    private function generateDtoListEnum(array $dtoList, string $tgApiEntityScopes): string
    {
        $cases = [];
        foreach ($dtoList as $dtoData) {
            if (isset($dtoData['description'])) {
                $cases[] = "#[Description('{$dtoData['description']}')]";
            }
            $cases[] = "case {$dtoData['orig']} = {$dtoData['dto']}::class;";
        }
        $cases = implode("\n    ", $cases);
        $enumNamespace = "{$this->namespace}\\TgApi\\$tgApiEntityScopes";
        $className = "TgApi{$tgApiEntityScopes}Enum";
        $tgApiEntityScopesLC = mb_strtolower($tgApiEntityScopes);
        $code = <<<PHP
<?php

declare(strict_types=1);

namespace $enumNamespace;

use {$this->namespace}\\Contracts\\TgApi\\TgApiEntityEnumContract;

#[Warning('File is auto-generated. Use DtoGenerator to change or CustomMethodEnum extends TgApiEntityScopeEnumContract')]
#[Description('List of Telegram Bot Api $tgApiEntityScopes')]
#[See('https://core.telegram.org/bots/api#available-$tgApiEntityScopesLC')]
enum $className: string implements TgApiEntityEnumContract
{
    $cases
}

PHP;
        $fileName = "{$this->dtoDir}/TgApi/$tgApiEntityScopes/$className.php";
        file_put_contents($fileName, $code);

        return "\\$enumNamespace\\$className";
    }

    private function getPhpTypesTemplate(array $phpTypes): array
    {
        $phpTypesTmpl = [];
        $fqcns = [];

        foreach ($phpTypes as $phpTypeKey => $phpTypeValue) {
            if (is_array($phpTypeValue)) {
                foreach ($phpTypeValue as $typeCur) {
                    if (str_starts_with($typeCur, '\\')) {
                        $phpTypesTmpl['array'][] = array_last(explode('\\', $typeCur)).'::class';
                        $fqcns[] = $typeCur;
                    } else {
                        $phpTypesTmpl['array'][] = "'$typeCur'";
                    }
                }
            } elseif (str_starts_with($phpTypeKey, '\\')) {
                $phpTypesTmpl[$phpTypeKey] = array_last(explode('\\', $phpTypeKey)).'::class';
                $fqcns[] = $phpTypeKey;
            } else {
                $phpTypesTmpl[$phpTypeKey] = "'$phpTypeKey'";
            }
        }
        if (isset($phpTypesTmpl['array'])) {
            $phpTypesTmpl['array'] = "[\n                "
                .implode(",\n                ", $phpTypesTmpl['array'])
                .",\n            ]";
        }
        ksort($phpTypesTmpl);
        $phpTypesStr = implode(",\n            ", $phpTypesTmpl);

        $fqcns = array_unique($fqcns);
        sort($fqcns);

        return [$phpTypesStr, $fqcns];
    }
}
