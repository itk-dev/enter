<?php

declare(strict_types=1);

namespace App\Source;

use App\Ngsi\NgsiEntity;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Converts one input data set into NGSI-LD entities.
 *
 * An implementation owns its feed's origin, field names, quirks and target
 * Smart Data Model. The broker and the import know none of that, so a new
 * ENTER data set costs exactly one class.
 */
#[AutoconfigureTag('app.source')]
interface SourceInterface extends \Stringable, \JsonSerializable
{
    public string $id {
        get;
    }

    public string $title {
        get;
    }
    public string $description {
        get;
    }
    public string $publisher {
        get;
    }
    public string $contact {
        get;
    }
    public string $landingPage {
        get;
    }

    // @todo access_url? What access? Isn't it just a URL?
    public string $accessUrl {
        get;
    }

    public string $mediaType {
        get;
    }

    public string $crs {
        get;
    }

    public string $model {
        get;
    }

    public string $contextUrl {
        get;
    }

    public string $updateFrequency {
        get;
    }

    public ?string $licence {
        get;
    }

    // @todo What does this mean?
    // Fields the feed carries that are not published. Recorded here because
    // the source class shows what is mapped but cannot show what was left
    // out, or why.
    /**
     * @var array<string, string>
     */
    public array $omittedFields {
        get;
    }

    /**
     * @return iterable<NgsiEntity>
     */
    // We should let the (data) source reader read.
    public function entities(): iterable;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
