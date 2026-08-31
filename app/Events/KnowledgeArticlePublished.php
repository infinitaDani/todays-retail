<?php

namespace App\Events;

use App\Modules\Knowledge\Models\KnowledgeArticleVersion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KnowledgeArticlePublished
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly KnowledgeArticleVersion $version)
    {
    }
}
