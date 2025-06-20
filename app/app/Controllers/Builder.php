<?php

namespace App\Controllers;

use CodeIgniter\CLI\CLI;
use ZipArchive;

class Builder extends BaseController
{
    public function index()
    {
        $data = ['output' => null, 'zipUrl' => null];

        if ($this->request->getMethod() === 'post') {
            $prompt = trim($this->request->getPost('prompt'));
            if ($prompt !== '') {
                // Capture CLI output
                ob_start();
                $commands = service('commands');
                // Run scaffold and migrations
                $commands->run('scaffold', [$prompt]);
                $commands->run('migrate', ['--all' => null]);
                $data['output'] = ob_get_clean();

                // After successful scaffolding, package the project
                $zipUrl = $this->packageProject();
                if ($zipUrl) {
                    $data['zipUrl'] = $zipUrl;
                }
            }
        }

        return view('builder/form', $data);
    }

    /**
     * Packages the entire project into a ZIP file, excluding builder-related files.
     * Returns the public URL to the ZIP or null on failure.
     */
    private function packageProject(): ?string
    {
        $rootPath   = realpath(ROOTPATH) . DIRECTORY_SEPARATOR;  // e.g. /var/www/project/
        $publicPath = realpath(ROOTPATH . 'public') . DIRECTORY_SEPARATOR;
        $buildDir   = $publicPath . 'builds' . DIRECTORY_SEPARATOR;

        // Ensure builds directory exists
        if (! is_dir($buildDir)) {
            mkdir($buildDir, 0755, true);
        }

        $timestamp = date('YmdHis');
        $zipName   = "project_{$timestamp}.zip";
        $zipPath   = $buildDir . $zipName;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        // Paths, relative to root, to exclude from the archive
        $excludes = [
            'app/Controllers/Builder.php',
            'app/Views/builder',
            'app/app/Views/builder',
            'app/Controllers/Chat.php',
            'public/chat.html',
            'app/app/Config/tools.json',
            'llm_server',
        ];

        $dirIter = new \RecursiveDirectoryIterator($rootPath, \FilesystemIterator::SKIP_DOTS);
        $iter    = new \RecursiveIteratorIterator($dirIter, \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iter as $filePath => $fileInfo) {
            $relativePath = str_replace('\\', '/', substr($filePath, strlen($rootPath)));

            // Skip excluded paths
            foreach ($excludes as $ex) {
                if (str_starts_with($relativePath, $ex)) {
                    continue 2; // skip this file/dir
                }
            }

            if ($fileInfo->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                // Special handling for Routes.php to strip builder route
                if ($relativePath === 'app/Config/Routes.php' || $relativePath === 'app/app/Config/Routes.php') {
                    $routesContent = file_get_contents($filePath);
                    $routesContent = preg_replace('/^.*builder.*Builder::index.*$/m', '', $routesContent);
                    $zip->addFromString($relativePath, $routesContent);
                } else {
                    $zip->addFile($filePath, $relativePath);
                }
            }
        }

        $zip->close();

        // Return URL relative to site base
        return base_url('builds/' . $zipName);
    }
} 