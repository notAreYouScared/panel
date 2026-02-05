<?php

namespace App\Forms\Components;

use App\Repositories\Daemon\DaemonFileRepository;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;

class FileTreeSelect extends SelectTree
{
    protected string $rootPath = '/';
    
    // Reduced from 10 to 2 for performance - only loads 2 levels initially
    protected int $maxDepth = 2;
    
    /**
     * Set the root path to start scanning from
     */
    public function rootPath(string $path): static
    {
        $this->rootPath = $path;
        
        return $this;
    }
    
    /**
     * Set maximum depth for directory scanning
     */
    public function maxDepth(int $depth): static
    {
        $this->maxDepth = $depth;
        
        return $this;
    }
    
    /**
     * Override getTreeUsing to build the tree from the file system
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up the component to build tree from file system
        $this->getTreeUsing(function () {
            return $this->buildFileTree();
        });
        
        // Configure for single selection (move to one location)
        $this->multiple(false);
        
        // Enable branch node selection (allow selecting directories)
        $this->enableBranchNode(true);
        
        // Make it searchable
        $this->searchable(true);
        
        // Set placeholder
        $this->placeholder('Select a destination folder');
    }
    
    /**
     * Build the file tree from the daemon repository
     */
    protected function buildFileTree(): array
    {
        try {
            $server = Filament::getTenant();
            if ($server === null) {
                \Log::warning('FileTreeSelect: No server tenant found');
                return [];
            }
            
            $repository = app(DaemonFileRepository::class)->setServer($server);
            
            // Build the tree starting from root, adding root as a selectable node
            $tree = [];
            
            // Add the root directory itself as a selectable option
            $rootName = $this->rootPath === '/' ? '/' : (basename($this->rootPath) ?: '/');
            $rootNode = [
                'name' => $rootName,
                'value' => $this->rootPath,
                'children' => [],
            ];
            
            // Scan subdirectories
            try {
                $children = $this->scanDirectory($repository, $this->rootPath, 0);
                if (count($children) > 0) {
                    $rootNode['children'] = $children;
                }
            } catch (\Exception $e) {
                \Log::error('FileTreeSelect: Error scanning root directory', [
                    'path' => $this->rootPath,
                    'error' => $e->getMessage()
                ]);
            }
            
            $tree[] = $rootNode;
            
            return $tree;
        } catch (\Exception $e) {
            \Log::error('FileTreeSelect: Error building file tree', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Return a minimal tree with just the root as fallback
            $rootName = $this->rootPath === '/' ? '/' : (basename($this->rootPath) ?: '/');
            return [[
                'name' => $rootName,
                'value' => $this->rootPath,
                'children' => [],
            ]];
        }
    }
    
    /**
     * Recursively scan directories and build tree structure
     */
    protected function scanDirectory(DaemonFileRepository $repository, string $path, int $depth): array
    {
        // Stop if we've reached max depth
        if ($depth >= $this->maxDepth) {
            return [];
        }
        
        try {
            $items = $repository->getDirectory($path);
            $tree = [];
            
            // Check if the response contains an error
            if (isset($items['error'])) {
                \Log::warning('FileTreeSelect: API returned error for path', [
                    'path' => $path,
                    'error' => $items['error']
                ]);
                return [];
            }
            
            // Filter and sort to show directories only
            // API returns 'file' and 'directory' keys, not 'is_file' and 'is_directory'
            $directories = collect($items)
                ->filter(fn($item) => is_array($item) && isset($item['directory']) && $item['directory'] === true)
                ->sortBy('name')
                ->values();
            
            foreach ($directories as $item) {
                $fullPath = rtrim($path, '/') . '/' . $item['name'];
                
                // Build node for this directory
                $node = [
                    'name' => $item['name'],
                    'value' => $fullPath,
                    'children' => [],
                ];
                
                // Only recursively scan subdirectories if we're at depth 0
                // This prevents deep recursive scans that timeout on large structures
                if ($depth === 0) {
                    $children = $this->scanDirectory($repository, $fullPath, $depth + 1);
                    if (count($children) > 0) {
                        $node['children'] = $children;
                    }
                }
                
                $tree[] = $node;
            }
            
            return $tree;
        } catch (\Exception $e) {
            \Log::warning('FileTreeSelect: Error scanning directory', [
                'path' => $path,
                'depth' => $depth,
                'error' => $e->getMessage()
            ]);
            // If we can't scan this directory, return empty
            return [];
        }
    }
}
