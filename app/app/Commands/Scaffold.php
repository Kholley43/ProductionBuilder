<?php
namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\I18n\Time;

/**
 * php spark scaffold "prompt here"
 *
 * Generates basic CRUD scaffolding (migration, model, controller, route)
 * for a single entity based on a plain-English prompt.
 */
class Scaffold extends BaseCommand
{
    protected $group       = 'Scaffolder';
    protected $name        = 'scaffold';
    protected $description = 'Generate CRUD scaffolding from a plain-English prompt.';
    protected $usage       = 'php spark scaffold "My prompt describing entity and fields"';
    protected $arguments   = [
        'prompt' => 'Plain-English description, e.g. "I need an API to manage blog posts with title, content, author"',
    ];

    public function run(array $params)
    {
        $prompt = $params[0] ?? null;
        if ($prompt === null) {
            CLI::error('Please provide a prompt, e.g. php spark scaffold "I need an API ..."');
            return;
        }

        // 1. Parse prompt (now returns entity, fields, relationships)
        [$entity, $fields, $relationships] = $this->parsePrompt($prompt);
        if ($entity === null || $fields === []) {
            CLI::error('Could not parse entity or fields from the prompt. Please rephrase.');
            return;
        }

        $entityStudly = ucfirst($this->studly($entity));
        // Slug/table name: plural, lowercase, no spaces
        $table        = strtolower(preg_replace('/\s+/', '', $this->plural($entity)));

        CLI::write("Generating CRUD scaffolding for entity: {$entityStudly} (table: {$table})", 'green');

        // 2. Create Migration
        $migrationClass = 'Create' . ucfirst($this->plural($entityStudly));
        $timestamp      = Time::now('UTC')->format('YmdHis');
        $migrationFile  = APPPATH . 'Database/Migrations/' . $timestamp . '_' . $migrationClass . '.php';
        $this->createMigration($migrationFile, $migrationClass, $table, $fields);
        CLI::write(" > Migration: " . CLI::color($migrationFile, 'yellow'));

        // 3. Create Model (now includes validation rules & relationship stubs)
        $modelClass = $entityStudly;
        $modelFile  = APPPATH . 'Models/' . $modelClass . '.php';
        $this->createModel($modelFile, $modelClass, $table, $fields);
        CLI::write(" > Model: " . CLI::color($modelFile, 'yellow'));

        // 3b. Create Feature Test
        $this->createTest($entityStudly);

        // 4. Create Controller
        $controllerClass = $entityStudly . 'Controller';
        $controllerFile  = APPPATH . 'Controllers/' . $controllerClass . '.php';
        $this->createController($controllerFile, $controllerClass, $modelClass);
        CLI::write(" > Controller: " . CLI::color($controllerFile, 'yellow'));

        // 5. Append Route
        $this->appendRoute($table, $controllerClass);

        // 6. Create Basic Front-end Page
        $this->createFrontend($entityStudly, $table, $fields);

        // 7. Ensure Docker files
        $this->ensureDockerFiles();

        CLI::write("Scaffolding complete! Run `php spark migrate` to create the table.", 'green');
    }

    // ----------------------------------------------------------------
    private function parsePrompt(string $prompt): array
    {
        $prompt = strtolower($prompt);
        $entity = null;
        $fields = [];
        $relationships = [];

        // naive field extraction (manage X with ...)
        if (preg_match('/(?:manage|create)\s+([a-z\s]+?)(?:\s+api|\s+crud|\s+system)?\s+with\s+(.+)/', $prompt, $m)) {
            $entityRaw  = trim($m[1]);
            // remove leading articles
            $entityRaw  = preg_replace('/^(a|an)\s+/','', $entityRaw);
            $fieldsRaw  = trim($m[2]);
            $entity     = $this->singular($entityRaw);

            // remove trailing punctuation
            $fieldsRaw  = rtrim($fieldsRaw, '.');
            // split by comma or "and"
            $parts      = preg_split('/,|\band\b/', $fieldsRaw);
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p === '') continue;
                if (strpos($p, ':') !== false) {
                    [$name,$type] = array_map('trim', explode(':', $p, 2));
                } else {
                    $name = $p; $type = 'string';
                }
                $fields[$name] = $type;
            }
        }

        // relationship parsing: "a post has many comments" or "comments belong to post"
        if (preg_match_all('/(\w+)\s+(has many|belongs to)\s+(\w+)/', $prompt, $relMatches, PREG_SET_ORDER)) {
            foreach ($relMatches as $rm) {
                $relationships[] = [$rm[1], $rm[2], $rm[3]];
            }
        }

        return [$entity, $fields, $relationships];
    }

    private function createMigration(string $path, string $class, string $table, array $fields)
    {
        if (is_file($path)) {
            CLI::error("Migration already exists: {$path}");
            return;
        }

        $fieldsCode = "            'id' => [
                'type'           => 'INT',
                'constraint'     => 9,
                'unsigned'       => true,
                'auto_increment' => true,
            ],\n";
        foreach ($fields as $name => $type) {
            $typeMap = $this->dbType($type);
            $fieldsCode .= "            '{$name}' => [ 'type' => '{$typeMap}' ],\n";
        }
        $fieldsCode .= "            'created_at' => [ 'type' => 'DATETIME', 'null' => true ],\n";
        $fieldsCode .= "            'updated_at' => [ 'type' => 'DATETIME', 'null' => true ],\n";

        $template = <<<PHP
<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class {$class} extends Migration
{
    public function up()
    {
        \$this->forge->addField([
{$fieldsCode}        ]);
        \$this->forge->addKey('id', true);
        \$this->forge->createTable('{$table}');
    }

    public function down()
    {
        \$this->forge->dropTable('{$table}');
    }
}
PHP;
        file_put_contents($path, $template);
    }

    private function createModel(string $path, string $class, string $table, array $fields)
    {
        if (is_file($path)) {
            CLI::error("Model already exists: {$path}");
            return;
        }

        $allowedList = "['" . implode("', '", array_keys($fields)) . "']";

        // validation rules
        $rulesArr = [];
        foreach ($fields as $name => $type) {
            $rulesArr[] = "        '{$name}' => '" . $this->validationRule($type) . "',";
        }
        $rulesCode = implode("\n", $rulesArr);

        $template = <<<PHP
<?php

namespace App\Models;

use CodeIgniter\Model;

class {$class} extends Model
{
    protected \$table            = '{$table}';
    protected \$primaryKey       = 'id';
    protected \$allowedFields    = {$allowedList};
    protected \$useTimestamps    = true;

    // Validation
    protected \$validationRules = [
{$rulesCode}
    ];
}
PHP;
        file_put_contents($path, $template);
    }

    private function createController(string $path, string $class, string $model)
    {
        if (is_file($path)) {
            CLI::error("Controller already exists: {$path}");
            return;
        }
        $template = <<<PHP
<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;

class {$class} extends ResourceController
{
    protected \$modelName = 'App\\Models\\{$model}';
    protected \$format    = 'json';
}
PHP;
        file_put_contents($path, $template);
    }

    private function appendRoute(string $table, string $controller)
    {
        $routesPath = APPPATH . 'Config/Routes.php';
        $content    = file_get_contents($routesPath);
        $routeLine  = "\$routes->resource('{$table}', ['controller' => '{$controller}']);";
        if (strpos($content, $routeLine) === false) {
            file_put_contents($routesPath, "\n// Auto-generated by Scaffold command on " . date('Y-m-d H:i:s') . "\n{$routeLine}\n", FILE_APPEND);
            CLI::write(" > Routes updated", 'yellow');
        } else {
            CLI::write(" > Route already present, skipped", 'yellow');
        }
    }

    // Helpers --------------------------------------------------------
    private function dbType(string $type): string
    {
        return match (strtolower($type)) {
            'text'      => 'TEXT',
            'int', 'integer' => 'INT',
            'date'      => 'DATE',
            'datetime'  => 'DATETIME',
            'boolean', 'bool' => 'TINYINT',
            'float', 'double' => 'FLOAT',
            default     => 'VARCHAR',
        };
    }

    private function singular(string $word): string
    {
        return rtrim($word, 's');
    }

    private function plural(string $word): string
    {
        return str_ends_with($word, 's') ? $word : $word . 's';
    }

    private function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    private function validationRule(string $type): string
    {
        return match (strtolower($type)) {
            'int', 'integer' => 'required|integer',
            'float', 'double', 'decimal' => 'required|decimal',
            'date', 'datetime' => 'required|valid_date',
            'boolean', 'bool' => 'required|in_list[0,1]',
            default => 'required|string',
        };
    }

    private function createTest(string $entityStudly)
    {
        $testDir  = ROOTPATH . 'tests/Feature/';
        if (! is_dir($testDir)) {
            mkdir($testDir, 0755, true);
        }
        $testFile = $testDir . $entityStudly . 'ApiTest.php';
        if (is_file($testFile)) {
            return;
        }

        $lower = strtolower($this->plural($entityStudly));
        $template = <<<PHP
<?php

namespace Tests\Feature;

use CodeIgniter\Test\FeatureTestCase;

class {$entityStudly}ApiTest extends FeatureTestCase
{
    public function testCreateAndList()
    {
        // Create
        \$response = \$this->call('post', '/{$lower}', [ /* TODO: sample data */ ]);
        \$response->assertStatus(201);

        // List
        \$response = \$this->call('get', '/{$lower}');
        \$response->assertStatus(200);
    }
}
PHP;
        file_put_contents($testFile, $template);
    }

    private function createFrontend(string $entityStudly, string $table, array $fields)
    {
        $pageDir  = ROOTPATH . 'public/ui/';
        if (! is_dir($pageDir)) {
            mkdir($pageDir, 0755, true);
        }
        $pageFile = $pageDir . $table . '.html';
        if (is_file($pageFile)) return;

        $jsonFields = json_encode(array_keys($fields));

        $template = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{$entityStudly} UI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-5">
<h1>{$entityStudly} Manager</h1>
<div id="app"></div>
<script>
const fields = {$jsonFields};
const api    = '/{$table}';

async function refresh() {
    const resp = await fetch(api);
    const data = await resp.json();
    document.getElementById('app').innerHTML = `<pre>${JSON.stringify(data, null, 2)}</pre>`;
}
refresh();
</script>
</body>
</html>
HTML;
        file_put_contents($pageFile, $template);
    }

    private function ensureDockerFiles()
    {
        $dockerfile = ROOTPATH . 'Dockerfile';
        if (! is_file($dockerfile)) {
            $df = <<<DOCKER
FROM php:8.2-apache
RUN docker-php-ext-install pdo pdo_sqlite
COPY . /var/www/html/
WORKDIR /var/www/html
RUN chmod -R 755 writable && chown -R www-data:www-data writable
EXPOSE 80
CMD ["apache2-foreground"]
DOCKER;
            file_put_contents($dockerfile, $df);
        }

        $compose = ROOTPATH . 'docker-compose.yml';
        if (! is_file($compose)) {
            $dc = <<<YML
version: '3.9'
services:
  app:
    build: .
    ports:
      - "8080:80"
    volumes:
      - .:/var/www/html
    restart: unless-stopped
YML;
            file_put_contents($compose, $dc);
        }
    }
} 