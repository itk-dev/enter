<?php

declare(strict_types=1);

namespace App\Source;

/**
 * The configuration of a source.
 *
 * @see AsDataSource
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
readonly class Definition
{
    /**
     * @param array<string, string> $omittedFields
     */
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
        public ?string $licence,
        public array $omittedFields,
    ) {
    }

    /**
     * The definition a source class declares.
     *
     * Reading it takes no instance, so the metadata of every data set is
     * available without building the sources and their collaborators.
     *
     * @param class-string $class
     *
     * @throws \ReflectionException
     */
    public static function of(string $class): self
    {
        $reflection = new \ReflectionClass($class);
        $attribute = $reflection->getAttributes(Definition::class, flags: \ReflectionAttribute::IS_INSTANCEOF)[0]
            ?? throw new \LogicException(sprintf('Source %s declares no #[%s] attribute.', $class, Definition::class));

        return $attribute->newInstance();
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
}
