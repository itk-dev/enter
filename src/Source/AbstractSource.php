<?php

namespace App\Source;

/**
 * Abstract source.
 *
 * Extend this to easily create a source implemting the SourceInterface via constructor arguments.
 */
abstract readonly class AbstractSource implements SourceInterface
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public string $publisher,
        public string $contact,
        public string $landingPage,
        public string $accessUrl,
        public string $mediaType,
        public string $crs,
        public string $model,
        public string $contextUrl,
        public string $updateFrequency,
        public ?string $licence = null,
        /**
         * @var array<string, string>
         */
        public array $omittedFields = [],
    ) {
    }

    public function key(): string
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return sprintf('%s (%s)', $this->title, $this->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'publisher' => $this->publisher,
            'contact' => $this->contact,
            'landing_page' => $this->landingPage,
            'access_url' => $this->accessUrl,
            'media_type' => $this->mediaType,
            'crs' => $this->crs,
            'model' => $this->model,
            'context_url' => $this->contextUrl,
            'update_frequency' => $this->updateFrequency,
            'licence' => $this->licence,
            'omitted_fields' => $this->omittedFields,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
