<?php

namespace Laravel\Telescope\Storage;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Laravel\Telescope\Database\Factories\EntryModelFactory;

class EntryModel extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'telescope_entries';

    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    const UPDATED_AT = null;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'content' => 'json',
        'meta' => 'json',
    ];

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'uuid';

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Prevent Eloquent from overriding uuid with `lastInsertId`.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Scope the query for the given query options.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $type
     * @param  \Laravel\Telescope\Storage\EntryQueryOptions  $options
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithTelescopeOptions($query, $type, EntryQueryOptions $options)
    {
        $this->whereType($query, $type)
                ->whereBatchId($query, $options)
                ->whereTag($query, $options)
                ->whereFamilyHash($query, $options)
                ->whereBeforeSequence($query, $options)
                ->filter($query, $options);

        return $query;
    }

    /**
     * Scope the query for the given type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $type
     * @return $this
     */
    protected function whereType($query, $type)
    {
        $query->when($type, function ($query, $type) {
            return $query->where('type', $type);
        });

        return $this;
    }

    /**
     * Scope the query for the given batch ID.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Laravel\Telescope\Storage\EntryQueryOptions  $options
     * @return $this
     */
    protected function whereBatchId($query, EntryQueryOptions $options)
    {
        $query->when($options->batchId, function ($query, $batchId) {
            return $query->where('batch_id', $batchId);
        });

        return $this;
    }

    /**
     * Scope the query for the given tag(s).
     *
     * Supports multiple comma-separated tags with AND matching.
     * For example, "Auth:1,slow" will return entries that have BOTH tags.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Laravel\Telescope\Storage\EntryQueryOptions  $options
     * @return $this
     */
    protected function whereTag($query, EntryQueryOptions $options)
    {
        $query->when($options->tag, function ($query, $tag) {
            $tags = collect(explode(',', $tag))
                ->map(fn ($t) => trim($t))
                ->filter()
                ->values();

            if ($tags->isEmpty()) {
                return $query;
            }

            return $query->whereIn('uuid', function ($query) use ($tags) {
                if ($tags->count() === 1) {
                    // Single tag: simple lookup
                    $query->select('entry_uuid')
                        ->from('telescope_entries_tags')
                        ->where('tag', $tags->first());
                } else {
                    // Multiple tags: find entries that have ALL specified tags
                    $query->select('entry_uuid')
                        ->fromSub(function ($query) use ($tags) {
                            $query->select('entry_uuid', DB::raw('COUNT(DISTINCT tag) as tag_count'))
                                ->from('telescope_entries_tags')
                                ->whereIn('tag', $tags->all())
                                ->groupBy('entry_uuid');
                        }, 'matched_tags')
                        ->where('tag_count', $tags->count());
                }
            });
        });

        return $this;
    }

    /**
     * Scope the query for the given type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Laravel\Telescope\Storage\EntryQueryOptions  $options
     * @return $this
     */
    protected function whereFamilyHash($query, EntryQueryOptions $options)
    {
        $query->when($options->familyHash, function ($query, $hash) {
            return $query->where('family_hash', $hash);
        });

        return $this;
    }

    /**
     * Scope the query for the given pagination options.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Laravel\Telescope\Storage\EntryQueryOptions  $options
     * @return $this
     */
    protected function whereBeforeSequence($query, EntryQueryOptions $options)
    {
        $query->when($options->beforeSequence, function ($query, $beforeSequence) {
            return $query->where('sequence', '<', $beforeSequence);
        });

        return $this;
    }

    /**
     * Scope the query for the given display options.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Laravel\Telescope\Storage\EntryQueryOptions  $options
     * @return $this
     */
    protected function filter($query, EntryQueryOptions $options)
    {
        if ($options->familyHash || $options->tag || $options->batchId) {
            return $this;
        }

        $query->where('should_display_on_index', true);

        return $this;
    }

    /**
     * Get the current connection name for the model.
     *
     * @return string
     */
    public function getConnectionName()
    {
        return config('telescope.storage.database.connection');
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public static function newFactory()
    {
        return EntryModelFactory::new();
    }
}
