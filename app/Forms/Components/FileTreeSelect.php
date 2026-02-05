<?php

namespace App\Forms\Components;

use App\Repositories\Daemon\DaemonFileRepository;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;

class FileTreeSelect extends SelectTree
{
    protected string $rootPath = '/home/container';
    
    protected int $maxDepth = 10;
    
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
                return [];
            }
            
            $repository = app(DaemonFileRepository::class)->setServer($server);
            
            // Start building from root
            return $this->scanDirectory($repository, $this->rootPath, 0);
        } catch (\Exception $e) {
            // If we can't scan, return empty array
            return [];
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
            
            // Filter and sort to show directories only
            $directories = collect($items)
                ->filter(fn($item) => $item['is_file'] === false)
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
                
                // Recursively scan subdirectories
                $children = $this->scanDirectory($repository, $fullPath, $depth + 1);
                if (count($children) > 0) {
                    $node['children'] = $children;
                }
                
                $tree[] = $node;
            }
            
            return $tree;
        } catch (\Exception $e) {
            // If we can't scan this directory, return empty
            return [];
        }
    }
}
