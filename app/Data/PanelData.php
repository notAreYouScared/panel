<?php

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * Base Data class for Panel that provides backward compatibility with Fractal.
 *
 * When $isFractal is true, the output format matches Fractal's structure:
 * - Single items: { "object": "resource_name", "attributes": {...} }
 * - Collections: { "object": "list", "data": [...] }
 *
 * When $isFractal is false, returns clean Data object arrays for new API versions.
 */
abstract class PanelData extends Data
{

    /**
     * Whether to use Fractal-compatible output format.
     */
    protected bool $isFractal = false;

    /**
     * The resource name for JSONAPI output (used when $isFractal is true).
     */
    abstract public function getResourceName(): string;

    /**
     * Set whether to use Fractal-compatible output format.
     */
    public function setFractal(bool $value = true): static
    {
        $this->isFractal = $value;

        return $this;
    }

    /**
     * Check if Fractal format is enabled.
     */
    public function isFractal(): bool
    {
        return $this->isFractal;
    }

    /**
     * Convert the data to an array.
     *
     * When $isFractal is true, wraps the output in Fractal's format.
     * Otherwise, returns the standard Laravel Data array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = parent::toArray();

        if (!$this->isFractal) {
            return $data;
        }

        // Return Fractal-compatible format for single items
        return $this->wrapInFractalFormat($data);
    }

    /**
     * Wrap data in Fractal's single item format.
     *
     * @param  array<string, mixed>  $data
     * @return array{object: string, attributes: array<string, mixed>}
     */
    protected function wrapInFractalFormat(array $data): array
    {
        $attributes = $data;
        $relationships = [];

        // Extract relationships from the data
        if (isset($data['relationships'])) {
            $relationships = $data['relationships'];
            unset($attributes['relationships']);
        }

        $result = [
            'object' => $this->getResourceName(),
            'attributes' => $attributes,
        ];

        // Add relationships if they exist
        if (!empty($relationships)) {
            $result['attributes']['relationships'] = $relationships;
        }

        return $result;
    }

    /**
     * Create a Fractal-compatible collection wrapper.
     *
     * @param  array<int, static>  $items
     * @param  bool  $isFractal
     * @return array{object: string, data: array<int, array<string, mixed>>}|array<int, array<string, mixed>>
     */
    public static function collection(array $items, bool $isFractal = false): array
    {
        if (!$isFractal) {
            return array_map(fn ($item) => $item->toArray(), $items);
        }

        $resourceName = null;
        $data = [];

        foreach ($items as $item) {
            if ($item instanceof static) {
                if ($resourceName === null) {
                    $resourceName = $item->getResourceName();
                }
                
                $item->setFractal(true);
                $itemArray = $item->toArray();
                
                // Already wrapped, just extract it
                $data[] = $itemArray;
            }
        }

        return [
            'object' => 'list',
            'data' => $data,
        ];
    }
}
